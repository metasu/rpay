# rpay rewrite progress

Source of truth (legacy): `/www/wwwroot/pay` (PHP EasyPay-style gateway).
New implementation: `/root/workspace/rpay` (Rust), deployed at `/opt/services/rpay`,
running as systemd unit `rpay.service` on `127.0.0.1:16889`, reusing the existing
`pay` MySQL database/schema as-is (no migrations needed — same `pay_*` tables).

## Done
- `src/protocol.rs`: PHP-compatible canonical sign string (ksort, skip empty/sign/sign_type),
  MD5 sign/verify, RSA2 (SHA256) sign/verify supporting both PKCS8 and PKCS1 raw-base64 keys
  (Alipay-generated keys are PKCS8; code tries PKCS8 then falls back to PKCS1).
- `src/alipay.rs`: builds `alipay.trade.page.pay` (PC page pay) auto-submit HTML form;
  verifies async notify / sync return signatures against Alipay's public key.
- `src/store.rs`: sqlx MySQL layer against `pay_user`, `pay_order`, `pay_channel`, `pay_type`.
  NOTE: `money`/`realmoney` are DECIMAL columns — always `CAST(... AS CHAR)` in SELECTs,
  sqlx can't decode NEWDECIMAL into String directly.
- `src/web.rs`: routes
  - `POST/GET /submit.php` — MD5-signature-verified order create (idempotent on
    uid+out_trade_no), alipay-only for now, returns Alipay auto-submit HTML directly.
  - `POST /notify/alipay` — verifies Alipay signature + amount, marks paid (idempotent),
    fires merchant notify (MD5-signed GET callback, EasyPay v0/legacy format).
  - `GET /return/alipay` — verifies Alipay signature, 303-redirects browser to merchant
    return_url with our own signed params appended.
  - `GET /api/order` — status lookup by trade_no.
  - Background task `retry_pending_notifications` retries un-acked merchant notifies
    every 15s.
- Deployed: systemd unit `/etc/systemd/system/rpay.service`, binary at
  `/opt/services/rpay/bin/rpay`, secrets at `/opt/services/rpay/secrets/` (database-url,
  admin-password — admin UI not yet built, password unused).
- Smoke-tested against production `pay` DB + real Alipay merchant keys (uid 1001):
  submit → 200 + valid signed Alipay form; idempotent resubmit → same trade_no; tampered
  sign → 403; forged notify (no valid sig) → order stays unpaid.

## Done (admin backend + merchant portal, session-based)
- `src/session.rs`: stateless HMAC-SHA256 signed cookies (`subject.role.expiry.sig`),
  keyed by the existing `pay_config.syskey` (no new secret to manage). NOT compatible
  with legacy `authcode()` PHP cookies by design — fresh rewrite, users re-login once.
- `src/templates.rs`: shared minimal HTML shell/nav (no CDN dependency).
- `src/admin.rs` mounted at `/admin/*`: login (plaintext compare against
  `pay_config.admin_user`/`admin_pwd`, matching legacy exactly), dashboard stats,
  merchant list/search/pagination, merchant detail (status/pay toggle, min/max limits,
  balance adjust, key reset), order list/filter, channel list + JSON config editor
  (rate/status/appid/appkey/...), system settings editor (sitename/admin creds/limits).
- `src/portal.rs` mounted at `/user/*`: registration (email or phone + password,
  legacy-compatible `getMd5Pwd` hash), login (both uid+key and account+password modes),
  dashboard (balance, API key, integration snippet), own order list/pagination,
  settings (key reset, password change).
- All CRUD smoke-tested end-to-end against the real `pay` DB: admin login/dashboard/
  merchant edit/channel config save, merchant register/login (both modes)/wrong-password
  rejection/orders list/key reset — all verified working, then test rows cleaned up.
- Found + fixed 2nd instance of the DECIMAL-column decode bug (`pay_channel.rate`
  needed `CAST(... AS CHAR)` too, same as `money`/`realmoney` earlier).

## Done (WeChat Pay v2/v3, PayPal, Stripe)
- `src/wxpay_v2.rs`: legacy WeChat Pay MD5 signing (verified against WeChat's own
  documentation example value), flat-XML parser (dependency-free), `unified_order`
  (Native trade_type), notify ack XML. Config: `{appid, appmchid, appkey}`.
- `src/wxpay_v3.rs`: WeChat Pay V3 RSA-SHA256 request signing (`Authorization` header
  per spec), Native/H5/JSAPI order creation, AEAD_AES_256_GCM notify resource
  decryption (unit-tested round-trip), 公钥模式 response/notify signature verification
  (`Wechatpay-Serial` matched against configured `publickeyid`). Shared by `wxpayn`
  (direct) and `wxpaynp` (service-provider — adds `sub_mchid`/`sub_appid`, `sp_mchid`/
  `sp_appid` swapped into the request body).
- `src/paypal.rs`: OAuth2 client-credentials token, Orders v2 API (create + capture),
  webhook verification via PayPal's `verify-webhook-signature` endpoint (simpler/more
  robust than local cert-chain verification). CNY->target-currency conversion via a
  configurable `currency_rate` divisor (matches legacy `channel['currency_rate']`).
- `src/stripe.rs`: Checkout Sessions API (create + retrieve), `Stripe-Signature`
  webhook HMAC-SHA256 verification (unit-tested round-trip against a hand-built
  signature). Same `currency_rate` conversion convention as PayPal.
- `src/web.rs`: `submit()` now dispatches by `channel.plugin` (not just alipay) via
  `dispatch_pay()`. WeChat Native flows render a QR page (`templates::qrcode_page`,
  inline SVG via the `qrcode` crate, no external requests) that polls `/api/order`
  client-side and redirects through `/return/poll-complete` once paid. PayPal/Stripe
  redirect to their hosted checkout pages. Added notify/return routes for all four
  new providers; `wxpay_v3_notify` has to try-decrypt against every enabled
  `wxpayn`/`wxpaynp` channel since the merchant identity only appears inside the
  encrypted resource.
- Seeded 3 new (disabled) `pay_channel` rows — `wxpay`/`wxpayn`/`wxpaynp`, all
  type=2 (wxpay) — with placeholder JSON matching the Rust config struct field
  names exactly, editable via `/admin/channels/{id}`. Also rewrote the existing
  Stripe/PayPal channel rows' config to match our field names (they were empty).
- Fixed a latent bug found in the process: `alipay_notify`/`alipay_return` were
  re-resolving the channel via `channel_for_type_name` (type-based, ambiguous with
  multiple channels per type) instead of `channel_detail(order.channel)` (the exact
  channel recorded on the order). All new notify/return handlers use the latter.
- All new protocol/crypto logic is unit-tested (17 tests total): wxpay v2 MD5 sign
  matches WeChat's own doc example; XML round-trip; AES-GCM notify decrypt
  round-trip; Stripe webhook signature round-trip; PayPal/Stripe currency
  conversion. Full regression: real alipay submit → signed form still works after
  the `channel_for_type_name` return-type refactor (now `ChannelFullRow`).

## Fixed: real Alipay `invalid-signature` in production
Root causes found and fixed, in order:
1. **Double-escaped key material**: `pay_channel.config` for the alipay channel
   (id=2) had `appsecret`/`appkey` stored with literal `\/` sequences baked into
   the base64 (a double-JSON-escaping artifact), corrupting the DER. Fixed by
   stripping the erroneous escaping directly in the DB; re-verified both keys
   parse as valid 2048-bit RSA via `openssl pkey`.
2. **Wrong product/method**: this merchant's Alipay app is only approved for
   "手机网站支付" (`alipay.trade.wap.pay` / `QUICK_WAP_WAY`), not "电脑网站支付"
   (`alipay.trade.page.pay` / `FAST_INSTANT_TRADE_PAY`) — confirmed by the legacy
   PHP admin channel config screenshot (only the WAP checkbox is enabled) and by
   observing legacy `alipay_plugin.php`'s `submit()`/`qrcode()` dispatch logic.
   `src/alipay.rs` now exposes both `build_page_pay_form` and `build_wap_pay_form`
   (refactored to share a common `build_pay_form(method, product_code, ...)`).
   `src/web.rs::pay_alipay` picks between them based on a new `is_mobile_ua()`
   check (ports legacy `checkmobile()`'s UA token list exactly). Desktop users
   get a QR code (`templates::qrcode_page`) encoding a same-domain URL
   `/wappay/alipay/{trade_no}` (new route + `alipay_wappay` handler) that renders
   the wap-pay form when opened from the phone that scans it — matches legacy
   EasyPay's "PC can still scan-to-pay via Alipay even though only WAP is signed"
   behavior exactly.
3. Axum 0.8 route syntax gotcha hit during this change: path params must be
   `{trade_no}`, not `:trade_no` (the old syntax panics at router-build time).
4. **Real root cause, confirmed by the user checking the open platform
   console**: this app's "接口加签方式" is plain `RSA` (SHA1WithRSA), not `RSA2`
   (SHA256WithRSA) — we'd hardcoded RSA2 everywhere. Added `AlipayConfig::sign_type`
   (`"RSA2"` default, `"RSA"` opt-in) and `protocol::rsa_sign_sha1`/`rsa_verify_sha1`
   (PKCS#1 v1.5 + SHA1, matching PHP `openssl_sign($data,$sig,$key)`'s
   undocumented SHA1 default when no algo arg is given). `alipay.rs`'s
   `build_pay_form`/`verify_callback` now branch on `cfg.is_rsa2()`. New unit
   test `rsa_sha1_matches_openssl_reference` asserts **exact byte equality**
   (RSA/PKCS1v15 signing is deterministic) against a signature independently
   produced by `openssl dgst -sha1 -sign` — stronger than a self-roundtrip check.
   Live channel id=2's config updated with `"sign_type":"RSA"`; verified end to
   end that `submit.php` now emits `sign_type=RSA` in the generated form.
- Verified end-to-end against the live server: desktop UA → QR page whose
  `/wappay/alipay/{trade_no}` companion endpoint correctly emits
  `alipay.trade.wap.pay`/`QUICK_WAP_WAY`; iPhone UA on `submit.php` → direct
  wap-pay form, same method/product. Not yet confirmed against Alipay's live
  gateway with a real scan (that's on the user to test next, but the "invalid
  signature" root causes on our end are resolved).

**Not live-tested** (no real credentials configured for these channels yet — unlike
alipay, we don't have production wxpay/paypal/stripe merchant keys):
- wxpay/wxpayn/wxpaynp: fill in `/admin/channels/5,6,7` with real appid/appmchid/
  keys, flip status to 1. For V3 (`wxpayn`/`wxpaynp`), either set `platform_public_key`
  + `publickeyid` (公钥模式, recommended, no cert rotation) or extend
  `wxpay_v3::verify_platform_signature` to support fetching/caching the older
  rotating platform certificates if the merchant account predates 公钥模式 support.
- paypal/stripe: fill in `/admin/channels/3,4` with real credentials + webhook
  secrets, flip status to 1.

## Not done yet (legacy PHP has 56 payment plugins; alipay/wxpay(v2+v3)/paypal/stripe ported per user's explicit scope, QQ wallet explicitly excluded)
- The remaining ~50 third-party aggregator plugins (lakala/yeepay/huifu/unionpay/
  etc.) — user deferred these; see the channel priority breakdown discussed in
  chat if revisiting.
- RSA-signed submit.php (merchants with `keytype=1`) — only MD5 merchants supported now.
- Rate-limiting/risk checks (blacklist, IP limits, domain allowlist, blockname) present
  in legacy `Pay.php` submit() — not ported.
- Channel routing rules (subchannel, roll/round-robin groups, per-group rate overrides)
  — currently just picks the first enabled channel for a `type`.
- Durable notify outbox (currently just retries in-place on the order row's `notify`
  counter — good enough for low volume, not for high volume/ordering guarantees).
- Cutover: nginx still points `pay`-domain traffic at PHP; rpay is only bound to
  127.0.0.1:16889 for now, not yet exposed publicly or wired to a real domain.

## Next steps (suggested order)
1. Decide real public domain for rpay (config.toml's `public_base_url`/systemd
   `--public-base-url` currently placeholder `https://rpay.anut.top`).
2. Port wxpay (2nd most common channel) using the same `alipay.rs` pattern.
3. Minimal admin UI (merchant list, order list, channel config editor) — currently DB
   must be edited via `mysql` CLI directly.
4. Decide cutover plan for `/www/wwwroot/pay` (parallel-run vs hard cutover vs
   per-merchant migration).
