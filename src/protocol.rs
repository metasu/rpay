use std::collections::BTreeMap;

use base64::{engine::general_purpose::STANDARD, Engine};
use md5::{Digest, Md5};
use rand::Rng;
use rsa::{
    pkcs1::DecodeRsaPrivateKey,
    pkcs8::{DecodePrivateKey, DecodePublicKey},
    pkcs1v15::{Signature as RsaSignature, SigningKey, VerifyingKey},
    signature::{RandomizedSigner, SignatureEncoding, Verifier},
    RsaPrivateKey, RsaPublicKey,
};
use sha1::Sha1;
use sha2::Sha256;
use subtle::ConstantTimeEq;

/// Build the EasyPay/PHP-compatible canonical sign string for the **merchant**
/// protocol (MD5): ksort ascending, skip `sign`/`sign_type`/empty values,
/// join as `k=v&k=v`. `sign_type` is excluded here because the legacy EasyPay
/// merchant protocol does not include it in the sign string.
pub fn sign_content(params: &BTreeMap<String, String>) -> String {
    params
        .iter()
        .filter(|(k, v)| !v.is_empty() && k.as_str() != "sign" && k.as_str() != "sign_type")
        .map(|(k, v)| format!("{k}={v}"))
        .collect::<Vec<_>>()
        .join("&")
}

/// Build the Alipay RSA sign string: same as `sign_content` but **includes**
/// `sign_type` (matching the PHP AlipaySDK's `getSignContent` which only
/// skips `sign` and empty values). Using the wrong one causes Alipay's
/// gateway to reject with `invalid-signature` because the sign string it
/// reconstructs includes `sign_type=...` while ours didn't.
pub fn sign_content_alipay(params: &BTreeMap<String, String>) -> String {
    params
        .iter()
        .filter(|(k, v)| !v.is_empty() && k.as_str() != "sign")
        .map(|(k, v)| format!("{k}={v}"))
        .collect::<Vec<_>>()
        .join("&")
}

pub fn sign_md5(params: &BTreeMap<String, String>, key: &str) -> String {
    let mut hasher = Md5::new();
    hasher.update(sign_content(params).as_bytes());
    hasher.update(key.as_bytes());
    hex::encode(hasher.finalize())
}

pub fn verify_md5(params: &BTreeMap<String, String>, key: &str, signature: &str) -> bool {
    let expected = sign_md5(params, key);
    expected.as_bytes().ct_eq(signature.as_bytes()).into()
}

/// Matches legacy PHP `getMd5Pwd($pwd, $salt)`:
/// `md5(md5($pwd) . md5('1277180438'.$salt))`. Used for merchant portal
/// account/password login and registration, salted with the merchant's uid.
pub fn legacy_password_hash(password: &str, salt: &str) -> String {
    let inner_pwd = hex::encode(Md5::digest(password.as_bytes()));
    let inner_salt = hex::encode(Md5::digest(format!("1277180438{salt}").as_bytes()));
    hex::encode(Md5::digest(format!("{inner_pwd}{inner_salt}").as_bytes()))
}

/// Generates a random 32-char lowercase alphanumeric merchant API key, matching
/// the shape of legacy-generated keys.
pub fn generate_merchant_key() -> String {
    const CHARSET: &[u8] = b"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
    let mut rng = rand::thread_rng();
    (0..32)
        .map(|_| CHARSET[rng.gen_range(0..CHARSET.len())] as char)
        .collect()
}

/// Merchant/platform RSA keys as stored by legacy PHP: raw base64 DER, no PEM
/// header/footer. Wrap into PEM so the `rsa` crate can parse them.
fn wrap_public_key_pem(raw_base64: &str) -> String {
    format!(
        "-----BEGIN PUBLIC KEY-----\n{}\n-----END PUBLIC KEY-----",
        wrap64(raw_base64)
    )
}

fn wrap64(raw: &str) -> String {
    let raw = raw.trim();
    let raw = raw
        .strip_prefix("-----BEGIN RSA PRIVATE KEY-----")
        .and_then(|s| s.strip_suffix("-----END RSA PRIVATE KEY-----"))
        .map(str::trim)
        .unwrap_or(raw);
    let raw = raw
        .strip_prefix("-----BEGIN PRIVATE KEY-----")
        .and_then(|s| s.strip_suffix("-----END PRIVATE KEY-----"))
        .map(str::trim)
        .unwrap_or(raw);
    let raw = raw
        .strip_prefix("-----BEGIN PUBLIC KEY-----")
        .and_then(|s| s.strip_suffix("-----END PUBLIC KEY-----"))
        .map(str::trim)
        .unwrap_or(raw);
    let compact: String = raw.chars().filter(|c| !c.is_whitespace()).collect();
    compact
        .as_bytes()
        .chunks(64)
        .map(|c| std::str::from_utf8(c).unwrap())
        .collect::<Vec<_>>()
        .join("\n")
}

/// Alipay-generated app private keys may be exported as either PKCS1
/// ("RSA PRIVATE KEY") or PKCS8 ("PRIVATE KEY") DER, stored raw-base64
/// without PEM headers. Try PKCS8 first, then fall back to PKCS1.
fn parse_private_key(raw_base64: &str) -> Result<RsaPrivateKey, CryptoError> {
    let compact = wrap64(raw_base64);
    let pkcs8_pem = format!("-----BEGIN PRIVATE KEY-----\n{compact}\n-----END PRIVATE KEY-----");
    if let Ok(key) = RsaPrivateKey::from_pkcs8_pem(&pkcs8_pem) {
        return Ok(key);
    }
    let pkcs1_pem =
        format!("-----BEGIN RSA PRIVATE KEY-----\n{compact}\n-----END RSA PRIVATE KEY-----");
    RsaPrivateKey::from_pkcs1_pem(&pkcs1_pem).map_err(|_| CryptoError::InvalidKey)
}

#[derive(Debug, thiserror::Error)]
pub enum CryptoError {
    #[error("invalid key material")]
    InvalidKey,
    #[error("invalid signature encoding")]
    InvalidSignature,
}

/// RSA2 (SHA256 + PKCS#1 v1.5) sign using a raw-base64-DER PKCS8 private key.
pub fn rsa_sign_sha256(data: &str, private_key_raw_b64: &str) -> Result<String, CryptoError> {
    let private_key = parse_private_key(private_key_raw_b64)?;
    let signing_key = SigningKey::<Sha256>::new(private_key);
    let signature = signing_key.sign_with_rng(&mut rand::rngs::OsRng, data.as_bytes());
    Ok(STANDARD.encode(signature.to_bytes()))
}

/// RSA2 verify using a raw-base64-DER SubjectPublicKeyInfo public key.
pub fn rsa_verify_sha256(
    data: &str,
    signature_b64: &str,
    public_key_raw_b64: &str,
) -> Result<bool, CryptoError> {
    let pem = wrap_public_key_pem(public_key_raw_b64);
    let public_key =
        RsaPublicKey::from_public_key_pem(&pem).map_err(|_| CryptoError::InvalidKey)?;
    let verifying_key = VerifyingKey::<Sha256>::new(public_key);
    let bytes = STANDARD
        .decode(signature_b64.as_bytes())
        .map_err(|_| CryptoError::InvalidSignature)?;
    let signature =
        RsaSignature::try_from(bytes.as_slice()).map_err(|_| CryptoError::InvalidSignature)?;
    Ok(verifying_key.verify(data.as_bytes(), &signature).is_ok())
}

/// Plain RSA (SHA1 + PKCS#1 v1.5), matching legacy Alipay `sign_type=RSA`.
pub fn rsa_sign_sha1(data: &str, private_key_raw_b64: &str) -> Result<String, CryptoError> {
    let private_key = parse_private_key(private_key_raw_b64)?;
    let signing_key = SigningKey::<Sha1>::new(private_key);
    let signature = signing_key.sign_with_rng(&mut rand::rngs::OsRng, data.as_bytes());
    Ok(STANDARD.encode(signature.to_bytes()))
}

/// Plain RSA (SHA1) verify, counterpart to [`rsa_sign_sha1`].
pub fn rsa_verify_sha1(
    data: &str,
    signature_b64: &str,
    public_key_raw_b64: &str,
) -> Result<bool, CryptoError> {
    let pem = wrap_public_key_pem(public_key_raw_b64);
    let public_key =
        RsaPublicKey::from_public_key_pem(&pem).map_err(|_| CryptoError::InvalidKey)?;
    let verifying_key = VerifyingKey::<Sha1>::new(public_key);
    let bytes = STANDARD
        .decode(signature_b64.as_bytes())
        .map_err(|_| CryptoError::InvalidSignature)?;
    let signature =
        RsaSignature::try_from(bytes.as_slice()).map_err(|_| CryptoError::InvalidSignature)?;
    Ok(verifying_key.verify(data.as_bytes(), &signature).is_ok())
}

/// Parse a decimal amount string into minor units (fen).
pub fn parse_yuan_to_fen(value: &str) -> Option<i64> {
    if value.is_empty() || !value.bytes().all(|b| b.is_ascii_digit() || b == b'.') {
        return None;
    }
    let mut parts = value.split('.');
    let whole = parts.next()?;
    let fraction = parts.next().unwrap_or("");
    if parts.next().is_some() || whole.is_empty() || fraction.len() > 2 {
        return None;
    }
    let whole: i64 = whole.parse().ok()?;
    let fraction_padded = format!("{fraction:0<2}");
    let frac: i64 = fraction_padded.parse().ok()?;
    Some(whole.checked_mul(100)?.checked_add(frac)?)
}

pub fn fen_to_yuan_string(fen: i64) -> String {
    format!("{}.{:02}", fen / 100, fen.abs() % 100)
}

#[cfg(test)]
mod tests {
    use super::*;
    use rsa::pkcs8::{EncodePrivateKey, EncodePublicKey};

    fn ephemeral_keypair() -> (String, String) {
        let mut rng = rand::rngs::OsRng;
        let private_key = RsaPrivateKey::new(&mut rng, 2048).unwrap();
        let public_key = RsaPublicKey::from(&private_key);
        let private_der = private_key.to_pkcs8_der().unwrap();
        let public_der = public_key.to_public_key_der().unwrap();
        (
            STANDARD.encode(private_der.as_bytes()),
            STANDARD.encode(public_der.as_bytes()),
        )
    }

    #[test]
    fn sign_content_matches_php_ksort_semantics() {
        let mut params = BTreeMap::new();
        params.insert("pid".to_string(), "1001".to_string());
        params.insert("money".to_string(), "1.00".to_string());
        params.insert("sign".to_string(), "ignored".to_string());
        params.insert("sign_type".to_string(), "MD5".to_string());
        params.insert("empty_field".to_string(), "".to_string());
        assert_eq!(sign_content(&params), "money=1.00&pid=1001");
    }

    #[test]
    fn md5_roundtrip() {
        let mut params = BTreeMap::new();
        params.insert("a".to_string(), "1".to_string());
        let sig = sign_md5(&params, "secret");
        assert!(verify_md5(&params, "secret", &sig));
        assert!(!verify_md5(&params, "wrong", &sig));
    }

    #[test]
    fn legacy_password_hash_matches_php_reference() {
        assert_eq!(
            legacy_password_hash("mypassword123", "1001"),
            "96d760bb8925f4dc4ab5453a3e62e816"
        );
    }

    #[test]
    fn rsa_sha1_roundtrip_with_ephemeral_key() {
        let (private_key, public_key) = ephemeral_keypair();
        let data = "hello=world";
        let signature = rsa_sign_sha1(data, &private_key).unwrap();
        assert!(rsa_verify_sha1(data, &signature, &public_key).unwrap());
        assert!(!rsa_verify_sha1("tampered", &signature, &public_key).unwrap());
    }

    #[test]
    fn yuan_fen_roundtrip() {
        assert_eq!(parse_yuan_to_fen("1.00"), Some(100));
        assert_eq!(parse_yuan_to_fen("0.5"), Some(50));
        assert_eq!(parse_yuan_to_fen("15"), Some(1500));
        assert_eq!(parse_yuan_to_fen(""), None);
        assert_eq!(parse_yuan_to_fen("1.234"), None);
        assert_eq!(fen_to_yuan_string(1500), "15.00");
    }
}
