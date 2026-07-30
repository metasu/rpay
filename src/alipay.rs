use std::collections::BTreeMap;

use chrono::Local;
use serde::Deserialize;

use crate::protocol::{rsa_sign_sha1, rsa_sign_sha256, rsa_verify_sha1, rsa_verify_sha256};

fn default_sign_type() -> String {
    "RSA2".to_string()
}

#[derive(Clone, Debug, Deserialize)]
pub struct AlipayConfig {
    pub appid: String,
    /// Application private key, raw base64 DER (PKCS8), no PEM headers.
    pub appsecret: String,
    /// Alipay public key, raw base64 DER (SubjectPublicKeyInfo), no PEM headers.
    pub appkey: String,
    #[serde(default)]
    pub appmchid: String,
    /// "RSA2" (SHA256, recommended/default) or "RSA" (plain, SHA1) — must
    /// match whatever "接口加签方式" is configured for this app on Alipay's
    /// open platform console. Using the wrong one causes verification
    /// failures indistinguishable from a genuinely wrong key.
    #[serde(default = "default_sign_type")]
    pub sign_type: String,
}

impl AlipayConfig {
    fn is_rsa2(&self) -> bool {
        !self.sign_type.eq_ignore_ascii_case("RSA")
    }
}

pub const GATEWAY_URL: &str = "https://openapi.alipay.com/gateway.do";

/// Build the auto-submitting HTML form for `alipay.trade.page.pay` (PC page pay).
/// Only usable if the app has signed the "电脑网站支付" (FAST_INSTANT_TRADE_PAY) product.
pub fn build_page_pay_form(
    cfg: &AlipayConfig,
    out_trade_no: &str,
    total_amount_yuan: &str,
    subject: &str,
    notify_url: &str,
    return_url: &str,
    client_ip: &str,
) -> Result<String, crate::protocol::CryptoError> {
    build_pay_form(
        cfg,
        "alipay.trade.page.pay",
        "FAST_INSTANT_TRADE_PAY",
        out_trade_no,
        total_amount_yuan,
        subject,
        notify_url,
        return_url,
        client_ip,
    )
}

/// Build the auto-submitting HTML form for `alipay.trade.wap.pay` (mobile
/// website pay). Many merchant apps are only approved for this product
/// ("手机网站支付") rather than the PC page-pay product; a desktop user is
/// instead shown a QR code (see `templates::qrcode_page`) linking back to a
/// same-domain URL that renders this exact form when opened on a phone.
pub fn build_wap_pay_form(
    cfg: &AlipayConfig,
    out_trade_no: &str,
    total_amount_yuan: &str,
    subject: &str,
    notify_url: &str,
    return_url: &str,
    client_ip: &str,
) -> Result<String, crate::protocol::CryptoError> {
    build_pay_form(
        cfg,
        "alipay.trade.wap.pay",
        "QUICK_WAP_WAY",
        out_trade_no,
        total_amount_yuan,
        subject,
        notify_url,
        return_url,
        client_ip,
    )
}

#[allow(clippy::too_many_arguments)]
fn build_pay_form(
    cfg: &AlipayConfig,
    method: &str,
    product_code: &str,
    out_trade_no: &str,
    total_amount_yuan: &str,
    subject: &str,
    notify_url: &str,
    return_url: &str,
    client_ip: &str,
) -> Result<String, crate::protocol::CryptoError> {
    let mut biz = serde_json::json!({
        "out_trade_no": out_trade_no,
        "total_amount": total_amount_yuan,
        "subject": out_trade_no,
        "product_code": product_code,
        "business_params": { "mc_create_trade_ip": client_ip },
    });
    if !cfg.appmchid.is_empty() {
        biz["seller_id"] = serde_json::Value::String(cfg.appmchid.clone());
    }
    let biz_content = serde_json::to_string(&biz).unwrap();

    let mut params: BTreeMap<String, String> = BTreeMap::new();
    params.insert("app_id".into(), cfg.appid.clone());
    params.insert("method".into(), method.into());
    params.insert("format".into(), "JSON".into());
    params.insert("charset".into(), "UTF-8".into());
    let sign_type = if cfg.is_rsa2() { "RSA2" } else { "RSA" };
    params.insert("sign_type".into(), sign_type.into());
    params.insert("version".into(), "1.0".into());
    params.insert(
        "timestamp".into(),
        Local::now().format("%Y-%m-%d %H:%M:%S").to_string(),
    );
    params.insert("notify_url".into(), notify_url.into());
    if !return_url.is_empty() {
        params.insert("return_url".into(), return_url.into());
    }
    params.insert("biz_content".into(), biz_content);

    let sign_str = crate::protocol::sign_content_alipay(&params);
    let sign = if cfg.is_rsa2() {
        rsa_sign_sha256(&sign_str, &cfg.appsecret)?
    } else {
        rsa_sign_sha1(&sign_str, &cfg.appsecret)?
    };
    params.insert("sign".into(), sign);

    let mut html = String::from(
        "<!doctype html><html><body><form id='alipaysubmit' name='alipaysubmit' action='"
    );
    html.push_str(GATEWAY_URL);
    html.push_str("?charset=UTF-8' method='POST'>");
    for (k, v) in &params {
        html.push_str("<input type='hidden' name='");
        html.push_str(k);
        html.push_str("' value='");
        html.push_str(&html_escape(v));
        html.push_str("'/>");
    }
    html.push_str(
        "<input type='submit' value='ok' style='display:none;'></form><script>document.forms['alipaysubmit'].submit();</script></body></html>",
    );
    Ok(html)
}

fn html_escape(value: &str) -> String {
    value
        .replace('&', "&amp;")
        .replace('<', "&lt;")
        .replace('>', "&gt;")
        .replace('"', "&quot;")
        .replace('\'', "&#39;")
}

/// Verify an Alipay async notify (or sync return) request against the
/// configured Alipay public key. `params` must include `sign`.
pub fn verify_callback(
    cfg: &AlipayConfig,
    params: &BTreeMap<String, String>,
) -> Result<bool, crate::protocol::CryptoError> {
    let Some(sign) = params.get("sign") else {
        return Ok(false);
    };
    let data = crate::protocol::sign_content(params);
    if cfg.is_rsa2() {
        rsa_verify_sha256(&data, sign, &cfg.appkey)
    } else {
        rsa_verify_sha1(&data, sign, &cfg.appkey)
    }
}

/// Call `alipay.trade.refund` to refund an order back to the buyer's
/// original payment method. Returns Ok(()) on success, Err with a message
/// on failure.
pub async fn trade_refund(
    cfg: &AlipayConfig,
    out_trade_no: &str,
    refund_amount_yuan: &str,
) -> Result<(), String> {
    let biz = serde_json::json!({
        "out_trade_no": out_trade_no,
        "refund_amount": refund_amount_yuan,
    });
    let biz_content = serde_json::to_string(&biz).unwrap();

    let mut params: BTreeMap<String, String> = BTreeMap::new();
    params.insert("app_id".into(), cfg.appid.clone());
    params.insert("method".into(), "alipay.trade.refund".into());
    params.insert("format".into(), "JSON".into());
    params.insert("charset".into(), "UTF-8".into());
    let sign_type = if cfg.is_rsa2() { "RSA2" } else { "RSA" };
    params.insert("sign_type".into(), sign_type.into());
    params.insert("version".into(), "1.0".into());
    params.insert(
        "timestamp".into(),
        Local::now().format("%Y-%m-%d %H:%M:%S").to_string(),
    );
    params.insert("biz_content".into(), biz_content);

    let sign_str = crate::protocol::sign_content_alipay(&params);
    let sign = if cfg.is_rsa2() {
        rsa_sign_sha256(&sign_str, &cfg.appsecret)
    } else {
        rsa_sign_sha1(&sign_str, &cfg.appsecret)
    }
    .map_err(|e| format!("签名失败: {e}"))?;
    params.insert("sign".into(), sign);

    let client = reqwest::Client::new();
    let resp = client
        .post(GATEWAY_URL)
        .form(&params)
        .send()
        .await
        .map_err(|e| format!("请求支付宝失败: {e}"))?;
    let body = resp.text().await.map_err(|e| format!("读取响应失败: {e}"))?;
    let v: serde_json::Value = serde_json::from_str(&body)
        .map_err(|e| format!("解析响应失败: {e}"))?;
    let resp_data = &v["alipay_trade_refund_response"];
    let code = resp_data["code"].as_str().unwrap_or("");
    if code == "10000" {
        Ok(())
    } else {
        let msg = resp_data["msg"].as_str().unwrap_or("");
        let sub_msg = resp_data["sub_msg"].as_str().unwrap_or("");
        Err(format!("退款失败: {code} {msg} {sub_msg}"))
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn html_escape_handles_quotes() {
        assert_eq!(html_escape("a'b\"c"), "a&#39;b&quot;c");
    }
}
