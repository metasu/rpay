use std::collections::BTreeMap;

use md5::{Digest, Md5};
use rand::Rng;
use serde::Deserialize;

pub const UNIFIED_ORDER_URL: &str = "https://api.mch.weixin.qq.com/pay/unifiedorder";

#[derive(Clone, Debug, Deserialize)]
pub struct WxpayV2Config {
    pub appid: String,
    pub appmchid: String,
    /// APIv2 key, used for both request signing and notify verification.
    pub appkey: String,
}

/// WeChat Pay v2 signing: sort params by key (ASCII), join as `k=v&`, append
/// `&key=<api_key>`, MD5, uppercase hex. Matches the algorithm described in
/// WeChat Pay v2 docs (`签名生成算法`).
pub fn sign(params: &BTreeMap<String, String>, api_key: &str) -> String {
    let mut s = String::new();
    for (k, v) in params.iter().filter(|(k, v)| !v.is_empty() && k.as_str() != "sign") {
        s.push_str(k);
        s.push('=');
        s.push_str(v);
        s.push('&');
    }
    s.push_str("key=");
    s.push_str(api_key);
    let digest = Md5::digest(s.as_bytes());
    hex::encode_upper(digest)
}

pub fn verify(params: &BTreeMap<String, String>, api_key: &str) -> bool {
    let Some(sign_value) = params.get("sign") else {
        return false;
    };
    sign(params, api_key) == *sign_value
}

fn nonce_str() -> String {
    const CHARSET: &[u8] = b"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    let mut rng = rand::thread_rng();
    (0..32).map(|_| CHARSET[rng.gen_range(0..CHARSET.len())] as char).collect()
}

/// Serializes params as WeChat's flavor of XML: `<xml><k>v</k>...</xml>`,
/// with values wrapped in CDATA to avoid escaping issues (matches what the
/// WeChat Pay SDKs emit).
fn to_xml(params: &BTreeMap<String, String>) -> String {
    let mut xml = String::from("<xml>");
    for (k, v) in params {
        xml.push('<');
        xml.push_str(k);
        xml.push('>');
        xml.push_str("<![CDATA[");
        xml.push_str(v);
        xml.push_str("]]></");
        xml.push_str(k);
        xml.push('>');
    }
    xml.push_str("</xml>");
    xml
}

/// Minimal, dependency-free parser for WeChat's flat `<xml><k>v</k>...</xml>`
/// notify/response payloads (no nested elements, no attributes).
pub fn parse_flat_xml(xml: &str) -> BTreeMap<String, String> {
    let mut result = BTreeMap::new();
    let mut rest = xml;
    while let Some(open_start) = rest.find('<') {
        rest = &rest[open_start + 1..];
        if rest.starts_with("xml>") || rest.starts_with("/xml>") {
            let Some(next) = rest.find('<') else { break };
            rest = &rest[next..];
            continue;
        }
        let Some(tag_end) = rest.find('>') else { break };
        let tag = &rest[..tag_end];
        let tag = tag.trim_end_matches('/');
        if tag.is_empty() {
            break;
        }
        let close_tag = format!("</{tag}>");
        rest = &rest[tag_end + 1..];
        let Some(close_pos) = rest.find(&close_tag) else { break };
        let mut value = &rest[..close_pos];
        value = value.trim();
        value = value.strip_prefix("<![CDATA[").unwrap_or(value);
        value = value.strip_suffix("]]>").unwrap_or(value);
        result.insert(tag.to_string(), value.to_string());
        rest = &rest[close_pos + close_tag.len()..];
    }
    result
}

pub enum TradeType {
    Native,
    H5,
    Jsapi,
    App,
}

impl TradeType {
    fn as_str(&self) -> &'static str {
        match self {
            TradeType::Native => "NATIVE",
            TradeType::H5 => "MWEB",
            TradeType::Jsapi => "JSAPI",
            TradeType::App => "APP",
        }
    }
}

#[derive(Debug)]
pub struct UnifiedOrderResult {
    pub code_url: Option<String>,
    pub mweb_url: Option<String>,
    pub prepay_id: Option<String>,
}

#[derive(Debug, thiserror::Error)]
pub enum WxpayV2Error {
    #[error("http error: {0}")]
    Http(#[from] reqwest::Error),
    #[error("wechat error: {0}")]
    Wechat(String),
    #[error("invalid response signature")]
    InvalidSignature,
}

pub async fn unified_order(
    cfg: &WxpayV2Config,
    trade_type: TradeType,
    out_trade_no: &str,
    total_fee_fen: i64,
    body: &str,
    notify_url: &str,
    client_ip: &str,
    openid: Option<&str>,
) -> Result<UnifiedOrderResult, WxpayV2Error> {
    let mut params = BTreeMap::new();
    params.insert("appid".to_string(), cfg.appid.clone());
    params.insert("mch_id".to_string(), cfg.appmchid.clone());
    params.insert("nonce_str".to_string(), nonce_str());
    params.insert("body".to_string(), body.to_string());
    params.insert("out_trade_no".to_string(), out_trade_no.to_string());
    params.insert("total_fee".to_string(), total_fee_fen.to_string());
    params.insert("spbill_create_ip".to_string(), client_ip.to_string());
    params.insert("notify_url".to_string(), notify_url.to_string());
    params.insert("trade_type".to_string(), trade_type.as_str().to_string());
    if let Some(openid) = openid {
        params.insert("openid".to_string(), openid.to_string());
    }
    let sign_value = sign(&params, &cfg.appkey);
    params.insert("sign".to_string(), sign_value);

    let xml_body = to_xml(&params);
    let client = reqwest::Client::new();
    let resp = client
        .post(UNIFIED_ORDER_URL)
        .header("Content-Type", "text/xml")
        .body(xml_body)
        .timeout(std::time::Duration::from_secs(10))
        .send()
        .await?;
    let text = resp.text().await?;
    let result = parse_flat_xml(&text);

    if result.get("return_code").map(String::as_str) != Some("SUCCESS") {
        return Err(WxpayV2Error::Wechat(
            result.get("return_msg").cloned().unwrap_or_else(|| "unknown error".to_string()),
        ));
    }
    if result.get("result_code").map(String::as_str) != Some("SUCCESS") {
        return Err(WxpayV2Error::Wechat(format!(
            "{}: {}",
            result.get("err_code").cloned().unwrap_or_default(),
            result.get("err_code_des").cloned().unwrap_or_default()
        )));
    }
    if !verify(&result, &cfg.appkey) {
        return Err(WxpayV2Error::InvalidSignature);
    }

    Ok(UnifiedOrderResult {
        code_url: result.get("code_url").cloned(),
        mweb_url: result.get("mweb_url").cloned(),
        prepay_id: result.get("prepay_id").cloned(),
    })
}

/// Standard WeChat Pay v2 notify ack body.
pub fn ack_xml(success: bool, msg: &str) -> String {
    let code = if success { "SUCCESS" } else { "FAIL" };
    format!("<xml><return_code><![CDATA[{code}]]></return_code><return_msg><![CDATA[{msg}]]></return_msg></xml>")
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn sign_matches_known_wechat_doc_example() {
        // Example values adapted from WeChat Pay v2 signing documentation.
        let mut params = BTreeMap::new();
        params.insert("appid".to_string(), "wxd930ea5d5a258f4f".to_string());
        params.insert("mch_id".to_string(), "10000100".to_string());
        params.insert("device_info".to_string(), "1000".to_string());
        params.insert("body".to_string(), "test".to_string());
        params.insert("nonce_str".to_string(), "ibuaiVcKdpRxkhJA".to_string());
        let sig = sign(&params, "192006250b4c09247ec02edce69f6a2d");
        assert_eq!(sig, "9A0A8659F005D6984697E2CA0A9CF3B7");
    }

    #[test]
    fn verify_roundtrip() {
        let mut params = BTreeMap::new();
        params.insert("out_trade_no".to_string(), "T123".to_string());
        params.insert("total_fee".to_string(), "100".to_string());
        let s = sign(&params, "testkey");
        params.insert("sign".to_string(), s);
        assert!(verify(&params, "testkey"));
        assert!(!verify(&params, "wrongkey"));
    }

    #[test]
    fn parse_flat_xml_basic() {
        let xml = "<xml><return_code><![CDATA[SUCCESS]]></return_code><out_trade_no><![CDATA[T123]]></out_trade_no></xml>";
        let parsed = parse_flat_xml(xml);
        assert_eq!(parsed.get("return_code"), Some(&"SUCCESS".to_string()));
        assert_eq!(parsed.get("out_trade_no"), Some(&"T123".to_string()));
    }

    #[test]
    fn xml_roundtrip_through_parser() {
        let mut params = BTreeMap::new();
        params.insert("a".to_string(), "1".to_string());
        params.insert("b".to_string(), "hello world".to_string());
        let xml = to_xml(&params);
        let parsed = parse_flat_xml(&xml);
        assert_eq!(parsed, params);
    }
}
