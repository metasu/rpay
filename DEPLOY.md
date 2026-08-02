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
| MySQL / MariaDB | 5.6+ / 10.3+ | 数据存储 |
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

2. 在宝塔面板中安装 **Nginx** 和 **MySQL 5.6+**（或 MariaDB）

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

**推荐：宝塔面板建库**

在宝塔面板 → 数据库 → 添加数据库：
- 数据库名：`rpay`
- 用户名：`rpay`
- 密码：设置一个强密码
- 访问权限：本地服务器

宝塔会自动创建数据库和用户，无需手动执行 SQL。

**手动建库（无宝塔时）：**

```bash
mysql -uroot -p <<'SQL'
CREATE DATABASE rpay CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'rpay'@'localhost' IDENTIFIED BY '你的强密码';
GRANT ALL PRIVILEGES ON rpay.* TO 'rpay'@'localhost';
FLUSH PRIVILEGES;
SQL
```

> **MySQL 5.6 兼容**：rpay 的 `database/schema.sql` 不使用 JSON 类型、生成列或任何 5.7+ 专有语法，完全兼容 MySQL 5.6。

#### 数据库时间默认设置

项目数据库（包括订单、支付记录、退款、结算和日志等时间字段）默认使用新加坡时间：

- **时区**：`Asia/Singapore`
- **偏移**：`UTC+08:00`（东八区）
- **应用保证**：rpay 通过 SQLx `after_connect` 钩子，在连接池建立每一条 MySQL 连接时执行 `SET time_zone = '+08:00'`
- **启动校验**：rpay 启动时通过同一个连接池读取 `@@session.time_zone` 和 `NOW()`；会话不是 `+08:00` 时拒绝启动

因此，订单创建、支付完成、通知重试、订单过期和后台统计使用的 `NOW()`、`CURDATE()` 与 `DATE_SUB()` 均固定为 UTC+8，不依赖以下外部状态：

- rpay 编译主机或运行主机的系统时区；
- MySQL/MariaDB 的全局默认时区；
- 应用启动前后修改过的数据库全局变量；
- 连接池断线重连，因为每条新连接都会重新设置会话时区。

数据库全局时区仍建议设为 `+08:00`，作为其他客户端和运维查询的默认值，但它不再是 rpay 时间正确性的必要条件。检查当前 MySQL/MariaDB 时区：

```sql
SELECT @@global.time_zone AS global_time_zone,
       @@session.time_zone AS session_time_zone,
       NOW() AS database_now;
```

注意：命令行客户端查询到的 `@@session.time_zone` 仅代表该命令行连接，不能代表 rpay 连接池中的会话。部署后应同时检查服务日志中的 `MySQL session time zone initialized` 记录。

如需设置数据库全局默认时区，可在 MySQL/MariaDB 配置文件的 `[mysqld]` 段加入：

```ini
default-time-zone = '+08:00'
```

也可临时调整全局值和当前运维连接；`SET GLOBAL` 不会修改已经建立的连接：

```sql
SET GLOBAL time_zone = '+08:00';
SET time_zone = '+08:00';
```

不要只把 SQL 的 `NOW()` 改成 Rust 的 `chrono::Local::now()`：后者依赖应用运行主机时区，并且会令订单创建时间与支付、通知、过期和统计使用不同的时间基准。如需改变默认时区，应同步评估历史订单、回调、对账和日志展示逻辑。

### 3.3 导入数据库表结构

在宝塔面板创建好数据库后，用库名和密码导入表结构和种子数据。

仓库提供经过 MySQL 实际导入验证的完整初始化文件，兼容 MySQL 5.6+：

- `scripts/init-db.sh`：一键脚本，自动导入 schema + seed、生成 syskey 和管理员密码
- `database/schema.sql`：29 张 EasyPay 兼容表的完整结构
- `database/seed.sql`：支付类型、插件元数据和默认禁用的渠道模板，不含管理员、商户、订单或真实支付密钥

#### 方式 0：一键脚本（推荐）

宝塔建库后，在服务器上执行：

```bash
# 宝塔已建好数据库 rpay，用户 rpay，密码已设置
DB_PASS=宝塔设置的数据库密码 ./scripts/init-db.sh
```

脚本会自动完成：导入 schema.sql → 导入 seed.sql → 生成随机 syskey 和管理员密码，并输出凭据。

> 如果没有通过宝塔建库，也可以用 root 密码让脚本自动建库建用户：
> `ROOT_PASS=mysql_root密码 DB_PASS=rpay应用密码 ./scripts/init-db.sh`

#### 方式 1：手动导入

```bash
mysql -urpay -p -h127.0.0.1 rpay < database/schema.sql
mysql -urpay -p -h127.0.0.1 rpay < database/seed.sql
```

> 也可以在宝塔 → 数据库 → 点击数据库名 → **导入** 上传 sql 文件。

导入后需要手动生成 syskey 和管理员密码（一键脚本会自动完成此步）：

```bash
SYSKEY=$(openssl rand -hex 32)
ADMIN_PASS=$(openssl rand -base64 24 | tr -d '\n')
mysql -urpay -p -h127.0.0.1 rpay <<SQL
INSERT INTO pay_config (k, v) VALUES
  ('syskey', '${SYSKEY}'),
  ('admin_user', 'admin'),
  ('admin_pwd', '${ADMIN_PASS}')
ON DUPLICATE KEY UPDATE v=VALUES(v);
SQL
echo "Admin password: ${ADMIN_PASS}"
echo "Syskey: ${SYSKEY}"
```

> **重要**：必须导入完整结构。仅手动创建 `pay_config`/`pay_user`/`pay_order`/`pay_channel` 4 张表是不够的。所有渠道模板默认关闭，填写真实渠道配置后再逐个启用。

#### 方式 B：从旧服务器导入完整 dump（迁移现有实例）

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

#### 附录：完整 29 表结构（历史参考）

以下内容与 `database/schema.sql` 对应，仅供查阅字段。实际部署请直接导入仓库 SQL 文件，避免复制不完整或因文档更新产生偏差：

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
  KEY `code` (`to`,`type`)
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
  KEY `channel` (`channel`,`uid`)
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

```

`syskey` 不属于公共 schema 或 seed，必须按下方安全初始化步骤为每个实例独立生成。

#### 插入初始配置数据（pay_config）

`database/seed.sql` 已插入非敏感默认配置。服务启动前还必须生成实例唯一的 Session 密钥和管理员密码；不要使用固定密码或把真实密码提交到 Git：

```bash
SYSKEY="$(openssl rand -hex 32)"
ADMIN_PASS="$(openssl rand -base64 24 | tr -d '\n')"

mysql -urpay -p -h127.0.0.1 rpay <<SQL
INSERT INTO pay_config (k, v) VALUES
  ('syskey', '${SYSKEY}'),
  ('admin_user', 'admin'),
  ('admin_pwd', '${ADMIN_PASS}')
ON DUPLICATE KEY UPDATE v=VALUES(v);
SQL

printf '首次管理员账号：admin\n首次管理员密码：%s\n' "$ADMIN_PASS"
```

保存首次密码到安全的密码管理器，登录 `/admin` 后立即修改。`syskey` 用于签名 Session Cookie，部署后不得随意改变，否则现有登录会话全部失效。

以下旧配置示例仅用于解释字段，通常无需手动执行：

```sql

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
  (2, 'stripe', 0, 'Stripe', 1),
  (3, 'paypal', 0, 'PayPal', 1),
  (4, 'wxpay',  0, '微信支付', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), showname=VALUES(showname), status=VALUES(status);
```

#### 插入支付插件（pay_plugin）

`database/seed.sql` 已使用实际表结构的 `name/showname/author/link/types/transtypes` 字段插入 rpay 支持的插件元数据，无需再次手工插入。运行时实际分发依据是 `pay_channel.plugin`。

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

-- Stripe（type=2 对应 pay_type.id=2, plugin=stripe）
INSERT INTO pay_channel (id, mode, type, plugin, name, rate, status, apptype, daytop, daystatus, paymin, paymax, appwxmp, appwxa, costrate, config, daymaxorder)
VALUES (3, 0, 2, 'stripe', 'Stripe', 100.00, 1, NULL, 0, 0, '', '', NULL, NULL, 0.00,
  '{"appsecret":"sk_live_填入你的SecretKey","appkey":"whsec_填入WebhookSigningSecret","currency":"eur","currency_rate":7.8}',
  0)
ON DUPLICATE KEY UPDATE name=VALUES(name), status=VALUES(status);

-- PayPal（type=3 对应 pay_type.id=3, plugin=paypal）
INSERT INTO pay_channel (id, mode, type, plugin, name, rate, status, apptype, daytop, daystatus, paymin, paymax, appwxmp, appwxa, costrate, config, daymaxorder)
VALUES (4, 0, 3, 'paypal', 'PayPal', 100.00, 1, NULL, 0, 0, '', '', NULL, NULL, 0.00,
  '{"appid":"填入PayPalClientID","appsecret":"填入PayPalSecret","sandbox":false,"currency":"GBP","currency_rate":9.1,"webhook_id":"填入WebhookID"}',
  0)
ON DUPLICATE KEY UPDATE name=VALUES(name), status=VALUES(status);

-- 微信支付V2（type=4 对应 pay_type.id=4, plugin=wxpay）
INSERT INTO pay_channel (id, mode, type, plugin, name, rate, status, apptype, daytop, daystatus, paymin, paymax, appwxmp, appwxa, costrate, config, daymaxorder)
VALUES (5, 0, 4, 'wxpay', '微信支付(V2)', 100.00, 0, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL,
  '{"appid":"","appmchid":"","appkey":""}',
  0)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 微信支付V3（type=4 对应 pay_type.id=4, plugin=wxpayn；与 V2 共用 type）
INSERT INTO pay_channel (id, mode, type, plugin, name, rate, status, apptype, daytop, daystatus, paymin, paymax, appwxmp, appwxa, costrate, config, daymaxorder)
VALUES (6, 0, 4, 'wxpayn', '微信支付(V3)', 100.00, 0, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL,
  '{"appid":"","appmchid":"","appsecret":"","appkey":"","mch_private_key":"","platform_public_key":"","publickeyid":""}',
  0)
ON DUPLICATE KEY UPDATE name=VALUES(name);
```

> **字段说明**：
> - `type`：关联 `pay_type.id`（1=支付宝, 2=Stripe, 3=PayPal, 4=微信 V2/V3；两个微信插件暂不区分）；
> - `plugin`：rpay 运行时分发名，例如 `alipay`、`wxpay`、`wxpayn`、`paypal`、`stripe`；
> - `status`：1=启用，0=禁用；先确认密钥和回调可用，再逐个启用；
> - `config`：JSON 格式的渠道配置，不同插件字段不同；
> - `rate`：手续费率（百分比，100.00=无手续费）。
>
> 全新安装优先使用 `database/seed.sql` 提供的五个默认关闭模板，并通过 `/admin/channels` 编辑渠道，避免重复执行本节旧 SQL 示例。

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

### 3.6 运行参数和数据库连接

当前程序不读取 `config/config.toml`；监听地址、外部域名和数据库 URL 文件必须通过 systemd 的 `ExecStart` 参数或 `RPAY_*` 环境变量提供。仓库中的 TOML 示例仅供记录，不是有效运行配置。

#### secrets/database-url

```bash
echo -n "mysql://pay:***@127.0.0.1:3306/pay" > /opt/services/rpay/secrets/database-url
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

> **注意**：`ExecStart` 中的 `--public-base-url` 必须替换为实际 HTTPS 域名。程序当前不会自动读取 `config.toml`；如不使用命令行参数，请改用 `RPAY_PUBLIC_BASE_URL`、`RPAY_LISTEN` 和 `RPAY_DATABASE_URL_FILE` 环境变量。

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
没有自动过期机制时，待支付订单会永久留在数据库。后台定时任务每 3 分钟扫描一次，将超过 30 分钟未支付的订单标记为已关闭（status=2）。

**关键修复**：原实现使用 `DATE_SUB(NOW(), INTERVAL ? MINUTE)` 参数化 SQL，在 MySQL 5.6 + SQLx 预处理语句下兼容性不可靠，导致后台任务静默失败。修复方式是在 Rust 侧用 `chrono::Local::now().naive_local() - chrono::Duration::minutes(minutes)` 计算截止时间，SQL 改为 `UPDATE pay_order SET status=2 WHERE status=0 AND addtime < ?` 绑定普通时间参数。同时不再用 `let _ = ...` 吞掉错误，改为 `match` 输出过期数量和错误日志。

### 8. 编译需要足够内存
Rust release 编译内存消耗大，512MB VPS 会 OOM。解决办法：添加 2GB swap 或在本地编译后上传二进制。

### 9. systemd 安全加固的坑
`ProtectSystem=strict` 会将整个文件系统设为只读，必须用 `ReadWritePaths` 显式开放需要写入的目录。`MemoryDenyWriteExecute=true` 会阻止 JIT，如果将来引入需要 JIT 的依赖需要去掉此选项。

### 10. 源码和部署目录缺一不可
- 源码目录（如 `/root/workspace/rpay/`）— 用于修改代码和编译，不参与运行，可在任意位置
- `/opt/services/rpay/`（部署目录）— 运行时需要的二进制、配置、密钥
- 迁移到新 VPS 时，两者都需要，或者在新 VPS 上只放二进制 + 配置，源码可以之后从 git 克隆

### 11. 必须导入完整数据库结构
仅手动创建 `pay_config`/`pay_user`/`pay_order`/`pay_channel` 4 张表是不够的。`pay_type` 表缺失会导致 `/admin/channels` 页面 500 错误（`list_channels_full()` SQL 中 `LEFT JOIN pay_type B ON A.type=B.id`）。全新部署直接导入仓库的 `database/schema.sql` 和 `database/seed.sql`；迁移旧实例时才导入旧服务器的完整 29 表 dump。

### 12. 管理员凭据在数据库中，不在 secrets file
rpay 的管理员登录从 `pay_config` 表读取 `admin_user` 和 `admin_pwd`，不是从 `secrets/admin-password` 文件。全新部署时按 3.3 节生成随机 `syskey` 和管理员密码；修改密码也是更新数据库，不是改文件。

### 13. WordPress 桌面端 rpay 重复下单

**现象**：手机端点击一次支付正常创建一笔订单；电脑端点击一次，约两秒后可能出现两笔不同的 WordPress 未支付订单，并进一步在 rpay 产生两笔订单。

**根因**：根因在 WordPress 的 erphpdown 插件桌面端弹窗/iframe 链路，而不是 rpay 回调。桌面端可能对同一个 `.erphpdown-iframe` 支付链接重复绑定或触发点击处理器，进而使 `payment/rpay.php` 被加载两次。该入口每次调用 `_epd_create_page_order('rpay')` 都会生成新的 WordPress 商户订单号，因此网关无法把两个不同的 `out_trade_no` 判断为同一笔。

**修复职责分层**：

1. **WordPress 主修复**（必须部署）：
   - 文件：`wp-content/plugins/erphpdown/static/erphpdown.js`。
   - 对 `.erphpdown-iframe` 使用命名空间事件，先 `off()` 再 `on()`，并在点击后调用 `preventDefault()` 和 `stopImmediatePropagation()`，保证一次点击仅打开一个支付 iframe。
   - 文件：`wp-content/plugins/erphpdown/includes/pay.erphp.php`。
   - 仅在 `$payment == 'rpay'` 时，根据会话、商品/会员类型、金额、数量、客户数据和支付方式计算支付意图；30 秒内相同请求复用第一笔未支付的 WordPress 订单，不重复插入 `icemoney`。
2. **rpay 第二层保护**（已内置）：`src/web.rs` 按 `(merchant uid, out_trade_no)` 查找既有订单；相同商户订单号重复提交会复用已有网关订单。该保护无法替代 WordPress 修复，因为原问题会生成两个不同的 `out_trade_no`。

**验证步骤**：

1. 清理 WordPress 页面/对象缓存与 PHP OPcache，确保插件新文件生效。
2. 使用电脑浏览器登录同一用户，打开开发者工具的 Network 面板。
3. 只点击一次 rpay 充值或购买按钮。
4. 确认 WordPress 的 `icemoney` 后台只新增一笔未支付订单；rpay `/admin/orders` 也只新增一笔订单。
5. 再用手机端执行相同测试，确认仍为一次点击一笔订单。

> 30 秒窗口仅用于折叠同一次桌面端重复加载；超过窗口后的用户主动重新发起支付会正常创建新订单。

### 14. WordPress 全站页面前缀出现“4选项”

**现象**：站点所有页面在 `<!DOCTYPE HTML>` 前出现 `4选项` 等无关文字。

**根因**：`wp-content/plugins/erphpdown/includes/pay.erphp.php` 的 PHP 开始标签前存在裸文本。该文件会在插件初始化阶段加载，PHP 会将开标签前的任何字符直接写入每个 HTTP 响应，因此影响全站，而非特定主题模板。

**修复**：删除 PHP 开始标签前的所有裸文本，确保文件首字节就是 `<?php`。同时检查 `wp-content/plugins/erphpdown/static/erphpdown.js` 的首字节，不应在 `/*! Layer ... */` 前出现额外字符，否则可能导致支付前端脚本异常。

**验证**：清理缓存后执行：

```bash
curl -sS -L https://你的WordPress域名/ | head -c 32
```

输出应直接以 `<!DOCTYPE HTML>` 或 `<!doctype html>` 开始，不应含有 `4选项`。本问题未修改 modown 或 monster8 的主题模板。

### 16. MySQL 5.6 参数化 INTERVAL 兼容性问题

**现象**：订单过期后台任务每 3 分钟执行但始终失败，订单保持 `status=0` 永不过期，且日志中无任何错误信息。

**根因**：`UPDATE pay_order SET status=2 WHERE status=0 AND addtime < DATE_SUB(NOW(), INTERVAL ? MINUTE)` 中的 `INTERVAL ? MINUTE` 在 MySQL 5.6 与 SQLx 预处理语句组合下无法正确绑定参数。旧代码用 `let _ = ...` 忽略错误，所以任务失败时没有任何日志。

**修复**：用 `format!` 将分钟数直接嵌入 SQL，使用 `DATE_SUB(NOW(), INTERVAL {} MINUTE)`，避免参数化 `INTERVAL ? MINUTE` 的绑定问题，同时保证截止时间与 `addtime` 使用同一个 MySQL 会话时区：

```rust
let sql = format!(
    "UPDATE pay_order SET status=2 WHERE status=0 AND addtime < DATE_SUB(NOW(), INTERVAL {} MINUTE)",
    minutes
);
sqlx::query(&sql).execute(&self.pool).await?;
```

> **注意**：不能用 `chrono::Local::now()` 计算截止时间再绑定参数。rpay 会在每条 SQLx 连接建立时强制 MySQL 会话使用 `+08:00`，订单 `addtime` 和过期判断都应继续使用同一数据库会话时间基准。`minutes` 是代码控制的 `i64`，直接格式化进 SQL 不存在注入风险。

### 17. 后台任务 panic 监控

**问题**：Tokio `spawn` 的后台任务如果 panic 退出，主服务仍显示正常运行，但实际功能已丢失。

**修复**：`main.rs` 中所有后台任务通过 `task_monitor` 包装，若任务 panic 退出会输出 `tracing::error!` 日志：

```rust
async fn task_monitor<F>(name: &'static str, fut: F)
where
    F: std::future::Future<Output = ()> + Send + 'static,
{
    let handle = tokio::spawn(fut);
    if let Err(e) = handle.await {
        tracing::error!("task {name} panicked: {e}");
    }
}
```

### 18. RUST_LOG 环境变量

systemd service 必须设置 `Environment=RUST_LOG=info`，否则 `tracing` 默认只输出 error 级别日志，`expire_pending_orders task started` 等信息级日志不会出现在 `journalctl` 中。部署后通过 `journalctl -u rpay` 确认出现 `expire_pending_orders task started`。

### 15. WordPress 登录页“您即将提交的信息不安全”警告

**现象**：访问 `https://blog.anut.top/wp-login.php` 时浏览器弹出“您即将提交的信息不安全 / 由于系统正在使用不安全的连接提交此表单，他人将能看到您的信息”警告，登录页样式同时错乱。

**根因**：站点前端是 HTTPS（openresty 反向代理/CDN 在前端终结 SSL，回源为 HTTP），但 WordPress 数据库 `wp_options` 中的 `siteurl` 和 `home` 仍是 `http://blog.anut.top`。WordPress 据此生成的登录表单 action、`redirect_to`、CSS/JS 资源链接全部是 `http://`：

```html
<form name="loginform" action="http://blog.anut.top/wp-login.php" method="post">
<input type="hidden" name="redirect_to" value="http://blog.anut.top/wp-admin/" />
<link rel='stylesheet' href='http://blog.anut.top/wp-admin/load-styles.php?...' />
```

HTTPS 页面提交 HTTP 表单触发浏览器“不安全提交”警告；HTTP 资源被混合内容拦截导致样式错乱。`curl -sI http://blog.anut.top/wp-login.php` 实际返回 `301 → https://`，证明 SSL 在代理层终结，WordPress 进程本身看到的仍是 HTTP，因此默认不会自识别为 HTTPS。

**修复**：两步配合，缺一不可。

1. **更新数据库** `wp_options`，把 `siteurl` 和 `home` 改为 `https://blog.anut.top`：

   ```sql
   UPDATE wp_options SET option_value = 'https://blog.anut.top'
   WHERE option_name IN ('siteurl', 'home');
   ```

2. **修改 `wp-config.php`**，在 `/* Add any custom values between this line and the "stop editing" line. */` 之后、`/* That's all, stop editing! Happy publishing. */` 之前加入：

   ```php
   define('FORCE_SSL_ADMIN', true);

   // 反向代理/CDN 在前端终结 SSL，回源为 HTTP，需让 WordPress 识别前端协议为 HTTPS
   if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
       && strpos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false) {
       $_SERVER['HTTPS'] = 'on';
   }
   ```

   仅改数据库而不加这段代码，会因为 WordPress 仍认为自己是 HTTP 而与 `FORCE_SSL_ADMIN`/前端 HTTPS 形成重定向循环；仅加这段代码而不改数据库，表单 action 仍是 `http://`，警告不会消失。

**备份**（执行前务必先做）：

```bash
cp /www/wwwroot/wpsite/wp-config.php /www/wwwroot/wpsite/wp-config.php.bak.$(date +%Y%m%d%H%M%S)
mysqldump -uwpsite -p'密码' wpsite wp_options > /tmp/wp_options_backup_$(date +%Y%m%d%H%M%S).sql
```

**验证**：

```bash
curl -s https://blog.anut.top/wp-login.php | grep -iE 'form name="loginform"|redirect_to|load-styles'
```

输出应全部为 `https://blog.anut.top/...`，无 `http://` 链接。浏览器刷新登录页，警告消失、样式恢复正常。

> 后续如发现文章正文/图片里仍残留 `http://` 链接，可用 Better Search Replace 插件批量替换 `http://blog.anut.top` → `https://blog.anut.top`。

---

## 七、PayPal 对接配置

### 7.1 创建 PayPal 应用

1. 访问 [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/applications)
2. **My Apps & Credentials** → 选择 **Live**（实盘）或 **Sandbox**（沙箱）
3. **Create App**，填写应用名称
4. 创建后获取：
   - **Client ID** → 填入 `pay_channel.config.appid`
   - **Client Secret** → 填入 `pay_channel.config.appsecret`

### 7.2 配置 Webhook

Webhook 是服务器到服务器的异步通知，不依赖用户浏览器跳转，是订单确认的可靠兜底机制。

1. 在 PayPal App 页面 → **Webhooks** → **Add Webhook**
2. Webhook URL: `https://你的域名/notify/paypal`
3. 勾选事件：
   - **Payment capture completed** — 收款成功（必须）
   - **Payment capture refunded** — 退款成功（可选，当前 rpay 不处理退款回调）
   - 其他事件（declined/denied/pending/reversed）可选，rpay 会忽略不处理的事件
4. 保存后获取 **Webhook ID** → 填入 `pay_channel.config.webhook_id`

> **沙箱 Webhook**：在 Developer Dashboard 的 Sandbox 标签页配置，URL 同样是 `https://你的域名/notify/paypal`
>
> **实盘 Webhook**：也可通过 `https://www.paypal.com/businessmanage/notifications/webhooks` 配置

### 7.3 货币配置

rpay 收单金额为人民币（分），PayPal 渠道通过 `currency` 和 `currency_rate` 转换：

| 字段 | 说明 | 示例 |
|------|------|------|
| `currency` | PayPal 收款货币 | `GBP`（英镑）、`EUR`（欧元）、`USD`（美元） |
| `currency_rate` | CNY→目标货币汇率 | 9.1（¥9.1≈£1）、7.8（¥7.8≈€1）、7.2（¥7.2≈$1） |

> **避免双重货币转换费**：将 `currency` 设为商户 PayPal 账户的结算货币，这样 PayPal 不会二次转换。例如英国账户设 `GBP`，欧洲账户设 `EUR`。如果设为 `USD` 而商户账户是 GBP/EUR，PayPal 会收约 4% 货币转换费。

### 7.4 写入数据库

```sql
-- 沙箱配置（测试用）
UPDATE pay_channel SET 
  config = '{"appid":"沙箱ClientID","appsecret":"沙箱Secret","sandbox":true,"currency":"GBP","currency_rate":9.1,"webhook_id":"沙箱WebhookID"}',
  status = 1
WHERE plugin = 'paypal';

-- 实盘配置
UPDATE pay_channel SET 
  config = '{"appid":"实盘ClientID","appsecret":"实盘Secret","sandbox":false,"currency":"GBP","currency_rate":9.1,"webhook_id":"实盘WebhookID"}',
  status = 1
WHERE plugin = 'paypal';
```

修改后重启服务：`systemctl restart rpay.service`

### 7.5 Guest Checkout（访客支付）

PayPal 支持无 PayPal 账户的访客直接用信用卡（Visa/Mastercard 等）支付：

1. 登录 [PayPal Business Account](https://www.paypal.com/businessmanage)
2. **Account Settings** → **Website Payments** → **Website Preferences**
3. 开启 **"Account Optional"**（允许访客支付）

> 开启后，PayPal 支付页面会显示 "Pay with Debit or Credit Card" 选项，用户无需注册 PayPal 即可用卡支付。

### 7.6 测试

#### 沙箱测试

1. 在 [PayPal Developer Sandbox](https://developer.paypal.com/dashboard/sandbox/accounts) 创建买家测试账号
2. 将渠道配置切换为沙箱（`sandbox:true`）
3. 通过 `/submit.php` POST 提交 PayPal 订单，跳转到 PayPal 沙箱页面
4. 用沙箱 buy 账号登录完成支付

#### 实盘测试

将渠道配置切换为实盘（`sandbox:false`），用真实 PayPal 账号或信用卡支付最小金额（如 ¥1）。

> **注意**：实盘测试会真实扣款。最小金额建议 ¥1（按汇率约 £0.11），过低（如 ¥0.01）转换后低于 PayPal 最小收款额会下单失败。

### 7.7 支付流程

```
用户 → rpay /submit.php (POST, type=paypal)
     → rpay 调用 PayPal API: POST /v2/checkout/orders
     → 返回 approve_url，303 重定向用户到 PayPal 支付页面
     → 用户在 PayPal 完成支付
     → PayPal 重定向用户到 rpay /return/paypal?trade_no=xxx
     → rpay 调用 PayPal API: POST /v2/checkout/orders/{id}/capture 确认支付
     → 标记订单已付，重定向到商户 return_url
     →（异步）PayPal 发送 Webhook 到 rpay /notify/paypal（兜底）
```

### 7.8 pay_type 和 pay_channel 对应关系

`pay_channel.type` 必须对应 `pay_type.id`，否则提交订单会报"当前支付方式暂不可用"：

| pay_type.id | pay_type.name | pay_channel.plugin | 说明 |
|-------------|---------------|---------------------|------|
| 1 | alipay | alipay | 支付宝 |
| 2 | stripe | stripe | Stripe |
| 3 | paypal | paypal | PayPal |
| 4 | wxpay | wxpay / wxpayn | 微信支付 V2/V3，两个插件共用 type=4 |

> **注意**：`pay_type.status` 也必须为 `1`（启用），否则该支付方式不可用。

---

## 八、Stripe 对接配置

### 8.1 获取 API 密钥

1. 登录 [Stripe Dashboard](https://dashboard.stripe.com/apikeys)
2. 获取以下两个密钥：
   - **Secret Key**（`sk_live_...`）→ 填入 `pay_channel.config.appsecret`
   - **Publishable Key**（`pk_live_...`）→ 公钥，rpay 不直接使用，记录备用

> 沙箱测试用 **Test Mode** 的密钥（`sk_test_...`），实盘用 **Live Mode** 的密钥（`sk_live_...`）。

### 8.2 配置 Webhook

Webhook 是服务器到服务器的异步通知，不依赖用户浏览器跳转，是订单确认的可靠兜底机制。

1. 访问 [Stripe Webhooks](https://dashboard.stripe.com/webhooks) → **Add endpoint**
2. Endpoint URL: `https://你的域名/notify/stripe`
3. 勾选事件：
   - **`checkout.session.completed`** — 收款成功（必须）
   - **`charge.refunded`** — 退款成功（可选，当前 rpay 不处理退款回调）
4. 保存后获取 **Signing Secret**（`whsec_...`）→ 填入 `pay_channel.config.appkey`

> **多个网关共用 Stripe 账户**：Stripe 支持添加多个 Webhook endpoint，每个有独立的 signing secret。但 rpay 的 `config.appkey` 只能填一个，所以每个网关用各自 Webhook 的 signing secret。

### 8.3 支付方式与货币配置

Stripe 渠道默认向 Checkout 请求信用卡和支付宝，可通过 `payment_method_types` 显式调整：

| 字段 | 说明 | 示例 |
|------|------|------|
| `payment_method_types` | Checkout 支付方式；省略时默认信用卡和支付宝 | `["card","alipay"]`；仅信用卡用 `["card"]` |
| `currency` | Stripe 收款货币 | `eur`（欧元）、`gbp`（英镑）、`usd`（美元） |
| `currency_rate` | CNY→目标货币汇率 | 7.8（¥7.8≈€1）、9.1（¥9.1≈£1）、7.2（¥7.2≈$1） |

支付宝还需要在 Stripe Dashboard 的 **Settings → Payment methods** 中为对应的 Test/Live 模式启用。是否最终展示由 Stripe 根据账户注册地区、账户能力、Dashboard 设置、交易币种和客户位置共同决定，并非仅由网关服务器或客户 IP 决定。如果账户不支持支付宝且创建 Session 报错，可暂时配置为 `["card"]`。

rpay 收单金额为人民币（分），Stripe 渠道通过 `currency` 和 `currency_rate` 转换：

> **避免双重货币转换费**：将 `currency` 设为商户 Stripe 账户的结算货币。例如欧洲账户设 `eur`，英国账户设 `gbp`。如果设为 `usd` 而商户账户是 EUR/GBP，Stripe 会额外收货币转换费。

> **最小金额限制**：Stripe 对不同货币有最小收款额：
> - EUR: €0.50（对应人民币至少 ¥3.90，按 rate=7.8）
> - GBP: £0.30（对应人民币至少 ¥2.73，按 rate=9.1）
> - USD: $0.50（对应人民币至少 ¥3.60，按 rate=7.2）
>
> 低于最小金额 Stripe 会返回 `amount_too_small` 错误，导致下单失败。

### 8.4 写入数据库

```sql
-- 实盘配置：信用卡 + 支付宝
UPDATE pay_channel SET
  config = '{"appsecret":"sk_live_你的SecretKey","appkey":"whsec_你的SigningSecret","currency":"eur","currency_rate":7.8,"payment_method_types":["card","alipay"]}',
  status = 1
WHERE plugin = 'stripe';

-- 同时确保 pay_type 中 stripe 已启用
UPDATE pay_type SET status = 1 WHERE name = 'stripe';
```

修改后重启服务：`systemctl restart rpay.service`

### 8.5 测试

#### 沙箱测试

1. 在 Stripe Dashboard 切换到 **Test Mode**
2. 在 Test Mode 的 Payment methods 中启用支付宝
3. 使用 Test Mode 的 Secret Key（`sk_test_...`）配置渠道
4. 通过 `/submit.php` POST 提交 Stripe 订单，确认 Checkout 展示已启用且适用于当前交易的支付方式
5. 使用 [Stripe 测试卡号](https://docs.stripe.com/testing) 完成支付：
   - `4242 4242 4242 4242`（Visa，成功）
   - `4000 0027 6000 3184`（Visa，触发 3DS 验证）
   - 任意未来日期 + 任意 CVC + 任意邮编

#### 实盘测试

使用 Live Mode 的 Secret Key 配置渠道，用真实信用卡支付最小金额。

> **注意**：实盘测试会真实扣款。建议金额 ¥5（按汇率约 €0.64），低于 €0.50 会下单失败。

### 8.6 支付流程

```
用户 → rpay /submit.php (POST, type=stripe)
     → rpay 调用 Stripe API: POST /v1/checkout/sessions
     → 返回 checkout session URL，303 重定向用户到 Stripe Checkout 页面
     → 用户使用 Stripe 为本次交易展示的信用卡或支付宝完成支付
     → Stripe 重定向用户到 rpay /return/stripe?trade_no=xxx&session_id=xxx
     → rpay 调用 Stripe API: GET /v1/checkout/sessions/{id} 确认支付状态
     → 标记订单已付，重定向到商户 return_url
     →（异步）Stripe 发送 Webhook 到 rpay /notify/stripe（兜底）
```

### 8.7 config 字段说明

| 字段 | 说明 | 示例 |
|------|------|------|
| `appsecret` | Stripe Secret Key | `sk_live_...` / `sk_test_...` |
| `appkey` | Stripe Webhook Signing Secret | `whsec_...` |
| `currency` | 收款货币（小写） | `eur` / `gbp` / `usd` |
| `currency_rate` | CNY→目标货币汇率 | 7.8 |

---

## 九、WordPress (erphpdown) 适配

### 9.1 概述

rpay 通过兼容「易支付协议」与 WordPress erphpdown 插件对接。erphpdown 插件作为商户端，向 rpay 的 `/submit.php` 发起 POST 表单提交，rpay 根据 `type` 参数路由到对应支付渠道（支付宝、微信、Stripe、PayPal）。

### 9.2 支付类型与 paytype 映射

| rpay `type` 参数 | erphpdown paytype | 说明 | 管理后台代号 |
|---|---|---|---|
| `alipay` | 141 | 支付宝 | `rpay-ali` |
| `wxpay` | 142 | 微信支付 | `rpay-wx` |
| `stripe` | 143 | Stripe 信用卡 | `rpay-stripe` |
| `paypal` | 144 | PayPal | `rpay-paypal` |

### 9.3 修改的文件清单

#### erphpdown 插件

| 文件 | 修改内容 |
|---|---|
| `payment/rpay.php` | **新建**。商户端发起文件，组装签名参数并自动提交到 rpay `/submit.php`。`type` 参数从 URL GET 传入，支持 `alipay`/`wxpay`/`stripe`/`paypal`。调用 `_epd_create_page_order('rpay_'.$type)` 使 `ice_alipay` 字段存储具体支付类型。 |
| `payment/rpay/notify_url.php` | **新建**。异步回调处理，验证 MD5 签名后从数据库查出订单的 `ice_alipay` 值传给 `epd_set_order_success`，更新订单状态。 |
| `payment/rpay/return_url.php` | **新建**。同步跳转处理，验证签名后重定向到前台成功页面或 `erphpdown_return` cookie 中的地址。 |
| `admin/erphp-payment.php` | 新增 rpay 设置区块（第11节）：商户ID、商户key、API地址、隐藏支付宝/微信/Stripe/PayPal 四个复选框；保存/加载 options；更新支付接口顺序说明。 |
| `includes/pay.erphp.php` | 新增 rpay 30 秒去重窗口，按 `ice_alipay` 精确匹配（`rpay_stripe`/`rpay_paypal` 等），同一用户对同一资源、同一金额、同一支付类型的未支付订单在 30 秒内复用，避免重复建单且不跨类型误合并。 |
| `static/erphpdown.js` | 修复 `.erphpdown-iframe` 点击事件重复绑定导致的重复下单问题：使用 `off().on()` 并 `stopImmediatePropagation()`。 |

#### modown 主题

| 文件 | 修改内容 |
|---|---|
| `action/user.php` | 两处 VIP 购买区块各新增 4 个 rpay 支付按钮（微信、支付宝、Stripe、PayPal），位于 easepay 之后、vpay 之前。使用 `get_option('erphpdown_rpay_id')` 控制整组显示，各子项有独立隐藏开关。 |
| `template/user.php` | 充值表单三处修改：① paytype 路由新增 141/142/143/144 → `rpay.php?type=xxx`；② 自定义支付顺序 switch 新增 `rpay-ali`/`rpay-wx`/`rpay-stripe`/`rpay-paypal` case；③ 默认支付方式区新增 rpay 四个 radio button。 |

#### monster8 主题

| 文件 | 修改内容 |
|---|---|
| `action/user.php` | VIP 购买区块新增 4 个 rpay 支付按钮（微信、支付宝、Stripe、PayPal），位于 easepay 之后、vpay 之前。 |
| `template/erphpdown-recharge.php` | 充值表单三处修改：① paytype 路由新增 141/142/143/144；② switch 新增 4 个 case；③ 默认支付方式区新增 4 个 radio button。 |

### 9.4 WordPress 管理后台配置

1. 进入 **erphpdown → 支付设置 → 11、rpay（支付宝/微信/Stripe/PayPal）**
2. 填写：
   - **商户ID**：rpay 后台创建商户后获得的 `pid`
   - **商户key**：对应商户的 `key`（用于 MD5 签名）
   - **APIaddress**：rpay 服务地址，**结尾不要加 `/`**，例如 `https://pay.example.com`
3. 勾选需要隐藏的支付方式（支付宝/微信/Stripe/PayPal），未勾选的方式将显示给用户
4. （可选）在「充值支付接口顺序」中填入 `rpay-ali,rpay-wx,rpay-stripe,rpay-paypal` 自定义充值页支付方式排序

### 9.5 rpay 后台渠道配置

rpay 需在管理后台配置对应支付渠道，rpay 的 `type` 参数会匹配 `pay_type` 表中的 `name` 字段：

| pay_type.name | 对应 plugin | config 结构 |
|---|---|---|
| `alipay` | `alipay` | `AlipayConfig`（appid, appsecret, appkey, sign_type） |
| `wxpay` | `wxpay` 或 `wxpayn`/`wxpaynp` | `WxpayV2Config`（appid, appmchid, appkey）或 `WxpayV3Config` |
| `stripe` | `stripe` | `StripeConfig`（appsecret, appkey, currency, currency_rate, payment_method_types） |
| `paypal` | `paypal` | `PaypalConfig`（client_id, client_secret, currency, currency_rate） |

> **注意**：`pay_type.name` 必须与 erphpdown 传来的 `type` 参数完全一致（小写），否则 rpay 返回「当前支付方式暂不可用」。

### 9.6 签名机制

erphpdown → rpay 请求签名（MD5）：

1. 将所有参数按 key 升序排列（ksort）
2. 跳过空值和 `sign`/`sign_type` 字段
3. 拼接为 `k1=v1&k2=v2&...` 格式
4. 末尾追加商户 key：`...&kN=vN{key}`
5. MD5 取小写 hex

rpay → erphpdown 回调签名验证（`notify_url.php`/`return_url.php`）：

同样 ksort + 跳过空值/sign/sign_type + 拼接 + 追加 key + MD5。

### 9.7 排坑记录

#### 9.7.1 重复下单问题

**现象**：用户点击 VIP 购买按钮时，有时会创建两个相同订单。

**根因**：`erphpdown.js` 中 `.erphpdown-iframe` 的 click 事件被多次绑定（layer.js 弹窗每次打开都会重新绑定），导致一次点击触发两次表单提交。

**修复**：
- `static/erphpdown.js`：改用 `$("body").off("click.erphpdown-iframe").on("click.erphpdown-iframe", ...)` 确保只绑定一次，并 `stopImmediatePropagation()` 阻止后续 handler。
- `includes/pay.erphp.php`：新增 30 秒去重窗口，同一用户对同一资源/同一金额的 rpay 未支付订单在 30 秒内复用，不重复创建。

#### 9.7.1b 跨支付类型订单误合并

**现象**：用户先用 rpay-Stripe 发起 17 元支付并取消，再用 rpay-PayPal 发起 17 元支付，rpay 后台只看到一笔订单。

**根因**：30 秒去重逻辑用 `ice_alipay='rpay'` 匹配，不区分 Stripe/PayPal/支付宝/微信。同金额的不同支付类型订单在 30 秒内被复用为同一笔。

**修复**：
- `rpay.php`：`_epd_create_page_order('rpay')` 改为 `_epd_create_page_order('rpay_'.$type)`，使 `ice_alipay` 字段存储 `rpay_stripe`/`rpay_paypal`/`rpay_alipay`/`rpay_wxpay`。
- `pay.erphp.php`：去重条件从 `ice_alipay='rpay'` 改为 `ice_alipay='".$payment."'"`，匹配条件从 `$payment == 'rpay'` 改为 `$payment == 'rpay' || strpos($payment, 'rpay_') === 0`。
- `notify_url.php`：回调时从数据库查出订单的 `ice_alipay` 值传给 `epd_set_order_success`，而不是硬编码 `'rpay'`。

#### 9.7.2 pay.erphp.php 文件首字节问题

**现象**：`pay.erphp.php` 文件开头有 BOM 或空行，导致 `header()` 调用时报 "headers already sent" 错误。

**修复**：确保文件第一字节为 `<?php`，无 BOM、无前导空行。

#### 9.7.3 Alipay wap pay vs page pay

**现象**：支付宝支付在桌面端打开后报签名错误。

**根因**：rpay 根据 User-Agent 判断移动端/桌面端。移动端直接渲染 wap-pay 表单；桌面端显示二维码，扫码后打开 wap-pay 表单。如果商户的支付宝应用只签约了「手机网站支付」而未签约「电脑网站支付」，使用 page pay 会报错。

**修复**：rpay 桌面端统一使用 wap-pay + 二维码方案，不依赖 page pay 产品。

#### 9.7.4 Stripe 汇率转换

**注意**：erphpdown 传入的金额为人民币（CNY），rpay Stripe 渠道通过 `currency_rate` 字段将 CNY 转换为目标货币。例如 `currency_rate=7.8` 表示 7.8 CNY = 1 EUR。`currency` 字段必须与 Stripe 账户支持的货币一致。

#### 9.7.5 PayPal 汇率转换

与 Stripe 类似，PayPal 渠道的 `currency_rate` 将 CNY 分转换为 PayPal 目标货币的最小单位。`currency` 字段必须与 PayPal 商户账户支持的货币一致。

#### 9.7.6 rpay API 地址末尾斜杠

**注意**：erphpdown 后台填写的 rpay API 地址**结尾不要加 `/`**。`rpay.php` 中使用 `rtrim($rpay_url, '/') . '/submit.php'` 拼接提交地址，如果末尾多加斜杠虽然会被 rtrim 处理，但部分主题的 JS 代码可能直接拼接 URL 导致问题。

#### 9.7.7 Stripe/PayPal 结账页暴露站点信息

**现象**：Stripe Checkout 和 PayPal 审批页的商品名称显示「独立站名+用户ID」（如 `XXX站订单[admin]`），暴露站点身份和用户信息。

**根因**：erphpdown 创建订单时 `subject` 字段为 `站点名订单[用户名]`，rpay 原样传给 Stripe/PayPal 作为商品名称。

**修复**：rpay `web.rs` 新增 `external_checkout_name()` 函数，对 Stripe 和 PayPal 的结账请求统一替换商品名称为固定字符串 `"Source Code"`。rpay 数据库和后台仍保留 WordPress 传入的原始商品名称用于内部对账，订单号通过 `client_reference_id`（Stripe）/ `reference_id`（PayPal）传递。

### 9.8 支付流程图

```
用户选择支付方式
  → erphpdown rpay.php?type={alipay|wxpay|stripe|paypal}
  → 组装参数 + MD5 签名
  → POST 到 rpay /submit.php
  → rpay 验证签名、创建/复用订单
  → 根据 type 路由到对应支付渠道
    → alipay: 渲染 wap-pay 表单（移动端）或二维码页面（桌面端）
    → wxpay:  调用微信统一下单，返回二维码 URL，渲染二维码页面
    → stripe: 调用 Stripe Checkout Sessions API，303 重定向到 Stripe Checkout
    → paypal: 调用 PayPal Orders API，303 重定向到 PayPal 审批页
  → 用户完成支付
  → 支付渠道异步通知 rpay
  → rpay 验证签名，标记订单已付
  → rpay 回调 erphpdown notify_url.php
  → erphpdown 验证签名，更新订单状态（VIP 或余额）
  → 用户被重定向到前台成功页面
```

### 9.9 部署步骤

1. 将修改后的 erphpdown 插件文件上传到 `/wp-content/plugins/erphpdown/`
2. 将修改后的主题文件上传到对应主题目录（`modown/` 或 `monster8/`）
3. WordPress 后台 → erphpdown → 支付设置 → 配置 rpay 商户信息
4. rpay 后台 → 确保 `pay_type` 表中有 `alipay`/`wxpay`/`stripe`/`paypal` 记录且状态为启用
5. rpay 后台 → 为每个 pay_type 配置至少一个启用的 pay_channel（含正确的 plugin 和 config JSON）
6. 测试支付流程：小额充值 → 验证回调 → 验证订单状态更新

---

## 十、当前实现状态与已知限制

本节汇总当前 Rust 实现的功能范围和维护注意事项。它是公开的项目状态说明，不包含服务器路径、内部地址、生产数据库信息或支付密钥；实际部署参数以本指南前文和运行环境为准。

### 10.1 已实现功能

- **协议与签名**：兼容 EasyPay 风格的参数规范化和 MD5 签名/验签；支持支付宝 RSA/RSA2 签名，并兼容常见的 PKCS#1、PKCS#8 私钥编码。
- **订单与通知**：支持签名校验、订单创建、重复提交幂等处理、支付状态查询、支付回调验签，以及商户通知失败重试。
- **支付宝**：支持移动端 WAP 支付；桌面端可通过二维码进入移动端支付流程；支持同步跳转和异步通知。
- **微信支付**：支持 V2 MD5 统一下单和 V3 RSA-SHA256 请求签名、Native/H5/JSAPI 场景及通知报文解密。`wxpay`、`wxpayn` 和 `wxpaynp` 是运行时插件名，V2/V3 渠道仍按当前 `pay_type` 映射共用微信支付类型 `4`，不在 `type` 层面细分。
- **PayPal**：支持 OAuth2 客户端凭据、订单创建与捕获、Webhook 校验和目标货币换算。
- **Stripe**：支持 Checkout Session、Webhook 签名校验和目标货币换算。
- **后台与商户门户**：`/admin/*` 提供管理员登录、统计、商户、订单、渠道和系统设置；`/user/*` 提供商户注册、登录、订单、密钥和账户设置。Session 使用 `pay_config.syskey` 进行 HMAC 签名，不兼容旧 PHP 实现的登录 Cookie，升级后需要重新登录。
- **数据库兼容**：使用现有 EasyPay 兼容的 `pay_*` 表结构，不要求额外迁移；支付类型与渠道的对应关系以第 7.8 节为准。

### 10.2 已知限制

- 目前仅移植支付宝、微信支付 V2/V3、PayPal 和 Stripe；旧 PHP 项目中的其他支付插件尚未移植。
- 商户使用 RSA 签名提交（`keytype=1`）尚未支持，目前以 MD5 商户签名为主。
- 黑名单、IP 限制、域名白名单等旧系统风控规则尚未完整移植。
- 渠道路由目前选择指定支付类型下的首个启用渠道，尚未实现子渠道、轮询/分组和按组费率覆盖。
- 商户通知目前基于订单记录重试，尚未提供独立的持久化通知队列；高并发或严格顺序场景应先评估可靠性要求。
- 各支付渠道在启用前仍需使用对应的沙箱或生产凭据完成小额下单、回调和退款验证；不要仅凭代码编译成功就启用生产支付。

### 10.3 维护与排查要点

- 回调处理应使用订单中记录的具体 `pay_channel`，不能只按支付类型重新选择渠道，否则同一类型配置多个渠道时可能验签错误。
- 金额和费率等 MySQL `DECIMAL` 字段在查询时需要按字符串处理，避免驱动对 `NEWDECIMAL` 的解码差异。
- WordPress/erphpdown 集成时，支付类型必须使用 `alipay`、`wxpay`、`stripe` 或 `paypal`，并确保 `pay_channel.type` 与对应的 `pay_type.id` 一致。
