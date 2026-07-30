use aes_gcm::{
    aead::{Aead, KeyInit, Payload},
    Aes256Gcm, Key, Nonce,
};
use base64::{engine::general_purpose::STANDARD, Engine};
use rand::Rng;
use serde::Deserialize;
use serde_json::json;

use crate::protocol::{rsa_sign_sha256, rsa_verify_sha256, CryptoError};

pub const API_BASE: &str = "https://api.mch.weixin.qq.com";

/// Shared config for `wxpayn` (direct) and `wxpaynp` (service-provider) —
/// `sub_mchid`/`sub_appid` are only used in service-provider mode.
#[derive(Clone, Debug, Deserialize)]
pub struct WxpayV3Config {
    pub appid: String,
    pub appmchid: String,
    /// APIv3 key (32-byte secret), used to decrypt notify resource payloads.
    pub appsecret: String,
    /// Merchant API certificate serial number, sent as `serial_no`.
    pub appkey: String,
    /// Merchant API private key, raw base64 (PKCS1 or PKCS8), signs requests.
    pub mch_private_key: String,
    /// WeChat Pay public key (公钥模式), raw base64 SPKI, verifies responses/
    /// notifies. Matched against the `Wechatpay-Serial` header via `publickeyid`.
    pub platform_public_key: Option<String>,
    pub publickeyid: Option<String>,
    #[serde(default)]
    pub sub_mchid: Option<String>,
    #[serde(default)]
    pub sub_appid: Option<String>,
}

fn nonce_str() -> String {
    const CHARSET: &[u8] = b"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    let mut rng = rand::thread_rng();
    (0..32).map(|_| CHARSET[rng.gen_range(0..CHARSET.len())] as char).collect()
}

/// Builds the `Authorization` header per WeChat Pay V3 spec:
/// `WECHATPAY2-SHA256-RSA2048 mchid="...",nonce_str="...",timestamp="...",serial_no="...",signature="..."`
pub fn build_authorization(
    cfg: &WxpayV3Config,
    method: &str,
    url_path_with_query: &str,
    body: &str,
) -> Result<String, CryptoError> {
    let timestamp = chrono::Utc::now().timestamp().to_string();
    let nonce = nonce_str();
    let message = format!("{method}\n{url_path_with_query}\n{timestamp}\n{nonce}\n{body}\n");
    let signature = rsa_sign_sha256(&message, &cfg.mch_private_key)?;
    Ok(format!(
        "WECHATPAY2-SHA256-RSA2048 mchid=\"{}\",nonce_str=\"{}\",timestamp=\"{}\",serial_no=\"{}\",signature=\"{}\"",
        cfg.appmchid, nonce, timestamp, cfg.appkey, signature
    ))
}

#[derive(Debug, thiserror::Error)]
pub enum WxpayV3Error {
    #[error("http error: {0}")]
    Http(#[from] reqwest::Error),
    #[error("crypto error: {0}")]
    Crypto(#[from] CryptoError),
    #[error("wechat error: {code}: {message}")]
    Wechat { code: String, message: String },
    #[error("invalid response signature")]
    InvalidSignature,
    #[error("malformed response")]
    Malformed,
}

pub enum PayScene<'a> {
    Native,
    H5 { client_ip: &'a str },
    Jsapi { openid: &'a str },
}

/// Creates a WeChat Pay V3 order (`/v3/pay/transactions/{native,h5,jsapi}`).
/// Returns the `code_url` (Native, render as QR), `h5_url` (H5, redirect), or
/// `prepay_id` (JSAPI, used by the client-side JS bridge).
pub async fn create_order(
    cfg: &WxpayV3Config,
    scene: PayScene<'_>,
    out_trade_no: &str,
    total_fee_fen: i64,
    description: &str,
    notify_url: &str,
) -> Result<CreateOrderResult, WxpayV3Error> {
    let (path, mut body) = match &scene {
        PayScene::Native => (
            "/v3/pay/transactions/native",
            json!({
                "appid": cfg.appid,
                "mchid": cfg.appmchid,
                "description": description,
                "out_trade_no": out_trade_no,
                "notify_url": notify_url,
                "amount": { "total": total_fee_fen, "currency": "CNY" },
            }),
        ),
        PayScene::H5 { client_ip } => (
            "/v3/pay/transactions/h5",
            json!({
                "appid": cfg.appid,
                "mchid": cfg.appmchid,
                "description": description,
                "out_trade_no": out_trade_no,
                "notify_url": notify_url,
                "amount": { "total": total_fee_fen, "currency": "CNY" },
                "scene_info": { "payer_client_ip": client_ip, "h5_info": { "type": "Wap" } },
            }),
        ),
        PayScene::Jsapi { openid } => (
            "/v3/pay/transactions/jsapi",
            json!({
                "appid": cfg.appid,
                "mchid": cfg.appmchid,
                "description": description,
                "out_trade_no": out_trade_no,
                "notify_url": notify_url,
                "amount": { "total": total_fee_fen, "currency": "CNY" },
                "payer": { "openid": openid },
            }),
        ),
    };
    if let Some(sub_mchid) = &cfg.sub_mchid {
        body["sp_mchid"] = json!(cfg.appmchid);
        body["sub_mchid"] = json!(sub_mchid);
        body["mchid"] = serde_json::Value::Null;
        body.as_object_mut().unwrap().remove("mchid");
        body["appid"] = json!(cfg.sub_appid.as_deref().unwrap_or(&cfg.appid));
        body["sp_appid"] = json!(cfg.appid);
    }
    let body_str = serde_json::to_string(&body).map_err(|_| WxpayV3Error::Malformed)?;
    let authorization = build_authorization(cfg, "POST", path, &body_str)?;

    let client = reqwest::Client::new();
    let resp = client
        .post(format!("{API_BASE}{path}"))
        .header("Authorization", authorization)
        .header("Accept", "application/json")
        .header("Content-Type", "application/json")
        .body(body_str)
        .timeout(std::time::Duration::from_secs(10))
        .send()
        .await?;
    let status = resp.status();
    let text = resp.text().await?;
    let json: serde_json::Value = serde_json::from_str(&text).map_err(|_| WxpayV3Error::Malformed)?;
    if !status.is_success() {
        return Err(WxpayV3Error::Wechat {
            code: json.get("code").and_then(|v| v.as_str()).unwrap_or("UNKNOWN").to_string(),
            message: json.get("message").and_then(|v| v.as_str()).unwrap_or("").to_string(),
        });
    }
    Ok(CreateOrderResult {
        code_url: json.get("code_url").and_then(|v| v.as_str()).map(String::from),
        h5_url: json.get("h5_url").and_then(|v| v.as_str()).map(String::from),
        prepay_id: json.get("prepay_id").and_then(|v| v.as_str()).map(String::from),
    })
}

#[derive(Debug)]
pub struct CreateOrderResult {
    pub code_url: Option<String>,
    pub h5_url: Option<String>,
    pub prepay_id: Option<String>,
}

/// Verifies a notify/response signature (公钥模式): the WeChat side signs
/// `{timestamp}\n{nonce}\n{body}\n` with its platform private key; we verify
/// with the configured `platform_public_key`, gated on `Wechatpay-Serial`
/// matching the configured `publickeyid`.
pub fn verify_platform_signature(
    cfg: &WxpayV3Config,
    serial: &str,
    timestamp: &str,
    nonce: &str,
    body: &str,
    signature_b64: &str,
) -> Result<bool, CryptoError> {
    let Some(platform_key) = cfg.platform_public_key.as_deref() else {
        return Ok(false);
    };
    if cfg.publickeyid.as_deref() != Some(serial) {
        return Ok(false);
    }
    let message = format!("{timestamp}\n{nonce}\n{body}\n");
    rsa_verify_sha256(&message, signature_b64, platform_key)
}

#[derive(Debug, Deserialize)]
pub struct NotifyResource {
    pub ciphertext: String,
    pub nonce: String,
    pub associated_data: Option<String>,
}

#[derive(Debug, Deserialize)]
pub struct NotifyEnvelope {
    pub id: String,
    pub event_type: String,
    pub resource: NotifyResource,
}

/// Decrypts the AEAD_AES_256_GCM `resource.ciphertext` from a V3 notify body
/// using the merchant's APIv3 key. Returns the decrypted JSON as a string.
pub fn decrypt_resource(cfg: &WxpayV3Config, resource: &NotifyResource) -> Result<String, WxpayV3Error> {
    if cfg.appsecret.len() != 32 {
        return Err(WxpayV3Error::Malformed);
    }
    let key = Key::<Aes256Gcm>::from_slice(cfg.appsecret.as_bytes());
    let cipher = Aes256Gcm::new(key);
    let nonce = Nonce::from_slice(resource.nonce.as_bytes());
    let ciphertext = STANDARD.decode(&resource.ciphertext).map_err(|_| WxpayV3Error::Malformed)?;
    let aad = resource.associated_data.as_deref().unwrap_or("").as_bytes();
    let plaintext = cipher
        .decrypt(nonce, Payload { msg: &ciphertext, aad })
        .map_err(|_| WxpayV3Error::InvalidSignature)?;
    String::from_utf8(plaintext).map_err(|_| WxpayV3Error::Malformed)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn decrypt_resource_roundtrip() {
        use aes_gcm::aead::AeadInPlace;
        let key_str = "12345678901234567890123456789012"[..32].to_string();
        let cfg = WxpayV3Config {
            appid: "wx1".into(),
            appmchid: "mch1".into(),
            appsecret: key_str.clone(),
            appkey: "serial1".into(),
            mch_private_key: String::new(),
            platform_public_key: None,
            publickeyid: None,
            sub_mchid: None,
            sub_appid: None,
        };
        let key = Key::<Aes256Gcm>::from_slice(key_str.as_bytes());
        let cipher = Aes256Gcm::new(key);
        let nonce_bytes = b"123456789012";
        let nonce = Nonce::from_slice(nonce_bytes);
        let plaintext = br#"{"out_trade_no":"T1","trade_state":"SUCCESS"}"#;
        let aad = b"transaction";
        let mut buf = plaintext.to_vec();
        let tag = cipher.encrypt_in_place_detached(nonce, aad, &mut buf).unwrap();
        buf.extend_from_slice(&tag);
        let resource = NotifyResource {
            ciphertext: STANDARD.encode(&buf),
            nonce: String::from_utf8(nonce_bytes.to_vec()).unwrap(),
            associated_data: Some("transaction".to_string()),
        };
        let decrypted = decrypt_resource(&cfg, &resource).unwrap();
        assert!(decrypted.contains("T1"));
        assert!(decrypted.contains("SUCCESS"));
    }
}
