use std::{fs, net::SocketAddr, path::PathBuf};

use clap::Parser;
use rpay::{
    app, expire_pending_orders, retry_pending_notifications, session::SessionCodec, store::Store,
    AppState,
};
use sqlx::mysql::MySqlPoolOptions;

#[derive(Parser)]
#[command(
    name = "rpay",
    version,
    about = "Rust rewrite of the EasyPay PHP gateway"
)]
struct Cli {
    #[arg(
        long,
        env = "RPAY_DATABASE_URL_FILE",
        default_value = "/opt/services/rpay/secrets/database-url"
    )]
    database_url_file: PathBuf,
    #[arg(long, env = "RPAY_LISTEN", default_value = "127.0.0.1:16889")]
    listen: SocketAddr,
    #[arg(
        long,
        env = "RPAY_PUBLIC_BASE_URL",
        default_value = "https://yzf.anut.top"
    )]
    public_base_url: String,
}

#[tokio::main]
async fn main() -> Result<(), Box<dyn std::error::Error>> {
    tracing_subscriber::fmt()
        .with_env_filter(tracing_subscriber::EnvFilter::from_default_env())
        .init();

    let cli = Cli::parse();
    let database_url = fs::read_to_string(&cli.database_url_file)
        .map_err(|e| format!("failed to read {}: {e}", cli.database_url_file.display()))?
        .trim()
        .to_string();

    // Every pooled connection gets its own MySQL session. Force UTC+8 on each
    // connection instead of relying on the server global setting, which does
    // not update sessions that were opened before it changed.
    let pool = MySqlPoolOptions::new()
        .max_connections(10)
        .after_connect(|connection, _metadata| {
            Box::pin(async move {
                sqlx::query("SET time_zone = '+08:00'")
                    .execute(&mut *connection)
                    .await?;
                Ok(())
            })
        })
        .connect(&database_url)
        .await?;

    let (database_time_zone, database_now): (String, String) =
        sqlx::query_as("SELECT @@session.time_zone, CAST(NOW() AS CHAR)")
            .fetch_one(&pool)
            .await?;
    if database_time_zone != "+08:00" {
        return Err(format!(
            "MySQL session time zone verification failed: expected +08:00, got {database_time_zone}"
        )
        .into());
    }
    tracing::info!(
        mysql_session_time_zone = %database_time_zone,
        mysql_now = %database_now,
        "MySQL session time zone initialized"
    );

    let store = Store::new(pool);

    // Session cookies are signed with the platform's existing `syskey`
    // (already used by the legacy PHP app), so the signing secret survives
    // deploys/restarts without needing a new secret file.
    let syskey = store
        .config_get("syskey")
        .await?
        .ok_or("pay_config.syskey missing — is the database installed?")?;

    let state = AppState {
        store,
        public_base_url: cli.public_base_url.trim_end_matches('/').to_string(),
        session: SessionCodec::new(syskey.into_bytes()),
    };

    tokio::spawn(task_monitor(
        "retry_pending_notifications",
        retry_pending_notifications(state.clone()),
    ));
    tokio::spawn(task_monitor(
        "expire_pending_orders",
        expire_pending_orders(state.clone()),
    ));

    let router = app(state);
    let listener = tokio::net::TcpListener::bind(cli.listen).await?;
    tracing::info!(listen = %cli.listen, "rpay listening");
    axum::serve(listener, router)
        .with_graceful_shutdown(shutdown_signal())
        .await?;
    Ok(())
}

async fn shutdown_signal() {
    let _ = tokio::signal::ctrl_c().await;
}

async fn task_monitor<F>(name: &'static str, fut: F)
where
    F: std::future::Future<Output = ()> + Send + 'static,
{
    let handle = tokio::spawn(fut);
    if let Err(e) = handle.await {
        tracing::error!("task {name} panicked: {e}");
    }
}
