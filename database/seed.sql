-- rpay public seed data
-- Contains no real merchant, administrator, order, or provider credentials.
-- Import after database/schema.sql.

SET NAMES utf8mb4;

INSERT INTO `pay_config` (`k`, `v`) VALUES
  ('sitename', 'rpay'),
  ('reg_open', '0'),
  ('settle_open', '0'),
  ('test_open', '0'),
  ('recharge', '0'),
  ('user_refund', '1'),
  ('verifytype', '1'),
  ('pay_minmoney', '0'),
  ('pay_maxmoney', '0'),
  ('version', '2052')
ON DUPLICATE KEY UPDATE `v`=VALUES(`v`);

INSERT INTO `pay_type` (`id`, `name`, `device`, `showname`, `status`) VALUES
  (1, 'alipay', 0, '支付宝', 1),
  (2, 'wxpay',  0, '微信支付', 1),
  (3, 'paypal', 0, 'PayPal', 1),
  (4, 'stripe', 0, 'Stripe', 1)
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`),
  `device`=VALUES(`device`),
  `showname`=VALUES(`showname`),
  `status`=VALUES(`status`);

-- Legacy EasyPay-compatible plugin metadata. rpay itself dispatches by
-- pay_channel.plugin; these rows keep imported/legacy administration tools
-- compatible with the database.
INSERT INTO `pay_plugin` (`name`, `showname`, `author`, `link`, `types`, `transtypes`) VALUES
  ('alipay', '支付宝官方', 'rpay', 'https://open.alipay.com/', 'alipay', 'alipay'),
  ('wxpay',  '微信支付 V2', 'rpay', 'https://pay.weixin.qq.com/', 'wxpay', 'wxpay'),
  ('wxpayn', '微信支付 V3', 'rpay', 'https://pay.weixin.qq.com/', 'wxpay', 'wxpay'),
  ('paypal',  'PayPal', 'rpay', 'https://www.paypal.com/', 'paypal', 'paypal'),
  ('stripe',  'Stripe', 'rpay', 'https://stripe.com/', 'stripe', 'stripe')
ON DUPLICATE KEY UPDATE
  `showname`=VALUES(`showname`),
  `author`=VALUES(`author`),
  `link`=VALUES(`link`),
  `types`=VALUES(`types`),
  `transtypes`=VALUES(`transtypes`);

-- Provider credentials are intentionally empty and every channel is disabled.
-- Fill config in the admin UI, then enable only the channels you can use.
INSERT INTO `pay_channel`
  (`id`, `mode`, `type`, `plugin`, `name`, `rate`, `status`, `config`, `daymaxorder`) VALUES
  (1, 0, 1, 'alipay', '支付宝', 100.00, 0,
   '{"appid":"","appkey":"","appsecret":"","sign_type":"RSA2"}', 0),
  (2, 0, 2, 'wxpay', '微信支付 V2', 100.00, 0,
   '{"appid":"","appmchid":"","appkey":""}', 0),
  (3, 0, 2, 'wxpayn', '微信支付 V3', 100.00, 0,
   '{"appid":"","appmchid":"","appkey":"","appsecret":"","platform_public_key":""}', 0),
  (4, 0, 3, 'paypal', 'PayPal', 100.00, 0,
   '{"appid":"","appsecret":"","appkey":"","currency":"usd","currency_rate":7.2,"sandbox":true}', 0),
  (5, 0, 4, 'stripe', 'Stripe', 100.00, 0,
   '{"appsecret":"","appkey":"","currency":"usd","currency_rate":7.2,"payment_method_types":["card","alipay"]}', 0)
ON DUPLICATE KEY UPDATE
  `type`=VALUES(`type`),
  `plugin`=VALUES(`plugin`),
  `name`=VALUES(`name`);
