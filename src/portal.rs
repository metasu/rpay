use axum::{
    extract::{Query, State},
    response::{Html, IntoResponse, Redirect, Response},
    routing::get,
    Form, Router,
};
use axum_extra::extract::cookie::{Cookie, CookieJar};
use serde::Deserialize;

use crate::{
    session::Role,
    store::StoreError,
    templates::{escape, page, portal_nav, status_badge},
    web::AppState,
};

const COOKIE_NAME: &str = "rpay_user";
const PAGE_SIZE: i64 = 20;

pub fn router() -> Router<AppState> {
    Router::new()
        .route("/user/register", get(register_page).post(register_submit))
        .route("/user/login", get(login_page).post(login_submit))
        .route("/user/logout", get(logout))
        .route("/user", get(dashboard))
        .route("/user/orders", get(orders_list))
        .route("/user/settings", get(settings_page).post(settings_submit))
}

fn current_uid(state: &AppState, jar: &CookieJar) -> Option<u64> {
    let token = jar.get(COOKIE_NAME)?.value().to_string();
    let (subject, role) = state.session.decode(&token)?;
    if role != Role::Merchant {
        return None;
    }
    subject.parse().ok()
}

fn unauthorized_redirect() -> Response {
    Redirect::to("/user/login").into_response()
}

fn server_error() -> Response {
    (axum::http::StatusCode::INTERNAL_SERVER_ERROR, "internal error").into_response()
}

// ---------- register ----------

async fn register_page(jar: CookieJar, State(state): State<AppState>) -> Response {
    if current_uid(&state, &jar).is_some() {
        return Redirect::to("/user").into_response();
    }
    Html(render_register(None)).into_response()
}

fn render_register(error: Option<&str>) -> String {
    let msg = error
        .map(|e| format!("<div class=\"msg error\">{}</div>", escape(e)))
        .unwrap_or_default();
    let body = format!(
        r#"<div class="center-box">
<div class="card">
<h1>注册商户</h1>
{msg}
<form method="post" action="/user/register">
<div class="form-row"><label>邮箱或手机号</label><input name="account" required></div>
<div class="form-row"><label>密码（至少6位）</label><input type="password" name="password" required></div>
<div class="form-row"><label>确认密码</label><input type="password" name="password2" required></div>
<div class="form-row" style="margin-top:16px"><button class="btn" style="width:100%" type="submit">注册</button></div>
</form>
<p style="text-align:center;margin-top:12px;font-size:13px"><a class="link" href="/user/login">已有账号？去登录</a></p>
</div>
</div>"#
    );
    page("注册商户 - rpay", "", &body)
}

#[derive(Deserialize)]
struct RegisterForm {
    account: String,
    password: String,
    password2: String,
}

async fn register_submit(
    State(state): State<AppState>,
    jar: CookieJar,
    Form(form): Form<RegisterForm>,
) -> Response {
    let account = form.account.trim();
    if account.is_empty() {
        return Html(render_register(Some("账号不能为空"))).into_response();
    }
    if form.password.len() < 6 {
        return Html(render_register(Some("密码至少6位"))).into_response();
    }
    if form.password != form.password2 {
        return Html(render_register(Some("两次输入的密码不一致"))).into_response();
    }
    match state.store.account_taken(account).await {
        Ok(true) => return Html(render_register(Some("该邮箱/手机号已被注册"))).into_response(),
        Ok(false) => {}
        Err(_) => return server_error(),
    }
    let uid = match state.store.register_merchant(account, &form.password).await {
        Ok(uid) => uid,
        Err(_) => return server_error(),
    };
    let token = state.session.encode(&uid.to_string(), Role::Merchant, 7 * 24 * 3600);
    let cookie = Cookie::build((COOKIE_NAME, token)).path("/").http_only(true).build();
    (jar.add(cookie), Redirect::to("/user")).into_response()
}

// ---------- login ----------

async fn login_page(jar: CookieJar, State(state): State<AppState>) -> Response {
    if current_uid(&state, &jar).is_some() {
        return Redirect::to("/user").into_response();
    }
    Html(render_login(None)).into_response()
}

fn render_login(error: Option<&str>) -> String {
    let msg = error
        .map(|e| format!("<div class=\"msg error\">{}</div>", escape(e)))
        .unwrap_or_default();
    let body = format!(
        r#"<div class="center-box">
<div class="card">
<h1>商户登录</h1>
{msg}
<form method="post" action="/user/login">
<div class="form-row"><label>商户ID / 邮箱 / 手机号</label><input name="user" required></div>
<div class="form-row"><label>密钥或密码</label><input type="password" name="pass" required></div>
<div class="form-row" style="margin-top:16px"><button class="btn" style="width:100%" type="submit">登录</button></div>
</form>
<p style="text-align:center;margin-top:12px;font-size:13px"><a class="link" href="/user/register">没有账号？去注册</a></p>
</div>
</div>"#
    );
    page("商户登录 - rpay", "", &body)
}

#[derive(Deserialize)]
struct LoginForm {
    user: String,
    pass: String,
}

async fn login_submit(
    State(state): State<AppState>,
    jar: CookieJar,
    Form(form): Form<LoginForm>,
) -> Response {
    let user = form.user.trim();
    let auth = if let Ok(uid) = user.parse::<u64>() {
        // Numeric input: try merchant-id + API key login first.
        match state.store.find_merchant_by_uid_for_login(uid).await {
            Ok(Some(row)) if row.key == form.pass => Some(row),
            _ => match state.store.find_merchant_by_account(user).await {
                Ok(v) => v,
                Err(_) => return server_error(),
            },
        }
    } else {
        match state.store.find_merchant_by_account(user).await {
            Ok(v) => v,
            Err(_) => return server_error(),
        }
    };

    let Some(row) = auth else {
        return Html(render_login(Some("账号或密码/密钥错误"))).into_response();
    };
    let key_login_ok = row.key == form.pass;
    let pwd_login_ok = row
        .pwd
        .as_deref()
        .map(|stored| !stored.is_empty() && stored == crate::protocol::legacy_password_hash(&form.pass, &row.uid.to_string()))
        .unwrap_or(false);
    if !key_login_ok && !pwd_login_ok {
        return Html(render_login(Some("账号或密码/密钥错误"))).into_response();
    }
    if row.status == 0 {
        return Html(render_login(Some("该商户已被封禁"))).into_response();
    }
    let token = state.session.encode(&row.uid.to_string(), Role::Merchant, 7 * 24 * 3600);
    let cookie = Cookie::build((COOKIE_NAME, token)).path("/").http_only(true).build();
    (jar.add(cookie), Redirect::to("/user")).into_response()
}

async fn logout(jar: CookieJar) -> Response {
    (jar.remove(Cookie::from(COOKIE_NAME)), Redirect::to("/user/login")).into_response()
}

// ---------- dashboard ----------

async fn dashboard(State(state): State<AppState>, jar: CookieJar) -> Response {
    let Some(uid) = current_uid(&state, &jar) else {
        return unauthorized_redirect();
    };
    let m = match state.store.merchant_detail(uid).await {
        Ok(m) => m,
        Err(StoreError::NotFound) => return unauthorized_redirect(),
        Err(_) => return server_error(),
    };
    let order_count = state.store.count_orders(Some(uid), None, None, None, None, false).await.unwrap_or(0);
    let paid_count = state.store.count_orders(Some(uid), Some(1), None, None, None, false).await.unwrap_or(0);
    let body = format!(
        r#"<h1>商户概览 #{uid}</h1>
<div class="grid">
<div class="stat"><div class="n">¥{money}</div><div class="l">账户余额</div></div>
<div class="stat"><div class="n">{orders}</div><div class="l">订单总数</div></div>
<div class="stat"><div class="n">{paid}</div><div class="l">已支付订单</div></div>
</div>
<div class="card">
<h2>接入信息</h2>
<table>
<tr><td style="width:140px;color:#6b7280">商户 PID</td><td>{uid}</td></tr>
<tr><td style="color:#6b7280">API Key</td><td><code class="k">{key}</code></td></tr>
<tr><td style="color:#6b7280">下单地址</td><td><code class="k">{base_url}/submit.php</code>（POST，签名方式：MD5）</td></tr>
</table>
</div>"#,
        uid = uid,
        money = escape(&m.money),
        orders = order_count,
        paid = paid_count,
        key = escape(&m.key),
        base_url = escape(&state.public_base_url),
    );
    Html(page("商户概览 - rpay", &portal_nav("dashboard"), &body)).into_response()
}

// ---------- orders ----------

#[derive(Deserialize)]
struct OrderQuery {
    page: Option<i64>,
}

async fn orders_list(State(state): State<AppState>, jar: CookieJar, Query(q): Query<OrderQuery>) -> Response {
    let Some(uid) = current_uid(&state, &jar) else {
        return unauthorized_redirect();
    };
    let page_no = q.page.unwrap_or(1).max(1);
    let offset = (page_no - 1) * PAGE_SIZE;
    let rows = match state.store.list_orders(offset, PAGE_SIZE, Some(uid), None, None, None, None, false).await {
        Ok(r) => r,
        Err(_) => return server_error(),
    };
    let total = state.store.count_orders(Some(uid), None, None, None, None, false).await.unwrap_or(0);
    let total_pages = ((total as f64) / (PAGE_SIZE as f64)).ceil().max(1.0) as i64;

    let mut table = String::from(
        "<table><tr><th>交易号</th><th>我的订单号</th><th>商品</th><th>金额</th><th>状态</th><th>时间</th></tr>",
    );
    for o in &rows {
        table.push_str(&format!(
            "<tr><td>{trade_no}</td><td>{out_trade_no}</td><td>{name}</td><td>¥{money}</td><td>{status}</td><td>{addtime}</td></tr>",
            trade_no = escape(&o.trade_no),
            out_trade_no = escape(&o.out_trade_no),
            name = escape(&o.name),
            money = escape(&o.money),
            status = status_badge(o.status),
            addtime = o.addtime.map(|t| t.to_string()).unwrap_or_default(),
        ));
    }
    table.push_str("</table>");

    let mut pager = String::from("<div class=\"pager\">");
    if page_no > 1 {
        pager.push_str(&format!("<a class=\"btn secondary sm\" href=\"/user/orders?page={}\">上一页</a>", page_no - 1));
    }
    pager.push_str(&format!("<span style=\"padding:6px 4px;font-size:13px;color:#6b7280\">第 {page_no}/{total_pages} 页，共 {total} 条</span>"));
    if page_no < total_pages {
        pager.push_str(&format!("<a class=\"btn secondary sm\" href=\"/user/orders?page={}\">下一页</a>", page_no + 1));
    }
    pager.push_str("</div>");

    let body = format!("<h1>我的订单</h1><div class=\"card\">{table}{pager}</div>");
    Html(page("我的订单 - rpay", &portal_nav("orders"), &body)).into_response()
}

// ---------- settings ----------

async fn settings_page(State(state): State<AppState>, jar: CookieJar) -> Response {
    let Some(uid) = current_uid(&state, &jar) else {
        return unauthorized_redirect();
    };
    render_settings(&state, uid, None).await
}

async fn render_settings(state: &AppState, uid: u64, notice: Option<&str>) -> Response {
    let m = match state.store.merchant_detail(uid).await {
        Ok(m) => m,
        Err(_) => return server_error(),
    };
    let msg = notice
        .map(|n| format!("<div class=\"msg ok\">{}</div>", escape(n)))
        .unwrap_or_default();
    let body = format!(
        r#"<h1>密钥与安全</h1>
{msg}
<div class="card">
<h2>API 密钥</h2>
<p><code class="k">{key}</code></p>
<form method="post" action="/user/settings">
<input type="hidden" name="action" value="reset_key">
<button class="btn secondary" type="submit" onclick="return confirm('重置后旧密钥立即失效，确认继续？')">重置密钥</button>
</form>
</div>
<div class="card">
<h2>修改登录密码</h2>
<form method="post" action="/user/settings">
<input type="hidden" name="action" value="change_password">
<label>新密码（至少6位）</label>
<input type="password" name="new_password" required>
<div style="margin-top:16px"><button class="btn" type="submit">保存</button></div>
</form>
</div>"#,
        key = escape(&m.key),
    );
    Html(page("密钥与安全 - rpay", &portal_nav("settings"), &body)).into_response()
}

#[derive(Deserialize)]
struct SettingsForm {
    action: String,
    new_password: Option<String>,
}

async fn settings_submit(
    State(state): State<AppState>,
    jar: CookieJar,
    Form(form): Form<SettingsForm>,
) -> Response {
    let Some(uid) = current_uid(&state, &jar) else {
        return unauthorized_redirect();
    };
    match form.action.as_str() {
        "reset_key" => {
            if state.store.reset_merchant_key(uid).await.is_err() {
                return server_error();
            }
            render_settings(&state, uid, Some("密钥已重置")).await
        }
        "change_password" => {
            let Some(pwd) = form.new_password.filter(|p| p.len() >= 6) else {
                return render_settings(&state, uid, Some("密码至少6位，未保存")).await;
            };
            let hashed = crate::protocol::legacy_password_hash(&pwd, &uid.to_string());
            if state.store.set_merchant_password(uid, &hashed).await.is_err() {
                return server_error();
            }
            render_settings(&state, uid, Some("密码已修改")).await
        }
        _ => render_settings(&state, uid, None).await,
    }
}
