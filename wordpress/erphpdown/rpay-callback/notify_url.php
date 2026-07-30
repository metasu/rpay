<?php
require_once('../../../../../wp-load.php');

function rpay_verify_sign($params, $key){
    ksort($params);
    $sign_str = '';
    foreach($params as $k => $v){
        if($v === '' || $k === 'sign' || $k === 'sign_type') continue;
        if($sign_str) $sign_str .= '&';
        $sign_str .= $k.'='.$v;
    }
    $expected = md5($sign_str . $key);
    return $expected === $params['sign'];
}

$rpay_key = trim(get_option('erphpdown_rpay_key'));
$rpay_pid = trim(get_option('erphpdown_rpay_id'));

if(!$rpay_key || !$rpay_pid){
    echo "fail";
    exit;
}

$params = $_GET;
if(empty($params) || empty($params['sign'])){
    $params = $_POST;
}

if(empty($params) || empty($params['sign'])){
    echo "fail";
    exit;
}

if(rpay_verify_sign($params, $rpay_key)){
    $out_trade_no = esc_sql($params['out_trade_no']);
    $trade_status = $params['trade_status'];
    $total_fee = esc_sql($params['money']);

    if($trade_status == 'TRADE_SUCCESS'){
        if(strstr($out_trade_no,'MD') || strstr($out_trade_no,'FK')){
            epd_set_wppay_success($out_trade_no,$total_fee,'rpay');
        }else{
            epd_set_order_success($out_trade_no,$total_fee,'rpay');
        }
    }
    echo "success";
}else{
    echo "fail";
}
?>
