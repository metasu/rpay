<?php
require_once('../../../../../wp-config.php');

function rpay_verify_sign_return($params, $key){
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

if(empty($_GET) || empty($_GET['sign'])){
    echo "fail";
    exit;
}

if(rpay_verify_sign_return($_GET, $rpay_key)){
    $trade_status = $_GET['trade_status'];

    if($trade_status == 'TRADE_SUCCESS'){
        $re = str_replace('#domain#', $_SERVER['HTTP_HOST'], get_option('erphp_url_front_success'));
        if(isset($_COOKIE['erphpdown_return']) && $_COOKIE['erphpdown_return']){
            $re = $_COOKIE['erphpdown_return'];
        }
        if($re)
            wp_redirect($re);
        else{
            echo 'success';
            exit;
        }
    }
    else {
        echo "trade_status=".$_GET['trade_status'];
    }
}
else {
    echo "fail";
}
?>
