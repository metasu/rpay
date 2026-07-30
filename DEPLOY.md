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
| Nginx / Nginx Proxy Manager / Caddy | 任意 | HTTPS 反向代理 |
| systemd | 任意 | 服务管理 |

### 可选软件
- **Docker + Nginx Proxy Manager**: 如果你用 NPM 做反代和 SSL 证书管理
- **aaPanel / 宝塔面板**: 如果你习惯用面板管理 Nginx/MySQL

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
CREATE DATABASE pay CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'pay'@'localhost' IDENTIFIED BY '你的强密码';
GRANT ALL PRIVILEGES ON pay.* TO 'pay'@'localhost';
FLUSH PRIVILEGES;
SQL
```

### 3.3 导入数据库表结构

数据库表结构来自旧版 PHP 支付网关（EasyPay），rpay 复用相同的表结构。如果你有旧数据库的 `mysqldump` 备份，直接导入：

```bash
# 从旧服务器导出（在旧服务器执行）
mysqldump -uroot -p pay > pay_schema.sql

# 在新服务器导入
mysql -uroot -p pay < pay_schema.sql
```

如果没有旧数据库，需要手动创建核心表。以下是最关键的 4 张表：

```sql
-- pay_config: 系统配置（必须包含 syskey）
CREATE TABLE IF NOT EXISTS `pay_config` (
  `k` varchar(32) NOT NULL,
  `v` text,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 插入 syskey（32位随机字符串，用于 session 加密）
INSERT INTO `pay_config` (`k`, `v`) VALUES ('syskey', '替换为你的32位随机字符串')
ON DUPLICATE KEY UPDATE `v` = VALUES(`v`);

-- pay_user: 商户表
CREATE TABLE IF NOT EXISTS `pay_user` (
  `uid` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `gid` int(11) unsigned NOT NULL DEFAULT '0',
  `upid` int(11) unsigned NOT NULL DEFAULT '0',
  `key` varchar(32) NOT NULL,
  `pwd` varchar(32) DEFAULT NULL,
  `account` varchar(128) DEFAULT NULL,
  `username` varchar(128) DEFAULT NULL,
  `money` decimal(10,2) NOT NULL,
  `email` varchar(32) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `addtime` datetime DEFAULT NULL,
  `lasttime` datetime DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `pay` tinyint(1) NOT NULL DEFAULT '1',
  `keytype` tinyint(1) NOT NULL DEFAULT '0',
  `keylogin` tinyint(1) NOT NULL DEFAULT '1',
  `pay_minmoney` varchar(10) DEFAULT NULL,
  `pay_maxmoney` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- pay_order: 订单表
CREATE TABLE IF NOT EXISTS `pay_order` (
  `trade_no` varchar(20) NOT NULL,
  `out_trade_no` varchar(64) NOT NULL,
  `api_trade_no` varchar(64) DEFAULT NULL,
  `uid` int(11) unsigned NOT NULL,
  `type` int(11) unsigned NOT NULL,
  `channel` int(11) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `money` varchar(10) NOT NULL,
  `realmoney` varchar(10) DEFAULT NULL,
  `profitmoney` decimal(10,2) DEFAULT NULL,
  `notify_url` varchar(255) NOT NULL,
  `return_url` varchar(255) NOT NULL,
  `param` text,
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `payurl` text,
  `buyer` varchar(64) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `endtime` datetime DEFAULT NULL,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`trade_no`),
  KEY `uid` (`uid`),
  KEY `out_trade_no` (`out_trade_no`)
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
  `config` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

生成 syskey：

```bash
openssl rand -hex 16
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
SRC_DIR=/root/workspace/rpay
INSTALL_DIR=/opt/services/rpay

# ---- 获取源码（三选一） ----

# 方式 A：从旧服务器打包复制
# 在旧服务器执行：
#   tar czf /tmp/rpay-src.tar.gz -C /root/workspace rpay
#   scp /tmp/rpay-src.tar.gz 新服务器:/tmp/
# 在新服务器执行：
#   mkdir -p /root/workspace
#   tar xzf /tmp/rpay-src.tar.gz -C /root/workspace

# 方式 B：用 rsync 从旧服务器同步
#   rsync -avz 旧服务器:/root/workspace/rpay/ /root/workspace/rpay/

# 方式 C：如果已推送到 git 仓库
#   git clone https://your-git-repo/rpay.git $SRC_DIR

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

#### secrets/admin-password

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

#### 方案 A：Nginx 直接反代

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

#### 方案 B：Nginx Proxy Manager (Docker)

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

#### 方案 C：Caddy（自动 HTTPS）

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
│   ├── database-url       # MySQL 连接串（敏感）
│   └── admin-password     # 管理后台密码（敏感）
├── data/                  # 运行时数据（预留，当前为空）
├── logs/                  # 日志目录（预留，日志实际输出到 journal）
└── backups/               # 备份目录（预留）
```

源码目录（编译用，不参与运行）：

```
/root/workspace/rpay/
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
- `/root/workspace/rpay/`（源码）— 用于修改代码和编译，不参与运行
- `/opt/services/rpay/`（部署目录）— 运行时需要的二进制、配置、密钥
- 迁移到新 VPS 时，两者都需要，或者在新 VPS 上只放二进制 + 配置，源码可以之后从 git 克隆
