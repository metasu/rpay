# rpay

> Rust-native payment gateway for multi-channel payments, merchant integration, and operational management.
>
> 面向多渠道收款、商户接入与运营管理的 Rust 原生支付网关。

[English](#english) | [中文](#中文)

<a id="english"></a>

## English

### Overview

`rpay` is a self-hosted payment gateway written in Rust. It provides a unified HTTP interface for merchants and routes orders to configurable payment channels while keeping order records, callback verification, merchant management, and channel operations in one service.

The project is designed for deployments where the operator needs control over the source code, database, credentials, payment-channel switches, and public endpoint. It is suitable for integrating a website, a WordPress site, or another merchant application with multiple payment providers.

### Highlights

- Rust 2021 implementation with Rust 1.75+ support.
- Axum-based HTTP service with Tokio async runtime.
- MySQL/MariaDB persistence through SQLx.
- Unified merchant-facing payment submission endpoint compatible with the EasyPay-style protocol.
- Payment-channel routing with independent payment type, channel, plugin, status, fee rate, limits, and JSON configuration.
- Admin panel for merchants, orders, channels, statistics, refunds, and batch order operations.
- Merchant portal with order management and API integration guidance.
- Synchronous return URLs and asynchronous notification endpoints.
- Automatic expiration of unpaid orders after approximately 30 minutes.
- Session cookies, request signing, callback verification, rate limiting, request tracing, and panic handling.
- systemd deployment with security hardening options.
- WordPress `erphpdown` integration files for Alipay, WeChat Pay, Stripe, and PayPal.
- External checkout privacy protection: Stripe Checkout and PayPal pages use a generic checkout name instead of exposing internal site or user information.

### Supported payment channels

| Provider | Plugin | Notes |
|---|---|---|
| Alipay | `alipay` | Mobile web payment flow and desktop QR-code flow |
| WeChat Pay V2 | `wxpay` | WeChat Pay V2 unified order integration |
| WeChat Pay V3 | `wxpayn` | WeChat Pay V3 API integration |
| PayPal | `paypal` | Orders API, capture, return handling, and webhook fallback |
| Stripe | `stripe` | Checkout Sessions, configurable currency conversion, and webhook fallback |

Every provider is disabled until its credentials, callback URL, currency settings, and channel configuration have been reviewed. Do not enable a production channel with placeholder credentials.

### Architecture

```text
Merchant application / WordPress erphpdown
                |
                |  signed POST /submit.php
                v
        rpay HTTP gateway (Axum)
          |       |       |
          |       |       +--> Merchant return / notify callbacks
          |       +----------> Payment provider APIs and webhooks
          +------------------> MySQL / MariaDB

Admin panel and merchant portal are served by the same gateway.
```

Main source modules:

| Module | Responsibility |
|---|---|
| `src/main.rs` | Process entry point, startup, HTTP server, and background tasks |
| `src/web.rs` | Public routes, payment submission, returns, notifications, and order flow |
| `src/admin.rs` | Admin authentication, merchant/order/channel/statistics operations |
| `src/portal.rs` | Merchant portal and merchant-facing pages |
| `src/store.rs` | SQLx database access and persistence logic |
| `src/protocol.rs` | Signing, verification, and payment protocol helpers |
| `src/session.rs` | Session cookie handling |
| `src/alipay.rs` | Alipay requests and signature handling |
| `src/wxpay_v2.rs` | WeChat Pay V2 integration |
| `src/wxpay_v3.rs` | WeChat Pay V3 integration |
| `src/paypal.rs` | PayPal API integration |
| `src/stripe.rs` | Stripe API integration |
| `src/templates.rs` | Shared HTML templates and components |

### Requirements

- Linux x86_64 is the primary deployment target.
- Rust toolchain 1.75 or newer.
- MySQL 5.7+ or MariaDB 10.3+.
- A public HTTPS endpoint for provider callbacks and merchant redirects.
- Nginx, Caddy, or another reverse proxy for TLS termination is recommended.
- systemd is recommended for long-running production service management.
- At least 1 GB RAM is recommended for release builds; small VPS instances may need swap or a locally built binary.

### Quick start

The commands below are a development-oriented outline. For a production installation, follow [`DEPLOY.md`](DEPLOY.md) from start to finish.
```bash
# Clone and enter the repository
git clone https://github.com/metasu/rpay.git
cd rpay

# 1. Initialize the database (requires MySQL/MariaDB)
#    With root access (creates user + database automatically):
ROOT_PASS=mysql_root_password DB_PASS=rpay_app_password ./scripts/init-db.sh
#    Or if the MySQL user already exists:
DB_PASS=your_mysql_password ./scripts/init-db.sh

# 2. Create the database URL file
echo -n "mysql://rpay:your_password@127.0.0.1:3306/rpay" > /opt/services/rpay/secrets/database-url
chmod 600 /opt/services/rpay/secrets/database-url

# 3. Deploy (builds, installs binary, creates systemd service, starts)
PUBLIC_URL=https://pay.example.com ./scripts/deploy.sh

# Or do it manually:
cargo build --release
./target/release/rpay \
  --listen 127.0.0.1:16889 \
  --public-base-url https://pay.example.com \
  --database-url-file /opt/services/rpay/secrets/database-url
```

The init script (`scripts/init-db.sh`) creates the database, imports the full 29-table schema and seed data, and generates a random `syskey` and admin password automatically.

The deploy script (`scripts/deploy.sh`) compiles the binary, creates the system user and directories, installs the systemd service from `deploy/rpay.service`, and starts the service.

The current runtime configuration is supplied through command-line arguments or `RPAY_*` environment variables. The TOML file is an example/reference file and should not be treated as the complete runtime configuration contract.

After starting the service:

1. Log in to `/admin` with the generated admin credentials.
2. Create a merchant account and generate a unique merchant API key.
3. Configure provider credentials and callback/webhook endpoints in the admin panel.
4. Enable only the channels that have been tested successfully.

For a complete production deployment guide (systemd, Nginx, SSL, WordPress integration), see [`DEPLOY.md`](DEPLOY.md).

### HTTP integration

The merchant-facing flow follows the EasyPay-style pattern:

1. The merchant creates or reuses an order and signs the request with its merchant key.
2. The merchant sends a POST request to `https://your-rpay-host/submit.php`.
3. rpay validates the signature, merchant status, payment type, and channel status.
4. rpay creates or reuses the gateway order and redirects the customer to the selected provider.
5. The provider returns the customer or sends an asynchronous notification.
6. rpay verifies the provider response, updates the order, and notifies the merchant.

Important endpoints include:

- `/submit.php` - merchant payment submission.
- `/notify/{provider}` - asynchronous provider notification endpoints.
- `/return/{provider}` - synchronous customer return endpoints.
- `/healthz` - local and reverse-proxy health check.
- `/admin` - administration interface.

The exact request fields and integration behavior are documented in the source and deployment guide. Do not expose a production instance without HTTPS and correct callback routing.

### WordPress integration

The repository contains the rpay adaptation for WordPress `erphpdown`, including payment entry points, notification and return handlers, admin settings, duplicate-order protection, and theme integration for `modown` and `monster8`.

Supported WordPress payment types:

| rpay type | erphpdown paytype | Provider |
|---|---:|---|
| `alipay` | 141 | Alipay |
| `wxpay` | 142 | WeChat Pay |
| `stripe` | 143 | Stripe |
| `paypal` | 144 | PayPal |

The WordPress adapter signs merchant requests with MD5, passes the selected payment type explicitly, and verifies rpay callbacks before marking the WordPress order as paid. The integration also contains a short duplicate-submission window and keeps different payment types from being incorrectly merged.

See the WordPress section in [`DEPLOY.md`](DEPLOY.md) for file lists, configuration, payment-type mapping, callback setup, and troubleshooting.

### Security and production notes

- Keep database URLs, provider secrets, private keys, webhook secrets, administrator credentials, and merchant keys outside Git.
- Use a unique random `syskey` for every instance. Changing it invalidates existing sessions.
- Use HTTPS for the public base URL. Provider callbacks must be able to reach the configured public endpoint.
- Import the complete schema. Creating only the four commonly used tables is insufficient; the application expects the full EasyPay-compatible schema.
- Configure `pay_channel.type` to match the corresponding `pay_type.id`, and ensure both the payment type and channel are enabled.
- Store the database URL in a mode-600 file without a trailing newline when using `--database-url-file`.
- Test sandbox credentials and webhook delivery before enabling live mode.
- Use a reverse proxy and run the service as a dedicated unprivileged system user.
- Back up the database before schema changes, provider migration, or production upgrades.
- Payment compliance, merchant verification, tax obligations, refund policy, and provider terms remain the operator's responsibility.

### Documentation

- [`DEPLOY.md`](DEPLOY.md) - complete bilingual-ready deployment reference currently maintained in Chinese, including database initialization, systemd, reverse proxy, PayPal, Stripe, and WordPress integration.
- [`PROGRESS.md`](PROGRESS.md) - project progress and implementation notes.
- [`Cargo.toml`](Cargo.toml) - Rust version, dependencies, and feature configuration.
- [`config/config.example.toml`](config/config.example.toml) - example listener and public URL values.
- [`database/schema.sql`](database/schema.sql) - complete database schema.
- [`database/seed.sql`](database/seed.sql) - non-sensitive initial metadata and disabled channel templates.
- [`scripts/init-db.sh`](scripts/init-db.sh) - one-command database initialization script.
- [`scripts/deploy.sh`](scripts/deploy.sh) - one-command build + deploy + systemd setup script.
- [`deploy/rpay.service`](deploy/rpay.service) - systemd service template.

### License

No license file is currently present in the repository. Unless a license is added, users should not assume that the code may be redistributed or used outside the permissions granted by the copyright holder.

---

<a id="中文"></a>

## 中文

### 项目简介

`rpay` 是一个使用 Rust 编写、支持自托管的多渠道支付网关。它为商户提供统一的 HTTP 支付提交入口，并将订单路由到可配置的支付渠道，同时在同一个服务中处理订单记录、回调验签、商户管理和渠道运营。

项目适合希望自行掌控源代码、数据库、凭据、支付方式开关和公网入口的部署场景，可用于网站、WordPress 站点或其他商户应用的多支付平台接入。

### 核心特性

- Rust 2021 实现，支持 Rust 1.75 及以上版本。
- 基于 Axum 的 HTTP 服务，使用 Tokio 异步运行时。
- 通过 SQLx 使用 MySQL/MariaDB 持久化订单、商户、渠道和运营数据。
- 兼容易支付风格协议的统一商户下单接口。
- 支付方式、支付渠道、插件、启用状态、费率、金额限制和 JSON 配置相互独立，便于逐项开关和运维。
- 管理后台支持商户、订单、渠道、统计、退款和批量订单操作。
- 商户门户提供订单管理和 API 接入辅助。
- 支持同步 return URL 与异步 notify URL。
- 后台自动将约 30 分钟未支付的订单标记为过期/关闭。
- 提供 Session Cookie、请求签名、回调验签、限流、请求追踪和 panic 处理。
- 支持使用 systemd 部署，并可启用多项服务安全加固选项。
- 提供 WordPress `erphpdown` 适配文件，支持支付宝、微信支付、Stripe 和 PayPal。
- 结账页隐私保护：Stripe Checkout 和 PayPal 页面使用通用商品名称，不直接暴露站点名称或用户信息。

### 支持的支付渠道

| 支付平台 | 插件标识 | 说明 |
|---|---|---|
| 支付宝 | `alipay` | 移动网页支付，以及桌面端二维码支付流程 |
| 微信支付 V2 | `wxpay` | 微信支付 V2 统一下单 |
| 微信支付 V3 | `wxpayn` | 微信支付 V3 API |
| PayPal | `paypal` | Orders API、capture、同步返回和 Webhook 兜底 |
| Stripe | `stripe` | Checkout Sessions、可配置货币转换和 Webhook 兜底 |

每个支付平台默认关闭。完成凭据、回调地址、货币和渠道配置检查后，再单独启用对应渠道。不要使用占位密钥直接开启生产支付渠道。

### 系统结构

```text
商户应用 / WordPress erphpdown
                |
                |  带签名 POST /submit.php
                v
        rpay HTTP 网关（Axum）
          |       |       |
          |       |       +--> 商户 return / notify 回调
          |       +----------> 支付平台 API 与 Webhook
          +------------------> MySQL / MariaDB

管理后台和商户门户由同一个网关服务提供。
```

主要源码模块：

| 模块 | 职责 |
|---|---|
| `src/main.rs` | 进程入口、启动流程、HTTP 服务和后台任务 |
| `src/web.rs` | 公共路由、支付提交、返回页、通知和订单流程 |
| `src/admin.rs` | 管理员认证、商户/订单/渠道/统计操作 |
| `src/portal.rs` | 商户门户和商户侧页面 |
| `src/store.rs` | SQLx 数据库访问和持久化逻辑 |
| `src/protocol.rs` | 签名、验签和支付协议辅助逻辑 |
| `src/session.rs` | Session Cookie 处理 |
| `src/alipay.rs` | 支付宝请求和签名处理 |
| `src/wxpay_v2.rs` | 微信支付 V2 集成 |
| `src/wxpay_v3.rs` | 微信支付 V3 集成 |
| `src/paypal.rs` | PayPal API 集成 |
| `src/stripe.rs` | Stripe API 集成 |
| `src/templates.rs` | 共用 HTML 模板与组件 |

### 环境要求

- 主要部署目标为 Linux x86_64。
- Rust toolchain 1.75 或更高版本。
- MySQL 5.7+ 或 MariaDB 10.3+。
- 用于支付平台回调和商户跳转的公网 HTTPS 地址。
- 推荐使用 Nginx、Caddy 或其他反向代理终结 TLS。
- 生产环境推荐使用 systemd 管理长期运行服务。
- 建议编译时至少提供 1 GB 内存；小内存 VPS 可能需要配置 swap，或在其他机器编译后上传二进制文件。

### 快速开始

```bash
# 克隆仓库并进入目录
git clone https://github.com/metasu/rpay.git
cd rpay

# 1. 初始化数据库（需要 MySQL/MariaDB）
#    有 root 权限（自动创建用户和数据库）：
ROOT_PASS=mysql_root密码 DB_PASS=rpay应用密码 ./scripts/init-db.sh
#    或 MySQL 用户已存在：
DB_PASS=你的数据库密码 ./scripts/init-db.sh

# 2. 创建数据库连接文件
echo -n "mysql://rpay:你的密码@127.0.0.1:3306/rpay" > /opt/services/rpay/secrets/database-url
chmod 600 /opt/services/rpay/secrets/database-url

# 3. 一键部署（编译、安装二进制、创建 systemd 服务、启动）
PUBLIC_URL=https://你的域名 ./scripts/deploy.sh

# 或手动方式：
cargo build --release
./target/release/rpay \
  --listen 127.0.0.1:16889 \
  --public-base-url https://你的域名 \
  --database-url-file /opt/services/rpay/secrets/database-url
```

初始化脚本 `scripts/init-db.sh` 会自动创建数据库、导入完整 29 表结构和种子数据，并生成随机 `syskey` 和管理员密码。

部署脚本 `scripts/deploy.sh` 会编译二进制、创建系统用户和目录、从 `deploy/rpay.service` 安装 systemd 服务并启动。

当前程序运行时配置通过命令行参数或 `RPAY_*` 环境变量提供。TOML 文件是示例/记录文件，不应被视为完整的运行时配置契约。

启动服务后：

1. 使用生成的管理员凭据登录 `/admin`。
2. 创建商户并生成独立的商户 API key。
3. 在管理后台配置支付平台凭据、回调地址和 Webhook。
4. 仅启用已完成测试的渠道。

完整生产部署指南（systemd、Nginx、SSL、WordPress 接入）请阅读 [`DEPLOY.md`](DEPLOY.md)。

### HTTP 接入流程

商户侧流程遵循易支付风格协议：

1. 商户创建或复用订单，并使用商户 key 对请求签名。
2. 商户向 `https://你的网关域名/submit.php` 发起 POST 请求。
3. rpay 验证签名、商户状态、支付方式和渠道状态。
4. rpay 创建或复用网关订单，并将用户引导至对应支付平台。
5. 支付平台同步返回用户，或向 rpay 发送异步通知。
6. rpay 验证支付平台响应，更新订单并通知商户。

重要入口包括：

- `/submit.php`：商户支付提交入口。
- `/notify/{provider}`：支付平台异步通知入口。
- `/return/{provider}`：用户同步返回入口。
- `/healthz`：本机和反向代理健康检查。
- `/admin`：管理后台。

具体请求字段和接入行为以源码及部署文档为准。生产环境必须使用 HTTPS，并确保支付平台可以访问配置好的回调地址。

### WordPress 接入

仓库包含针对 WordPress `erphpdown` 的 rpay 适配实现，包括支付入口、异步通知和同步返回处理、后台设置、重复订单保护，以及 `modown` 和 `monster8` 主题集成。

支持的 WordPress 支付类型：

| rpay type | erphpdown paytype | 支付平台 |
|---|---:|---|
| `alipay` | 141 | 支付宝 |
| `wxpay` | 142 | 微信支付 |
| `stripe` | 143 | Stripe |
| `paypal` | 144 | PayPal |

WordPress 适配层使用 MD5 对商户请求签名，并显式传递支付类型；收到 rpay 回调后先验签，再将 WordPress 订单标记为已支付。适配层还包含短时间重复提交折叠机制，并避免把不同支付类型的订单错误合并。

文件清单、后台配置、支付类型映射、回调设置和排错方法请参阅 [`DEPLOY.md`](DEPLOY.md) 的 WordPress 章节。

### 安全与生产注意事项

- 数据库连接串、支付平台密钥、私钥、Webhook secret、管理员凭据和商户 key 不得提交到 Git。
- 每个实例都要使用独立随机 `syskey`。修改后已有 Session 会失效。
- 公网地址必须使用 HTTPS，支付平台回调必须能访问配置好的公网地址。
- 必须导入完整数据库结构。只创建常用的四张表是不够的，程序依赖完整的易支付兼容表结构。
- `pay_channel.type` 必须对应 `pay_type.id`，且支付方式和实际渠道都必须处于启用状态。
- 使用 `--database-url-file` 时，数据库 URL 文件应设置为 600 权限，并且末尾不要有换行。
- 生产启用前先使用沙箱凭据验证支付流程和 Webhook 投递。
- 使用反向代理，并让服务以专用的非特权系统用户运行。
- 修改数据库结构、迁移支付平台或升级生产服务前先备份数据库。
- 支付合规、商户审核、税务、退款政策及支付平台条款遵守责任由部署运营方承担。

### 文档索引

- [`DEPLOY.md`](DEPLOY.md)：完整部署参考，当前主要以中文维护，包含数据库初始化、systemd、反向代理、PayPal、Stripe 和 WordPress 接入。
- [`PROGRESS.md`](PROGRESS.md)：项目进度和实现记录。
- [`Cargo.toml`](Cargo.toml)：Rust 版本、依赖和功能配置。
- [`config/config.example.toml`](config/config.example.toml)：监听地址和公网 URL 示例。
- [`database/schema.sql`](database/schema.sql)：完整数据库结构。
- [`database/seed.sql`](database/seed.sql)：非敏感初始化元数据和默认关闭的渠道模板。
- [`scripts/init-db.sh`](scripts/init-db.sh)：一键数据库初始化脚本。
- [`scripts/deploy.sh`](scripts/deploy.sh)：一键编译部署脚本。
- [`deploy/rpay.service`](deploy/rpay.service)：systemd 服务模板。

### 许可证

当前仓库未发现许可证文件。在版权方明确添加许可证之前，不应默认认为代码可以被再分发或在超出版权方授权范围的场景中使用。

## License / 许可证

No license file is currently present in the repository. / 当前仓库未发现许可证文件。

