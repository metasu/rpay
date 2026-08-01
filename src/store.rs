use rand::Rng;
use sqlx::{MySqlPool, Row};
use thiserror::Error;

#[derive(Debug, Error)]
pub enum StoreError {
    #[error("not found")]
    NotFound,
    #[error("conflict: {0}")]
    Conflict(&'static str),
    #[error("database error")]
    Database(#[from] sqlx::Error),
}

#[derive(Clone, Debug)]
pub struct MerchantRow {
    pub uid: u64,
    pub gid: u64,
    pub key: String,
    pub status: i8,
    pub pay: i8,
    pub keytype: i8,
    pub publickey: Option<String>,
    pub pay_minmoney: Option<String>,
    pub pay_maxmoney: Option<String>,
}

#[derive(Clone, Debug)]
pub struct OrderRow {
    pub trade_no: String,
    pub out_trade_no: String,
    pub api_trade_no: Option<String>,
    pub uid: u64,
    pub type_id: u64,
    pub channel: u64,
    pub name: String,
    pub money: String,
    pub realmoney: Option<String>,
    pub notify_url: String,
    pub return_url: String,
    pub param: Option<String>,
    pub status: i8,
    pub payurl: Option<String>,
    pub buyer: Option<String>,
}

#[derive(Clone)]
pub struct Store {
    pool: MySqlPool,
}

impl Store {
    pub fn new(pool: MySqlPool) -> Self {
        Self { pool }
    }

    pub fn pool(&self) -> &MySqlPool {
        &self.pool
    }

    pub async fn merchant_by_pid(&self, pid: u64) -> Result<MerchantRow, StoreError> {
        let row = sqlx::query(
            "SELECT uid,gid,`key`,status,pay,keytype,publickey,pay_minmoney,pay_maxmoney FROM pay_user WHERE uid=? LIMIT 1",
        )
        .bind(pid)
        .fetch_optional(&self.pool)
        .await?
        .ok_or(StoreError::NotFound)?;
        Ok(MerchantRow {
            uid: row.try_get("uid")?,
            gid: row.try_get("gid")?,
            key: row.try_get("key")?,
            status: row.try_get("status")?,
            pay: row.try_get("pay")?,
            keytype: row.try_get("keytype")?,
            publickey: row.try_get("publickey")?,
            pay_minmoney: row.try_get("pay_minmoney")?,
            pay_maxmoney: row.try_get("pay_maxmoney")?,
        })
    }

    /// Resolve the payment `type` name (e.g. "alipay") to its type id, then
    /// pick the first enabled channel for it. Sufficient for a single-channel
    /// deployment; multi-channel routing/rate rules are not yet ported.
    pub async fn channel_for_type_name(&self, type_name: &str) -> Result<ChannelFullRow, StoreError> {
        let type_id: Option<u64> =
            sqlx::query_scalar("SELECT id FROM pay_type WHERE name=? AND status=1 LIMIT 1")
                .bind(type_name)
                .fetch_optional(&self.pool)
                .await?;
        let type_id = type_id.ok_or(StoreError::NotFound)?;
        let row = sqlx::query(
            "SELECT id,name,plugin,type,status,CAST(rate AS CHAR) AS rate,paymin,paymax,config FROM pay_channel WHERE type=? AND status=1 ORDER BY id LIMIT 1",
        )
        .bind(type_id)
        .fetch_optional(&self.pool)
        .await?
        .ok_or(StoreError::NotFound)?;
        Ok(ChannelFullRow {
            id: row.try_get("id")?,
            name: row.try_get("name")?,
            plugin: row.try_get("plugin")?,
            type_id: row.try_get("type")?,
            type_name: Some(type_name.to_string()),
            status: row.try_get("status")?,
            rate: row.try_get("rate")?,
            paymin: row.try_get("paymin")?,
            paymax: row.try_get("paymax")?,
            config: row.try_get("config")?,
        })
    }

    pub async fn find_existing_order(
        &self,
        uid: u64,
        out_trade_no: &str,
    ) -> Result<Option<OrderRow>, StoreError> {
        let row = sqlx::query(
            "SELECT trade_no,out_trade_no,api_trade_no,uid,type,channel,name,CAST(money AS CHAR) AS money,CAST(realmoney AS CHAR) AS realmoney,notify_url,return_url,param,status,payurl,buyer FROM pay_order WHERE uid=? AND out_trade_no=? LIMIT 1",
        )
        .bind(uid)
        .bind(out_trade_no)
        .fetch_optional(&self.pool)
        .await?;
        Ok(row.map(order_from_row).transpose()?)
    }

    pub async fn create_order(
        &self,
        uid: u64,
        out_trade_no: &str,
        name: &str,
        money: &str,
        notify_url: &str,
        return_url: &str,
        param: Option<&str>,
        client_ip: &str,
    ) -> Result<String, StoreError> {
        for _ in 0..5 {
            let trade_no = generate_trade_no();
            let result = sqlx::query(
                "INSERT INTO pay_order (trade_no,out_trade_no,uid,addtime,name,money,notify_url,return_url,param,ip,status,version,type,channel) VALUES (?,?,?,NOW(),?,?,?,?,?,?,0,0,0,0)",
            )
            .bind(&trade_no)
            .bind(out_trade_no)
            .bind(uid)
            .bind(name)
            .bind(money)
            .bind(notify_url)
            .bind(return_url)
            .bind(param)
            .bind(client_ip)
            .execute(&self.pool)
            .await;
            match result {
                Ok(_) => return Ok(trade_no),
                Err(sqlx::Error::Database(ref db)) if db.message().contains("Duplicate entry") => {
                    continue;
                }
                Err(e) => return Err(e.into()),
            }
        }
        Err(StoreError::Conflict("failed to allocate unique trade_no"))
    }

    pub async fn set_channel(
        &self,
        trade_no: &str,
        type_id: u64,
        channel_id: u64,
        realmoney: &str,
    ) -> Result<(), StoreError> {
        sqlx::query("UPDATE pay_order SET type=?,channel=?,realmoney=?,getmoney=? WHERE trade_no=?")
            .bind(type_id)
            .bind(channel_id)
            .bind(realmoney)
            .bind(realmoney)
            .bind(trade_no)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    pub async fn set_payurl(&self, trade_no: &str, payurl: &str) -> Result<(), StoreError> {
        sqlx::query("UPDATE pay_order SET payurl=? WHERE trade_no=?")
            .bind(payurl)
            .bind(trade_no)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    pub async fn order_by_trade_no(&self, trade_no: &str) -> Result<OrderRow, StoreError> {
        let row = sqlx::query(
            "SELECT trade_no,out_trade_no,api_trade_no,uid,type,channel,name,CAST(money AS CHAR) AS money,CAST(realmoney AS CHAR) AS realmoney,notify_url,return_url,param,status,payurl,buyer FROM pay_order WHERE trade_no=? LIMIT 1",
        )
        .bind(trade_no)
        .fetch_optional(&self.pool)
        .await?
        .ok_or(StoreError::NotFound)?;
        order_from_row(row)
    }

    /// Idempotently mark an order paid. Returns true if this call transitioned
    /// the order from unpaid to paid (i.e. the caller should send a merchant
    /// notification); returns false if it was already paid.
    pub async fn mark_paid(
        &self,
        trade_no: &str,
        api_trade_no: Option<&str>,
        buyer: Option<&str>,
    ) -> Result<bool, StoreError> {
        let mut tx = self.pool.begin().await?;
        let row = sqlx::query("SELECT status FROM pay_order WHERE trade_no=? FOR UPDATE")
            .bind(trade_no)
            .fetch_optional(&mut *tx)
            .await?
            .ok_or(StoreError::NotFound)?;
        let status: i8 = row.try_get("status")?;
        if status == 1 {
            tx.commit().await?;
            return Ok(false);
        }
        sqlx::query(
            "UPDATE pay_order SET status=1,endtime=NOW(),date=CURDATE(),api_trade_no=COALESCE(?,api_trade_no),buyer=COALESCE(?,buyer) WHERE trade_no=?",
        )
        .bind(api_trade_no)
        .bind(buyer)
        .bind(trade_no)
        .execute(&mut *tx)
        .await?;
        tx.commit().await?;
        Ok(true)
    }

    pub async fn record_notify_attempt(
        &self,
        trade_no: &str,
        success: bool,
    ) -> Result<(), StoreError> {
        if success {
            sqlx::query("UPDATE pay_order SET notify=1,notifytime=NOW() WHERE trade_no=?")
                .bind(trade_no)
                .execute(&self.pool)
                .await?;
        } else {
            sqlx::query(
                "UPDATE pay_order SET notify=notify+1,notifytime=NOW() WHERE trade_no=? AND notify<1",
            )
            .bind(trade_no)
            .execute(&self.pool)
            .await?;
        }
        Ok(())
    }

    /// Orders that are paid but not yet successfully notified, for background retry.
    pub async fn pending_notifications(&self, limit: i64) -> Result<Vec<OrderRow>, StoreError> {
        let rows = sqlx::query(
            "SELECT trade_no,out_trade_no,api_trade_no,uid,type,channel,name,CAST(money AS CHAR) AS money,CAST(realmoney AS CHAR) AS realmoney,notify_url,return_url,param,status,payurl,buyer FROM pay_order WHERE status=1 AND notify=0 AND (notifytime IS NULL OR notifytime < DATE_SUB(NOW(), INTERVAL 10 SECOND)) LIMIT ?",
        )
        .bind(limit)
        .fetch_all(&self.pool)
        .await?;
        rows.into_iter().map(order_from_row).collect()
    }

    pub async fn merchant_key(&self, uid: u64) -> Result<String, StoreError> {
        let key: Option<String> = sqlx::query_scalar("SELECT `key` FROM pay_user WHERE uid=? LIMIT 1")
            .bind(uid)
            .fetch_optional(&self.pool)
            .await?;
        key.ok_or(StoreError::NotFound)
    }

    pub async fn type_name(&self, type_id: u64) -> Result<String, StoreError> {
        let name: Option<String> =
            sqlx::query_scalar("SELECT name FROM pay_type WHERE id=? LIMIT 1")
                .bind(type_id)
                .fetch_optional(&self.pool)
                .await?;
        name.ok_or(StoreError::NotFound)
    }

    // ---- pay_config ----

    pub async fn config_get(&self, key: &str) -> Result<Option<String>, StoreError> {
        let v: Option<String> = sqlx::query_scalar("SELECT v FROM pay_config WHERE k=? LIMIT 1")
            .bind(key)
            .fetch_optional(&self.pool)
            .await?;
        Ok(v)
    }

    pub async fn config_set(&self, key: &str, value: &str) -> Result<(), StoreError> {
        sqlx::query("INSERT INTO pay_config (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=?")
            .bind(key)
            .bind(value)
            .bind(value)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    pub async fn config_many(&self, keys: &[&str]) -> Result<std::collections::HashMap<String, String>, StoreError> {
        let mut out = std::collections::HashMap::new();
        for key in keys {
            if let Some(v) = self.config_get(key).await? {
                out.insert(key.to_string(), v);
            }
        }
        Ok(out)
    }

    // ---- dashboard ----

    pub async fn dashboard_stats(&self) -> Result<DashboardStats, StoreError> {
        let merchant_count: i64 = sqlx::query_scalar("SELECT COUNT(*) FROM pay_user")
            .fetch_one(&self.pool)
            .await?;
        let order_count: i64 = sqlx::query_scalar("SELECT COUNT(*) FROM pay_order")
            .fetch_one(&self.pool)
            .await?;
        let paid_count_today: i64 = sqlx::query_scalar(
            "SELECT COUNT(*) FROM pay_order WHERE status=1 AND date=CURDATE()",
        )
        .fetch_one(&self.pool)
        .await?;
        let paid_amount_today: String = sqlx::query_scalar(
            "SELECT COALESCE(CAST(SUM(money) AS CHAR),'0.00') FROM pay_order WHERE status=1 AND date=CURDATE()",
        )
        .fetch_one(&self.pool)
        .await?;
        let paid_count_month: i64 = sqlx::query_scalar(
            "SELECT COUNT(*) FROM pay_order WHERE status=1 AND date>=DATE_FORMAT(CURDATE(),'%Y-%m-01')",
        )
        .fetch_one(&self.pool)
        .await?;
        let paid_amount_month: String = sqlx::query_scalar(
            "SELECT COALESCE(CAST(SUM(money) AS CHAR),'0.00') FROM pay_order WHERE status=1 AND date>=DATE_FORMAT(CURDATE(),'%Y-%m-01')",
        )
        .fetch_one(&self.pool)
        .await?;
        let paid_count_year: i64 = sqlx::query_scalar(
            "SELECT COUNT(*) FROM pay_order WHERE status=1 AND date>=DATE_FORMAT(CURDATE(),'%Y-01-01')",
        )
        .fetch_one(&self.pool)
        .await?;
        let paid_amount_year: String = sqlx::query_scalar(
            "SELECT COALESCE(CAST(SUM(money) AS CHAR),'0.00') FROM pay_order WHERE status=1 AND date>=DATE_FORMAT(CURDATE(),'%Y-01-01')",
        )
        .fetch_one(&self.pool)
        .await?;
        let paid_count_last_year: i64 = sqlx::query_scalar(
            "SELECT COUNT(*) FROM pay_order WHERE status=1 AND date>=DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR),'%Y-01-01') AND date<DATE_FORMAT(CURDATE(),'%Y-01-01')",
        )
        .fetch_one(&self.pool)
        .await?;
        let paid_amount_last_year: String = sqlx::query_scalar(
            "SELECT COALESCE(CAST(SUM(money) AS CHAR),'0.00') FROM pay_order WHERE status=1 AND date>=DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 YEAR),'%Y-01-01') AND date<DATE_FORMAT(CURDATE(),'%Y-01-01')",
        )
        .fetch_one(&self.pool)
        .await?;
        let monthly_rows = sqlx::query(
            "SELECT DATE_FORMAT(date,'%Y-%m') AS m, COUNT(*) AS c, COALESCE(CAST(SUM(money) AS CHAR),'0.00') AS a FROM pay_order WHERE status=1 AND date>=DATE_FORMAT(CURDATE(),'%Y-01-01') GROUP BY DATE_FORMAT(date,'%Y-%m') ORDER BY m",
        )
        .fetch_all(&self.pool)
        .await?;
        let monthly_stats = monthly_rows
            .into_iter()
            .map(|row| Ok(MonthlyStatsRow {
                month: row.try_get("m")?,
                count: row.try_get("c")?,
                amount: row.try_get("a")?,
            }))
            .collect::<Result<Vec<_>, StoreError>>()?;
        Ok(DashboardStats {
            merchant_count,
            order_count,
            paid_count_today,
            paid_amount_today,
            paid_count_month,
            paid_amount_month,
            paid_count_year,
            paid_amount_year,
            paid_count_last_year,
            paid_amount_last_year,
            monthly_stats,
        })
    }

    // ---- merchants (admin) ----

    pub async fn list_merchants(
        &self,
        offset: i64,
        limit: i64,
        search: Option<&str>,
    ) -> Result<Vec<MerchantListRow>, StoreError> {
        let rows = if let Some(q) = search {
            let like = format!("%{q}%");
            sqlx::query(
                "SELECT uid,account,username,CAST(money AS CHAR) AS money,status,pay,addtime FROM pay_user WHERE uid=? OR account LIKE ? OR username LIKE ? ORDER BY uid DESC LIMIT ? OFFSET ?",
            )
            .bind(q.parse::<u64>().unwrap_or(0))
            .bind(&like)
            .bind(&like)
            .bind(limit)
            .bind(offset)
            .fetch_all(&self.pool)
            .await?
        } else {
            sqlx::query(
                "SELECT uid,account,username,CAST(money AS CHAR) AS money,status,pay,addtime FROM pay_user ORDER BY uid DESC LIMIT ? OFFSET ?",
            )
            .bind(limit)
            .bind(offset)
            .fetch_all(&self.pool)
            .await?
        };
        rows.into_iter()
            .map(|row| {
                Ok(MerchantListRow {
                    uid: row.try_get("uid")?,
                    account: row.try_get("account")?,
                    username: row.try_get("username")?,
                    money: row.try_get("money")?,
                    status: row.try_get("status")?,
                    pay: row.try_get("pay")?,
                    addtime: row.try_get("addtime")?,
                })
            })
            .collect()
    }

    pub async fn count_merchants(&self, search: Option<&str>) -> Result<i64, StoreError> {
        let count = if let Some(q) = search {
            let like = format!("%{q}%");
            sqlx::query_scalar(
                "SELECT COUNT(*) FROM pay_user WHERE uid=? OR account LIKE ? OR username LIKE ?",
            )
            .bind(q.parse::<u64>().unwrap_or(0))
            .bind(&like)
            .bind(&like)
            .fetch_one(&self.pool)
            .await?
        } else {
            sqlx::query_scalar("SELECT COUNT(*) FROM pay_user")
                .fetch_one(&self.pool)
                .await?
        };
        Ok(count)
    }

    pub async fn merchant_detail(&self, uid: u64) -> Result<MerchantDetail, StoreError> {
        let row = sqlx::query(
            "SELECT uid,`key`,account,username,email,phone,CAST(money AS CHAR) AS money,status,pay,keytype,keylogin,pay_minmoney,pay_maxmoney,addtime,lasttime FROM pay_user WHERE uid=? LIMIT 1",
        )
        .bind(uid)
        .fetch_optional(&self.pool)
        .await?
        .ok_or(StoreError::NotFound)?;
        Ok(MerchantDetail {
            uid: row.try_get("uid")?,
            key: row.try_get("key")?,
            account: row.try_get("account")?,
            username: row.try_get("username")?,
            email: row.try_get("email")?,
            phone: row.try_get("phone")?,
            money: row.try_get("money")?,
            status: row.try_get("status")?,
            pay: row.try_get("pay")?,
            keytype: row.try_get("keytype")?,
            keylogin: row.try_get("keylogin")?,
            pay_minmoney: row.try_get("pay_minmoney")?,
            pay_maxmoney: row.try_get("pay_maxmoney")?,
            addtime: row.try_get("addtime")?,
            lasttime: row.try_get("lasttime")?,
        })
    }

    pub async fn update_merchant_status(
        &self,
        uid: u64,
        status: i8,
        pay: i8,
    ) -> Result<(), StoreError> {
        sqlx::query("UPDATE pay_user SET status=?,pay=? WHERE uid=?")
            .bind(status)
            .bind(pay)
            .bind(uid)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    pub async fn update_merchant_limits(
        &self,
        uid: u64,
        pay_minmoney: Option<&str>,
        pay_maxmoney: Option<&str>,
    ) -> Result<(), StoreError> {
        sqlx::query("UPDATE pay_user SET pay_minmoney=?,pay_maxmoney=? WHERE uid=?")
            .bind(pay_minmoney)
            .bind(pay_maxmoney)
            .bind(uid)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    pub async fn adjust_merchant_money(&self, uid: u64, delta: &str) -> Result<(), StoreError> {
        sqlx::query("UPDATE pay_user SET money=money+? WHERE uid=?")
            .bind(delta)
            .bind(uid)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    pub async fn set_merchant_password(&self, uid: u64, hashed_pwd: &str) -> Result<(), StoreError> {
        sqlx::query("UPDATE pay_user SET pwd=? WHERE uid=?")
            .bind(hashed_pwd)
            .bind(uid)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    pub async fn reset_merchant_key(&self, uid: u64) -> Result<String, StoreError> {
        let new_key = crate::protocol::generate_merchant_key();
        sqlx::query("UPDATE pay_user SET `key`=? WHERE uid=?")
            .bind(&new_key)
            .bind(uid)
            .execute(&self.pool)
            .await?;
        Ok(new_key)
    }

    pub async fn create_merchant(
        &self,
        account: &str,
        username: &str,
        email: Option<&str>,
        phone: Option<&str>,
        password: Option<&str>,
    ) -> Result<u64, StoreError> {
        let key = crate::protocol::generate_merchant_key();
        let pwd = password.map(|p| {
            use md5::Digest;
            let digest = md5::Md5::digest(p.as_bytes());
            format!("{:x}", digest)
        });
        let now = chrono::Local::now().naive_local();
        let result = sqlx::query(
            "INSERT INTO pay_user (`key`,account,username,email,phone,pwd,money,status,pay,keytype,keylogin,addtime) VALUES (?,?,?,?,?,?,0,1,1,0,1,?)",
        )
        .bind(&key)
        .bind(account)
        .bind(username)
        .bind(email)
        .bind(phone)
        .bind(pwd)
        .bind(now)
        .execute(&self.pool)
        .await?;
        Ok(result.last_insert_id() as u64)
    }

    pub async fn delete_merchant(&self, uid: u64) -> Result<bool, StoreError> {
        let result = sqlx::query("DELETE FROM pay_user WHERE uid=?")
            .bind(uid)
            .execute(&self.pool)
            .await?;
        Ok(result.rows_affected() > 0)
    }

    // ---- merchant auth / registration ----

    pub async fn find_merchant_by_account(
        &self,
        account: &str,
    ) -> Result<Option<MerchantAuthRow>, StoreError> {
        let row = sqlx::query(
            "SELECT uid,`key`,pwd,status,pay,keylogin FROM pay_user WHERE email=? OR phone=? LIMIT 1",
        )
        .bind(account)
        .bind(account)
        .fetch_optional(&self.pool)
        .await?;
        row.map(|row| {
            Ok(MerchantAuthRow {
                uid: row.try_get("uid")?,
                key: row.try_get("key")?,
                pwd: row.try_get("pwd")?,
                status: row.try_get("status")?,
                pay: row.try_get("pay")?,
                keylogin: row.try_get("keylogin")?,
            })
        })
        .transpose()
    }

    pub async fn expire_pending_orders(&self, minutes: i64) -> Result<u64, StoreError> {
        let sql = format!(
            "UPDATE pay_order SET status=2 WHERE status=0 AND addtime < DATE_SUB(NOW(), INTERVAL {} MINUTE)",
            minutes
        );
        let result = sqlx::query(&sql)
            .execute(&self.pool)
            .await?;
        Ok(result.rows_affected())
    }

    pub async fn find_merchant_by_uid_for_login(
        &self,
        uid: u64,
    ) -> Result<Option<MerchantAuthRow>, StoreError> {
        let row = sqlx::query(
            "SELECT uid,`key`,pwd,status,pay,keylogin FROM pay_user WHERE uid=? LIMIT 1",
        )
        .bind(uid)
        .fetch_optional(&self.pool)
        .await?;
        row.map(|row| {
            Ok(MerchantAuthRow {
                uid: row.try_get("uid")?,
                key: row.try_get("key")?,
                pwd: row.try_get("pwd")?,
                status: row.try_get("status")?,
                pay: row.try_get("pay")?,
                keylogin: row.try_get("keylogin")?,
            })
        })
        .transpose()
    }

    pub async fn account_taken(&self, account: &str) -> Result<bool, StoreError> {
        let count: i64 = sqlx::query_scalar("SELECT COUNT(*) FROM pay_user WHERE email=? OR phone=?")
            .bind(account)
            .bind(account)
            .fetch_one(&self.pool)
            .await?;
        Ok(count > 0)
    }

    /// Registers a new merchant. `account` is stored as email if it looks like
    /// one, otherwise as phone, matching legacy registration semantics closely
    /// enough for a fresh signup flow.
    pub async fn register_merchant(
        &self,
        account: &str,
        password: &str,
    ) -> Result<u64, StoreError> {
        let key = crate::protocol::generate_merchant_key();
        let is_email = account.contains('@');
        let mut tx = self.pool.begin().await?;
        let result = sqlx::query(
            "INSERT INTO pay_user (gid,upid,`key`,pwd,money,email,phone,cert,certtype,certmethod,level,pay,settle,keylogin,apply,mode,status,refund,transfer,keytype,open_code,voice_order,addtime) VALUES (0,0,?,'',0.00,?,?,0,0,0,1,1,1,1,0,0,1,1,0,0,0,0,NOW())",
        )
        .bind(&key)
        .bind(if is_email { Some(account) } else { None })
        .bind(if is_email { None } else { Some(account) })
        .execute(&mut *tx)
        .await?;
        let uid = result.last_insert_id();
        let hashed = crate::protocol::legacy_password_hash(password, &uid.to_string());
        sqlx::query("UPDATE pay_user SET pwd=? WHERE uid=?")
            .bind(&hashed)
            .bind(uid)
            .execute(&mut *tx)
            .await?;
        tx.commit().await?;
        Ok(uid)
    }

    // ---- orders (admin + merchant listing) ----

    pub async fn list_orders(
        &self,
        offset: i64,
        limit: i64,
        uid: Option<u64>,
        status: Option<i8>,
        search: Option<&str>,
        product: Option<&str>,
        channel_filter: Option<&str>,
        channel_exclude: bool,
    ) -> Result<Vec<OrderListRow>, StoreError> {
        let mut sql = String::from(
            "SELECT o.trade_no,o.out_trade_no,o.uid,o.name,CAST(o.money AS CHAR) AS money,o.status,o.addtime,o.domain,c.name AS channel_name,c.plugin AS channel_plugin FROM pay_order o LEFT JOIN pay_channel c ON c.id=o.channel WHERE 1=1",
        );
        if uid.is_some() {
            sql.push_str(" AND o.uid=?");
        }
        if status.is_some() {
            sql.push_str(" AND o.status=?");
        }
        if search.is_some() {
            sql.push_str(" AND (o.trade_no LIKE ? OR o.out_trade_no LIKE ?)");
        }
        if product.is_some() {
            sql.push_str(" AND o.name LIKE ?");
        }
        if channel_filter.is_some() {
            sql.push_str(if channel_exclude { " AND (c.plugin<>? AND c.name<>?)" } else { " AND (c.plugin=? OR c.name=?)" });
        }
        sql.push_str(" ORDER BY o.addtime DESC LIMIT ? OFFSET ?");
        let mut query = sqlx::query(&sql);
        if let Some(u) = uid {
            query = query.bind(u);
        }
        if let Some(s) = status {
            query = query.bind(s);
        }
        if let Some(s) = search {
            let like = format!("%{s}%");
            query = query.bind(like.clone()).bind(like);
        }
        if let Some(p) = product {
            query = query.bind(format!("%{p}%"));
        }
        if let Some(c) = channel_filter {
            query = query.bind(c).bind(c);
        }
        query = query.bind(limit).bind(offset);
        let rows = query.fetch_all(&self.pool).await?;
        rows.into_iter()
            .map(|row| {
                Ok(OrderListRow {
                    trade_no: row.try_get("trade_no")?,
                    out_trade_no: row.try_get("out_trade_no")?,
                    uid: row.try_get("uid")?,
                    name: row.try_get("name")?,
                    money: row.try_get("money")?,
                    status: row.try_get("status")?,
                    addtime: row.try_get("addtime")?,
                    domain: row.try_get("domain")?,
                    channel_name: row.try_get("channel_name")?,
                    channel_plugin: row.try_get("channel_plugin")?,
                })
            })
            .collect()
    }

    pub async fn count_orders(&self, uid: Option<u64>, status: Option<i8>, search: Option<&str>, product: Option<&str>, channel_filter: Option<&str>, channel_exclude: bool) -> Result<i64, StoreError> {
        let mut sql = String::from("SELECT COUNT(*) FROM pay_order o LEFT JOIN pay_channel c ON c.id=o.channel WHERE 1=1");
        if uid.is_some() { sql.push_str(" AND o.uid=?"); }
        if status.is_some() { sql.push_str(" AND o.status=?"); }
        if search.is_some() { sql.push_str(" AND (o.trade_no LIKE ? OR o.out_trade_no LIKE ?)"); }
        if product.is_some() { sql.push_str(" AND o.name LIKE ?"); }
        if channel_filter.is_some() { sql.push_str(if channel_exclude { " AND (c.plugin<>? AND c.name<>?)" } else { " AND (c.plugin=? OR c.name=?)" }); }
        let mut query = sqlx::query_scalar(&sql);
        if let Some(u) = uid { query = query.bind(u); }
        if let Some(s) = status { query = query.bind(s); }
        if let Some(s) = search { let like = format!("%{s}%"); query = query.bind(like.clone()).bind(like); }
        if let Some(p) = product { query = query.bind(format!("%{p}%")); }
        if let Some(c) = channel_filter { query = query.bind(c).bind(c); }
        let count: i64 = query.fetch_one(&self.pool).await?;
        Ok(count)
    }

    pub async fn order_detail(&self, trade_no: &str) -> Result<OrderDetailRow, StoreError> {
        let row = sqlx::query(
            "SELECT trade_no,out_trade_no,api_trade_no,uid,type,channel,name,CAST(money AS CHAR) AS money,CAST(realmoney AS CHAR) AS realmoney,CAST(getmoney AS CHAR) AS getmoney,CAST(profitmoney AS CHAR) AS profitmoney,CAST(refundmoney AS CHAR) AS refundmoney,notify_url,return_url,param,status,buyer,addtime,endtime,domain,ip,notify FROM pay_order WHERE trade_no=? LIMIT 1",
        )
        .bind(trade_no)
        .fetch_optional(&self.pool)
        .await?
        .ok_or(StoreError::NotFound)?;
        Ok(OrderDetailRow {
            trade_no: row.try_get("trade_no")?,
            out_trade_no: row.try_get("out_trade_no")?,
            api_trade_no: row.try_get("api_trade_no")?,
            uid: row.try_get("uid")?,
            type_id: row.try_get("type")?,
            channel: row.try_get("channel")?,
            name: row.try_get("name")?,
            money: row.try_get("money")?,
            realmoney: row.try_get("realmoney")?,
            getmoney: row.try_get("getmoney")?,
            profitmoney: row.try_get("profitmoney")?,
            refundmoney: row.try_get("refundmoney")?,
            notify_url: row.try_get("notify_url")?,
            return_url: row.try_get("return_url")?,
            param: row.try_get("param")?,
            status: row.try_get("status")?,
            buyer: row.try_get("buyer")?,
            addtime: row.try_get("addtime")?,
            endtime: row.try_get("endtime")?,
            domain: row.try_get("domain")?,
            ip: row.try_get("ip")?,
            notify_status: row.try_get("notify")?,
        })
    }

    pub async fn order_delete(&self, trade_no: &str) -> Result<bool, StoreError> {
        let result = sqlx::query("DELETE FROM pay_order WHERE trade_no=?")
            .bind(trade_no)
            .execute(&self.pool)
            .await?;
        Ok(result.rows_affected() > 0)
    }

    pub async fn batch_delete_orders(&self, trade_nos: &[String]) -> Result<u64, StoreError> {
        if trade_nos.is_empty() {
            return Ok(0);
        }
        let placeholders = trade_nos.iter().map(|_| "?").collect::<Vec<_>>().join(",");
        let sql = format!("DELETE FROM pay_order WHERE trade_no IN ({placeholders})");
        let mut query = sqlx::query(&sql);
        for tn in trade_nos {
            query = query.bind(tn);
        }
        let result = query.execute(&self.pool).await?;
        Ok(result.rows_affected())
    }

    pub async fn order_set_refunded(&self, trade_no: &str, refund_amount: &str) -> Result<bool, StoreError> {
        let result = sqlx::query(
            "UPDATE pay_order SET status=3, refundmoney=? WHERE trade_no=? AND status=1"
        )
        .bind(refund_amount)
        .bind(trade_no)
        .execute(&self.pool)
        .await?;
        Ok(result.rows_affected() > 0)
    }

    pub async fn order_update_status(&self, trade_no: &str, status: i8) -> Result<bool, StoreError> {
        let result = sqlx::query("UPDATE pay_order SET status=? WHERE trade_no=?")
            .bind(status)
            .bind(trade_no)
            .execute(&self.pool)
            .await?;
        Ok(result.rows_affected() > 0)
    }

    pub async fn order_stats(&self, uid: Option<u64>, start: Option<&str>, end: Option<&str>, product: Option<&str>, channel_filter: Option<&str>, channel_exclude: bool) -> Result<OrderStatsRow, StoreError> {
        let mut conds: Vec<&str> = Vec::new();
        if uid.is_some() { conds.push("o.uid=?"); }
        if start.is_some() { conds.push("o.date>=?"); }
        if end.is_some() { conds.push("o.date<=?"); }
        if product.is_some() { conds.push("o.name LIKE ?"); }
        if channel_filter.is_some() { conds.push(if channel_exclude { "(c.plugin<>? AND c.name<>?)" } else { "(c.plugin=? OR c.name=?)" }); }
        let cond = if conds.is_empty() { String::new() } else { format!("WHERE {}", conds.join(" AND ")) };
        let sql = format!(
            "SELECT COUNT(*) AS total,CAST(SUM(CASE WHEN o.status=1 THEN 1 ELSE 0 END) AS SIGNED) AS paid,CAST(SUM(CASE WHEN o.status=0 THEN 1 ELSE 0 END) AS SIGNED) AS unpaid,CAST(SUM(CASE WHEN o.status=2 THEN 1 ELSE 0 END) AS SIGNED) AS closed,CAST(SUM(CASE WHEN o.status=3 THEN 1 ELSE 0 END) AS SIGNED) AS refunded,COALESCE(CAST(SUM(o.money) AS CHAR),'0.00') AS total_amount,COALESCE(CAST(SUM(CASE WHEN o.status=1 THEN o.money ELSE 0 END) AS CHAR),'0.00') AS paid_amount,COALESCE(CAST(SUM(CASE WHEN o.status=1 THEN COALESCE(o.profitmoney,0) ELSE 0 END) AS CHAR),'0.00') AS profit_amount FROM pay_order o LEFT JOIN pay_channel c ON c.id=o.channel {cond}"
        );
        let mut query = sqlx::query(&sql);
        if let Some(u) = uid { query = query.bind(u); }
        if let Some(s) = start { query = query.bind(s); }
        if let Some(e) = end { query = query.bind(e); }
        if let Some(p) = product { query = query.bind(format!("%{p}%")); }
        if let Some(c) = channel_filter { query = query.bind(c).bind(c); }
        let row = query.fetch_one(&self.pool).await?;
        Ok(OrderStatsRow {
            total_count: row.try_get("total")?,
            paid_count: row.try_get("paid")?,
            unpaid_count: row.try_get("unpaid")?,
            closed_count: row.try_get("closed")?,
            refunded_count: row.try_get("refunded")?,
            total_amount: row.try_get("total_amount")?,
            paid_amount: row.try_get("paid_amount")?,
            profit_amount: row.try_get("profit_amount")?,
        })
    }

    pub async fn daily_stats(&self, start: Option<&str>, end: Option<&str>) -> Result<Vec<DailyStatsRow>, StoreError> {
        let mut conds: Vec<&str> = vec!["status=1"];
        if start.is_some() { conds.push("date>=?"); }
        if end.is_some() { conds.push("date<=?"); }
        let cond = conds.join(" AND ");
        let sql = format!(
            "SELECT CAST(date AS CHAR) AS d, COUNT(*) AS c, COALESCE(CAST(SUM(money) AS CHAR),'0.00') AS a FROM pay_order WHERE {cond} GROUP BY date ORDER BY date DESC"
        );
        let mut query = sqlx::query(&sql);
        if let Some(s) = start { query = query.bind(s); }
        if let Some(e) = end { query = query.bind(e); }
        let rows = query.fetch_all(&self.pool).await?;
        rows.into_iter()
            .map(|row| Ok(DailyStatsRow {
                date: row.try_get("d")?,
                count: row.try_get("c")?,
                amount: row.try_get("a")?,
            }))
            .collect()
    }

    // ---- channels (admin) ----

    pub async fn list_channels_full(&self) -> Result<Vec<ChannelFullRow>, StoreError> {
        let rows = sqlx::query(
            "SELECT A.id,A.name,A.plugin,A.type,A.status,CAST(A.rate AS CHAR) AS rate,A.paymin,A.paymax,A.config,B.name AS type_name FROM pay_channel A LEFT JOIN pay_type B ON A.type=B.id ORDER BY A.id",
        )
        .fetch_all(&self.pool)
        .await?;
        rows.into_iter()
            .map(|row| {
                Ok(ChannelFullRow {
                    id: row.try_get("id")?,
                    name: row.try_get("name")?,
                    plugin: row.try_get("plugin")?,
                    type_id: row.try_get("type")?,
                    type_name: row.try_get("type_name")?,
                    status: row.try_get("status")?,
                    rate: row.try_get("rate")?,
                    paymin: row.try_get("paymin")?,
                    paymax: row.try_get("paymax")?,
                    config: row.try_get("config")?,
                })
            })
            .collect()
    }

    pub async fn channel_detail(&self, id: u64) -> Result<ChannelFullRow, StoreError> {
        let row = sqlx::query(
            "SELECT A.id,A.name,A.plugin,A.type,A.status,CAST(A.rate AS CHAR) AS rate,A.paymin,A.paymax,A.config,B.name AS type_name FROM pay_channel A LEFT JOIN pay_type B ON A.type=B.id WHERE A.id=? LIMIT 1",
        )
        .bind(id)
        .fetch_optional(&self.pool)
        .await?
        .ok_or(StoreError::NotFound)?;
        Ok(ChannelFullRow {
            id: row.try_get("id")?,
            name: row.try_get("name")?,
            plugin: row.try_get("plugin")?,
            type_id: row.try_get("type")?,
            type_name: row.try_get("type_name")?,
            status: row.try_get("status")?,
            rate: row.try_get("rate")?,
            paymin: row.try_get("paymin")?,
            paymax: row.try_get("paymax")?,
            config: row.try_get("config")?,
        })
    }

    pub async fn update_channel(
        &self,
        id: u64,
        status: i8,
        rate: &str,
        paymin: Option<&str>,
        paymax: Option<&str>,
        config: &str,
    ) -> Result<(), StoreError> {
        sqlx::query("UPDATE pay_channel SET status=?,rate=?,paymin=?,paymax=?,config=? WHERE id=?")
            .bind(status)
            .bind(rate)
            .bind(paymin)
            .bind(paymax)
            .bind(config)
            .bind(id)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    pub async fn list_types(&self) -> Result<Vec<(u64, String, i8)>, StoreError> {
        let rows = sqlx::query("SELECT id,name,status FROM pay_type ORDER BY id")
            .fetch_all(&self.pool)
            .await?;
        rows.into_iter()
            .map(|row| Ok((row.try_get("id")?, row.try_get("name")?, row.try_get("status")?)))
            .collect()
    }
}

#[derive(Clone, Debug)]
pub struct DashboardStats {
    pub merchant_count: i64,
    pub order_count: i64,
    pub paid_count_today: i64,
    pub paid_amount_today: String,
    pub paid_count_month: i64,
    pub paid_amount_month: String,
    pub paid_count_year: i64,
    pub paid_amount_year: String,
    pub paid_count_last_year: i64,
    pub paid_amount_last_year: String,
    pub monthly_stats: Vec<MonthlyStatsRow>,
}

#[derive(Clone, Debug)]
pub struct MonthlyStatsRow {
    pub month: String,
    pub count: i64,
    pub amount: String,
}

#[derive(Clone, Debug)]
pub struct MerchantListRow {
    pub uid: u64,
    pub account: Option<String>,
    pub username: Option<String>,
    pub money: String,
    pub status: i8,
    pub pay: i8,
    pub addtime: Option<chrono::NaiveDateTime>,
}

#[derive(Clone, Debug)]
pub struct MerchantDetail {
    pub uid: u64,
    pub key: String,
    pub account: Option<String>,
    pub username: Option<String>,
    pub email: Option<String>,
    pub phone: Option<String>,
    pub money: String,
    pub status: i8,
    pub pay: i8,
    pub keytype: i8,
    pub keylogin: i8,
    pub pay_minmoney: Option<String>,
    pub pay_maxmoney: Option<String>,
    pub addtime: Option<chrono::NaiveDateTime>,
    pub lasttime: Option<chrono::NaiveDateTime>,
}

#[derive(Clone, Debug)]
pub struct MerchantAuthRow {
    pub uid: u64,
    pub key: String,
    pub pwd: Option<String>,
    pub status: i8,
    pub pay: i8,
    pub keylogin: i8,
}

#[derive(Clone, Debug)]
pub struct OrderListRow {
    pub trade_no: String,
    pub out_trade_no: String,
    pub uid: u64,
    pub name: String,
    pub money: String,
    pub status: i8,
    pub addtime: Option<chrono::NaiveDateTime>,
    pub domain: Option<String>,
    pub channel_name: Option<String>,
    pub channel_plugin: Option<String>,
}

#[derive(Clone, Debug)]
pub struct OrderDetailRow {
    pub trade_no: String,
    pub out_trade_no: String,
    pub api_trade_no: Option<String>,
    pub uid: u64,
    pub type_id: u64,
    pub channel: u64,
    pub name: String,
    pub money: String,
    pub realmoney: Option<String>,
    pub getmoney: Option<String>,
    pub profitmoney: Option<String>,
    pub refundmoney: Option<String>,
    pub notify_url: String,
    pub return_url: String,
    pub param: Option<String>,
    pub status: i8,
    pub buyer: Option<String>,
    pub addtime: Option<chrono::NaiveDateTime>,
    pub endtime: Option<chrono::NaiveDateTime>,
    pub domain: Option<String>,
    pub ip: Option<String>,
    pub notify_status: i64,
}

#[derive(Clone, Debug)]
pub struct OrderStatsRow {
    pub total_count: i64,
    pub paid_count: i64,
    pub unpaid_count: i64,
    pub closed_count: i64,
    pub refunded_count: i64,
    pub total_amount: String,
    pub paid_amount: String,
    pub profit_amount: String,
}

#[derive(Clone, Debug)]
pub struct DailyStatsRow {
    pub date: String,
    pub count: i64,
    pub amount: String,
}

#[derive(Clone, Debug)]
pub struct ChannelFullRow {
    pub id: u64,
    pub name: String,
    pub plugin: String,
    pub type_id: u64,
    pub type_name: Option<String>,
    pub status: i8,
    pub rate: String,
    pub paymin: Option<String>,
    pub paymax: Option<String>,
    pub config: Option<String>,
}

fn order_from_row(row: sqlx::mysql::MySqlRow) -> Result<OrderRow, StoreError> {
    Ok(OrderRow {
        trade_no: row.try_get("trade_no")?,
        out_trade_no: row.try_get("out_trade_no")?,
        api_trade_no: row.try_get("api_trade_no")?,
        uid: row.try_get("uid")?,
        type_id: row.try_get("type")?,
        channel: row.try_get("channel")?,
        name: row.try_get("name")?,
        money: row.try_get("money")?,
        realmoney: row.try_get("realmoney")?,
        notify_url: row.try_get("notify_url")?,
        return_url: row.try_get("return_url")?,
        param: row.try_get("param")?,
        status: row.try_get("status")?,
        payurl: row.try_get("payurl")?,
        buyer: row.try_get("buyer")?,
    })
}

/// Matches legacy PHP `date("YmdHis").rand(11111,99999)`.
fn generate_trade_no() -> String {
    let now = chrono::Local::now().format("%Y%m%d%H%M%S");
    let suffix: u32 = rand::thread_rng().gen_range(11111..99999);
    format!("{now}{suffix}")
}
