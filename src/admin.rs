use axum::{
    body::Bytes,
    extract::{Path, Query, State},
    response::{Html, IntoResponse, Redirect, Response},
    routing::{get, post},
    Form, Router,
};
use axum_extra::extract::cookie::{Cookie, CookieJar};
use serde::Deserialize;

use crate::{
    session::Role,
    store::StoreError,
    templates::{admin_nav, escape, page, status_badge},
    web::AppState,
};

const COOKIE_NAME: &str = "rpay_admin";
const PAGE_SIZE: i64 = 20;

pub fn router() -> Router<AppState> {
    Router::new()
        .route("/admin/login", get(login_page).post(login_submit))
        .route("/admin/logout", get(logout))
        .route("/admin", get(dashboard))
        .route("/admin/merchants", get(merchants_list).post(merchant_create))
        .route("/admin/merchants/new", get(merchant_create_page))
        .route("/admin/merchants/{uid}", get(merchant_detail).post(merchant_update))
        .route("/admin/merchants/{uid}/reset-key", post(merchant_reset_key))
        .route("/admin/merchants/{uid}/delete", post(merchant_delete))
        .route("/admin/orders", get(orders_list).post(orders_batch_action))
        .route("/admin/orders/stats", get(orders_stats))
        .route("/admin/orders/{trade_no}", get(order_detail).post(order_update))
        .route("/admin/orders/{trade_no}/delete", post(order_delete))
        .route("/admin/orders/{trade_no}/refund", post(order_refund))
        .route("/admin/channels", get(channels_list))
        .route("/admin/channels/{id}", get(channel_detail).post(channel_update))
        .route("/admin/settings", get(settings_page).post(settings_update))
}

fn require_admin(state: &AppState, jar: &CookieJar) -> Option<()> {
    let token = jar.get(COOKIE_NAME)?.value().to_string();
    let (_subject, role) = state.session.decode(&token)?;
    if role == Role::Admin {
        Some(())
    } else {
        None
    }
}

fn unauthorized_redirect() -> Response {
    Redirect::to("/admin/login").into_response()
}

// ---------- login ----------

async fn login_page(jar: CookieJar, State(state): State<AppState>) -> Response {
    if require_admin(&state, &jar).is_some() {
        return Redirect::to("/admin").into_response();
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
<h1>管理员登录</h1>
{msg}
<form method="post" action="/admin/login">
<div class="form-row"><label>用户名</label><input name="username" required></div>
<div class="form-row"><label>密码</label><input type="password" name="password" required></div>
<div class="form-row" style="margin-top:16px"><button class="btn" style="width:100%" type="submit">登录</button></div>
</form>
</div>
</div>"#
    );
    page("管理员登录 - rpay", "", &body)
}

#[derive(Deserialize)]
struct LoginForm {
    username: String,
    password: String,
}

async fn login_submit(
    State(state): State<AppState>,
    jar: CookieJar,
    Form(form): Form<LoginForm>,
) -> Response {
    let admin_user = state.store.config_get("admin_user").await.ok().flatten();
    let admin_pwd = state.store.config_get("admin_pwd").await.ok().flatten();
    let ok = matches!((admin_user, admin_pwd), (Some(u), Some(p)) if u == form.username && p == form.password);
    if !ok {
        return Html(render_login(Some("用户名或密码错误"))).into_response();
    }
    let token = state.session.encode("admin", Role::Admin, 30 * 24 * 3600);
    let cookie = Cookie::build((COOKIE_NAME, token))
        .path("/")
        .http_only(true)
        .build();
    (jar.add(cookie), Redirect::to("/admin")).into_response()
}

async fn logout(jar: CookieJar) -> Response {
    (jar.remove(Cookie::from(COOKIE_NAME)), Redirect::to("/admin/login")).into_response()
}

// ---------- dashboard ----------

async fn dashboard(State(state): State<AppState>, jar: CookieJar) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let stats = match state.store.dashboard_stats().await {
        Ok(s) => s,
        Err(_) => return server_error(),
    };
    let mut monthly_table = String::from("<table><tr><th>月份</th><th>成交笔数</th><th>成交金额</th></tr>");
    for m in &stats.monthly_stats {
        monthly_table.push_str(&format!(
            "<tr><td>{month}</td><td>{count}</td><td>¥{amount}</td></tr>",
            month = escape(&m.month),
            count = m.count,
            amount = escape(&m.amount),
        ));
    }
    monthly_table.push_str("</table>");
    let body = format!(
        r#"<h1>概览</h1>
<div class="grid">
<div class="stat"><div class="n">{merchants}</div><div class="l">商户总数</div></div>
<div class="stat"><div class="n">{orders}</div><div class="l">订单总数</div></div>
<div class="stat"><div class="n">{paid_today}</div><div class="l">今日成交笔数</div></div>
<div class="stat"><div class="n">¥{amount_today}</div><div class="l">今日成交金额</div></div>
<div class="stat"><div class="n">{paid_month}</div><div class="l">本月成交笔数</div></div>
<div class="stat"><div class="n">¥{amount_month}</div><div class="l">本月成交金额</div></div>
<div class="stat"><div class="n">{paid_year}</div><div class="l">今年成交笔数</div></div>
<div class="stat"><div class="n">¥{amount_year}</div><div class="l">今年成交金额</div></div>
<div class="stat"><div class="n">{paid_last_year}</div><div class="l">去年成交笔数</div></div>
<div class="stat"><div class="n">¥{amount_last_year}</div><div class="l">去年成交金额</div></div>
</div>
<h2 style="margin-top:24px">今年每月成交</h2>
<div class="card">{monthly_table}</div>"#,
        merchants = stats.merchant_count,
        orders = stats.order_count,
        paid_today = stats.paid_count_today,
        amount_today = escape(&stats.paid_amount_today),
        paid_month = stats.paid_count_month,
        amount_month = escape(&stats.paid_amount_month),
        paid_year = stats.paid_count_year,
        amount_year = escape(&stats.paid_amount_year),
        paid_last_year = stats.paid_count_last_year,
        amount_last_year = escape(&stats.paid_amount_last_year),
        monthly_table = monthly_table,
    );
    Html(page("概览 - rpay 管理后台", &admin_nav("dashboard"), &body)).into_response()
}

fn server_error() -> Response {
    (axum::http::StatusCode::INTERNAL_SERVER_ERROR, "internal error").into_response()
}

// ---------- merchants ----------

#[derive(Deserialize)]
struct ListQuery {
    q: Option<String>,
    page: Option<i64>,
}

async fn merchants_list(
    State(state): State<AppState>,
    jar: CookieJar,
    Query(q): Query<ListQuery>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let page_no = q.page.unwrap_or(1).max(1);
    let offset = (page_no - 1) * PAGE_SIZE;
    let search = q.q.as_deref().filter(|s| !s.is_empty());
    let rows = match state.store.list_merchants(offset, PAGE_SIZE, search).await {
        Ok(r) => r,
        Err(_) => return server_error(),
    };
    let total = state.store.count_merchants(search).await.unwrap_or(0);

    let mut table = String::from(
        "<table><tr><th>UID</th><th>账号</th><th>用户名</th><th>余额</th><th>状态</th><th>收款</th><th>注册时间</th><th></th></tr>",
    );
    for m in &rows {
        table.push_str(&format!(
            "<tr><td>{uid}</td><td>{account}</td><td>{username}</td><td>¥{money}</td><td>{status}</td><td>{pay}</td><td>{addtime}</td><td><a class=\"link\" href=\"/admin/merchants/{uid}\">管理</a></td></tr>",
            uid = m.uid,
            account = escape(m.account.as_deref().unwrap_or("-")),
            username = escape(m.username.as_deref().unwrap_or("-")),
            money = escape(&m.money),
            status = if m.status == 1 { "<span class=\"badge ok\">正常</span>" } else { "<span class=\"badge bad\">封禁</span>" },
            pay = if m.pay == 1 { "开启" } else { "关闭" },
            addtime = m.addtime.map(|t| t.format("%Y-%m-%d").to_string()).unwrap_or_default(),
        ));
    }
    table.push_str("</table>");

    let pager = render_pager("/admin/merchants", q.q.as_deref(), page_no, total);
    let body = format!(
        r#"<h1>商户管理</h1>
<div class="card">
<form method="get" style="display:flex;gap:8px;margin-bottom:16px">
<input name="q" placeholder="按UID/账号/用户名搜索" value="{search}">
<button class="btn secondary" type="submit">搜索</button>
<a class="btn" href="/admin/merchants/new">新增商户</a>
</form>
{table}
{pager}
</div>"#,
        search = escape(q.q.as_deref().unwrap_or("")),
    );
    Html(page("商户管理 - rpay 管理后台", &admin_nav("merchants"), &body)).into_response()
}

fn render_pager(base: &str, q: Option<&str>, page_no: i64, total: i64) -> String {
    let total_pages = ((total as f64) / (PAGE_SIZE as f64)).ceil().max(1.0) as i64;
    let qparam = q.map(|s| format!("&q={}", urlencoding_light(s))).unwrap_or_default();
    let mut html = String::from("<div class=\"pager\">");
    if page_no > 1 {
        html.push_str(&format!(
            "<a class=\"btn secondary sm\" href=\"{base}?page={}{qparam}\">上一页</a>",
            page_no - 1
        ));
    }
    html.push_str(&format!("<span style=\"padding:6px 4px;font-size:13px;color:#6b7280\">第 {page_no}/{total_pages} 页，共 {total} 条</span>"));
    if page_no < total_pages {
        html.push_str(&format!(
            "<a class=\"btn secondary sm\" href=\"{base}?page={}{qparam}\">下一页</a>",
            page_no + 1
        ));
    }
    html.push_str("</div>");
    html
}

fn urlencoding_light(s: &str) -> String {
    url::form_urlencoded::byte_serialize(s.as_bytes()).collect()
}

async fn merchant_detail(
    State(state): State<AppState>,
    jar: CookieJar,
    Path(uid): Path<u64>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    render_merchant_detail(&state, uid, None).await
}

async fn render_merchant_detail(state: &AppState, uid: u64, notice: Option<&str>) -> Response {
    let m = match state.store.merchant_detail(uid).await {
        Ok(m) => m,
        Err(StoreError::NotFound) => return (axum::http::StatusCode::NOT_FOUND, "商户不存在").into_response(),
        Err(_) => return server_error(),
    };
    let msg = notice
        .map(|n| format!("<div class=\"msg ok\">{}</div>", escape(n)))
        .unwrap_or_default();
    let body = format!(
        r#"<h1>商户 #{uid}</h1>
{msg}
<div class="card">
<h2>基本信息</h2>
<table>
<tr><td style="width:140px;color:#6b7280">API Key</td><td><code class="k">{key}</code> <form method="post" action="/admin/merchants/{uid}/reset-key" style="display:inline"><button class="btn sm secondary" type="submit" onclick="return confirm('重置后旧密钥立即失效，确认继续？')">重置密钥</button></form></td></tr>
<tr><td style="color:#6b7280">邮箱/手机</td><td>{email} / {phone}</td></tr>
<tr><td style="color:#6b7280">余额</td><td>¥{money}</td></tr>
<tr><td style="color:#6b7280">注册时间</td><td>{addtime}</td></tr>
<tr><td style="color:#6b7280">最近登录</td><td>{lasttime}</td></tr>
</table>
</div>
<div class="card">
<h2>状态与限额</h2>
<form method="post" action="/admin/merchants/{uid}">
<label>账户状态</label>
<select name="status">
<option value="1" {status_ok}>正常</option>
<option value="0" {status_bad}>封禁</option>
</select>
<label>收款开关</label>
<select name="pay">
<option value="1" {pay_on}>开启</option>
<option value="0" {pay_off}>关闭</option>
</select>
<label>单笔最小金额（元，留空不限制）</label>
<input name="pay_minmoney" value="{pay_min}">
<label>单笔最大金额（元，留空不限制）</label>
<input name="pay_maxmoney" value="{pay_max}">
<label>余额调整（正数增加，负数扣减，元）</label>
<input name="adjust_money" value="0.00">
<div style="margin-top:16px"><button class="btn" type="submit">保存</button> <a class="btn secondary" href="/admin/merchants">返回列表</a></div>
</form>
</div>
<div class="card" style="margin-top:16px">
<h2>删除商户</h2>
<form method="post" action="/admin/merchants/{uid}/delete" onsubmit="return confirm('确定删除此商户？所有关联订单将无法通过此商户查询，此操作不可恢复！')">
<button class="btn danger" type="submit">删除商户</button>
</form>
</div>"#,
        uid = uid,
        key = escape(&m.key),
        email = escape(m.email.as_deref().unwrap_or("-")),
        phone = escape(m.phone.as_deref().unwrap_or("-")),
        money = escape(&m.money),
        addtime = m.addtime.map(|t| t.to_string()).unwrap_or_default(),
        lasttime = m.lasttime.map(|t| t.to_string()).unwrap_or_else(|| "从未登录".to_string()),
        status_ok = if m.status == 1 { "selected" } else { "" },
        status_bad = if m.status == 0 { "selected" } else { "" },
        pay_on = if m.pay == 1 { "selected" } else { "" },
        pay_off = if m.pay == 0 { "selected" } else { "" },
        pay_min = escape(m.pay_minmoney.as_deref().unwrap_or("")),
        pay_max = escape(m.pay_maxmoney.as_deref().unwrap_or("")),
    );
    Html(page(&format!("商户 #{uid} - rpay"), &admin_nav("merchants"), &body)).into_response()
}

#[derive(Deserialize)]
struct MerchantUpdateForm {
    status: i8,
    pay: i8,
    pay_minmoney: String,
    pay_maxmoney: String,
    adjust_money: String,
}

async fn merchant_update(
    State(state): State<AppState>,
    jar: CookieJar,
    Path(uid): Path<u64>,
    Form(form): Form<MerchantUpdateForm>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let min = if form.pay_minmoney.trim().is_empty() { None } else { Some(form.pay_minmoney.trim()) };
    let max = if form.pay_maxmoney.trim().is_empty() { None } else { Some(form.pay_maxmoney.trim()) };
    if state.store.update_merchant_status(uid, form.status, form.pay).await.is_err() {
        return server_error();
    }
    if state.store.update_merchant_limits(uid, min, max).await.is_err() {
        return server_error();
    }
    let adjust = form.adjust_money.trim();
    if !adjust.is_empty() && adjust != "0" && adjust != "0.00" {
        if crate::protocol::parse_yuan_to_fen(adjust.trim_start_matches('-')).is_some() {
            let _ = state.store.adjust_merchant_money(uid, adjust).await;
        }
    }
    render_merchant_detail(&state, uid, Some("已保存")).await
}

async fn merchant_reset_key(
    State(state): State<AppState>,
    jar: CookieJar,
    Path(uid): Path<u64>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    if state.store.reset_merchant_key(uid).await.is_err() {
        return server_error();
    }
    render_merchant_detail(&state, uid, Some("密钥已重置")).await
}

async fn merchant_create_page(
    State(state): State<AppState>,
    jar: CookieJar,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let body = r#"<h1>新增商户</h1>
<div class="card">
<form method="post" action="/admin/merchants">
<label>账号（登录用）</label>
<input name="account" required placeholder="如 merchant001">
<label>用户名（显示名）</label>
<input name="username" required placeholder="如 某某商城">
<label>邮箱（选填）</label>
<input name="email" type="email" placeholder="example@mail.com">
<label>手机（选填）</label>
<input name="phone" placeholder="13800000000">
<label>登录密码（选填）</label>
<input name="password" type="password" placeholder="留空则不可通过门户登录">
<div style="margin-top:16px">
<button class="btn" type="submit">创建商户</button>
<a class="btn secondary" href="/admin/merchants">返回列表</a>
</div>
</form>
</div>"#;
    Html(page("新增商户 - rpay 管理后台", &admin_nav("merchants"), body)).into_response()
}

#[derive(Deserialize)]
struct MerchantCreateForm {
    account: String,
    username: String,
    email: Option<String>,
    phone: Option<String>,
    password: Option<String>,
}

async fn merchant_create(
    State(state): State<AppState>,
    jar: CookieJar,
    Form(form): Form<MerchantCreateForm>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let account = form.account.trim();
    let username = form.username.trim();
    if account.is_empty() || username.is_empty() {
        let body = r#"<div class="card"><div class="msg bad">账号和用户名不能为空</div><a class="btn" href="/admin/merchants/new">返回</a></div>"#;
        return Html(page("新增商户 - rpay", &admin_nav("merchants"), body)).into_response();
    }
    let email = form.email.as_deref().map(|s| s.trim()).filter(|s| !s.is_empty());
    let phone = form.phone.as_deref().map(|s| s.trim()).filter(|s| !s.is_empty());
    let password = form.password.as_deref().map(|s| s.trim()).filter(|s| !s.is_empty());
    match state.store.create_merchant(account, username, email, phone, password).await {
        Ok(uid) => Redirect::to(&format!("/admin/merchants/{uid}")).into_response(),
        Err(_) => {
            let body = r#"<div class="card"><div class="msg bad">创建失败，账号可能已存在</div><a class="btn" href="/admin/merchants/new">返回</a></div>"#;
            Html(page("新增商户 - rpay", &admin_nav("merchants"), body)).into_response()
        }
    }
}

async fn merchant_delete(
    State(state): State<AppState>,
    jar: CookieJar,
    Path(uid): Path<u64>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    match state.store.delete_merchant(uid).await {
        Ok(true) => Redirect::to("/admin/merchants").into_response(),
        Ok(false) => (axum::http::StatusCode::NOT_FOUND, "商户不存在").into_response(),
        Err(_) => server_error(),
    }
}

// ---------- orders ----------

#[derive(Deserialize)]
struct OrderListQuery {
    uid: Option<String>,
    status: Option<String>,
    q: Option<String>,
    page: Option<i64>,
    start: Option<String>,
    end: Option<String>,
    product: Option<String>,
    channel: Option<String>,
    exclude_channel: Option<String>,
}

fn parse_optional_u64(value: Option<&str>) -> Option<u64> {
    value.filter(|s| !s.trim().is_empty()).and_then(|s| s.parse().ok())
}

fn parse_optional_i8(value: Option<&str>) -> Option<i8> {
    value.filter(|s| !s.trim().is_empty()).and_then(|s| s.parse().ok())
}

async fn orders_list(
    State(state): State<AppState>,
    jar: CookieJar,
    Query(q): Query<OrderListQuery>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let page_no = q.page.unwrap_or(1).max(1);
    let offset = (page_no - 1) * PAGE_SIZE;
    let search = q.q.as_deref().filter(|s| !s.is_empty());
    let uid = parse_optional_u64(q.uid.as_deref());
    let status = parse_optional_i8(q.status.as_deref());
    let product = q.product.as_deref().filter(|s| !s.trim().is_empty());
    let channel = q.channel.as_deref().filter(|s| !s.trim().is_empty());
    let exclude_channel = q.exclude_channel.as_deref() == Some("1");
    let rows = match state.store.list_orders(offset, PAGE_SIZE, uid, status, search, product, channel, exclude_channel).await {
        Ok(r) => r,
        Err(_) => return server_error(),
    };
    let total = state.store.count_orders(uid, status, search, product, channel, exclude_channel).await.unwrap_or(0);

    let mut table = String::from(
        "<table><tr><th><input type=\"checkbox\" onclick=\"document.querySelectorAll('input[name=trade_nos]').forEach(c=>c.checked=this.checked)\"></th><th>交易号</th><th>商户订单号</th><th>商户</th><th>商品</th><th>网站来源</th><th>细分通道</th><th>金额</th><th>状态</th><th>时间</th><th></th></tr>",
    );
    for o in &rows {
        table.push_str(&format!(
            "<tr><td><input type=\"checkbox\" name=\"trade_nos\" value=\"{trade_no}\" form=\"batchForm\"></td><td><a class=\"link\" href=\"/admin/orders/{trade_no}\">{trade_no}</a></td><td>{out_trade_no}</td><td><a class=\"link\" href=\"/admin/merchants/{uid}\">{uid}</a></td><td>{name}</td><td>{domain}</td><td>{channel}</td><td>¥{money}</td><td>{status}</td><td>{addtime}</td><td><a class=\"link\" href=\"/admin/orders/{trade_no}\">详情</a></td></tr>",
            trade_no = escape(&o.trade_no),
            out_trade_no = escape(&o.out_trade_no),
            uid = o.uid,
            name = escape(&o.name),
            domain = escape(o.domain.as_deref().unwrap_or("-")),
            channel = escape(o.channel_plugin.as_deref().or(o.channel_name.as_deref()).unwrap_or("-")),
            money = escape(&o.money),
            status = status_badge(o.status),
            addtime = o.addtime.map(|t| t.format("%Y-%m-%d %H:%M").to_string()).unwrap_or_default(),
        ));
    }
    table.push_str("</table>");
    let pager = render_pager("/admin/orders", q.q.as_deref(), page_no, total);
    let body = format!(
        r#"<h1>订单查询</h1>
<div class="card">
<form method="get" style="display:flex;gap:8px;margin-bottom:16px">
<input name="q" placeholder="交易号/商户订单号" value="{search}">
<input name="uid" placeholder="商户UID" value="{uid}">
<input name="product" placeholder="商品名" value="{product}">
<input name="channel" placeholder="渠道插件或名称，如 rpay-stripe" value="{channel}">
<label style="font-size:13px"><input type="checkbox" name="exclude_channel" value="1" style="width:auto" {exclude}> 排除该渠道</label>
<select name="status">
<option value="">全部状态</option>
<option value="1" {s1}>已支付</option>
<option value="0" {s0}>待支付</option>
<option value="2" {s2}>已关闭</option>
<option value="3" {s3}>已退款</option>
</select>
<button class="btn secondary" type="submit">筛选</button>
<a class="btn" href="/admin/orders/stats">统计</a>
</form>
<form id="batchForm" method="post" onsubmit="return confirm('确定执行批量操作？')">
<div style="display:flex;gap:8px;margin-bottom:16px">
<button class="btn danger" type="submit" name="action" value="delete">批量删除</button>
</div>
</form>
{table}
{pager}
</div>"#,
        search = escape(q.q.as_deref().unwrap_or("")),
        uid = escape(q.uid.as_deref().unwrap_or("")),
        product = escape(q.product.as_deref().unwrap_or("")),
        channel = escape(q.channel.as_deref().unwrap_or("")),
        exclude = if exclude_channel { "checked" } else { "" },
        s1 = if status == Some(1) { "selected" } else { "" },
        s0 = if status == Some(0) { "selected" } else { "" },
        s2 = if status == Some(2) { "selected" } else { "" },
        s3 = if status == Some(3) { "selected" } else { "" },
    );
    Html(page("订单查询 - rpay 管理后台", &admin_nav("orders"), &body)).into_response()
}

async fn orders_stats(
    State(state): State<AppState>,
    jar: CookieJar,
    Query(q): Query<OrderListQuery>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let start = q.start.as_deref().filter(|s| !s.is_empty());
    let end = q.end.as_deref().filter(|s| !s.is_empty());
    let uid = parse_optional_u64(q.uid.as_deref());
    let product = q.product.as_deref().filter(|s| !s.trim().is_empty());
    let channel = q.channel.as_deref().filter(|s| !s.trim().is_empty());
    let exclude_channel = q.exclude_channel.as_deref() == Some("1");
    let stats = match state.store.order_stats(uid, start, end, product, channel, exclude_channel).await {
        Ok(s) => s,
        Err(_) => return server_error(),
    };
    let daily = match state.store.daily_stats(start, end).await {
        Ok(d) => d,
        Err(_) => Vec::new(),
    };

    let mut daily_table = String::from("<table><tr><th>日期</th><th>成交笔数</th><th>成交金额</th></tr>");
    for d in &daily {
        daily_table.push_str(&format!(
            "<tr><td>{date}</td><td>{count}</td><td>¥{amount}</td></tr>",
            date = escape(&d.date),
            count = d.count,
            amount = escape(&d.amount),
        ));
    }
    daily_table.push_str("</table>");

    let period_label = match (start, end) {
        (Some(s), Some(e)) => format!("{s} ~ {e}"),
        (Some(s), None) => format!("{s} ~ 至今"),
        (None, Some(e)) => format!("截止 {e}"),
        (None, None) => "全部时间".to_string(),
    };

    let body = format!(
        r#"<h1>订单统计</h1>
<div class="card">
<form method="get" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
<label style="font-size:13px;color:#6b7280">商户UID</label>
<input name="uid" placeholder="留空查全部" value="{uid}" style="width:120px">
<input name="product" placeholder="商品名" value="{product}" style="width:180px">
<label style="font-size:13px;color:#6b7280">开始日期</label>
<input type="date" name="start" value="{start}">
<label style="font-size:13px;color:#6b7280">结束日期</label>
<input type="date" name="end" value="{end}">
<button class="btn secondary" type="submit">查询</button>
<a class="btn" href="/admin/orders/stats">重置</a>
<a class="btn" href="/admin/orders">返回订单列表</a>
</form>
<div class="grid">
<div class="stat"><div class="n">{total}</div><div class="l">订单总数</div></div>
<div class="stat"><div class="n">{paid}</div><div class="l">已支付</div></div>
<div class="stat"><div class="n">{unpaid}</div><div class="l">待支付</div></div>
<div class="stat"><div class="n">{closed}</div><div class="l">已关闭</div></div>
<div class="stat"><div class="n">{refunded}</div><div class="l">已退款</div></div>
<div class="stat"><div class="n">¥{paid_amt}</div><div class="l">已支付金额</div></div>
<div class="stat"><div class="n">¥{total_amt}</div><div class="l">订单总金额</div></div>
<div class="stat"><div class="n">¥{profit}</div><div class="l">利润总额</div></div>
</div>
<h2 style="margin-top:24px">每日成交（{period}）</h2>
{daily_table}
</div>"#,
        uid = escape(q.uid.as_deref().unwrap_or("")),
        product = escape(q.product.as_deref().unwrap_or("")),
        start = escape(q.start.as_deref().unwrap_or("")),
        end = escape(q.end.as_deref().unwrap_or("")),
        period = escape(&period_label),
        total = stats.total_count,
        paid = stats.paid_count,
        unpaid = stats.unpaid_count,
        closed = stats.closed_count,
        refunded = stats.refunded_count,
        paid_amt = escape(&stats.paid_amount),
        total_amt = escape(&stats.total_amount),
        profit = escape(&stats.profit_amount),
    );
    Html(page("订单统计 - rpay 管理后台", &admin_nav("orders"), &body)).into_response()
}

async fn order_detail(
    State(state): State<AppState>,
    jar: CookieJar,
    Path(trade_no): Path<String>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    render_order_detail(&state, &trade_no, None).await
}

async fn render_order_detail(state: &AppState, trade_no: &str, notice: Option<&str>) -> Response {
    let order = match state.store.order_detail(trade_no).await {
        Ok(o) => o,
        Err(_) => return (axum::http::StatusCode::NOT_FOUND, "order not found").into_response(),
    };
    let notice_html = notice
        .map(|n| format!("<div class=\"msg ok\">{}</div>", escape(n)))
        .unwrap_or_default();
    let status_options = match order.status {
        0 => "<option value=\"0\" selected>待支付</option><option value=\"1\">已支付</option><option value=\"2\">已关闭</option><option value=\"3\">已退款</option>",
        1 => "<option value=\"0\">待支付</option><option value=\"1\" selected>已支付</option><option value=\"2\">已关闭</option><option value=\"3\">已退款</option>",
        2 => "<option value=\"0\">待支付</option><option value=\"1\">已支付</option><option value=\"2\" selected>已关闭</option><option value=\"3\">已退款</option>",
        _ => "<option value=\"0\">待支付</option><option value=\"1\">已支付</option><option value=\"2\">已关闭</option><option value=\"3\" selected>已退款</option>",
    };
    let body = format!(
        r#"<h1>订单详情</h1>
{notice_html}
<div class="card">
<table class="kv">
<tr><th>系统交易号</th><td>{trade_no}</td></tr>
<tr><th>商户订单号</th><td>{out_trade_no}</td></tr>
<tr><th>第三方交易号</th><td>{api_trade_no}</td></tr>
<tr><th>商户UID</th><td><a class="link" href="/admin/merchants/{uid}">{uid}</a></td></tr>
<tr><th>支付渠道ID</th><td>{channel}</td></tr>
<tr><th>商品名称</th><td>{name}</td></tr>
<tr><th>订单金额</th><td>¥{money}</td></tr>
<tr><th>实际支付</th><td>{realmoney}</td></tr>
<tr><th>到账金额</th><td>{getmoney}</td></tr>
<tr><th>利润</th><td>{profitmoney}</td></tr>
<tr><th>买家</th><td>{buyer}</td></tr>
<tr><th>状态</th><td>{status}</td></tr>
<tr><th>通知状态</th><td>{notify_status}</td></tr>
<tr><th>创建时间</th><td>{addtime}</td></tr>
<tr><th>完成时间</th><td>{endtime}</td></tr>
<tr><th>来源域名</th><td>{domain}</td></tr>
<tr><th>客户端IP</th><td>{ip}</td></tr>
<tr><th>通知URL</th><td>{notify_url}</td></tr>
<tr><th>返回URL</th><td>{return_url}</td></tr>
<tr><th>参数</th><td>{param}</td></tr>
</table>
</div>
<div class="card" style="margin-top:16px">
<h2>修改状态</h2>
<form method="post" style="display:flex;gap:8px;align-items:center">
<select name="status" style="width:auto">{status_options}</select>
<button class="btn" type="submit">保存</button>
</form>
</div>
<div class="card" style="margin-top:16px">
<h2>原路退款</h2>
<form method="post" action="/admin/orders/{trade_no}/refund" onsubmit="return confirm('确定原路退款？退款将原路返回买家支付账户。')">
<button class="btn danger" type="submit" {refund_disabled}>原路退款 ¥{realmoney}</button>
</form>
</div>
<div class="card" style="margin-top:16px">
<h2>删除订单</h2>
<form method="post" action="/admin/orders/{trade_no}/delete" onsubmit="return confirm('确定删除此订单？此操作不可恢复！')">
<button class="btn danger" type="submit">删除订单</button>
</form>
</div>"#,
        trade_no = escape(&order.trade_no),
        out_trade_no = escape(&order.out_trade_no),
        api_trade_no = escape(order.api_trade_no.as_deref().unwrap_or("-")),
        uid = order.uid,
        channel = order.channel,
        name = escape(&order.name),
        money = escape(&order.money),
        realmoney = escape(order.realmoney.as_deref().unwrap_or(&order.money)),
        getmoney = escape(order.getmoney.as_deref().unwrap_or("-")),
        profitmoney = escape(order.profitmoney.as_deref().unwrap_or("-")),
        buyer = escape(order.buyer.as_deref().unwrap_or("-")),
        status = status_badge(order.status),
        refund_disabled = if order.status == 1 { "" } else { "disabled" },
        notify_status = if order.notify_status > 0 { format!("已通知({})", order.notify_status) } else { "未通知".to_string() },
        addtime = order.addtime.map(|t| t.to_string()).unwrap_or_else(|| "-".into()),
        endtime = order.endtime.map(|t| t.to_string()).unwrap_or_else(|| "-".into()),
        domain = escape(order.domain.as_deref().unwrap_or("-")),
        ip = escape(order.ip.as_deref().unwrap_or("-")),
        notify_url = escape(&order.notify_url),
        return_url = escape(&order.return_url),
        param = escape(order.param.as_deref().unwrap_or("-")),
    );
    Html(page("订单详情 - rpay 管理后台", &admin_nav("orders"), &body)).into_response()
}

#[derive(Deserialize)]
struct OrderUpdateForm {
    status: i8,
}

async fn order_update(
    State(state): State<AppState>,
    jar: CookieJar,
    Path(trade_no): Path<String>,
    Form(form): Form<OrderUpdateForm>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    match state.store.order_update_status(&trade_no, form.status).await {
        Ok(true) => render_order_detail(&state, &trade_no, Some("状态已更新")).await,
        Ok(false) => (axum::http::StatusCode::NOT_FOUND, "order not found").into_response(),
        Err(_) => server_error(),
    }
}

async fn order_delete(
    State(state): State<AppState>,
    jar: CookieJar,
    Path(trade_no): Path<String>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    match state.store.order_delete(&trade_no).await {
        Ok(true) => Redirect::to("/admin/orders").into_response(),
        Ok(false) => (axum::http::StatusCode::NOT_FOUND, "order not found").into_response(),
        Err(_) => server_error(),
    }
}

#[derive(Deserialize)]
struct BatchActionForm {
    action: String,
    #[serde(deserialize_with = "deserialize_string_or_vec")]
    trade_nos: Vec<String>,
}

fn deserialize_string_or_vec<'de, D>(deserializer: D) -> Result<Vec<String>, D::Error>
where
    D: serde::Deserializer<'de>,
{
    use serde::de;
    #[derive(Deserialize)]
    #[serde(untagged)]
    enum StrOrVec {
        Single(String),
        Multi(Vec<String>),
    }
    match StrOrVec::deserialize(deserializer)? {
        StrOrVec::Single(s) => Ok(vec![s]),
        StrOrVec::Multi(v) => Ok(v),
    }
}

async fn orders_batch_action(
    State(state): State<AppState>,
    jar: CookieJar,
    body: Bytes,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let pairs = url::form_urlencoded::parse(&body);
    let action = pairs
        .clone()
        .find(|(k, _)| k == "action")
        .map(|(_, v)| v.to_string())
        .unwrap_or_default();
    let trade_nos: Vec<String> = pairs
        .filter(|(k, _)| k == "trade_nos")
        .map(|(_, v)| v.to_string())
        .collect();
    if trade_nos.is_empty() {
        return Redirect::to("/admin/orders").into_response();
    }
    match action.as_str() {
        "delete" => {
            match state.store.batch_delete_orders(&trade_nos).await {
                Ok(n) => {
                    let body = format!(
                        r#"<div class="card" style="text-align:center;padding:32px">
<h2>已删除 {n} 条订单</h2>
<a class="btn" href="/admin/orders">返回订单列表</a>
</div>"#
                    );
                    Html(page("批量操作 - rpay 管理后台", &admin_nav("orders"), &body)).into_response()
                }
                Err(_) => server_error(),
            }
        }
        _ => Redirect::to("/admin/orders").into_response(),
    }
}

async fn order_refund(
    State(state): State<AppState>,
    jar: CookieJar,
    Path(trade_no): Path<String>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let order = match state.store.order_detail(&trade_no).await {
        Ok(o) => o,
        Err(_) => return (axum::http::StatusCode::NOT_FOUND, "order not found").into_response(),
    };
    if order.status != 1 {
        return render_order_detail(&state, &trade_no, Some("订单状态不支持退款")).await;
    }
    let channel = match state.store.channel_detail(order.channel).await {
        Ok(c) => c,
        Err(_) => return render_order_detail(&state, &trade_no, Some("渠道配置不存在")).await,
    };
    let refund_amount = order.realmoney.as_deref().unwrap_or(&order.money);
    let result = match channel.plugin.as_str() {
        "alipay" => {
            let cfg: crate::alipay::AlipayConfig = match serde_json::from_str(channel.config.as_deref().unwrap_or("{}")) {
                Ok(c) => c,
                Err(_) => return render_order_detail(&state, &trade_no, Some("渠道配置解析失败")).await,
            };
            crate::alipay::trade_refund(&cfg, &order.trade_no, refund_amount).await
        }
        _ => Err(format!("渠道 {} 暂不支持原路退款", channel.plugin)),
    };
    match result {
        Ok(()) => {
            match state.store.order_set_refunded(&trade_no, refund_amount).await {
                Ok(true) => render_order_detail(&state, &trade_no, Some("退款成功")).await,
                Ok(false) => render_order_detail(&state, &trade_no, Some("退款已提交但状态更新失败")).await,
                Err(_) => render_order_detail(&state, &trade_no, Some("退款已提交但数据库更新失败")).await,
            }
        }
        Err(e) => render_order_detail(&state, &trade_no, Some(&e)).await,
    }
}

// ---------- channels ----------

async fn channels_list(State(state): State<AppState>, jar: CookieJar) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let channels = match state.store.list_channels_full().await {
        Ok(c) => c,
        Err(_) => return server_error(),
    };
    let mut table = String::from(
        "<table><tr><th>ID</th><th>名称</th><th>插件</th><th>支付方式</th><th>费率%</th><th>状态</th><th></th></tr>",
    );
    for c in &channels {
        table.push_str(&format!(
            "<tr><td>{id}</td><td>{name}</td><td>{plugin}</td><td>{type_name}</td><td>{rate}</td><td>{status}</td><td><a class=\"link\" href=\"/admin/channels/{id}\">配置</a></td></tr>",
            id = c.id,
            name = escape(&c.name),
            plugin = escape(&c.plugin),
            type_name = escape(c.type_name.as_deref().unwrap_or("-")),
            rate = escape(&c.rate),
            status = if c.status == 1 { "<span class=\"badge ok\">启用</span>" } else { "<span class=\"badge bad\">停用</span>" },
        ));
    }
    table.push_str("</table>");
    let body = format!("<h1>支付渠道</h1><div class=\"card\">{table}</div>");
    Html(page("支付渠道 - rpay 管理后台", &admin_nav("channels"), &body)).into_response()
}

async fn channel_detail(
    State(state): State<AppState>,
    jar: CookieJar,
    Path(id): Path<u64>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    render_channel_detail(&state, id, None).await
}

async fn render_channel_detail(state: &AppState, id: u64, notice: Option<&str>) -> Response {
    let c = match state.store.channel_detail(id).await {
        Ok(c) => c,
        Err(StoreError::NotFound) => return (axum::http::StatusCode::NOT_FOUND, "渠道不存在").into_response(),
        Err(_) => return server_error(),
    };
    let msg = notice
        .map(|n| format!("<div class=\"msg ok\">{}</div>", escape(n)))
        .unwrap_or_default();
    let pretty_config = c
        .config
        .as_deref()
        .and_then(|s| serde_json::from_str::<serde_json::Value>(s).ok())
        .and_then(|v| serde_json::to_string_pretty(&v).ok())
        .unwrap_or_else(|| c.config.clone().unwrap_or_default());
    let body = format!(
        r#"<h1>渠道配置：{name}</h1>
{msg}
<div class="card">
<form method="post" action="/admin/channels/{id}">
<label>状态</label>
<select name="status">
<option value="1" {on}>启用</option>
<option value="0" {off}>停用</option>
</select>
<label>费率（%，通常 100.00 表示无额外扣费）</label>
<input name="rate" value="{rate}">
<label>最小订单金额（元，留空不限制）</label>
<input name="paymin" value="{paymin}" placeholder="如 0.01">
<label>最大订单金额（元，留空不限制）</label>
<input name="paymax" value="{paymax}" placeholder="如 1000">
<label>渠道配置 JSON（如 appid/appkey/appsecret，字段含义取决于插件：{plugin}）</label>
<textarea name="config" rows="14" style="width:100%;font-family:monospace;font-size:13px;padding:10px;border:1px solid #e2e6ee;border-radius:6px">{config}</textarea>
<div style="margin-top:16px"><button class="btn" type="submit">保存</button> <a class="btn secondary" href="/admin/channels">返回列表</a></div>
</form>
</div>"#,
        name = escape(&c.name),
        id = id,
        on = if c.status == 1 { "selected" } else { "" },
        off = if c.status == 0 { "selected" } else { "" },
        rate = escape(&c.rate),
        paymin = escape(c.paymin.as_deref().unwrap_or("")),
        paymax = escape(c.paymax.as_deref().unwrap_or("")),
        plugin = escape(&c.plugin),
        config = escape(&pretty_config),
    );
    Html(page(&format!("渠道配置 - {}", c.name), &admin_nav("channels"), &body)).into_response()
}

#[derive(Deserialize)]
struct ChannelUpdateForm {
    status: i8,
    rate: String,
    paymin: Option<String>,
    paymax: Option<String>,
    config: String,
}

async fn channel_update(
    State(state): State<AppState>,
    jar: CookieJar,
    Path(id): Path<u64>,
    Form(form): Form<ChannelUpdateForm>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    if serde_json::from_str::<serde_json::Value>(&form.config).is_err() {
        return render_channel_detail(&state, id, Some("配置不是合法的 JSON，未保存")).await;
    }
    if state
        .store
        .update_channel(id, form.status, &form.rate, form.paymin.as_deref(), form.paymax.as_deref(), &form.config)
        .await
        .is_err()
    {
        return server_error();
    }
    render_channel_detail(&state, id, Some("已保存")).await
}

// ---------- settings ----------

const SETTING_KEYS: &[&str] = &["sitename", "admin_user", "admin_pwd", "pay_maxmoney", "pay_minmoney"];

async fn settings_page(State(state): State<AppState>, jar: CookieJar) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    render_settings(&state, None).await
}

async fn render_settings(state: &AppState, notice: Option<&str>) -> Response {
    let cfg = state.store.config_many(SETTING_KEYS).await.unwrap_or_default();
    let msg = notice
        .map(|n| format!("<div class=\"msg ok\">{}</div>", escape(n)))
        .unwrap_or_default();
    let body = format!(
        r#"<h1>系统设置</h1>
{msg}
<div class="card">
<form method="post" action="/admin/settings">
<label>站点名称</label>
<input name="sitename" value="{sitename}">
<label>管理员用户名</label>
<input name="admin_user" value="{admin_user}">
<label>管理员密码</label>
<input name="admin_pwd" value="{admin_pwd}">
<label>单笔最大支付金额（元，0 不限）</label>
<input name="pay_maxmoney" value="{pay_maxmoney}">
<label>单笔最小支付金额（元，0 不限）</label>
<input name="pay_minmoney" value="{pay_minmoney}">
<div style="margin-top:16px"><button class="btn" type="submit">保存</button></div>
</form>
</div>"#,
        sitename = escape(cfg.get("sitename").map(String::as_str).unwrap_or("")),
        admin_user = escape(cfg.get("admin_user").map(String::as_str).unwrap_or("")),
        admin_pwd = escape(cfg.get("admin_pwd").map(String::as_str).unwrap_or("")),
        pay_maxmoney = escape(cfg.get("pay_maxmoney").map(String::as_str).unwrap_or("0")),
        pay_minmoney = escape(cfg.get("pay_minmoney").map(String::as_str).unwrap_or("0")),
    );
    Html(page("系统设置 - rpay 管理后台", &admin_nav("settings"), &body)).into_response()
}

#[derive(Deserialize)]
struct SettingsForm {
    sitename: String,
    admin_user: String,
    admin_pwd: String,
    pay_maxmoney: String,
    pay_minmoney: String,
}

async fn settings_update(
    State(state): State<AppState>,
    jar: CookieJar,
    Form(form): Form<SettingsForm>,
) -> Response {
    if require_admin(&state, &jar).is_none() {
        return unauthorized_redirect();
    }
    let pairs = [
        ("sitename", form.sitename.as_str()),
        ("admin_user", form.admin_user.as_str()),
        ("admin_pwd", form.admin_pwd.as_str()),
        ("pay_maxmoney", form.pay_maxmoney.as_str()),
        ("pay_minmoney", form.pay_minmoney.as_str()),
    ];
    for (k, v) in pairs {
        if state.store.config_set(k, v).await.is_err() {
            return server_error();
        }
    }
    render_settings(&state, Some("已保存")).await
}

#[cfg(test)]
mod tests {
    use super::{parse_optional_i8, parse_optional_u64};

    #[test]
    fn empty_order_numeric_filters_are_unset() {
        assert_eq!(parse_optional_u64(Some("")), None);
        assert_eq!(parse_optional_i8(Some("")), None);
        assert_eq!(parse_optional_u64(Some("42")), Some(42));
        assert_eq!(parse_optional_i8(Some("1")), Some(1));
    }
}
