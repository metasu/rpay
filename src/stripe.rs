use hmac::{Hmac, Mac};
use serde::Deserialize;
use sha2::Sha256;
use subtle::ConstantTimeEq;

type HmacSha256 = Hmac<Sha256>;

pub const API_BASE: &str = "https://api.stripe.com/v1";

#[derive(Clone, Debug, Deserialize)]
pub struct StripeConfig {
    pub appsecret: String,
    /// Webhook signing secret (`whsec_...`), required to verify notify calls.
    pub appkey: Option<String>,
    #[serde(default = "default_currency")]
    pub currency: String,
    /// Divides the CNY amount to convert to `currency`, e.g. 7.2 for a rough
    /// CNY->USD rate. Matches legacy `channel['currency_rate']` semantics.
    #[serde(default = "default_rate")]
    pub currency_rate: f64,
    /// Checkout methods, additionally filtered by Stripe account capabilities.
    #[serde(default = "default_payment_method_types")]
    pub payment_method_types: Vec<String>,
}

fn default_currency() -> String {
    "usd".to_string()
}
fn default_rate() -> f64 {
    1.0
}
fn default_payment_method_types() -> Vec<String> {
    vec!["card".to_string(), "alipay".to_string()]
}

impl StripeConfig {
    /// Converts a CNY-fen amount into the smallest unit of `currency` (e.g.
    /// USD cents), matching Stripe's `unit_amount` convention.
    pub fn convert_fen_to_smallest_unit(&self, fen: i64) -> i64 {
        let cny = fen as f64 / 100.0;
        let converted = cny / self.currency_rate;
        (converted * 100.0).round() as i64
    }
}

#[derive(Debug, thiserror::Error)]
pub enum StripeError {
    #[error("http error: {0}")]
    Http(#[from] reqwest::Error),
    #[error("stripe error: {0}")]
    Api(String),
    #[error("malformed response")]
    Malformed,
}

pub struct CheckoutSession {
    pub id: String,
    pub url: String,
}

pub async fn create_checkout_session(
    cfg: &StripeConfig,
    out_trade_no: &str,
    total_fee_fen: i64,
    description: &str,
    success_url: &str,
    cancel_url: &str,
) -> Result<CheckoutSession, StripeError> {
    let amount = cfg.convert_fen_to_smallest_unit(total_fee_fen);
    let mut params: Vec<(String, String)> = vec![
        ("mode".to_string(), "payment".to_string()),
        ("line_items[0][price_data][currency]".to_string(), cfg.currency.clone()),
        ("line_items[0][price_data][product_data][name]".to_string(), description.to_string()),
        ("line_items[0][price_data][unit_amount]".to_string(), amount.to_string()),
        ("line_items[0][quantity]".to_string(), "1".to_string()),
        ("client_reference_id".to_string(), out_trade_no.to_string()),
        ("metadata[out_trade_no]".to_string(), out_trade_no.to_string()),
        ("success_url".to_string(), success_url.to_string()),
        ("cancel_url".to_string(), cancel_url.to_string()),
    ];
    for (index, method) in cfg.payment_method_types.iter().enumerate() {
        params.push((format!("payment_method_types[{index}]"), method.clone()));
    }
    let client = reqwest::Client::new();
    let resp = client
        .post(format!("{API_BASE}/checkout/sessions"))
        .bearer_auth(&cfg.appsecret)
        .form(&params)
        .timeout(std::time::Duration::from_secs(10))
        .send()
        .await?;
    let status = resp.status();
    let json: serde_json::Value = resp.json().await?;
    if !status.is_success() {
        return Err(StripeError::Api(json.to_string()));
    }
    let id = json.get("id").and_then(|v| v.as_str()).ok_or(StripeError::Malformed)?.to_string();
    let url = json.get("url").and_then(|v| v.as_str()).ok_or(StripeError::Malformed)?.to_string();
    Ok(CheckoutSession { id, url })
}

pub async fn retrieve_checkout_session(cfg: &StripeConfig, id: &str) -> Result<serde_json::Value, StripeError> {
    let client = reqwest::Client::new();
    let resp = client
        .get(format!("{API_BASE}/checkout/sessions/{id}"))
        .bearer_auth(&cfg.appsecret)
        .timeout(std::time::Duration::from_secs(10))
        .send()
        .await?;
    let status = resp.status();
    let json: serde_json::Value = resp.json().await?;
    if !status.is_success() {
        return Err(StripeError::Api(json.to_string()));
    }
    Ok(json)
}

/// Verifies the `Stripe-Signature` header: `t=<timestamp>,v1=<hex hmac>[,v1=...]`.
/// Signed payload is `"{timestamp}.{raw_body}"`, HMAC-SHA256 keyed by the
/// webhook signing secret, compared in constant time against each `v1` value.
pub fn verify_webhook_signature(cfg: &StripeConfig, signature_header: &str, body: &str) -> bool {
    let Some(secret) = cfg.appkey.as_deref() else {
        return false;
    };
    let mut timestamp = None;
    let mut signatures = Vec::new();
    for part in signature_header.split(',') {
        let mut kv = part.splitn(2, '=');
        match (kv.next(), kv.next()) {
            (Some("t"), Some(v)) => timestamp = Some(v),
            (Some("v1"), Some(v)) => signatures.push(v),
            _ => {}
        }
    }
    let Some(timestamp) = timestamp else { return false };
    if signatures.is_empty() {
        return false;
    }
    let signed_payload = format!("{timestamp}.{body}");
    let mut mac = HmacSha256::new_from_slice(secret.as_bytes()).expect("hmac key");
    mac.update(signed_payload.as_bytes());
    let expected = hex::encode(mac.finalize().into_bytes());
    signatures
        .iter()
        .any(|sig| sig.as_bytes().ct_eq(expected.as_bytes()).into())
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn currency_conversion() {
        let cfg = StripeConfig {
            appsecret: "sk_test".into(),
            appkey: None,
            currency: "usd".into(),
            currency_rate: 7.2,
            payment_method_types: default_payment_method_types(),
        };
        // 720 CNY fen (7.20 CNY) at rate 7.2 -> 100 cents ($1.00)
        assert_eq!(cfg.convert_fen_to_smallest_unit(720), 100);
    }

    #[test]
    fn payment_methods_default_to_card_and_alipay() {
        let cfg: StripeConfig = serde_json::from_str(r#"{"appsecret":"sk_test"}"#).unwrap();
        assert_eq!(cfg.payment_method_types, vec!["card", "alipay"]);
    }

    #[test]
    fn payment_methods_can_be_limited_to_card() {
        let cfg: StripeConfig = serde_json::from_str(
            r#"{"appsecret":"sk_test","payment_method_types":["card"]}"#,
        ).unwrap();
        assert_eq!(cfg.payment_method_types, vec!["card"]);
    }

    #[test]
    fn webhook_signature_roundtrip() {
        let cfg = StripeConfig {
            appsecret: "sk_test".into(),
            appkey: Some("whsec_testsecret".into()),
            currency: "usd".into(),
            currency_rate: 1.0,
            payment_method_types: default_payment_method_types(),
        };
        let body = r#"{"id":"evt_1"}"#;
        let timestamp = "1614556800";
        let signed_payload = format!("{timestamp}.{body}");
        let mut mac = HmacSha256::new_from_slice(b"whsec_testsecret").unwrap();
        mac.update(signed_payload.as_bytes());
        let sig = hex::encode(mac.finalize().into_bytes());
        let header = format!("t={timestamp},v1={sig}");
        assert!(verify_webhook_signature(&cfg, &header, body));
        assert!(!verify_webhook_signature(&cfg, &format!("t={timestamp},v1=deadbeef"), body));
    }
}
