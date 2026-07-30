/// Minimal shared HTML shell — no external CDN dependency, so both the admin
/// backend and merchant portal render correctly even with no internet egress.
pub fn page(title: &str, nav_html: &str, body_html: &str) -> String {
    format!(
        r#"<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<style>
:root {{ --brand:#2563eb; --bg:#f4f6f9; --card:#fff; --border:#e2e6ee; --text:#1f2430; --muted:#6b7280; }}
* {{ box-sizing: border-box; }}
body {{ margin:0; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; background:var(--bg); color:var(--text); }}
header.topbar {{ background:#111827; color:#fff; padding:0 24px; height:56px; display:flex; align-items:center; justify-content:space-between; }}
header.topbar .brand {{ font-weight:600; font-size:16px; }}
header.topbar nav a {{ color:#cbd5e1; text-decoration:none; margin-left:20px; font-size:14px; }}
header.topbar nav a:hover {{ color:#fff; }}
.wrap {{ max-width:1080px; margin:24px auto; padding:0 16px; }}
.card {{ background:var(--card); border:1px solid var(--border); border-radius:10px; padding:20px; margin-bottom:16px; }}
.grid {{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; }}
.stat {{ background:var(--card); border:1px solid var(--border); border-radius:10px; padding:16px; }}
.stat .n {{ font-size:26px; font-weight:700; color:var(--brand); }}
.stat .l {{ font-size:13px; color:var(--muted); margin-top:4px; }}
table {{ width:100%; border-collapse:collapse; font-size:14px; }}
th, td {{ padding:10px 12px; border-bottom:1px solid var(--border); text-align:left; }}
th {{ color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; }}
tr:hover td {{ background:#fafbfc; }}
.btn {{ display:inline-block; background:var(--brand); color:#fff; border:none; border-radius:6px; padding:8px 16px; font-size:14px; cursor:pointer; text-decoration:none; }}
.btn:hover {{ opacity:.9; }}
.btn.secondary {{ background:#e5e7eb; color:#111827; }}
.btn.danger {{ background:#dc2626; }}
.btn.sm {{ padding:4px 10px; font-size:12px; }}
input, select {{ padding:8px 10px; border:1px solid var(--border); border-radius:6px; font-size:14px; width:100%; }}
label {{ font-size:13px; color:var(--muted); display:block; margin-bottom:4px; margin-top:12px; }}
.form-row {{ margin-bottom:8px; }}
.badge {{ display:inline-block; padding:2px 8px; border-radius:99px; font-size:12px; font-weight:600; }}
.badge.ok {{ background:#dcfce7; color:#166534; }}
.badge.warn {{ background:#fef9c3; color:#854d0e; }}
.badge.bad {{ background:#fee2e2; color:#991b1b; }}
.msg {{ padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:14px; }}
.msg.error {{ background:#fee2e2; color:#991b1b; }}
.msg.ok {{ background:#dcfce7; color:#166534; }}
.center-box {{ max-width:380px; margin:80px auto; }}
.pager {{ margin-top:16px; display:flex; gap:8px; }}
h1 {{ font-size:20px; margin:0 0 16px; }}
h2 {{ font-size:16px; margin:0 0 12px; }}
a.link {{ color:var(--brand); text-decoration:none; }}
code.k {{ background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:13px; }}
</style>
</head>
<body>
{nav_html}
<div class="wrap">
{body_html}
</div>
</body>
</html>"#
    )
}

pub fn admin_nav(active: &str) -> String {
    let item = |href: &str, label: &str, key: &str| -> String {
        let cls = if key == active { " style=\"color:#fff;font-weight:600\"" } else { "" };
        format!("<a href=\"{href}\"{cls}>{label}</a>")
    };
    format!(
        r#"<header class="topbar">
<div class="brand">rpay 管理后台</div>
<nav>
{dashboard}{merchants}{orders}{channels}{settings}
<a href="/admin/logout">退出</a>
</nav>
</header>"#,
        dashboard = item("/admin", "概览", "dashboard"),
        merchants = item("/admin/merchants", "商户", "merchants"),
        orders = item("/admin/orders", "订单", "orders"),
        channels = item("/admin/channels", "支付渠道", "channels"),
        settings = item("/admin/settings", "系统设置", "settings"),
    )
}

pub fn portal_nav(active: &str) -> String {
    let item = |href: &str, label: &str, key: &str| -> String {
        let cls = if key == active { " style=\"color:#fff;font-weight:600\"" } else { "" };
        format!("<a href=\"{href}\"{cls}>{label}</a>")
    };
    format!(
        r#"<header class="topbar">
<div class="brand">商户中心</div>
<nav>
{dashboard}{orders}{settings}
<a href="/user/logout">退出</a>
</nav>
</header>"#,
        dashboard = item("/user", "概览", "dashboard"),
        orders = item("/user/orders", "订单", "orders"),
        settings = item("/user/settings", "密钥/设置", "settings"),
    )
}

pub fn escape(s: &str) -> String {
    s.replace('&', "&amp;")
        .replace('<', "&lt;")
        .replace('>', "&gt;")
        .replace('"', "&quot;")
}

/// Renders a scan-to-pay page: a QR code (inline SVG, no external requests)
/// plus JS polling of `/api/order` that redirects to `redirect_url` (which
/// already carries our own signed merchant-callback params) once paid.
pub fn qrcode_page(title: &str, qr_data: &str, trade_no: &str, redirect_url: &str) -> String {
    let svg = qrcode::QrCode::new(qr_data.as_bytes())
        .ok()
        .map(|code| {
            code.render::<qrcode::render::svg::Color>()
                .min_dimensions(260, 260)
                .build()
        })
        .unwrap_or_default();
    let body = format!(
        r#"<div class="center-box" style="text-align:center">
<div class="card">
<h1>请使用手机扫码支付</h1>
<div style="margin:20px 0">{svg}</div>
<p style="color:#6b7280;font-size:13px">支付完成后将自动跳转，请勿关闭此页面</p>
</div>
</div>
<script>
(function poll() {{
  fetch('/api/order?trade_no={trade_no}').then(r => r.json()).then(function(d) {{
    if (d.status === 1) {{ window.location.href = {redirect_url}; return; }}
    setTimeout(poll, 2000);
  }}).catch(function() {{ setTimeout(poll, 3000); }});
}})();
</script>"#,
        svg = svg,
        trade_no = escape(trade_no),
        redirect_url = serde_json::to_string(redirect_url).unwrap_or_else(|_| "\"\"".to_string()),
    );
    page(title, "", &body)
}

pub fn status_badge(status: i8) -> String {
    match status {
        1 => "<span class=\"badge ok\">已支付</span>".to_string(),
        0 => "<span class=\"badge warn\">待支付</span>".to_string(),
        2 => "<span class=\"badge bad\">已关闭</span>".to_string(),
        3 => "<span class=\"badge bad\">已退款</span>".to_string(),
        _ => "<span class=\"badge bad\">未知</span>".to_string(),
    }
}
