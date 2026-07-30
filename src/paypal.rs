use serde::Deserialize;
use serde_json::json;

#[derive(Clone, Debug, Deserialize)]
pub struct PaypalConfig {
    pub appid: String,
    pub appsecret: String,
    #[serde(default)]
    pub sandbox: bool,
    #[serde(default = "default_currency")]
    pub currency: String,
    /// Divides the CNY amount to convert to `currency` (e.g. 7.2 for a rough
    /// CNY->USD rate). Matches legacy `channel['currency_rate']` semantics.
    #[serde(default = "default_rate")]
    pub currency_rate: f64,
    /// PayPal webhook ID (from the Developer Dashboard), required to verify
    /// webhook signatures via PayPal's verification API.
    pub webhook_id: Option<String>,
}

fn default_currency() -> String {
    "USD".to_string()
}
fn default_rate() -> f64 {
    1.0
}

impl PaypalConfig {
    fn base_url(&self) -> &'static str {
        if self.sandbox {
            "https://api-m.sandbox.paypal.com"
        } else {
            "https://api-m.paypal.com"
        }
    }

    pub fn convert_fen_to_units(&self, fen: i64) -> String {
        let cny = fen as f64 / 100.0;
        let converted = cny / self.currency_rate;
        format!("{converted:.2}")
    }
}

#[derive(Debug, thiserror::Error)]
pub enum PaypalError {
    #[error("http error: {0}")]
    Http(#[from] reqwest::Error),
    #[error("paypal error: {0}")]
    Api(String),
    #[error("malformed response")]
    Malformed,
}

async fn get_access_token(cfg: &PaypalConfig) -> Result<String, PaypalError> {
    let client = reqwest::Client::new();
    let resp = client
        .post(format!("{}/v1/oauth2/token", cfg.base_url()))
        .basic_auth(&cfg.appid, Some(&cfg.appsecret))
        .header("Content-Type", "application/x-www-form-urlencoded")
        .body("grant_type=client_credentials")
        .timeout(std::time::Duration::from_secs(10))
        .send()
        .await?;
    let json: serde_json::Value = resp.json().await?;
    json.get("access_token")
        .and_then(|v| v.as_str())
        .map(String::from)
        .ok_or(PaypalError::Malformed)
}

pub struct CreatedOrder {
    pub order_id: String,
    pub approve_url: String,
}

pub async fn create_order(
    cfg: &PaypalConfig,
    out_trade_no: &str,
    total_fee_fen: i64,
    description: &str,
    return_url: &str,
    cancel_url: &str,
) -> Result<CreatedOrder, PaypalError> {
    let token = get_access_token(cfg).await?;
    let value = cfg.convert_fen_to_units(total_fee_fen);
    let body = json!({
        "intent": "CAPTURE",
        "purchase_units": [{
            "reference_id": out_trade_no,
            "description": description,
            "amount": { "currency_code": cfg.currency, "value": value },
        }],
        "application_context": {
            "return_url": return_url,
            "cancel_url": cancel_url,
            "user_action": "PAY_NOW",
        },
    });
    let client = reqwest::Client::new();
    let resp = client
        .post(format!("{}/v2/checkout/orders", cfg.base_url()))
        .bearer_auth(&token)
        .header("Content-Type", "application/json")
        .json(&body)
        .timeout(std::time::Duration::from_secs(10))
        .send()
        .await?;
    let status = resp.status();
    let json: serde_json::Value = resp.json().await?;
    if !status.is_success() {
        return Err(PaypalError::Api(json.to_string()));
    }
    let order_id = json.get("id").and_then(|v| v.as_str()).ok_or(PaypalError::Malformed)?.to_string();
    let approve_url = json
        .get("links")
        .and_then(|l| l.as_array())
        .and_then(|links| {
            links
                .iter()
                .find(|link| link.get("rel").and_then(|r| r.as_str()) == Some("approve"))
        })
        .and_then(|link| link.get("href"))
        .and_then(|v| v.as_str())
        .ok_or(PaypalError::Malformed)?
        .to_string();
    Ok(CreatedOrder { order_id, approve_url })
}

pub async fn capture_order(cfg: &PaypalConfig, order_id: &str) -> Result<serde_json::Value, PaypalError> {
    let token = get_access_token(cfg).await?;
    let client = reqwest::Client::new();
    let resp = client
        .post(format!("{}/v2/checkout/orders/{order_id}/capture", cfg.base_url()))
        .bearer_auth(&token)
        .header("Content-Type", "application/json")
        .timeout(std::time::Duration::from_secs(10))
        .send()
        .await?;
    let status = resp.status();
    let json: serde_json::Value = resp.json().await?;
    if !status.is_success() {
        return Err(PaypalError::Api(json.to_string()));
    }
    Ok(json)
}

/// Verifies a webhook notification via PayPal's server-side verification API
/// (simpler and more robust than local cert-chain verification).
pub async fn verify_webhook(
    cfg: &PaypalConfig,
    headers: &std::collections::HashMap<String, String>,
    body: &str,
) -> Result<bool, PaypalError> {
    let Some(webhook_id) = &cfg.webhook_id else {
        return Ok(false);
    };
    let token = get_access_token(cfg).await?;
    let event: serde_json::Value = serde_json::from_str(body).map_err(|_| PaypalError::Malformed)?;
    let payload = json!({
        "auth_algo": headers.get("paypal-auth-algo"),
        "cert_url": headers.get("paypal-cert-url"),
        "transmission_id": headers.get("paypal-transmission-id"),
        "transmission_sig": headers.get("paypal-transmission-sig"),
        "transmission_time": headers.get("paypal-transmission-time"),
        "webhook_id": webhook_id,
        "webhook_event": event,
    });
    let client = reqwest::Client::new();
    let resp = client
        .post(format!("{}/v1/notifications/verify-webhook-signature", cfg.base_url()))
        .bearer_auth(&token)
        .header("Content-Type", "application/json")
        .json(&payload)
        .timeout(std::time::Duration::from_secs(10))
        .send()
        .await?;
    let json: serde_json::Value = resp.json().await?;
    Ok(json.get("verification_status").and_then(|v| v.as_str()) == Some("SUCCESS"))
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn currency_conversion() {
        let cfg = PaypalConfig {
            appid: "x".into(),
            appsecret: "y".into(),
            sandbox: true,
            currency: "USD".into(),
            currency_rate: 7.2,
            webhook_id: None,
        };
        // 720 CNY fen (= 7.20 CNY) at rate 7.2 -> 1.00 USD
        assert_eq!(cfg.convert_fen_to_units(720), "1.00");
    }
}
