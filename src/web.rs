use std::collections::{BTreeMap, HashSet};

use axum::{
    body::Bytes,
    extract::{Path, Query, State},
    http::{HeaderMap, StatusCode},
    response::{Html, IntoResponse, Redirect, Response},
    routing::{get, post},
    Router,
};
use url::form_urlencoded;

use crate::{
    admin, alipay::{self, AlipayConfig},
    paypal::{self, PaypalConfig},
    portal, protocol,
    session::SessionCodec,
    store::{ChannelFullRow, Store, StoreError},
    stripe::{self, StripeConfig},
    templates,
    wxpay_v2::{self, WxpayV2Config},
    wxpay_v3::{self, WxpayV3Config},
};

#[derive(Clone)]
pub struct AppState {
    pub store: Store,
    pub public_base_url: String,
    pub session: SessionCodec,
}

pub fn app(state: AppState) -> Router {
    Router::new()
        .route("/healthz", get(health))
        .route("/submit.php", post(submit).get(submit))
        .route("/notify/alipay", post(alipay_notify))
        .route("/return/alipay", get(alipay_return))
        .route("/wappay/alipay/{trade_no}", get(alipay_wappay))
        .route("/notify/wxpay", post(wxpay_v2_notify))
        .route("/notify/wxpayv3", post(wxpay_v3_notify))
        .route("/notify/paypal", post(paypal_notify))
        .route("/return/paypal", get(paypal_return))
        .route("/notify/stripe", post(stripe_notify))
        .route("/return/stripe", get(stripe_return))
        .route("/return/poll-complete", get(poll_complete_return))
        .route("/api/order", get(api_order))
        .merge(admin::router())
        .merge(portal::router())
        .with_state(state)
}

async fn health() -> &'static str {
    "ok"
}

fn deserialize_boolish<'de, D>(deserializer: D) -> Result<bool, D::Error>
where
    D: serde::Deserializer<'de>,
{
    use serde::de::Error;
    let value = <String as serde::Deserialize>::deserialize(deserializer)?;
    match value.as_str() {
        "1" | "true" | "yes" | "on" => Ok(true),
        "0" | "false" | "no" | "off" | "" => Ok(false),
        _ => Err(D::Error::custom("expected a boolean or 1/0")),
    }
}

fn parse_unique_params(bytes: &[u8]) -> Result<BTreeMap<String, String>, &'static str> {
    if bytes.len() > 16 * 1024 {
        return Err("request too large");
    }
    let mut result = BTreeMap::new();
    let mut seen = HashSet::new();
    for (k, v) in form_urlencoded::parse(bytes) {
        let k = k.into_owned();
        if !seen.insert(k.clone()) {
            return Err("duplicate field");
        }
        if k.len() > 64 || v.len() > 4096 {
            return Err("field too large");
        }
        result.insert(k, v.into_owned());
    }
    Ok(result)
}

async fn submit(State(state): State<AppState>, headers: HeaderMap, body: Bytes) -> Response {
    let params = if headers
        .get("content-type")
        .and_then(|v| v.to_str().ok())
        .map(|v| v.starts_with("application/x-www-form-urlencoded"))
        .unwrap_or(!body.is_empty())
    {
        match parse_unique_params(&body) {
            Ok(p) => p,
            Err(e) => return text_response(StatusCode::BAD_REQUEST, e),
        }
    } else {
        BTreeMap::new()
    };

    let required = [
        "pid",
        "type",
        "out_trade_no",
        "notify_url",
        "return_url",
        "name",
        "money",
        "sign",
    ];
    for key in required {
        if !params.contains_key(key) {
            return text_response(StatusCode::BAD_REQUEST, "missing required field");
        }
    }

    let pid: u64 = match params["pid"].parse() {
        Ok(v) => v,
        Err(_) => return text_response(StatusCode::BAD_REQUEST, "invalid pid"),
    };

    let merchant = match state.store.merchant_by_pid(pid).await {
        Ok(m) => m,
        Err(StoreError::NotFound) => return text_response(StatusCode::FORBIDDEN, "商户不存在"),
        Err(_) => return text_response(StatusCode::SERVICE_UNAVAILABLE, "database error"),
    };
    if merchant.status == 0 || merchant.pay == 0 {
        return text_response(StatusCode::FORBIDDEN, "商户已被封禁，无法支付");
    }
    if merchant.keytype == 1 {
        return text_response(StatusCode::FORBIDDEN, "该商户只能使用RSA签名类型，暂未支持");
    }

    let signature = params["sign"].clone();
    if !protocol::verify_md5(&params, &merchant.key, &signature) {
        return text_response(StatusCode::FORBIDDEN, "签名校验失败");
    }

    let out_trade_no = params["out_trade_no"].clone();
    if out_trade_no.len() > 150
        || !out_trade_no
            .bytes()
            .all(|b| b.is_ascii_alphanumeric() || matches!(b, b'.' | b'_' | b'-' | b'|'))
    {
        return text_response(StatusCode::BAD_REQUEST, "订单号格式不正确");
    }
    let name = truncate_utf8(&params["name"], 127);
    let money = params["money"].clone();
    let Some(fen) = protocol::parse_yuan_to_fen(&money) else {
        return text_response(StatusCode::BAD_REQUEST, "金额不合法");
    };
    let notify_url = params["notify_url"].clone();
    let return_url = params["return_url"].clone();
    let param = params.get("param").cloned();
    let pay_type = params["type"].clone();
    let client_ip = "0.0.0.0";

    let existing = match state.store.find_existing_order(pid, &out_trade_no).await {
        Ok(v) => v,
        Err(_) => return text_response(StatusCode::SERVICE_UNAVAILABLE, "database error"),
    };
    let trade_no = if let Some(order) = &existing {
        if order.status > 0 {
            return text_response(
                StatusCode::CONFLICT,
                "该订单已完成支付，请勿重复发起支付",
            );
        }
        if order.money != money || order.name != name || order.notify_url != notify_url
            || order.return_url != return_url
        {
            return text_response(
                StatusCode::CONFLICT,
                "该订单支付参数有变化，请更换订单号重新发起支付",
            );
        }
        order.trade_no.clone()
    } else {
        match state
            .store
            .create_order(
                pid,
                &out_trade_no,
                &name,
                &money,
                &notify_url,
                &return_url,
                param.as_deref(),
                client_ip,
            )
            .await
        {
            Ok(v) => v,
            Err(_) => return text_response(StatusCode::INTERNAL_SERVER_ERROR, "创建订单失败，请返回重试"),
        }
    };

    let channel = match state.store.channel_for_type_name(&pay_type).await {
        Ok(c) => c,
        Err(_) => return text_response(StatusCode::SERVICE_UNAVAILABLE, "当前支付方式暂不可用"),
    };

    let _ = state
        .store
        .set_channel(&trade_no, channel.type_id, channel.id, &money)
        .await;

    dispatch_pay(&state, &channel, &trade_no, &money, fen, &name, client_ip, &headers).await
}

/// Matches legacy PHP `checkmobile()`: substring match against a fixed list
/// of mobile UA tokens (case-insensitive).
fn is_mobile_ua(headers: &HeaderMap) -> bool {
    const TOKENS: [&str; 8] = [
        "android", "midp", "nokia", "mobile", "iphone", "ipod", "blackberry", "windows phone",
    ];
    headers
        .get("user-agent")
        .and_then(|v| v.to_str().ok())
        .map(|ua| {
            let ua = ua.to_lowercase();
            TOKENS.iter().any(|t| ua.contains(t))
        })
        .unwrap_or(false)
}

/// Routes a freshly-created order to the correct provider integration based
/// on the channel's `plugin` field. `wxpayn`/`wxpaynp` share the same V3
/// client (service-provider mode is toggled purely by `sub_mchid` in config).
async fn dispatch_pay(
    state: &AppState,
    channel: &ChannelFullRow,
    trade_no: &str,
    money: &str,
    fen: i64,
    name: &str,
    client_ip: &str,
    headers: &HeaderMap,
) -> Response {
    match channel.plugin.as_str() {
        "alipay" => pay_alipay(state, channel, trade_no, money, name, client_ip, is_mobile_ua(headers)).await,
        "wxpay" => pay_wxpay_v2(state, channel, trade_no, fen, name, client_ip).await,
        "wxpayn" | "wxpaynp" => pay_wxpay_v3(state, channel, trade_no, fen, name, client_ip).await,
        "paypal" => pay_paypal(state, channel, trade_no, fen, name).await,
        "stripe" => pay_stripe(state, channel, trade_no, fen, name).await,
        other => text_response(
            StatusCode::NOT_IMPLEMENTED,
            &format!("支付插件 {other} 暂未在新网关实现"),
        ),
    }
}

fn parse_config<T: serde::de::DeserializeOwned>(channel: &ChannelFullRow) -> Option<T> {
    channel.config.as_deref().and_then(|c| serde_json::from_str::<T>(c).ok())
}

fn external_checkout_name(_merchant_order_name: &str) -> &'static str {
    "Source Code"
}

/// Most merchant apps are only approved for the "手机网站支付" (wap pay)
/// product, not "电脑网站支付" (page pay) — using the wrong `method`/
/// `product_code` against Alipay's gateway can manifest as sundry errors
/// including signature verification failures. On mobile we render the
/// wap-pay auto-submit form directly; on desktop we show a QR code (matching
/// legacy EasyPay's behavior) linking to `/wappay/alipay/:trade_no`, which
/// renders that same wap-pay form when opened from the phone that scans it.
async fn pay_alipay(
    state: &AppState,
    channel: &ChannelFullRow,
    trade_no: &str,
    money: &str,
    name: &str,
    client_ip: &str,
    is_mobile: bool,
) -> Response {
    let Some(cfg) = parse_config::<AlipayConfig>(channel) else {
        return text_response(StatusCode::SERVICE_UNAVAILABLE, "支付渠道配置错误");
    };
    let notify_url = format!("{}/notify/alipay", state.public_base_url);
    if is_mobile {
        return match alipay::build_wap_pay_form(&cfg, trade_no, money, name, &notify_url, "", client_ip) {
            Ok(html) => Html(html).into_response(),
            Err(_) => text_response(StatusCode::INTERNAL_SERVER_ERROR, "支付宝下单失败"),
        };
    }
    let qr_url = format!("{}/wappay/alipay/{trade_no}", state.public_base_url);
    let redirect_url = poll_complete_url(trade_no);
    Html(templates::qrcode_page("支付宝扫码支付", &qr_url, trade_no, &redirect_url)).into_response()
}

/// Reached by scanning the desktop QR code from a phone (or by a mobile
/// browser hitting the payment link directly): re-derives the wap-pay form
/// for an already-created order from its `trade_no`.
async fn alipay_wappay(State(state): State<AppState>, Path(trade_no): Path<String>) -> Response {
    let Ok(order) = state.store.order_by_trade_no(&trade_no).await else {
        return text_response(StatusCode::NOT_FOUND, "订单不存在");
    };
    if order.status > 0 {
        return text_response(StatusCode::CONFLICT, "该订单已完成支付");
    }
    let Ok(channel) = state.store.channel_detail(order.channel).await else {
        return text_response(StatusCode::SERVICE_UNAVAILABLE, "支付渠道不存在");
    };
    let Some(cfg) = parse_config::<AlipayConfig>(&channel) else {
        return text_response(StatusCode::SERVICE_UNAVAILABLE, "支付渠道配置错误");
    };
    let notify_url = format!("{}/notify/alipay", state.public_base_url);
    match alipay::build_wap_pay_form(
        &cfg,
        &trade_no,
        &order.money,
        &order.name,
        &notify_url,
        "",
        "0.0.0.0",
    ) {
        Ok(html) => Html(html).into_response(),
        Err(_) => text_response(StatusCode::INTERNAL_SERVER_ERROR, "支付宝下单失败"),
    }
}

async fn pay_wxpay_v2(
    state: &AppState,
    channel: &ChannelFullRow,
    trade_no: &str,
    fen: i64,
    name: &str,
    client_ip: &str,
) -> Response {
    let Some(cfg) = parse_config::<WxpayV2Config>(channel) else {
        return text_response(StatusCode::SERVICE_UNAVAILABLE, "支付渠道配置错误");
    };
    let notify_url = format!("{}/notify/wxpay", state.public_base_url);
    let result = wxpay_v2::unified_order(
        &cfg,
        wxpay_v2::TradeType::Native,
        trade_no,
        fen,
        name,
        &notify_url,
        client_ip,
        None,
    )
    .await;
    let Ok(order) = result else {
        return text_response(StatusCode::INTERNAL_SERVER_ERROR, "微信支付下单失败");
    };
    let Some(code_url) = order.code_url else {
        return text_response(StatusCode::INTERNAL_SERVER_ERROR, "微信支付未返回二维码");
    };
    let redirect_url = poll_complete_url(trade_no);
    Html(templates::qrcode_page("微信支付", &code_url, trade_no, &redirect_url)).into_response()
}

async fn pay_wxpay_v3(
    state: &AppState,
    channel: &ChannelFullRow,
    trade_no: &str,
    fen: i64,
    name: &str,
    client_ip: &str,
) -> Response {
    let Some(cfg) = parse_config::<WxpayV3Config>(channel) else {
        return text_response(StatusCode::SERVICE_UNAVAILABLE, "支付渠道配置错误");
    };
    let _ = client_ip;
    let notify_url = format!("{}/notify/wxpayv3", state.public_base_url);
    let result = wxpay_v3::create_order(&cfg, wxpay_v3::PayScene::Native, trade_no, fen, name, &notify_url).await;
    let Ok(order) = result else {
        return text_response(StatusCode::INTERNAL_SERVER_ERROR, "微信支付下单失败");
    };
    let Some(code_url) = order.code_url else {
        return text_response(StatusCode::INTERNAL_SERVER_ERROR, "微信支付未返回二维码");
    };
    let redirect_url = poll_complete_url(trade_no);
    Html(templates::qrcode_page("微信支付", &code_url, trade_no, &redirect_url)).into_response()
}

async fn pay_paypal(state: &AppState, channel: &ChannelFullRow, trade_no: &str, fen: i64, name: &str) -> Response {
    let Some(cfg) = parse_config::<PaypalConfig>(channel) else {
        return text_response(StatusCode::SERVICE_UNAVAILABLE, "支付渠道配置错误");
    };
    let return_url = format!("{}/return/paypal?trade_no={trade_no}", state.public_base_url);
    let cancel_url = format!("{}/return/paypal?trade_no={trade_no}&cancelled=true", state.public_base_url);
    match paypal::create_order(
        &cfg,
        trade_no,
        fen,
        external_checkout_name(name),
        &return_url,
        &cancel_url,
    )
    .await
    {
        Ok(order) => Redirect::to(&order.approve_url).into_response(),
        Err(_) => text_response(StatusCode::INTERNAL_SERVER_ERROR, "PayPal 下单失败"),
    }
}

async fn pay_stripe(state: &AppState, channel: &ChannelFullRow, trade_no: &str, fen: i64, name: &str) -> Response {
    let Some(cfg) = parse_config::<StripeConfig>(channel) else {
        return text_response(StatusCode::SERVICE_UNAVAILABLE, "支付渠道配置错误");
    };
    let success_url = format!(
        "{}/return/stripe?trade_no={trade_no}&session_id={{CHECKOUT_SESSION_ID}}",
        state.public_base_url
    );
    let cancel_url = format!("{}/return/stripe?trade_no={trade_no}&cancelled=true", state.public_base_url);
    match stripe::create_checkout_session(
        &cfg,
        trade_no,
        fen,
        external_checkout_name(name),
        &success_url,
        &cancel_url,
    )
    .await
    {
        Ok(session) => Redirect::to(&session.url).into_response(),
        Err(_) => text_response(StatusCode::INTERNAL_SERVER_ERROR, "Stripe 下单失败"),
    }
}

/// The QR-code waiting page polls `/api/order` client-side, then navigates
/// here once paid; this redirects on to the merchant's actual `return_url`
/// with our signed callback params appended (same signing as `notify_merchant`).
fn poll_complete_url(trade_no: &str) -> String {
    format!("/return/poll-complete?trade_no={trade_no}")
}

async fn poll_complete_return(State(state): State<AppState>, Query(q): Query<ReturnQuery>) -> Response {
    let Ok(order) = state.store.order_by_trade_no(&q.trade_no).await else {
        return (StatusCode::NOT_FOUND, "order not found").into_response();
    };
    if order.status != 1 {
        return (StatusCode::CONFLICT, "order not paid yet").into_response();
    }
    Redirect::to(&build_callback_url(&order.return_url, &state, &order.trade_no).await).into_response()
}

fn truncate_utf8(value: &str, max_chars: usize) -> String {
    value.chars().take(max_chars).collect()
}

fn text_response(status: StatusCode, msg: &str) -> Response {
    (status, msg.to_string()).into_response()
}

async fn alipay_notify(State(state): State<AppState>, body: Bytes) -> Response {
    let params = match parse_unique_params(&body) {
        Ok(p) => p,
        Err(_) => return (StatusCode::OK, "fail").into_response(),
    };
    let Some(our_trade_no) = params.get("out_trade_no").cloned() else {
        return (StatusCode::OK, "fail").into_response();
    };
    let Ok(order) = state.store.order_by_trade_no(&our_trade_no).await else {
        return (StatusCode::OK, "fail").into_response();
    };
    let Ok(channel) = state.store.channel_detail(order.channel).await else {
        return (StatusCode::OK, "fail").into_response();
    };
    let Some(cfg) = parse_config::<AlipayConfig>(&channel) else {
        return (StatusCode::OK, "fail").into_response();
    };

    let verified = alipay::verify_callback(&cfg, &params).unwrap_or(false);
    if !verified {
        return (StatusCode::OK, "fail").into_response();
    }
    let trade_status = params.get("trade_status").map(String::as_str).unwrap_or("");
    let total_amount = params.get("total_amount").cloned().unwrap_or_default();
    let expected = order.realmoney.clone().unwrap_or_else(|| order.money.clone());
    if trade_status != "TRADE_SUCCESS" && trade_status != "TRADE_FINISHED" {
        return (StatusCode::OK, "success").into_response();
    }
    if amounts_differ(&total_amount, &expected) {
        return (StatusCode::OK, "fail").into_response();
    }

    let alipay_txn_id = params.get("trade_no").cloned();
    let buyer = params
        .get("buyer_id")
        .or_else(|| params.get("buyer_open_id"))
        .cloned();
    let became_paid = state
        .store
        .mark_paid(&order.trade_no, alipay_txn_id.as_deref(), buyer.as_deref())
        .await
        .unwrap_or(false);

    if became_paid {
        let _ = notify_merchant(&state, &order.trade_no).await;
    }
    (StatusCode::OK, "success").into_response()
}

fn amounts_differ(a: &str, b: &str) -> bool {
    let pa = protocol::parse_yuan_to_fen(a);
    let pb = protocol::parse_yuan_to_fen(b);
    match (pa, pb) {
        (Some(x), Some(y)) => x != y,
        _ => true,
    }
}

#[derive(serde::Deserialize)]
struct ReturnQuery {
    trade_no: String,
}

async fn alipay_return(
    State(state): State<AppState>,
    Query(alipay_params): Query<BTreeMap<String, String>>,
) -> Response {
    let our_trade_no = alipay_params.get("out_trade_no").cloned().unwrap_or_default();
    let Ok(order) = state.store.order_by_trade_no(&our_trade_no).await else {
        return (StatusCode::NOT_FOUND, "order not found").into_response();
    };
    let Ok(channel) = state.store.channel_detail(order.channel).await else {
        return (StatusCode::SERVICE_UNAVAILABLE, "channel unavailable").into_response();
    };
    let Some(cfg) = parse_config::<AlipayConfig>(&channel) else {
        return (StatusCode::SERVICE_UNAVAILABLE, "channel config error").into_response();
    };

    let verified = alipay::verify_callback(&cfg, &alipay_params).unwrap_or(false);
    if !verified {
        return (StatusCode::FORBIDDEN, "支付宝返回验证失败").into_response();
    }

    Redirect::to(&build_callback_url(&order.return_url, &state, &order.trade_no).await)
        .into_response()
}

async fn build_callback_url(base_url: &str, state: &AppState, trade_no: &str) -> String {
    let order = match state.store.order_by_trade_no(trade_no).await {
        Ok(o) => o,
        Err(_) => return base_url.to_string(),
    };
    let key = match state.store.merchant_key(order.uid).await {
        Ok(k) => k,
        Err(_) => return base_url.to_string(),
    };
    let type_name = state
        .store
        .type_name(order.type_id)
        .await
        .unwrap_or_else(|_| "alipay".to_string());

    let mut params = BTreeMap::new();
    params.insert("pid".to_string(), order.uid.to_string());
    params.insert("trade_no".to_string(), order.trade_no.clone());
    params.insert("out_trade_no".to_string(), order.out_trade_no.clone());
    params.insert("type".to_string(), type_name);
    params.insert("name".to_string(), order.name.clone());
    params.insert("money".to_string(), order.money.clone());
    params.insert("trade_status".to_string(), "TRADE_SUCCESS".to_string());
    if let Some(p) = &order.param {
        params.insert("param".to_string(), p.clone());
    }
    let sign = protocol::sign_md5(&params, &key);
    params.insert("sign".to_string(), sign);
    params.insert("sign_type".to_string(), "MD5".to_string());

    let query: String = form_urlencoded::Serializer::new(String::new())
        .extend_pairs(params.iter())
        .finish();
    if base_url.contains('?') {
        format!("{base_url}&{query}")
    } else {
        format!("{base_url}?{query}")
    }
}

async fn notify_merchant(state: &AppState, trade_no: &str) -> Result<(), ()> {
    let order = state.store.order_by_trade_no(trade_no).await.map_err(|_| ())?;
    let key = state.store.merchant_key(order.uid).await.map_err(|_| ())?;
    let type_name = state
        .store
        .type_name(order.type_id)
        .await
        .unwrap_or_else(|_| "alipay".to_string());

    let mut params = BTreeMap::new();
    params.insert("pid".to_string(), order.uid.to_string());
    params.insert("trade_no".to_string(), order.trade_no.clone());
    params.insert("out_trade_no".to_string(), order.out_trade_no.clone());
    params.insert("type".to_string(), type_name);
    params.insert("name".to_string(), order.name.clone());
    params.insert("money".to_string(), order.money.clone());
    params.insert("trade_status".to_string(), "TRADE_SUCCESS".to_string());
    if let Some(p) = &order.param {
        params.insert("param".to_string(), p.clone());
    }
    let sign = protocol::sign_md5(&params, &key);
    params.insert("sign".to_string(), sign);
    params.insert("sign_type".to_string(), "MD5".to_string());

    let query: String = form_urlencoded::Serializer::new(String::new())
        .extend_pairs(params.iter())
        .finish();
    let url = if order.notify_url.contains('?') {
        format!("{}&{}", order.notify_url, query)
    } else {
        format!("{}?{}", order.notify_url, query)
    };

    let success = match reqwest::Client::new()
        .get(&url)
        .timeout(std::time::Duration::from_secs(8))
        .send()
        .await
    {
        Ok(resp) => match resp.text().await {
            Ok(body) => body.trim().eq_ignore_ascii_case("success"),
            Err(_) => false,
        },
        Err(_) => false,
    };
    let _ = state.store.record_notify_attempt(trade_no, success).await;
    if success {
        Ok(())
    } else {
        Err(())
    }
}

pub async fn retry_pending_notifications(state: AppState) {
    let mut interval = tokio::time::interval(std::time::Duration::from_secs(15));
    loop {
        interval.tick().await;
        let Ok(orders) = state.store.pending_notifications(20).await else {
            continue;
        };
        for order in orders {
            let _ = notify_merchant(&state, &order.trade_no).await;
        }
    }
}

pub async fn expire_pending_orders(state: AppState) {
    let mut interval = tokio::time::interval(std::time::Duration::from_secs(180));
    loop {
        interval.tick().await;
        let _ = state.store.expire_pending_orders(30).await;
    }
}

#[derive(serde::Deserialize)]
struct OrderQuery {
    trade_no: Option<String>,
}

#[derive(serde::Serialize)]
struct OrderJson {
    trade_no: String,
    out_trade_no: String,
    status: i8,
}

async fn api_order(State(state): State<AppState>, Query(q): Query<OrderQuery>) -> Response {
    let Some(trade_no) = q.trade_no else {
        return (StatusCode::BAD_REQUEST, "missing trade_no").into_response();
    };
    match state.store.order_by_trade_no(&trade_no).await {
        Ok(order) => axum::Json(OrderJson {
            trade_no: order.trade_no,
            out_trade_no: order.out_trade_no,
            status: order.status,
        })
        .into_response(),
        Err(StoreError::NotFound) => StatusCode::NOT_FOUND.into_response(),
        Err(_) => StatusCode::INTERNAL_SERVER_ERROR.into_response(),
    }
}

// ---------- WeChat Pay v2 (wxpay) ----------

async fn wxpay_v2_notify(State(state): State<AppState>, body: Bytes) -> Response {
    let Ok(text) = String::from_utf8(body.to_vec()) else {
        return (StatusCode::OK, wxpay_v2::ack_xml(false, "bad body")).into_response();
    };
    let params = wxpay_v2::parse_flat_xml(&text);
    let Some(our_trade_no) = params.get("out_trade_no").cloned() else {
        return (StatusCode::OK, wxpay_v2::ack_xml(false, "missing out_trade_no")).into_response();
    };
    let Ok(order) = state.store.order_by_trade_no(&our_trade_no).await else {
        return (StatusCode::OK, wxpay_v2::ack_xml(false, "order not found")).into_response();
    };
    let Ok(channel) = state.store.channel_detail(order.channel).await else {
        return (StatusCode::OK, wxpay_v2::ack_xml(false, "channel not found")).into_response();
    };
    let Some(cfg) = parse_config::<WxpayV2Config>(&channel) else {
        return (StatusCode::OK, wxpay_v2::ack_xml(false, "bad channel config")).into_response();
    };
    if params.get("return_code").map(String::as_str) != Some("SUCCESS")
        || params.get("result_code").map(String::as_str) != Some("SUCCESS")
    {
        return (StatusCode::OK, wxpay_v2::ack_xml(true, "OK")).into_response();
    }
    if !wxpay_v2::verify(&params, &cfg.appkey) {
        return (StatusCode::OK, wxpay_v2::ack_xml(false, "bad signature")).into_response();
    }
    let expected_fen = protocol::parse_yuan_to_fen(order.realmoney.as_deref().unwrap_or(&order.money));
    let actual_fen: Option<i64> = params.get("total_fee").and_then(|v| v.parse().ok());
    if expected_fen.is_none() || expected_fen != actual_fen {
        return (StatusCode::OK, wxpay_v2::ack_xml(false, "amount mismatch")).into_response();
    }

    let txn_id = params.get("transaction_id").cloned();
    let openid = params.get("openid").cloned();
    let became_paid = state
        .store
        .mark_paid(&order.trade_no, txn_id.as_deref(), openid.as_deref())
        .await
        .unwrap_or(false);
    if became_paid {
        let _ = notify_merchant(&state, &order.trade_no).await;
    }
    (StatusCode::OK, wxpay_v2::ack_xml(true, "OK")).into_response()
}

// ---------- WeChat Pay v3 (wxpayn / wxpaynp) ----------

async fn wxpay_v3_notify(State(state): State<AppState>, headers: HeaderMap, body: Bytes) -> Response {
    let header = |name: &str| headers.get(name).and_then(|v| v.to_str().ok()).unwrap_or("").to_string();
    let serial = header("wechatpay-serial");
    let timestamp = header("wechatpay-timestamp");
    let nonce = header("wechatpay-nonce");
    let signature = header("wechatpay-signature");
    let Ok(body_str) = String::from_utf8(body.to_vec()) else {
        return wxpay_v3_ack(false);
    };
    let Ok(envelope) = serde_json::from_str::<wxpay_v3::NotifyEnvelope>(&body_str) else {
        return wxpay_v3_ack(false);
    };
    let Ok(decrypted) = serde_json::from_str::<serde_json::Value>("{}") else {
        return wxpay_v3_ack(false);
    };
    let _ = decrypted;

    // We need the channel config before we can decrypt/verify, but the
    // decrypted resource is the only place `out_trade_no` lives — so we must
    // try every enabled wxpayn/wxpaynp channel's key until one decrypts
    // successfully (in practice there is normally exactly one).
    let Ok(channels) = state.store.list_channels_full().await else {
        return wxpay_v3_ack(false);
    };
    for channel in channels
        .iter()
        .filter(|c| c.plugin == "wxpayn" || c.plugin == "wxpaynp")
    {
        let Some(cfg) = parse_config::<WxpayV3Config>(channel) else { continue };
        if !wxpay_v3::verify_platform_signature(&cfg, &serial, &timestamp, &nonce, &body_str, &signature)
            .unwrap_or(false)
        {
            continue;
        }
        let Ok(plaintext) = wxpay_v3::decrypt_resource(&cfg, &envelope.resource) else { continue };
        let Ok(data) = serde_json::from_str::<serde_json::Value>(&plaintext) else { continue };
        let Some(our_trade_no) = data.get("out_trade_no").and_then(|v| v.as_str()) else { continue };
        let Ok(order) = state.store.order_by_trade_no(our_trade_no).await else { continue };
        if order.channel != channel.id {
            continue;
        }
        if data.get("trade_state").and_then(|v| v.as_str()) != Some("SUCCESS") {
            return wxpay_v3_ack(true);
        }
        let expected_fen = protocol::parse_yuan_to_fen(order.realmoney.as_deref().unwrap_or(&order.money));
        let actual_fen = data.get("amount").and_then(|a| a.get("total")).and_then(|v| v.as_i64());
        if expected_fen.is_none() || expected_fen != actual_fen {
            return wxpay_v3_ack(false);
        }
        let txn_id = data.get("transaction_id").and_then(|v| v.as_str());
        let openid = data
            .get("payer")
            .and_then(|p| p.get("openid"))
            .and_then(|v| v.as_str());
        let became_paid = state.store.mark_paid(&order.trade_no, txn_id, openid).await.unwrap_or(false);
        if became_paid {
            let _ = notify_merchant(&state, &order.trade_no).await;
        }
        return wxpay_v3_ack(true);
    }
    wxpay_v3_ack(false)
}

fn wxpay_v3_ack(success: bool) -> Response {
    let status = if success { StatusCode::OK } else { StatusCode::BAD_REQUEST };
    let code = if success { "SUCCESS" } else { "FAIL" };
    (status, axum::Json(serde_json::json!({ "code": code, "message": "" }))).into_response()
}

// ---------- PayPal ----------

#[derive(serde::Deserialize)]
struct PaypalReturnQuery {
    trade_no: String,
    #[serde(default, deserialize_with = "deserialize_boolish")]
    cancelled: bool,
}

async fn paypal_return(State(state): State<AppState>, Query(q): Query<PaypalReturnQuery>) -> Response {
    let Ok(order) = state.store.order_by_trade_no(&q.trade_no).await else {
        return (StatusCode::NOT_FOUND, "order not found").into_response();
    };
    if q.cancelled {
        return Redirect::to(&order.return_url).into_response();
    }
    let Ok(channel) = state.store.channel_detail(order.channel).await else {
        return (StatusCode::SERVICE_UNAVAILABLE, "channel unavailable").into_response();
    };
    let Some(cfg) = parse_config::<PaypalConfig>(&channel) else {
        return (StatusCode::SERVICE_UNAVAILABLE, "channel config error").into_response();
    };
    let Ok(capture) = paypal::capture_order(&cfg, &order.out_trade_no).await else {
        return (StatusCode::BAD_GATEWAY, "PayPal capture failed").into_response();
    };
    if capture.get("status").and_then(|v| v.as_str()) == Some("COMPLETED") {
        let txn_id = capture
            .get("purchase_units")
            .and_then(|p| p.get(0))
            .and_then(|p| p.get("payments"))
            .and_then(|p| p.get("captures"))
            .and_then(|c| c.get(0))
            .and_then(|c| c.get("id"))
            .and_then(|v| v.as_str());
        let became_paid = state.store.mark_paid(&order.trade_no, txn_id, None).await.unwrap_or(false);
        if became_paid {
            let _ = notify_merchant(&state, &order.trade_no).await;
        }
    }
    Redirect::to(&build_callback_url(&order.return_url, &state, &order.trade_no).await).into_response()
}

async fn paypal_notify(State(state): State<AppState>, headers: HeaderMap, body: Bytes) -> Response {
    let Ok(body_str) = String::from_utf8(body.to_vec()) else {
        return StatusCode::BAD_REQUEST.into_response();
    };
    let Ok(event) = serde_json::from_str::<serde_json::Value>(&body_str) else {
        return StatusCode::BAD_REQUEST.into_response();
    };
    let Some(reference_id) = event
        .get("resource")
        .and_then(|r| r.get("purchase_units"))
        .and_then(|p| p.get(0))
        .and_then(|p| p.get("reference_id"))
        .and_then(|v| v.as_str())
        .map(String::from)
        .or_else(|| {
            event
                .get("resource")
                .and_then(|r| r.get("supplementary_data"))
                .and_then(|s| s.get("related_ids"))
                .and_then(|r| r.get("order_id"))
                .and_then(|v| v.as_str())
                .map(String::from)
        })
    else {
        return StatusCode::OK.into_response();
    };
    let Ok(order) = state.store.order_by_trade_no(&reference_id).await else {
        return StatusCode::OK.into_response();
    };
    let Ok(channel) = state.store.channel_detail(order.channel).await else {
        return StatusCode::OK.into_response();
    };
    let Some(cfg) = parse_config::<PaypalConfig>(&channel) else {
        return StatusCode::OK.into_response();
    };
    let header_map: std::collections::HashMap<String, String> = headers
        .iter()
        .map(|(k, v)| (k.as_str().to_lowercase(), v.to_str().unwrap_or("").to_string()))
        .collect();
    if !paypal::verify_webhook(&cfg, &header_map, &body_str).await.unwrap_or(false) {
        return StatusCode::OK.into_response();
    }
    let event_type = event.get("event_type").and_then(|v| v.as_str()).unwrap_or("");
    if event_type == "PAYMENT.CAPTURE.COMPLETED" || event_type == "CHECKOUT.ORDER.APPROVED" {
        let became_paid = state.store.mark_paid(&order.trade_no, None, None).await.unwrap_or(false);
        if became_paid {
            let _ = notify_merchant(&state, &order.trade_no).await;
        }
    }
    StatusCode::OK.into_response()
}

// ---------- Stripe ----------

#[derive(serde::Deserialize)]
struct StripeReturnQuery {
    trade_no: String,
    session_id: Option<String>,
    #[serde(default, deserialize_with = "deserialize_boolish")]
    cancelled: bool,
}

async fn stripe_return(State(state): State<AppState>, Query(q): Query<StripeReturnQuery>) -> Response {
    let Ok(order) = state.store.order_by_trade_no(&q.trade_no).await else {
        return (StatusCode::NOT_FOUND, "order not found").into_response();
    };
    if q.cancelled {
        return Redirect::to(&order.return_url).into_response();
    }
    let Some(session_id) = q.session_id else {
        return (StatusCode::BAD_REQUEST, "missing session_id").into_response();
    };
    let Ok(channel) = state.store.channel_detail(order.channel).await else {
        return (StatusCode::SERVICE_UNAVAILABLE, "channel unavailable").into_response();
    };
    let Some(cfg) = parse_config::<StripeConfig>(&channel) else {
        return (StatusCode::SERVICE_UNAVAILABLE, "channel config error").into_response();
    };
    let Ok(session) = stripe::retrieve_checkout_session(&cfg, &session_id).await else {
        return (StatusCode::BAD_GATEWAY, "Stripe lookup failed").into_response();
    };
    if session.get("payment_status").and_then(|v| v.as_str()) == Some("paid") {
        let txn_id = session.get("payment_intent").and_then(|v| v.as_str());
        let became_paid = state.store.mark_paid(&order.trade_no, txn_id, None).await.unwrap_or(false);
        if became_paid {
            let _ = notify_merchant(&state, &order.trade_no).await;
        }
    }
    Redirect::to(&build_callback_url(&order.return_url, &state, &order.trade_no).await).into_response()
}

async fn stripe_notify(State(state): State<AppState>, headers: HeaderMap, body: Bytes) -> Response {
    let Ok(body_str) = String::from_utf8(body.to_vec()) else {
        return StatusCode::BAD_REQUEST.into_response();
    };
    let Ok(event) = serde_json::from_str::<serde_json::Value>(&body_str) else {
        return StatusCode::BAD_REQUEST.into_response();
    };
    let Some(client_reference_id) = event
        .get("data")
        .and_then(|d| d.get("object"))
        .and_then(|o| o.get("client_reference_id"))
        .and_then(|v| v.as_str())
        .map(String::from)
    else {
        return StatusCode::OK.into_response();
    };
    let Ok(order) = state.store.order_by_trade_no(&client_reference_id).await else {
        return StatusCode::OK.into_response();
    };
    let Ok(channel) = state.store.channel_detail(order.channel).await else {
        return StatusCode::OK.into_response();
    };
    let Some(cfg) = parse_config::<StripeConfig>(&channel) else {
        return StatusCode::OK.into_response();
    };
    let signature_header = headers.get("stripe-signature").and_then(|v| v.to_str().ok()).unwrap_or("");
    if !stripe::verify_webhook_signature(&cfg, signature_header, &body_str) {
        return StatusCode::BAD_REQUEST.into_response();
    }
    let event_type = event.get("type").and_then(|v| v.as_str()).unwrap_or("");
    if event_type == "checkout.session.completed" {
        let txn_id = event
            .get("data")
            .and_then(|d| d.get("object"))
            .and_then(|o| o.get("payment_intent"))
            .and_then(|v| v.as_str());
        let became_paid = state.store.mark_paid(&order.trade_no, txn_id, None).await.unwrap_or(false);
        if became_paid {
            let _ = notify_merchant(&state, &order.trade_no).await;
        }
    }
    StatusCode::OK.into_response()
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn external_checkout_name_does_not_expose_merchant_order_name() {
        assert_eq!(
            external_checkout_name("Independent Site - User 123"),
            "Source Code"
        );
    }

    #[test]
    fn return_queries_accept_legacy_and_canonical_cancel_values() {
        let legacy: StripeReturnQuery = serde_json::from_value(
            serde_json::json!({"trade_no":"T1","cancelled":"1"}),
        ).unwrap();
        let canonical: StripeReturnQuery = serde_json::from_value(
            serde_json::json!({"trade_no":"T1","cancelled":"true"}),
        ).unwrap();
        let not_cancelled: PaypalReturnQuery = serde_json::from_value(
            serde_json::json!({"trade_no":"T1","cancelled":"0"}),
        ).unwrap();
        assert!(legacy.cancelled);
        assert!(canonical.cancelled);
        assert!(!not_cancelled.cancelled);
    }
}
