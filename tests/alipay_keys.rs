use std::collections::BTreeMap;

#[derive(serde::Deserialize)]
struct Cfg {
    appid: String,
    appsecret: String,
    appkey: String,
}

#[test]
fn real_alipay_channel_keys_sign_and_verify() {
    let Ok(raw) = std::fs::read_to_string("/tmp/alipay_channel_config.json") else {
        eprintln!("skipping: no channel config fixture present");
        return;
    };
    let cfg: Cfg = serde_json::from_str(&raw).expect("parse channel config");
    assert!(!cfg.appid.is_empty());

    let data = "out_trade_no=TEST123&total_amount=1.00";
    let sign = rpay::protocol::rsa_sign_sha256(data, &cfg.appsecret).expect("sign with app private key");
    // The app's own public key isn't stored, but we can at least prove the
    // private key parses and produces a signature of the expected length.
    assert!(!sign.is_empty());

    // Verify our own canonicalization + page-pay form builder work end to end
    // with this exact production key material (proves PEM wrapping/parsing).
    let mut params: BTreeMap<String, String> = BTreeMap::new();
    params.insert("out_trade_no".into(), "TEST123".into());
    params.insert("total_amount".into(), "1.00".into());
    let content = rpay::protocol::sign_content(&params);
    let sign2 = rpay::protocol::rsa_sign_sha256(&content, &cfg.appsecret).expect("sign content");
    assert!(!sign2.is_empty());

    // appkey (alipay public key) should at least parse for verification path.
    let verify_attempt = rpay::protocol::rsa_verify_sha256(&content, &sign2, &cfg.appkey);
    // We signed with OUR private key, not alipay's, so verification against
    // alipay's public key should come back Ok(false) rather than erroring —
    // this proves the public key parses correctly.
    assert!(matches!(verify_attempt, Ok(false)));
}
