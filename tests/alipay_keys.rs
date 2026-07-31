use base64::{engine::general_purpose::STANDARD, Engine};
use rsa::{
    pkcs8::{EncodePrivateKey, EncodePublicKey},
    RsaPrivateKey, RsaPublicKey,
};

#[test]
fn ephemeral_alipay_style_keys_sign_and_verify() {
    let mut rng = rand::rngs::OsRng;
    let private_key = RsaPrivateKey::new(&mut rng, 2048).unwrap();
    let public_key = RsaPublicKey::from(&private_key);
    let private_der = private_key.to_pkcs8_der().unwrap();
    let public_der = public_key.to_public_key_der().unwrap();
    let private_b64 = STANDARD.encode(private_der.as_bytes());
    let public_b64 = STANDARD.encode(public_der.as_bytes());
    let data = "out_trade_no=TEST123&total_amount=1.00";

    let signature = rpay::protocol::rsa_sign_sha256(data, &private_b64).unwrap();
    assert!(rpay::protocol::rsa_verify_sha256(data, &signature, &public_b64).unwrap());
    assert!(!rpay::protocol::rsa_verify_sha256("tampered", &signature, &public_b64).unwrap());
}
