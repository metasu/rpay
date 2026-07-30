<?php
session_start();
$_SESSION['erphpdown_token']=md5(time().rand(100,999));
if(isset($_GET['redirect_url'])){
    $_COOKIE['erphpdown_return'] = urldecode($_GET['redirect_url']);
    setcookie('erphpdown_return',urldecode($_GET['redirect_url']),0,'/');
}else{
    $_COOKIE['erphpdown_return'] = '';
    setcookie('erphpdown_return','',0,'/');
}
require_once('../../../../wp-load.php');
header("Content-Type: text/html;charset=utf-8");
date_default_timezone_set('Asia/Shanghai');

$epd_order = _epd_create_page_order('rpay');
$price = $epd_order['price'];
$out_trade_no = $epd_order['trade_order_id'];
$subject = $epd_order['subject'];

$rpay_pid = trim(get_option('erphpdown_rpay_id'));
$rpay_key = trim(get_option('erphpdown_rpay_key'));
$rpay_url = trim(get_option('erphpdown_rpay_url'));

$notify_url = ERPHPDOWN_URL.'/payment/rpay/notify_url.php';
$return_url = ERPHPDOWN_URL.'/payment/rpay/return_url.php';

$type='alipay';
if(isset($_GET['type']) && $_GET['type']) $type = $_GET['type'];

$parameter = array(
    "pid" => $rpay_pid,
    "type" => $type,
    "notify_url"    => $notify_url,
    "return_url"    => $return_url,
    "out_trade_no"  => $out_trade_no,
    "name"  => $subject,
    "money" => $price,
);

ksort($parameter);
$sign_str = '';
foreach($parameter as $k => $v){
    if($v === '' || $k === 'sign' || $k === 'sign_type') continue;
    if($sign_str) $sign_str .= '&';
    $sign_str .= $k.'='.$v;
}
$sign = md5($sign_str . $rpay_key);
$parameter['sign'] = $sign;
$parameter['sign_type'] = 'MD5';

$submit_url = rtrim($rpay_url, '/') . '/submit.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>正在跳转...</title>
    <link rel="shortcut icon" href="<?php echo get_option('erphp_url_front_favicon');?>">
    <style>input{display:none}</style>
</head>
<form id='rpaysubmit' name='rpaysubmit' action='<?php echo $submit_url; ?>' method='POST'>
<?php foreach($parameter as $k => $v){ ?>
    <input type='hidden' name='<?php echo htmlspecialchars($k); ?>' value='<?php echo htmlspecialchars($v); ?>'>
<?php } ?>
</form>
<script>document.forms['rpaysubmit'].submit();</script>
</body>
</html>
