-- rpay database schema
-- Compatible with MySQL 5.7+ and MariaDB 10.3+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- pay_config: 系统配置
CREATE TABLE IF NOT EXISTS `pay_config` (
  `k` varchar(32) NOT NULL,
  `v` text,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_user: 商户表
CREATE TABLE IF NOT EXISTS `pay_user` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `gid` int(11) unsigned NOT NULL DEFAULT '0',
  `upid` int(11) unsigned NOT NULL DEFAULT '0',
  `key` varchar(32) NOT NULL,
  `pwd` varchar(32) DEFAULT NULL,
  `account` varchar(128) DEFAULT NULL,
  `username` varchar(128) DEFAULT NULL,
  `codename` varchar(32) DEFAULT NULL,
  `settle_id` tinyint(4) NOT NULL DEFAULT '1',
  `alipay_uid` varchar(32) DEFAULT NULL,
  `qq_uid` varchar(32) DEFAULT NULL,
  `wx_uid` varchar(32) DEFAULT NULL,
  `money` decimal(10,2) NOT NULL,
  `email` varchar(32) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `qq` varchar(20) DEFAULT NULL,
  `url` varchar(64) DEFAULT NULL,
  `cert` tinyint(4) NOT NULL DEFAULT '0',
  `certtype` tinyint(4) NOT NULL DEFAULT '0',
  `certmethod` tinyint(4) NOT NULL DEFAULT '0',
  `certno` varchar(18) DEFAULT NULL,
  `certname` varchar(32) DEFAULT NULL,
  `certtime` datetime DEFAULT NULL,
  `certtoken` varchar(64) DEFAULT NULL,
  `certcorpno` varchar(30) DEFAULT NULL,
  `certcorpname` varchar(80) DEFAULT NULL,
  `addtime` datetime DEFAULT NULL,
  `lasttime` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  `level` tinyint(1) NOT NULL DEFAULT '1',
  `pay` tinyint(1) NOT NULL DEFAULT '1',
  `settle` tinyint(1) NOT NULL DEFAULT '1',
  `keylogin` tinyint(1) NOT NULL DEFAULT '1',
  `apply` tinyint(1) NOT NULL DEFAULT '0',
  `mode` tinyint(4) NOT NULL DEFAULT '0',
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `refund` tinyint(1) NOT NULL DEFAULT '1',
  `transfer` tinyint(1) NOT NULL DEFAULT '0',
  `keytype` tinyint(1) NOT NULL DEFAULT '0',
  `publickey` varchar(500) DEFAULT NULL,
  `channelinfo` text,
  `ordername` varchar(255) DEFAULT NULL,
  `msgconfig` text,
  `remain_money` varchar(20) DEFAULT NULL,
  `open_code` tinyint(1) NOT NULL DEFAULT '0',
  `deposit` decimal(10,2) DEFAULT NULL,
  `voice_devid` varchar(30) DEFAULT NULL,
  `voice_order` tinyint(1) NOT NULL DEFAULT '0',
  `pay_maxmoney` varchar(10) DEFAULT NULL,
  `pay_minmoney` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`uid`),
  KEY `email` (`email`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_order: 订单表
CREATE TABLE IF NOT EXISTS `pay_order` (
  `trade_no` char(19) NOT NULL,
  `out_trade_no` varchar(150) NOT NULL,
  `api_trade_no` varchar(150) DEFAULT NULL,
  `uid` int(11) unsigned NOT NULL,
  `tid` tinyint(11) unsigned NOT NULL DEFAULT '0',
  `type` int(10) unsigned NOT NULL,
  `channel` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `money` decimal(10,2) NOT NULL,
  `realmoney` decimal(10,2) DEFAULT NULL,
  `getmoney` decimal(10,2) DEFAULT NULL,
  `profitmoney` decimal(10,2) DEFAULT NULL,
  `refundmoney` decimal(10,2) DEFAULT NULL,
  `notify_url` varchar(255) DEFAULT NULL,
  `return_url` varchar(255) DEFAULT NULL,
  `param` varchar(255) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `endtime` datetime DEFAULT NULL,
  `date` date DEFAULT NULL,
  `domain` varchar(64) DEFAULT NULL,
  `domain2` varchar(64) DEFAULT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `buyer` varchar(100) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `notify` int(5) NOT NULL DEFAULT '0',
  `notifytime` datetime DEFAULT NULL,
  `invite` int(11) unsigned NOT NULL DEFAULT '0',
  `invitemoney` decimal(10,2) DEFAULT NULL,
  `combine` tinyint(1) NOT NULL DEFAULT '0',
  `profits` int(11) NOT NULL DEFAULT '0',
  `profits2` int(11) NOT NULL DEFAULT '0',
  `settle` tinyint(1) NOT NULL DEFAULT '0',
  `subchannel` int(11) NOT NULL DEFAULT '0',
  `payurl` varchar(500) DEFAULT NULL,
  `ext` text,
  `version` tinyint(1) NOT NULL DEFAULT '0',
  `bill_trade_no` varchar(150) DEFAULT NULL,
  `bill_mch_trade_no` varchar(150) DEFAULT NULL,
  `mobile` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`trade_no`),
  KEY `uid` (`uid`),
  KEY `out_trade_no` (`out_trade_no`,`uid`),
  KEY `api_trade_no` (`api_trade_no`),
  KEY `bill_trade_no` (`bill_trade_no`),
  KEY `bill_mch_trade_no` (`bill_mch_trade_no`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_channel: 支付渠道表
CREATE TABLE IF NOT EXISTS `pay_channel` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `mode` tinyint(1) DEFAULT '0',
  `type` int(11) unsigned NOT NULL,
  `plugin` varchar(30) NOT NULL,
  `name` varchar(30) NOT NULL,
  `rate` decimal(5,2) NOT NULL DEFAULT '100.00',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `apptype` varchar(50) DEFAULT NULL,
  `daytop` int(10) DEFAULT '0',
  `daystatus` tinyint(1) DEFAULT '0',
  `paymin` varchar(10) DEFAULT NULL,
  `paymax` varchar(10) DEFAULT NULL,
  `appwxmp` int(11) DEFAULT NULL,
  `appwxa` int(11) DEFAULT NULL,
  `costrate` decimal(5,2) DEFAULT NULL,
  `config` text,
  `daymaxorder` int(10) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_type: 支付方式表（channels 页面依赖此表）
CREATE TABLE IF NOT EXISTS `pay_type` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `device` int(1) unsigned NOT NULL DEFAULT '0',
  `showname` varchar(30) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `name` (`name`,`device`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_plugin: 支付插件表
CREATE TABLE IF NOT EXISTS `pay_plugin` (
  `name` varchar(30) NOT NULL,
  `showname` varchar(60) DEFAULT NULL,
  `author` varchar(60) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `types` varchar(50) DEFAULT NULL,
  `transtypes` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_group: 商户组
CREATE TABLE IF NOT EXISTS `pay_group` (
  `gid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `info` varchar(1024) DEFAULT NULL,
  `isbuy` tinyint(1) NOT NULL DEFAULT '0',
  `price` decimal(10,2) DEFAULT NULL,
  `sort` int(10) NOT NULL DEFAULT '0',
  `expire` int(10) NOT NULL DEFAULT '0',
  `config` text,
  `settings` text,
  `visible` varchar(30) DEFAULT NULL,
  `index` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_anounce: 公告
CREATE TABLE IF NOT EXISTS `pay_anounce` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `content` text,
  `color` varchar(10) DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT '1',
  `addtime` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_batch: 结算批次
CREATE TABLE IF NOT EXISTS `pay_batch` (
  `batch` varchar(20) NOT NULL,
  `allmoney` decimal(10,2) NOT NULL,
  `count` int(11) NOT NULL DEFAULT '0',
  `time` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_blacklist: 黑名单
CREATE TABLE IF NOT EXISTS `pay_blacklist` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `content` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `endtime` datetime DEFAULT NULL,
  `remark` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `content` (`content`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_cache: 缓存
CREATE TABLE IF NOT EXISTS `pay_cache` (
  `k` varchar(32) NOT NULL,
  `v` longtext,
  `expire` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_domain: 域名
CREATE TABLE IF NOT EXISTS `pay_domain` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT '0',
  `domain` varchar(128) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `addtime` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `domain` (`domain`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_invitecode: 邀请码
CREATE TABLE IF NOT EXISTS `pay_invitecode` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `addtime` datetime NOT NULL,
  `usetime` datetime DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_log: 日志
CREATE TABLE IF NOT EXISTS `pay_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT '0',
  `type` varchar(20) DEFAULT NULL,
  `date` datetime NOT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `city` varchar(20) DEFAULT NULL,
  `data` text,
  PRIMARY KEY (`id`),
  KEY `logincheck` (`ip`,`date`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_psorder: 分账订单
CREATE TABLE IF NOT EXISTS `pay_psorder` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `rid` int(11) NOT NULL,
  `trade_no` char(19) NOT NULL,
  `api_trade_no` varchar(150) NOT NULL,
  `sub_trade_no` varchar(25) DEFAULT NULL,
  `settle_no` varchar(150) DEFAULT NULL,
  `money` decimal(10,2) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `result` text,
  `addtime` datetime DEFAULT NULL,
  `delay` tinyint(1) NOT NULL DEFAULT '0',
  `rdata` text,
  PRIMARY KEY (`id`),
  KEY `trade_no` (`trade_no`),
  KEY `addtime` (`addtime`,`delay`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_psreceiver: 分账接收方
CREATE TABLE IF NOT EXISTS `pay_psreceiver` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `channel` int(11) NOT NULL,
  `subchannel` int(11) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `account` varchar(128) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `rate` varchar(10) DEFAULT NULL,
  `minmoney` varchar(10) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `addtime` datetime DEFAULT NULL,
  `info` varchar(1024) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `channel` (`channel`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_record: 资金记录
CREATE TABLE IF NOT EXISTS `pay_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `action` tinyint(1) NOT NULL DEFAULT '0',
  `money` decimal(10,2) NOT NULL,
  `oldmoney` decimal(10,2) NOT NULL,
  `newmoney` decimal(10,2) NOT NULL,
  `type` varchar(20) DEFAULT NULL,
  `trade_no` varchar(64) DEFAULT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_refundorder: 退款订单
CREATE TABLE IF NOT EXISTS `pay_refundorder` (
  `refund_no` char(19) NOT NULL,
  `out_refund_no` varchar(150) NOT NULL,
  `trade_no` char(19) NOT NULL,
  `uid` int(11) NOT NULL DEFAULT '0',
  `money` decimal(10,2) NOT NULL,
  `reducemoney` decimal(10,2) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `addtime` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  PRIMARY KEY (`refund_no`),
  KEY `out_refund_no` (`out_refund_no`,`uid`),
  KEY `trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_regcode: 注册码
CREATE TABLE IF NOT EXISTS `pay_regcode` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT '0',
  `scene` varchar(20) NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `code` varchar(32) NOT NULL,
  `to` varchar(32) DEFAULT NULL,
  `time` int(11) NOT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `errcount` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `code` (`to`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_risk: 风控
CREATE TABLE IF NOT EXISTS `pay_risk` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL DEFAULT '0',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `url` varchar(64) DEFAULT NULL,
  `content` varchar(64) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_roll: 轮播
CREATE TABLE IF NOT EXISTS `pay_roll` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` int(11) unsigned NOT NULL,
  `name` varchar(30) NOT NULL,
  `kind` int(1) unsigned NOT NULL DEFAULT '0',
  `info` text,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `index` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_settle: 结算
CREATE TABLE IF NOT EXISTS `pay_settle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `batch` varchar(20) DEFAULT NULL,
  `auto` tinyint(1) NOT NULL DEFAULT '1',
  `type` tinyint(1) NOT NULL DEFAULT '1',
  `account` varchar(128) NOT NULL,
  `username` varchar(128) NOT NULL,
  `money` decimal(10,2) NOT NULL,
  `realmoney` decimal(10,2) NOT NULL,
  `addtime` datetime DEFAULT NULL,
  `endtime` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `transfer_no` varchar(64) DEFAULT NULL,
  `transfer_channel` int(10) unsigned DEFAULT NULL,
  `transfer_status` tinyint(1) NOT NULL DEFAULT '0',
  `transfer_result` varchar(64) DEFAULT NULL,
  `transfer_date` datetime DEFAULT NULL,
  `transfer_ext` text,
  `result` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `batch` (`batch`),
  KEY `transfer_no` (`transfer_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_subchannel: 子渠道
CREATE TABLE IF NOT EXISTS `pay_subchannel` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `channel` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `info` text,
  `addtime` datetime DEFAULT NULL,
  `usetime` datetime DEFAULT NULL,
  `apply_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `channel` (`channel`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_suborder: 子订单
CREATE TABLE IF NOT EXISTS `pay_suborder` (
  `sub_trade_no` varchar(25) NOT NULL,
  `trade_no` char(19) NOT NULL,
  `api_trade_no` varchar(150) DEFAULT NULL,
  `money` decimal(10,2) NOT NULL,
  `refundmoney` decimal(10,2) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `settle` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sub_trade_no`),
  KEY `trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_transfer: 转账
CREATE TABLE IF NOT EXISTS `pay_transfer` (
  `biz_no` char(19) NOT NULL,
  `out_biz_no` varchar(150) NOT NULL DEFAULT '',
  `pay_order_no` varchar(80) DEFAULT NULL,
  `uid` int(11) NOT NULL,
  `type` varchar(10) NOT NULL,
  `channel` int(10) unsigned NOT NULL,
  `account` varchar(128) NOT NULL,
  `username` varchar(128) DEFAULT NULL,
  `money` decimal(10,2) NOT NULL,
  `costmoney` decimal(10,2) DEFAULT NULL,
  `addtime` datetime DEFAULT NULL,
  `paytime` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `api` tinyint(1) NOT NULL DEFAULT '0',
  `desc` varchar(80) DEFAULT NULL,
  `result` varchar(80) DEFAULT NULL,
  `ext` text,
  PRIMARY KEY (`biz_no`),
  KEY `uid` (`uid`),
  KEY `out_biz_no` (`out_biz_no`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_weixin: 微信公众号
CREATE TABLE IF NOT EXISTS `pay_weixin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(4) unsigned NOT NULL DEFAULT '0',
  `name` varchar(30) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `appid` varchar(150) DEFAULT NULL,
  `appsecret` varchar(250) DEFAULT NULL,
  `access_token` varchar(300) DEFAULT NULL,
  `addtime` datetime DEFAULT NULL,
  `updatetime` datetime DEFAULT NULL,
  `expiretime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_wework: 企业微信
CREATE TABLE IF NOT EXISTS `pay_wework` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `appid` varchar(150) DEFAULT NULL,
  `appsecret` varchar(250) DEFAULT NULL,
  `access_token` varchar(300) DEFAULT NULL,
  `addtime` datetime DEFAULT NULL,
  `updatetime` datetime DEFAULT NULL,
  `expiretime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_wxkfaccount: 微信客服账号
CREATE TABLE IF NOT EXISTS `pay_wxkfaccount` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `wid` int(11) unsigned NOT NULL,
  `openkfid` varchar(60) NOT NULL,
  `url` varchar(100) DEFAULT NULL,
  `cursor` varchar(30) DEFAULT NULL,
  `name` varchar(300) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `usetime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `openkfid` (`openkfid`),
  KEY `wid` (`wid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_wxkflog: 微信客服日志
CREATE TABLE IF NOT EXISTS `pay_wxkflog` (
  `trade_no` char(19) NOT NULL,
  `aid` int(11) unsigned NOT NULL,
  `sid` char(32) NOT NULL,
  `payurl` varchar(500) NOT NULL,
  `addtime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`trade_no`),
  KEY `sid` (`sid`),
  KEY `addtime` (`addtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET FOREIGN_KEY_CHECKS=1;
