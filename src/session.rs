use base64::{engine::general_purpose::URL_SAFE_NO_PAD, Engine};
use hmac::{Hmac, Mac};
use sha2::Sha256;

type HmacSha256 = Hmac<Sha256>;

#[derive(Clone, Copy, Debug, PartialEq, Eq)]
pub enum Role {
    Admin,
    Merchant,
}

impl Role {
    fn as_str(&self) -> &'static str {
        match self {
            Role::Admin => "admin",
            Role::Merchant => "merchant",
        }
    }

    fn from_str(s: &str) -> Option<Role> {
        match s {
            "admin" => Some(Role::Admin),
            "merchant" => Some(Role::Merchant),
            _ => None,
        }
    }
}

/// Stateless signed session token: `subject.role.expiry.hmac`. Not compatible
/// with the legacy PHP `authcode()` cookie scheme by design — this is a fresh
/// implementation for the Rust rewrite, not a drop-in replacement for old
/// sessions (users must log in again once).
#[derive(Clone)]
pub struct SessionCodec {
    signing_key: Vec<u8>,
}

impl SessionCodec {
    pub fn new(signing_key: impl Into<Vec<u8>>) -> Self {
        Self {
            signing_key: signing_key.into(),
        }
    }

    pub fn encode(&self, subject: &str, role: Role, ttl_seconds: i64) -> String {
        let expiry = chrono::Utc::now().timestamp() + ttl_seconds;
        let payload = format!("{subject}.{}.{expiry}", role.as_str());
        let sig = self.sign(&payload);
        format!("{payload}.{sig}")
    }

    pub fn decode(&self, token: &str) -> Option<(String, Role)> {
        let mut parts = token.rsplitn(2, '.');
        let sig = parts.next()?;
        let payload = parts.next()?;
        let expected = self.sign(payload);
        if !constant_time_eq(sig.as_bytes(), expected.as_bytes()) {
            return None;
        }
        let mut fields = payload.split('.');
        let subject = fields.next()?.to_string();
        let role = Role::from_str(fields.next()?)?;
        let expiry: i64 = fields.next()?.parse().ok()?;
        if chrono::Utc::now().timestamp() > expiry {
            return None;
        }
        Some((subject, role))
    }

    fn sign(&self, payload: &str) -> String {
        let mut mac = HmacSha256::new_from_slice(&self.signing_key).expect("hmac key");
        mac.update(payload.as_bytes());
        URL_SAFE_NO_PAD.encode(mac.finalize().into_bytes())
    }
}

fn constant_time_eq(a: &[u8], b: &[u8]) -> bool {
    use subtle::ConstantTimeEq;
    a.len() == b.len() && a.ct_eq(b).into()
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn roundtrip() {
        let codec = SessionCodec::new(b"test-signing-key".to_vec());
        let token = codec.encode("1001", Role::Merchant, 3600);
        let (sub, role) = codec.decode(&token).expect("valid token");
        assert_eq!(sub, "1001");
        assert_eq!(role, Role::Merchant);
    }

    #[test]
    fn rejects_tampered_token() {
        let codec = SessionCodec::new(b"test-signing-key".to_vec());
        let mut token = codec.encode("1001", Role::Merchant, 3600);
        token.push('x');
        assert!(codec.decode(&token).is_none());
    }

    #[test]
    fn rejects_expired_token() {
        let codec = SessionCodec::new(b"test-signing-key".to_vec());
        let token = codec.encode("1001", Role::Merchant, -1);
        assert!(codec.decode(&token).is_none());
    }
}
