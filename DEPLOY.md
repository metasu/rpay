# rpay 支付网关从零部署指南

## 一、环境需求

### 系统要求
- **OS**: Linux x86_64 (Ubuntu 20.04+ / Debian 11+ / CentOS 8+)
- **内存**: 最低 512MB，建议 1GB+（编译时需要较多内存，1GB 以下可能 OOM）
- **磁盘**: 最低 1GB（源码 + 编译产物 + 数据库）

### 必须安装的软件

| 软件 | 最低版本 | 用途 |
|------|---------|------|
| Rust toolchain (rustc + cargo) | 1.75+ | 编译源码 |
| MySQL / MariaDB | 5.7+ / 10.3+ | 数据存储 |
| Nginx（宝塔自带或独立安装） | 任意 | HTTPS 反向代理 |
| systemd | 任意 | 服务管理 |

### 推荐方案：宝塔面板

推荐使用**宝塔面板**管理 Nginx 和 MySQL，可视化操作，降低运维门槛：
- 自动安装 Nginx + MySQL/MariaDB
- 可视化管理数据库（phpMyAdmin）
- 一键申请 SSL 证书
- 可视化配置反向代理

---

## 二、流程编排

```
1. 安装 Rust 工具链
2. 安装并配置 MySQL
3. 导入数据库表结构
4. 编译源码
5. 创建系统用户和部署目录
6. 部署二进制和配置文件
7. 配置 systemd service
8. 配置反向代理（Nginx/NPM + SSL）
9. 启动服务并验证
```

---

## 三、部署过程

### 3.1 安装 Rust 工具链

```bash
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source ~/.cargo/env
rustc --version  # 确认 >= 1.75
```

### 3.2 安装并配置 MySQL

#### 方式 A：宝塔面板安装（推荐）

1. 安装宝塔面板：
```bash
# Ubuntu/Debian
wget -O install.sh https://download.bt.cn/install/install-ubuntu_6.0.sh && bash install.sh ed8484bec
```

2. 在宝塔面板中安装 **Nginx** 和 **MySQL 5.7+**（或 MariaDB）

3. 在宝塔面板 **数据库** → **添加数据库**：
   - 数据库名：`rpay`（或任意名称）
   - 用户名：`rpay`（或任意名称）
   - 密码：设置强密码
   - 字符集：**utf8mb4**

4. 记下数据库名、用户名、密码，后续 3.6 节配置 `database-url` 时需要

#### 方式 B：手动安装

```bash
# Ubuntu/Debian
apt update && apt install -y mysql-server

# 或安装 MariaDB
apt install -y mariadb-server

# 启动
systemctl enable --now mysql

# 安全初始化
mysql_secure_installation
```

创建数据库和用户：

```bash
mysql -uroot -p <<'SQL'
CREATE DATABASE rpay CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'rpay'@'localhost' IDENTIFIED BY '你的强密码';
GRANT ALL PRIVILEGES ON rpay.* TO 'rpay'@'localhost';
FLUSH PRIVILEGES;
SQL
```

### 3.3 导入数据库表结构

数据库表结构来自旧版 PHP 支付网关（EasyPay），rpay 复用相同的表结构，共 29 张表。

> **重要**：必须导入完整的数据库 dump。仅手动创建 `pay_config`/`pay_user`/`pay_order`/`pay_channel` 4 张表是不够的——`pay_type`、`pay_plugin` 等表缺失会导致 `/admin/channels` 等页面返回 500 Internal Server Error。

#### 方式 A：从旧服务器导入完整 dump（推荐）

```bash
# 从旧服务器导出（包含完整表结构和数据）
mysqldump -uroot -p pay > pay_schema.sql
# 或导出为 gzip
gzip pay_schema.sql

# 在新服务器导入（宝塔环境用 127.0.0.1 连接）
mysql -urpay -p你的数据库密码 -h127.0.0.1 rpay < pay_schema.sql
# 或从 gzip 直接导入
zcat pay_20260730_150057.sql.gz | mysql -urpay -p你的数据库密码 -h127.0.0.1 rpay
```

> **宝塔面板导入**：也可以在宝塔 → 数据库 → 点击数据库名 → **导入** 上传 sql 文件

完整 dump 包含以下 29 张表：

```
pay_anounce, pay_batch, pay_blacklist, pay_cache, pay_channel, pay_config,
pay_domain, pay_group, pay_invitecode, pay_log, pay_order, pay_plugin,
pay_psorder, pay_psreceiver, pay_record, pay_refundorder, pay_regcode,
pay_risk, pay_roll, pay_settle, pay_subchannel, pay_suborder, pay_transfer,
pay_type, pay_user, pay_weixin, pay_wework, pay_wxkfaccount, pay_wxkflog
```

其中 `pay_type`（支付方式）和 `pay_plugin`（支付插件）是 channels 页面正常工作的前提。

#### 方式 B：无旧数据库时手动建表（完整 29 张表）

如果没有旧数据库 dump，需要手动创建所有 29 张表。以下为完整建表 SQL，可在宝塔 phpMyAdmin 中执行：

```sql
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

-- 插入 syskey（32位随机字符串，用于 session 加密）
INSERT INTO `pay_config` (`k`, `v`) VALUES ('syskey', '替换为你的32位随机字符串')
ON DUPLICATE KEY UPDATE `v` = VALUES(`v`);
```

生成 syskey：

```bash
openssl rand -hex 16
```

#### 插入初始配置数据（pay_config）

以下 SQL 插入网关运行所需的最低配置项：

```sql
-- 管理员凭据（密码请改为强密码）
INSERT INTO pay_config (k, v) VALUES ('admin_user', 'admin')
ON DUPLICATE KEY UPDATE v = VALUES(v);
INSERT INTO pay_config (k, v) VALUES ('admin_pwd', '123456')
ON DUPLICATE KEY UPDATE v = VALUES(v);
INSERT INTO pay_config (k, v) VALUES ('admin_paypwd', '123456')
ON DUPLICATE KEY UPDATE v = VALUES(v);

-- 站点基本信息
INSERT INTO pay_config (k, v) VALUES ('sitename', '聚合支付平台')
ON DUPLICATE KEY UPDATE v = VALUES(v);
INSERT INTO pay_config (k, v) VALUES ('kfqq', '1000000000')
ON DUPLICATE KEY UPDATE v = VALUES(v);

-- 功能开关
INSERT INTO pay_config (k, v) VALUES ('reg_open', '1')
ON DUPLICATE KEY UPDATE v = VALUES(v);
INSERT INTO pay_config (k, v) VALUES ('settle_open', '1')
ON DUPLICATE KEY UPDATE v = VALUES(v);
INSERT INTO pay_config (k, v) VALUES ('test_open', '1')
ON DUPLICATE KEY UPDATE v = VALUES(v);
INSERT INTO pay_config (k, v) VALUES ('recharge', '1')
ON DUPLICATE KEY UPDATE v = VALUES(v);
INSERT INTO pay_config (k, v) VALUES ('user_refund', '1')
ON DUPLICATE KEY UPDATE v = VALUES(v);
INSERT INTO pay_config (k, v) VALUES ('verifytype', '1')
ON DUPLICATE KEY UPDATE v = VALUES(v);

-- 系统参数
INSERT INTO pay_config (k, v) VALUES ('version', '2052')
ON DUPLICATE KEY UPDATE v = VALUES(v);
INSERT INTO pay_config (k, v) VALUES ('template', 'index1')
ON DUPLICATE KEY UPDATE v = VALUES(v);
```

#### 插入支付方式（pay_type）

```sql
INSERT INTO pay_type (id, name, device, showname, status) VALUES
  (1, 'alipay', 0, '支付宝', 1),
  (2, 'wxpay',  0, '微信支付', 1),
  (3, 'paypal', 0, 'PayPal', 0),
  (4, 'stripe', 0, 'Stripe', 0)
ON DUPLICATE KEY UPDATE name=VALUES(name), showname=VALUES(showname);
```

#### 插入支付插件（pay_plugin）

rpay 内置支持以下插件（`code` 列对应 `pay_channel.plugin` 字段）。仅插入你实际使用的插件即可：

```sql
-- rpay 内置实现的插件（代码中有对应模块）
INSERT INTO pay_plugin (code, name, shortname, url, types, localtypes) VALUES
  ('alipay',  '支付宝官方',     '支付宝', 'https://open.alipay.com/',     'alipay',                 'alipay'),
  ('wxpay',   '微信支付V2',     '微信',   'https://pay.weixin.qq.com/',   'wxpay',                  'wxpay'),
  ('wxpayn',  '微信支付V3',     '微信',   'https://pay.weixin.qq.com/',   'wxpay',                  'wxpay'),
  ('paypal',  'PayPal',         'PayPal', 'https://www.paypal.com/',       'paypal',                 NULL),
  ('stripe',  'Stripe',         'Stripe', 'https://stripe.com/',           'alipay,wxpay,paypal', NULL)
ON DUPLICATE KEY UPDATE name=VALUES(name);
```

> **注意**：`localtypes` 列为 `NULL` 的插件表示该插件由上游聚合支付平台处理，rpay 不直接对接。`localtypes` 不为 `NULL` 的插件（如 `alipay`、`wxpay`、`wxpayn`）由 rpay 直接调用官方 API。

#### 插入示例商户（pay_user）

至少需要一个商户才能接收支付请求：

```sql
-- uid 1: 测试商户，key 用于 API 签名
INSERT INTO pay_user (uid, gid, upid, `key`, username, keytype, money, status, level, pay, settle, refund)
VALUES (1, 0, 0, '请替换为32位随机字符串', 'testmerchant', 0, 0.00, 1, 1, 1, 1, 1);
```

生成商户 key：

```bash
openssl rand -hex 16
```

#### 生成支付宝 RSA 密钥对

支付宝支付渠道需要 RSA 密钥对。在支付宝开放平台获取商户私钥和支付宝公钥后，填入 `pay_channel.config` 的 JSON 配置中：

```bash
# 生成 RSA2048 密钥对（推荐 RSA2）
openssl genrsa -out app_private.pem 2048
openssl rsa -in app_private.pem -pubout -out app_public.pem

# 将私钥内容（去掉头尾标记和换行）填入 channel config 的 appsecret 字段
# 将支付宝公钥填入 appkey 字段
# appid 填入 appid 字段
# sign_type 设为 "RSA2"
```

渠道配置示例（通过管理后台 `/admin/channels` 页面填写）：

```json
{
  "appid": "2021004xxxxxxxxx",
  "appkey": "支付宝公钥（一长串Base64）",
  "appsecret": "商户私钥（一长串Base64）",
  "appmchid": "",
  "sign_type": "RSA2"
}
```

#### 插入支付渠道（pay_channel）

渠道是实际对接支付平台的配置。以下插入 5 个渠道模板，填入你的密钥后即可使用：

```sql
-- 支付宝（type=1 对应 pay_type.id=1, plugin=alipay）
INSERT INTO pay_channel (id, mode, type, plugin, name, rate, status, apptype, daytop, daystatus, paymin, paymax, appwxmp, appwxa, costrate, config, daymaxorder)
VALUES (2, 0, 1, 'alipay', '支付宝', 100.00, 1, '2', 0, 0, '', '', NULL, NULL, 0.00,
  '{"appid":"请填入支付宝APPID","appkey":"支付宝公钥","appsecret":"商户RSA私钥","appmchid":"","sign_type":"RSA2"}',
  0)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Stripe（type=4, plugin=stripe）
INSERT INTO pay_channel (id, mode, type, plugin, name, rate, status, apptype, daytop, daystatus, paymin, paymax, appwxmp, appwxa, costrate, config, daymaxorder)
VALUES (3, 0, 4, 'stripe', 'Stripe', 100.00, 0, NULL, 0, 0, '', '', NULL, NULL, 0.00,
  '{"appsecret":"","appkey":"","currency":"eur","currency_rate":7.8}',
  0)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- PayPal（type=6, plugin=paypal）
INSERT INTO pay_channel (id, mode, type, plugin, name, rate, status, apptype, daytop, daystatus, paymin, paymax, appwxmp, appwxa, costrate, config, daymaxorder)
VALUES (4, 0, 6, 'paypal', 'PayPal', 100.00, 0, NULL, 0, 0, '', '', NULL, NULL, 0.00,
  '{"appid":"","appsecret":"","sandbox":true,"currency":"GBP","currency_rate":9.1,"webhook_id":""}',
  0)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 微信支付V2（type=2, plugin=wxpay）
INSERT INTO pay_channel (id, mode, type, plugin, name, rate, status, apptype, daytop, daystatus, paymin, paymax, appwxmp, appwxa, costrate, config, daymaxorder)
VALUES (5, 0, 2, 'wxpay', '微信支付(V2)', 100.00, 0, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL,
  '{"appid":"","appmchid":"","appkey":""}',
  0)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 微信支付V3（type=2, plugin=wxpayn）
INSERT INTO pay_channel (id, mode, type, plugin, name, rate, status, apptype, daytop, daystatus, paymin, paymax, appwxmp, appwxa, costrate, config, daymaxorder)
VALUES (6, 0, 2, 'wxpayn', '微信支付(V3)', 100.00, 0, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL,
  '{"appid":"","appmchid":"","appsecret":"","appkey":"","mch_private_key":"","platform_public_key":"","publickeyid":""}',
  0)
ON DUPLICATE KEY UPDATE name=VALUES(name);
```

> **字段说明**：
> - `type`：关联 `pay_type.id`（1=支付宝, 2=微信, 3=PayPal, 4=Stripe）
> - `plugin`：关联 `pay_plugin.code`，决定用哪个支付模块
> - `status`：1=启用, 0=禁用
> - `config`：JSON 格式的渠道配置，不同插件字段不同
> - `rate`：手续费率（百分比，100.00=无手续费）

#### 导入后设置管理员凭据

rpay 的管理员登录从数据库 `pay_config` 表读取 `admin_user` 和 `admin_pwd`，**不是**从 `secrets/admin-password` 文件读取。导入 dump 后需要手动设置：

```bash
ADMIN_PASS=$(openssl rand -base64 12 | tr -d '/+=' | head -c 16)
mysql -urpay -p你的数据库密码 -h127.0.0.1 rpay <<SQL
INSERT INTO pay_config (k, v) VALUES ('admin_user', 'admin')
ON DUPLICATE KEY UPDATE v = VALUES(v);
INSERT INTO pay_config (k, v) VALUES ('admin_pwd', '$ADMIN_PASS')
ON DUPLICATE KEY UPDATE v = VALUES(v);
SQL
echo "Admin password: $ADMIN_PASS"
```

### 3.4 创建系统用户和部署目录

```bash
# 创建专用系统用户
useradd -r -s /usr/sbin/nologin -d /opt/services/rpay rpay

# 创建目录结构（网关默认安装路径）
INSTALL_DIR=/opt/services/rpay
mkdir -p $INSTALL_DIR/{bin,config,secrets,data,logs,backups}
```

### 3.5 获取并编译源码

源码可在任意目录，编译后直接部署到 `/opt/services/rpay`：

```bash
# SRC_DIR 为源码所在目录（可任意）
# 编译产物直接输出到 /opt/services/rpay/bin
SRC_DIR=/source/rpay
INSTALL_DIR=/opt/services/rpay

# ---- 获取源码 ----

# 从 GitHub 克隆
git clone https://github.com/metasu/rpay.git $SRC_DIR

# 或从旧服务器打包复制
#   tar czf /tmp/rpay-src.tar.gz -C /source rpay
#   scp /tmp/rpay-src.tar.gz 新服务器:/tmp/
#   tar xzf /tmp/rpay-src.tar.gz -C /source

# ---- 编译 ----
cd $SRC_DIR
cargo build --release --target-dir $INSTALL_DIR/bin/build

# 移动二进制到 bin 根目录，清理编译中间产物
mv $INSTALL_DIR/bin/build/release/rpay $INSTALL_DIR/bin/rpay
rm -rf $INSTALL_DIR/bin/build
chown -R rpay:rpay $INSTALL_DIR
chmod 750 $INSTALL_DIR/bin/rpay
chmod 700 $INSTALL_DIR/secrets
```

> **编译内存不足的解决办法**：
> ```bash
> # 使用 swap
> fallocate -l 2G /swapfile
> chmod 600 /swapfile
> mkswap /swapfile
> swapon /swapfile
> ```

### 3.6 配置文件

> 仓库中提供了示例文件：`config/config.example.toml` 和 `secrets/database-url.example`，可直接复制后修改。

#### config.toml

```bash
cat > /opt/services/rpay/config/config.toml <<'EOF'
listen = "127.0.0.1:16889"
public_base_url = "https://你的域名"
environment = "production"
table_prefix = "pay"
EOF
```

#### secrets/database-url

```bash
echo -n "mysql://pay:你的数据库密码@127.0.0.1:3306/pay" > /opt/services/rpay/secrets/database-url
chown rpay:rpay /opt/services/rpay/secrets/database-url
chmod 600 /opt/services/rpay/secrets/database-url
```

> **注意**：`database-url` 文件末尾不要有换行符，用 `echo -n`。

> **注意**：`secrets/admin-password` 文件仅作为密码记录备份，rpay 程序**不读取**此文件。管理员登录凭据从数据库 `pay_config` 表的 `admin_user` 和 `admin_pwd` 键读取（见 3.3 节）。

#### secrets/admin-password（可选，仅作记录）

```bash
echo -n "你的管理后台密码" > /opt/services/rpay/secrets/admin-password
chown rpay:rpay /opt/services/rpay/secrets/admin-password
chmod 600 /opt/services/rpay/secrets/admin-password
```

### 3.7 配置 systemd service

```bash
cat > /etc/systemd/system/rpay.service <<'EOF'
[Unit]
Description=rpay - Rust payment gateway
After=network-online.target mysql.service
Wants=network-online.target

[Service]
Type=simple
User=rpay
Group=rpay
WorkingDirectory=/opt/services/rpay
ExecStart=/opt/services/rpay/bin/rpay --listen 127.0.0.1:16889 --public-base-url https://你的域名 --database-url-file /opt/services/rpay/secrets/database-url
Restart=on-failure
RestartSec=3s
TimeoutStopSec=20s
KillSignal=SIGINT
UMask=0077
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadOnlyPaths=/opt/services/rpay/bin /opt/services/rpay/config /opt/services/rpay/secrets
ReadWritePaths=/opt/services/rpay/data /opt/services/rpay/logs /opt/services/rpay/backups
LimitNOFILE=4096
Environment=RUST_LOG=info

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable rpay.service
```

> **注意**：`ExecStart` 中的 `--public-base-url` 要替换为你的实际域名。也可以去掉这个参数，改为在 `config.toml` 中配置（程序会自动读取）。

### 3.8 配置反向代理

#### 方案 A：宝塔面板 Nginx 反代（推荐）

1. 宝塔面板 → **网站** → **添加站点**：
   - 域名：`你的域名`
   - PHP版本：**纯静态**
   - 不创建数据库

2. 点击站点名 → **反向代理** → **添加反向代理**：
   - 代理名称：`rpay`
   - 目标URL：`http://127.0.0.1:16889`
   - 发送域名：`$host`
   - 启用代理

3. 点击站点名 → **SSL** → **Let's Encrypt**：
   - 申请免费 SSL 证书
   - 开启 **强制HTTPS**

#### 方案 B：Nginx 手动配置

```nginx
server {
    listen 443 ssl http2;
    server_name 你的域名;

    ssl_certificate     /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:16889;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

server {
    listen 80;
    server_name 你的域名;
    return 301 https://$host$request_uri;
}
```

#### 方案 C：Nginx Proxy Manager (Docker)

如果你用 NPM（jc21/nginx-proxy-manager）：

1. 打开 NPM 管理面板
2. **Proxy Hosts** → **Add Proxy Host**
3. 填写：
   - Domain Names: `你的域名`
   - Forward Scheme: `http`
   - Forward Hostname: `127.0.0.1`
   - Forward Port: `16889`
4. **SSL** 标签页：申请 Let's Encrypt 证书，开启 Force SSL
5. 保存

#### 方案 D：Caddy（自动 HTTPS）

```Caddyfile
你的域名 {
    reverse_proxy 127.0.0.1:16889
}
```

### 3.9 启动服务并验证

```bash
systemctl start rpay.service
systemctl status rpay.service

# 本地验证
curl http://127.0.0.1:16889/healthz
# 应返回: ok

# 外网验证
curl https://你的域名/healthz
# 应返回: ok

# 查看日志
journalctl -u rpay.service -f
```

---

## 四、目录结构说明

```
/opt/services/rpay/
├── bin/
│   └── rpay              # 编译好的二进制（12MB）
├── config/
│   └── config.toml        # 配置文件（监听地址、域名、环境）
├── secrets/
│   └── database-url       # MySQL 连接串（敏感）
├── data/                  # 运行时数据（预留，当前为空）
├── logs/                  # 日志目录（预留，日志实际输出到 journal）
└── backups/               # 备份目录（预留）
```

源码目录（编译用，不参与运行，可在任意位置）：

```
/root/workspace/rpay/   # 本机默认路径，可改为任意目录
├── src/
│   ├── main.rs            # 入口，启动 HTTP 服务和后台任务
│   ├── web.rs             # 路由、支付提交、回调处理
│   ├── admin.rs           # 管理后台（商户/订单/渠道/统计）
│   ├── portal.rs          # 商户门户
│   ├── store.rs           # 数据库操作层（SQLx）
│   ├── alipay.rs          # 支付宝支付/退款
│   ├── wxpay_v2.rs        # 微信支付 V2
│   ├── wxpay_v3.rs        # 微信支付 V3
│   ├── paypal.rs          # PayPal
│   ├── stripe.rs          # Stripe
│   ├── protocol.rs        # 签名/验签（MD5/RSA）
│   ├── session.rs         # Cookie session
│   └── templates.rs       # 共享 HTML 组件
├── Cargo.toml             # 依赖配置
└── target/release/rpay    # 编译产物
```

---

## 五、更新部署流程

每次修改源码后重新部署：

```bash
# SRC_DIR 为源码所在目录（可任意）
SRC_DIR=/root/workspace/rpay
INSTALL_DIR=/opt/services/rpay

cd $SRC_DIR
cargo build --release --target-dir $INSTALL_DIR/bin/build
systemctl stop rpay.service
mv $INSTALL_DIR/bin/build/release/rpay $INSTALL_DIR/bin/rpay
rm -rf $INSTALL_DIR/bin/build
chown rpay:rpay $INSTALL_DIR/bin/rpay
systemctl start rpay.service
systemctl is-active rpay.service
```

---

## 六、经验教训

### 1. database-url 文件不要有尾部换行
`echo` 默认会加 `\n`，导致连接串解析失败。必须用 `echo -n`。

### 2. public_base_url 必须与实际域名一致
此值用于生成支付宝/微信的 `notify_url` 和 `return_url`。如果域名不对，支付回调会发到不存在的地址，导致订单无法自动确认。迁移时务必同步修改此配置。

### 3. MySQL SUM/DATE 类型需要 CAST
SQLx 对 MySQL 的 `SUM()` 返回的 decimal 类型和 `DATE` 类型处理严格，直接反序列化会报错。需要在 SQL 中用 `CAST(SUM(...) AS SIGNED)` 和 `CAST(date AS CHAR)` 转换。

### 4. 表单重复字段不能用 serde_urlencoded
Axum 的 `Form` 提取器底层用 `serde_urlencoded`，不支持重复字段（如 `trade_nos=a&trade_nos=b`）。批量操作时需要手动用 `url::form_urlencoded::parse` 解析原始请求体 `Bytes`。

### 5. 支付宝签名：sign 和 verify 的 sign_content 不同
- **签名时**：sign string 包含 `sign_type` 字段（匹配 PHP SDK 的 `getSignContent`）
- **验签时**：sign string 排除 `sign_type` 字段（匹配 PHP SDK 的 `verifyV2`）
- 如果两边不一致，会导致签名验证失败

### 6. 退款要用系统 trade_no 而非商户 out_trade_no
下单时传给支付宝的 `out_trade_no` 是我们系统的 `trade_no`，退款时也必须用 `trade_no`。用商户的 `out_trade_no` 退款会报 40004。

### 7. 待支付订单需要自动过期
没有自动过期机制时，待支付订单会永久留在数据库。需要后台定时任务将超过 30 分钟未支付的订单标记为已关闭（status=2）。

### 8. 编译需要足够内存
Rust release 编译内存消耗大，512MB VPS 会 OOM。解决办法：添加 2GB swap 或在本地编译后上传二进制。

### 9. systemd 安全加固的坑
`ProtectSystem=strict` 会将整个文件系统设为只读，必须用 `ReadWritePaths` 显式开放需要写入的目录。`MemoryDenyWriteExecute=true` 会阻止 JIT，如果将来引入需要 JIT 的依赖需要去掉此选项。

### 10. 源码和部署目录缺一不可
- 源码目录（如 `/root/workspace/rpay/`）— 用于修改代码和编译，不参与运行，可在任意位置
- `/opt/services/rpay/`（部署目录）— 运行时需要的二进制、配置、密钥
- 迁移到新 VPS 时，两者都需要，或者在新 VPS 上只放二进制 + 配置，源码可以之后从 git 克隆

### 11. 必须导入完整数据库 dump
仅手动创建 `pay_config`/`pay_user`/`pay_order`/`pay_channel` 4 张表是不够的。`pay_type` 表缺失会导致 `/admin/channels` 页面 500 错误（`list_channels_full()` SQL 中 `LEFT JOIN pay_type B ON A.type=B.id`）。必须从旧服务器导入完整的 29 张表 dump。

### 12. 管理员凭据在数据库中，不在 secrets 文件
rpay 的管理员登录从 `pay_config` 表读取 `admin_user` 和 `admin_pwd`，不是从 `secrets/admin-password` 文件。导入 dump 后需手动 INSERT 这两个键。修改密码也是更新数据库，不是改文件。
