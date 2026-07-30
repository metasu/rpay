<?php 
if (!session_id()) session_start();
require( dirname(__FILE__) . '/../../../../wp-load.php' );
require THEME_DIR.'/inc/sms/index.php';
use Qcloud\Sms\SmsSingleSender;
date_default_timezone_set('Asia/Shanghai');
global $wpdb;
if ( is_user_logged_in() ) { 
	global $current_user; 
	$uid = $current_user->ID;
	
	if($_POST['action']=='user.edit'){
		$userdata = array();
		$userdata['ID'] = $uid;
		$userdata['nickname'] = str_replace(array('<','>','&','"','\'','#','^','*','_','+','$','?','!'), '', esc_sql(trim($_POST['nickname'])) );
		$userdata['display_name'] = str_replace(array('<','>','&','"','\'','#','^','*','_','+','$','?','!'), '', esc_sql(trim($_POST['nickname'])) );
		$userdata['description'] = esc_sql(trim($_POST['description']));
		wp_update_user($userdata);
		update_user_meta($uid, 'qq', esc_sql(trim($_POST['qq'])) );
		$error = 0;	$msg = '';

		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action']=='user.email'){
		$user_email = apply_filters( 'user_registration_email', esc_sql(trim($_POST['email'])) );
		$user_email = strtolower($user_email);
		$error = 0;$msg = '';
			
		if(_MBT('register_email_suffix') && is_email( $user_email )){
		  	$email_suffixs=explode("\r\n",trim(strtolower(_MBT('register_email_suffix'))));
		  	$email_domain = explode('@', strtolower($user_email));
		  	if( in_array($email_domain[1], $email_suffixs) ){
		  		$error = 1;
		  		$msg = __('暂不支持此域名邮箱后缀','mobantu');
		  	}
		}

		if(_MBT('register_email_suffix2') && is_email( $user_email )){
		  	$email_suffixs2=explode("\r\n",trim(strtolower(_MBT('register_email_suffix2'))));
		  	$email_domain = explode('@', strtolower($user_email));
		  	if( !in_array($email_domain[1], $email_suffixs2) ){
		  		$error = 1;
		  		$msg = __('暂不支持此域名邮箱后缀','mobantu');
		  	}
		}

		if(!$error){
			if ( $user_email == '' ) {
				$error = 1;
				$msg = __('请输入邮箱地址','mobantu');
			} elseif ( $user_email == $current_user->user_email) {
				$error = 1;
				$msg = __('请输入一个新邮箱地址','mobantu');
			}elseif ( email_exists( $user_email ) && $user_email != $current_user->user_email) {
				$error = 1;
				$msg = __('此邮箱已被注册，请换一个','mobantu');
			}else{
				if(empty($_POST['captcha']) || empty($_SESSION['MBT_email_captcha']) || trim(strtolower($_POST['captcha'])) != $_SESSION['MBT_email_captcha']){
					$error = 1;
					$msg .= __('验证码错误','mobantu');
				}elseif($_SESSION['MBT_email_new'] != $user_email){
					$error = 1;
					$msg = __('邮箱与验证码不对应','mobantu');
				}else{
					unset($_SESSION['MBT_email_captcha']);
					unset($_SESSION['MBT_email_new']);
					$userdata = array();
					$userdata['ID'] = $uid;
					$userdata['user_email'] = $user_email;
					wp_update_user($userdata);
					$error = 0;	
				}
			}
		}
		
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action']=='user.mobile'){
		$mobile = esc_sql(trim($_POST['mobile']));
		$captcha = trim($_POST['captcha']);
		$error = 0;$msg = '';
		if(MBThemes_is_phone($mobile)){
			if(empty($captcha) || empty($_SESSION['MBT_mobile_captcha']) || trim(strtolower($captcha)) != $_SESSION['MBT_mobile_captcha'] || $mobile != $_SESSION['MBT_captcha_mobile'] || time() > $_SESSION['MBT_captcha_mobile_time']){
				$error = 1;
			  	$msg = __('验证码错误','mobantu');
			}else{
				$mobile_id = $wpdb->get_var( $wpdb->prepare(
		            "SELECT ID FROM $wpdb->users WHERE mobile = %s",
		            $mobile ) );
				
				if ( $mobile_id == $current_user->ID) {
					$error = 1;
					$msg = __('请输入一个新手机号','mobantu');
				} elseif ( $mobile_id && $mobile_id != $current_user->ID) {
					$error = 1;
					$msg = __('此手机号已被注册，请换一个','mobantu');
				}else{
					$ff = $wpdb->update( $wpdb->users, array('mobile' => $mobile), array("ID"=>$current_user->ID) );
					if($ff){
						unset($_SESSION['MBT_mobile_captcha']);
						unset($_SESSION['MBT_captcha_mobile']);
						unset($_SESSION['MBT_captcha_mobile_time']);
					}
				}
			}
		}else{
			$error = 1;
			$msg = __('手机号格式错误','mobantu');
		}
		
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action']=='user.email.captcha'){
		$user_email = apply_filters( 'user_registration_email', esc_sql(trim($_POST['email'])) );
		$user_email = strtolower($user_email);
		$error = 0;$msg = '';

		if(_MBT('register_email_suffix') && is_email( $user_email )){
		  	$email_suffixs=explode("\r\n",trim(strtolower(_MBT('register_email_suffix'))));
		  	$email_domain = explode('@', strtolower($user_email));
		  	if( in_array($email_domain[1], $email_suffixs) ){
		  		$error = 1;
		  		$msg = __('暂不支持此域名邮箱后缀','mobantu');
		  	}
		}

		if(_MBT('register_email_suffix2') && is_email( $user_email )){
		  	$email_suffixs2=explode("\r\n",trim(strtolower(_MBT('register_email_suffix2'))));
		  	$email_domain = explode('@', strtolower($user_email));
		  	if( !in_array($email_domain[1], $email_suffixs2) ){
		  		$error = 1;
		  		$msg = __('暂不支持此域名邮箱后缀','mobantu');
		  	}
		}

		if(!$error){
			if ( $user_email == '' ) {
				$error = 1;
				$msg = __('请输入邮箱地址','mobantu');
			} elseif ( $user_email == $current_user->user_email) {
				$error = 1;
				$msg = __('请输入一个新邮箱地址','mobantu');
			} elseif ( email_exists( $user_email ) && $user_email != $current_user->user_email) {
				$error = 1;
				$msg = __('此邮箱已被注册，请换一个','mobantu');
			}else{
				
				$originalcode = '0,1,2,3,4,5,6,7,8,9';
				$originalcode = explode(',',$originalcode);
				$countdistrub = 10;
				$_dscode = "";
				$counts=6;
				for($j=0;$j<$counts;$j++){
					$dscode = $originalcode[rand(0,$countdistrub-1)];
					$_dscode.=$dscode;
				}
				$_SESSION['MBT_email_captcha']=strtolower($_dscode);
				$_SESSION['MBT_email_new']=$user_email;
				$message = __('验证码：','mobantu').$_dscode;   
				wp_mail($user_email, '['.get_bloginfo('name').']'.__('验证码','mobantu'), $message);    
				$error = 0;	
			}
		}
		
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action']=='user.mobile.captcha'){
		$user_mobile = esc_sql(trim($_POST['mobile']));
		$error = 0;$msg = '';
		if(MBThemes_is_phone($user_mobile)){
			$mobile_id = $wpdb->get_var( $wpdb->prepare(
		            "SELECT ID FROM $wpdb->users WHERE mobile = %s",
		            $user_mobile ) );
			if ( $mobile_id == $current_user->ID) {
				$error = 1;
				$msg = __('请输入一个新手机号','mobantu');
			} elseif ( $mobile_id && $mobile_id != $current_user->ID) {
				$error = 1;
				$msg = __('此手机号已被注册，请换一个','mobantu');
			}else{
				if(_MBT('oauth_sms_select') == 'juhe'){
					$code = rand(100000, 999999);
					$apiUrl = 'http://v.juhe.cn/sms/send?';
					$params = [
					    'tpl_id' => _MBT('oauth_juhe_sms_temp'),
					    'key' => _MBT('oauth_juhe_sms_key'),
					    'mobile' => $user_mobile,
					    'vars' => '{"code":'.$code.'}'
					    //'tpl_value' => urlencode('#code#='.$code),
					];
					$paramsString = http_build_query($params);

					// 发起接口网络请求
					$response = null;
					try {
					    $response = juheHttpRequest($apiUrl, $paramsString, 1);
					} catch (Exception $e) {
					    //var_dump($e);
					    $error = 1;
						$msg = __('系统超时，请稍后重试','mobantu');
					    //此处根据自己的需求进行自身的异常处理
					}
					if (!$response) {
						$error = 1;
					    $msg = "请求异常" . PHP_EOL;
					}
					$result = json_decode($response, true);
					if (!$result) {
						$error = 1;
					    $msg = "请求异常" . PHP_EOL;
					}
					$errorCode = $result['error_code'];
					if ($errorCode === 0) {
					    $_SESSION['MBT_mobile_captcha']=$code;
		                $_SESSION['MBT_captcha_mobile']=$user_mobile;
		                $_SESSION['MBT_captcha_mobile_time']=strtotime("+5 minutes");
		                //$data = $result['result'];
					    //echo "请求唯一标示：{$data["sid"]}" . PHP_EOL;
					    //echo "请求消耗次数：{$data["fee"]}" . PHP_EOL;
					} else {
					    // 请求异常
					    $error = 1;
					    $msg = "请求异常:{$errorCode}_{$result["reason"]}" . PHP_EOL;
					}
				}elseif(_MBT('oauth_sms_select') == 'qcloud'){
					$phoneNumbers = [$user_mobile];

					try {
					    $ssender = new SmsSingleSender(_MBT('oauth_qcloud_access_id'), _MBT('oauth_qcloud_access_secret'));
					    $params = [$code];
					    $result = $ssender->sendWithParam("86", $phoneNumbers[0], _MBT('oauth_qcloud_sms_temp'),
					        $params, _MBT('oauth_qcloud_sms_sign'), "", "");  // 签名参数未提供或者为空时，会使用默认签名发送短信
					    $rsp = json_decode($result, true);

					    if($rsp['result'] == '0'){
						    $_SESSION['MBT_mobile_captcha']=$code;
				            $_SESSION['MBT_captcha_mobile']=$user_mobile;
				            $_SESSION['MBT_captcha_mobile_time']=strtotime("+5 minutes");
				            
				        }else{
				        	$error = 1;
				        	$msg = $rsp['errmsg'];
				        }
					    
					} catch(\Exception $e) {
					    //echo var_dump($e);
					    $error = 1;
					    $msg = '发送失败，请稍后重试';
					}
	            }else{
					$config = [
		                'accessKeyId' => _MBT('oauth_aliyun_access_id'),                
		                'accessKeySecret' => _MBT('oauth_aliyun_access_secret'),           
		                'signName' => _MBT('oauth_aliyun_sms_sign'), 
		                'templateCode' => _MBT('oauth_aliyun_sms_temp')            
		            ];
		            $code = rand(1000, 9999);    
		            $sms = new \Sms($config);
		            $status = $sms->send_verify($user_mobile, $code);  
		            if (!$status) {
		                //echo $sms->error;
		                $error = 1;
						$msg = $sms->error;
		            } else {
		                $_SESSION['MBT_mobile_captcha']=$code;
		                $_SESSION['MBT_captcha_mobile']=$user_mobile;
		                $_SESSION['MBT_captcha_mobile_time']=strtotime("+5 minutes");
		            }
		        }
			}
		}else{
			$error = 1;
			$msg = __('手机号格式错误','mobantu');
		}
		
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action']=='user.idcard'){
		$error = 0;$msg = '';
		$name = trim($_POST['name']);
		$idcard = trim($_POST['idcard']);
		$nonce = $_POST['nonce'];
		if(_MBT('user_idcard_verify') && isset($_SESSION['verify_user_nonce']) && $_SESSION['verify_user_nonce'] && $nonce == $_SESSION['verify_user_nonce']){
			if($name && $idcard){
				$verify_user = get_user_meta($current_user->ID,'verify_user',true);
				$verify_error = get_user_meta($current_user->ID,'verify_error',true);
				if($verify_error){
					$error = 1;
					$msg = __('抱歉，您暂时无法进行实名认证，请联系网站客服','mobantu');
				}elseif($verify_user){
					$error = 1;
					$msg = __('系统超时，请稍后重试','mobantu');
				}else{
					$host = "https://dfidveri.market.alicloudapi.com";
				    $path = "/verify_id_name";
				    $method = "POST";
				    $appcode = _MBT('user_idcard_verify_appcode');
				    $headers = array();
				    array_push($headers, "Authorization:APPCODE " . $appcode);
				    //根据API的要求，定义相对应的Content-Type
				    array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
				    $querys = "";
				    $bodys = "id_number=".$idcard."&name=".$name;
				    $url = $host . $path;

				    $curl = curl_init();
				    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
				    curl_setopt($curl, CURLOPT_URL, $url);
				    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
				    //curl_setopt($curl, CURLOPT_FAILONERROR, false);
				    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				    //curl_setopt($curl, CURLOPT_HEADER, true);
				    if (1 == strpos("$".$host, "https://"))
				    {
				        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
				        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
				    }
				    curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
				    $ret = curl_exec($curl);
				    curl_close($curl);
				    $output = json_decode($ret);
				    if($output){
				        if($output->state == '1'){
				            $msg = __('认证成功','mobantu');
				            update_user_meta($current_user->ID,'verify_user',1);
				            update_user_meta($current_user->ID,'verify_name',$name);
				            update_user_meta($current_user->ID,'verify_idcard',$idcard);
				        }else{
				            $error = 1;
							$msg = __('认证失败，请检查信息是否输入正确','mobantu');
							update_user_meta($current_user->ID,'verify_error',1);
				        }
				    }else{
				        $error = 1;
						$msg = __('系统超时，请稍后重试','mobantu');
				    }
				}
			}else{
				$error = 1;
				$msg = __('请输入正确的姓名与身份证','mobantu');
			}
		}else{
			$error = 1;
			$msg = __('系统超时，请稍后重试','mobantu');
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action']=='user.password'){
		$error = 0;$msg = '';
		
		$userdata = array();
		$userdata['ID'] = $current_user->ID;
		$userdata['user_pass'] = $_POST['password'];
		$result = wp_update_user($userdata);
		if ( is_wp_error( $result ) ) {
			$error = 1;
			$msg = __('修改失败','mobantu');
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($_POST['action']=='user.charge.submit'){
		$error = 0;$iframe = 0;$url = '';
		$paytype = $_POST['paytype'];

		if(_MBT('recharge_iframe')){
			if($paytype=='2'){
				$iframe = 1;
				$url=constant("erphpdown")."payment/f2fpay.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
			}elseif($paytype=='3'){
				if(erphpdown_is_weixin() && get_option('ice_weixin_app')){
					$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.get_option('ice_weixin_appid').'&redirect_uri='.urlencode(constant("erphpdown")).'payment%2Fweixin.php%3Fice_money%3D'.$_POST['ice_money'].'&response_type=code&scope=snsapi_base&state=STATE&connect_redirect=1#wechat_redirect';
				}else{
					$iframe = 1;
					$url=constant("erphpdown")."payment/weixin.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
				}
			}elseif($paytype=='52'){
				$iframe = 1;
				$url=constant("erphpdown")."payment/paypy.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
			}elseif($paytype=='51'){
				$iframe = 1;
				$url=constant("erphpdown")."payment/paypy.php?ice_money=".$_POST['ice_money']."&type=alipay"."&timestamp=".time();
			}elseif($paytype=='61'){
				$iframe = 1;
				$url=constant("erphpdown")."payment/xhpay3.php?ice_money=".$_POST['ice_money']."&type=2"."&timestamp=".time();
			}elseif($paytype=='62'){
				$iframe = 1;
				$url=constant("erphpdown")."payment/xhpay3.php?ice_money=".$_POST['ice_money']."&type=1"."&timestamp=".time();
			}elseif($paytype=='92'){
				$iframe = 1;
				$url=constant("erphpdown")."payment/payjs.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
			}elseif($paytype=='91'){
				$iframe = 1;
				$url=constant("erphpdown")."payment/payjs.php?ice_money=".$_POST['ice_money']."&type=alipay"."&timestamp=".time();
			}elseif($paytype=='102'){
				$iframe = 1;
				$url=constant("erphpdown")."payment/vpay.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
			}elseif($paytype=='101'){
				$iframe = 1;
				$url=constant("erphpdown")."payment/vpay.php?ice_money=".$_POST['ice_money']."&type=2"."&timestamp=".time();
			}

			if(erphpdown_is_mobile()){
				$iframe = 0;
			}
		}

		$arr=array(
			"error"=>$error, 
			"iframe"=>$iframe,
			"url"=>$url
		); 
		
		$jarr=json_encode($arr); 
		echo $jarr;

	}elseif($_POST['action']=='user.vip.credit'){
		//$erphp_vip_name  = get_option('erphp_vip_name')?get_option('erphp_vip_name'):'VIP';
		$erphp_life_name    = get_option('erphp_life_name')?get_option('erphp_life_name'):'终身VIP';
		$erphp_super_name    = get_option('erphp_super_name')?get_option('erphp_super_name'):'全站通VIP';
		$error = 0;$msg = '';$link = get_permalink(MBThemes_page("template/user.php"));$payment = '';
		if(get_option('erphp_url_front_recharge')){
			$link = get_option('erphp_url_front_recharge');
		}

		if(_MBT('vip_only_pay')){
			$error = 1;$msg = __('抱歉，仅支持在线支付升级','mobantu');
		}else{
			$userType=isset($_POST['userType']) && is_numeric($_POST['userType']) ?intval($_POST['userType']) :0;
			$oldUserType = getUsreMemberTypeById($uid);
		    if(get_option('vip_update_down') && $oldUserType && $oldUserType > $userType){
				$error = 1; $msg = __('抱歉，暂不允许向下升级续费','mobantu');
			}elseif($oldUserType == '11' && !get_option('erphp_life_days')){
				$error = 1;$msg = sprintf( __('您已经是%s，请勿重复升级','mobantu'), $erphp_super_name);
			}elseif($oldUserType == '10' && $userType < 11 && !get_option('erphp_life_days')){
				$error = 1;$msg = sprintf( __('您已经是%s，请勿重复升级','mobantu'), $erphp_life_name);
			}else{
				if($userType >5 && $userType < 12){
					$okMoney=erphpGetUserOkMoney();
					$okMoney=sprintf("%.2f",$okMoney);
					$priceArr=array('6'=>'erphp_day_price','7'=>'erphp_month_price','8'=>'erphp_quarter_price','9'=>'erphp_year_price','10'=>'erphp_life_price','11'=>'erphp_super_price');
					$priceType=$priceArr[$userType];
					$price=get_option($priceType);

					$vip_update_pay = get_option('vip_update_pay');
					if($vip_update_pay){
						$erphp_quarter_price = get_option('erphp_quarter_price');
						$erphp_month_price  = get_option('erphp_month_price');
						$erphp_day_price  = get_option('erphp_day_price');
						$erphp_year_price    = get_option('erphp_year_price');
						$erphp_life_price  = get_option('erphp_life_price');
						$erphp_super_price  = get_option('erphp_super_price');

						if($userType == 7){
							if($oldUserType == 6){
	                     		$price = $erphp_month_price - $erphp_day_price;
	                     	}
						}elseif($userType == 8){
							if($oldUserType == 6){
	                     		$price = $erphp_quarter_price - $erphp_day_price;
	                     	}elseif($oldUserType == 7){
	                     		$price = $erphp_quarter_price - $erphp_month_price;
	                     	}
						}elseif($userType == 9){
							if($oldUserType == 6){
	                     		$price = $erphp_year_price - $erphp_day_price;
	                     	}elseif($oldUserType == 7){
	                     		$price = $erphp_year_price - $erphp_month_price;
	                     	}elseif($oldUserType == 8){
	                     		$price = $erphp_year_price - $erphp_quarter_price;
	                     	}
						}elseif($userType == 10){
							if($oldUserType == 6){
	                     		$price = $erphp_life_price - $erphp_day_price;
	                     	}elseif($oldUserType == 7){
	                     		$price = $erphp_life_price - $erphp_month_price;
	                     	}elseif($oldUserType == 8){
	                     		$price = $erphp_life_price - $erphp_quarter_price;
	                     	}elseif($oldUserType == 9){
	                     		$price = $erphp_life_price - $erphp_year_price;
	                     	}
						}elseif($userType == 11){
							if($oldUserType == 6){
	                     		$price = $erphp_super_price - $erphp_day_price;
	                     	}elseif($oldUserType == 7){
	                     		$price = $erphp_super_price - $erphp_month_price;
	                     	}elseif($oldUserType == 8){
	                     		$price = $erphp_super_price - $erphp_quarter_price;
	                     	}elseif($oldUserType == 9){
	                     		$price = $erphp_super_price - $erphp_year_price;
	                     	}elseif($oldUserType == 10){
	                     		$price = $erphp_super_price - $erphp_life_price;
	                     	}
						}
					}

					if(isset($_SESSION['erphp_promo_code']) && $_SESSION['erphp_promo_code']){
				        $promo = str_replace("\\","", $_SESSION['erphp_promo_code']);
				        $promo_arr = json_decode($promo,true);
				        if($promo_arr['type'] == 1){
				            $promo_money = get_option('erphp_promo_money1');
				            if($promo_money){
				                $price = $price - $promo_money;
				            }
				        }elseif($promo_arr['type'] == 2){
				            $promo_money = get_option('erphp_promo_money2');
				            if($promo_money){
				                $price = $price * 0.1 * $promo_money;
				            }
				        }
				    }

					if($price <= 0){
						$error = 1;$msg = __('VIP价格错误','mobantu');
					}elseif($okMoney < $price){
						$error = 2;$msg = __('余额不足，请先充值','mobantu');
					}elseif($okMoney >=$price){
						if(erphpSetUserMoneyXiaoFei($price)){
							if(function_exists('addUserMoneyLog')){
                                addUserMoneyLog($current_user->ID, '-'.$price, __('升级VIP','mobantu'));
                            }
							if(userPayMemberSetData($userType)){
								addVipLog($price, $userType);

								_mbt_add_notice($current_user->ID, __('您好，您已成功升级成为VIP。','mobantu'), 'vip', $userType);

								_mbt_add_activity($current_user->ID,'vip','',$userType);

								if(!get_option('erphp_vip_ref_no')){
									$EPD = new EPD();
									$EPD->doAff($price, $uid);
								}

								if(get_option('erphp_remind')){
									$headers = 'Content-Type: text/html; charset=' . get_option('blog_charset') . "\n";
									$typeName = getVipTypeName($userType);
									wp_mail(get_option('admin_email'), '['.get_bloginfo('name').']VIP订单提醒 - '.$typeName, '用户'.$current_user->user_login.'消费'.$price.get_option('ice_name_alipay').'购买了'.$typeName, $headers);
								}

							}else{
								$error = 1;$msg = __('升级失败','mobantu');
							}
						}else{
							$error = 1;$msg = __('升级失败','mobantu');
						}
					}else{
						$error = 1;$msg = __('升级失败','mobantu');
					}
				}
			}
		}

		$arr=array(
			"error"=>$error, 
			"msg"=>$msg,
			"link"=>$link,
			"payment"=>$payment
		); 
		
		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($_POST['action']=='user.vip.cat.credit'){
		$erphp_life_name    = get_option('erphp_life_name')?get_option('erphp_life_name'):'终身VIP';
		$error = 0;$msg = '';$link = get_permalink(MBThemes_page("template/user.php"));$payment = '';
		$cat=isset($_POST['cat']) && is_numeric($_POST['cat']) ?intval($_POST['cat']) :0;
		$userType=isset($_POST['userType']) && is_numeric($_POST['userType']) ?intval($_POST['userType']) :0;
		$oldUserType = getUsreMemberCatById($uid,$cat);
		if(get_option('erphp_url_front_recharge')){
			$link = get_option('erphp_url_front_recharge');
		}
		
		if(get_option('vip_update_down') && $oldUserType && $oldUserType > $userType){
			$error = 1; $msg = __('抱歉，暂不允许向下升级续费','mobantu');
		}elseif($oldUserType == '10'){
			$error = 1;$msg = sprintf( __('您已经是此分类的%s，请勿重复升级','mobantu'), $erphp_life_name);
		}else{
			if($userType >5 && $userType < 11){
				$okMoney=erphpGetUserOkMoney();
				$okMoney=sprintf("%.2f",$okMoney);
				$priceArr=array('7'=>'cat_vip_month','8'=>'cat_vip_quarter','9'=>'cat_vip_year','10'=>'cat_vip_life');
				$priceType=$priceArr[$userType];
				$price=get_term_meta($cat,$priceType,true);

				if(isset($_SESSION['erphp_promo_code']) && $_SESSION['erphp_promo_code']){
			        $promo = str_replace("\\","", $_SESSION['erphp_promo_code']);
			        $promo_arr = json_decode($promo,true);
			        if($promo_arr['type'] == 1){
			            $promo_money = get_option('erphp_promo_money1');
			            if($promo_money){
			                $price = $price - $promo_money;
			            }
			        }elseif($promo_arr['type'] == 2){
			            $promo_money = get_option('erphp_promo_money2');
			            if($promo_money){
			                $price = $price * 0.1 * $promo_money;
			            }
			        }
			    }

				if($price <= 0){
					$error = 1;$msg = __('VIP价格错误','mobantu');
				}elseif($okMoney < $price){
					$error = 2;$msg = __('余额不足，请先充值','mobantu');
				}elseif($okMoney >=$price){
					if(erphpSetUserMoneyXiaoFei($price)){
						if(function_exists('addUserMoneyLog')){
                            addUserMoneyLog($current_user->ID, '-'.$price, __('升级分类VIP','mobantu'));
                        }
						if(userPayCatSetData($userType,$cat)){
							addVipCatLog($price, $userType, $cat);

							_mbt_add_notice($current_user->ID, __('您好，您已成功升级成为分类VIP。','mobantu'), 'vip', $userType);

							_mbt_add_activity($current_user->ID,'vip',$cat,$userType);

							if(!get_option('erphp_vip_ref_no')){
								$EPD = new EPD();
								$EPD->doAff($price, $uid);
							}

							if(get_option('erphp_remind')){
								$headers = 'Content-Type: text/html; charset=' . get_option('blog_charset') . "\n";
								$cat_vip_name = get_term_meta($cat,'cat_vip_name',true);
								$userTypeName = $cat_vip_name?$cat_vip_name:get_category($cat)->name;
								if($userType == 7){
									$userTypeName .= __('-包月','mobantu');
								}elseif($userType == 8){
									$userTypeName .= __('-包季','mobantu');
								}elseif($userType == 9){
									$userTypeName .= __('-包年','mobantu');
								}elseif($userType == 10){
									$userTypeName .= __('-终身','mobantu');
								}
								wp_mail(get_option('admin_email'), '['.get_bloginfo('name').']'.__('VIP订单提醒 - ','mobantu').$userTypeName, sprintf(__('用户%s消费%s购买了%s','mobantu'), $current_user->user_login,$price.get_option('ice_name_alipay'),$userTypeName), $headers);
							}

						}else{
							$error = 1;$msg = __('升级失败','mobantu');
						}
					}else{
						$error = 1;$msg = __('升级失败','mobantu');
					}
				}else{
					$error = 1;$msg = __('升级失败','mobantu');
				}
			}
			
		}

		$arr=array(
			"error"=>$error, 
			"msg"=>$msg,
			"link"=>$link,
			"payment"=>$payment
		); 
		
		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($_POST['action']=='user.vip'){
		$error = 0;$msg = '';$link = get_permalink(MBThemes_page("template/user.php"));$payment = '';
		$userType=isset($_POST['userType']) && is_numeric($_POST['userType']) ?intval($_POST['userType']) :0;

		if(get_option('erphp_url_front_recharge')){
			$link = get_option('erphp_url_front_recharge');
		}
		
		$erphp_super_name    = get_option('erphp_super_name')?get_option('erphp_super_name'):'全站通VIP';
		$erphp_life_name    = get_option('erphp_life_name')?get_option('erphp_life_name'):'终身VIP';
		$erphp_year_name    = get_option('erphp_year_name')?get_option('erphp_year_name'):'包年VIP';
		$erphp_quarter_name = get_option('erphp_quarter_name')?get_option('erphp_quarter_name'):'包季VIP';
		$erphp_month_name  = get_option('erphp_month_name')?get_option('erphp_month_name'):'包月VIP';
		$erphp_day_name  = get_option('erphp_day_name')?get_option('erphp_day_name'):'体验VIP';
		$erphp_vip_name  = get_option('erphp_vip_name')?get_option('erphp_vip_name'):'VIP';
		
		$oldUserType = getUsreMemberTypeById($uid);
		if(get_option('vip_update_down') && $oldUserType && $oldUserType > $userType){
			$error = 1; $msg = __('抱歉，暂不允许向下升级续费','mobantu');
		}elseif($oldUserType == '11' && !get_option('erphp_life_days')){
			$error = 1;$msg = sprintf( __('您已经是%s，请勿重复升级','mobantu'), $erphp_super_name);
		}elseif($oldUserType == '10' && $userType < 11 && !get_option('erphp_life_days')){
			$error = 1;$msg = sprintf( __('您已经是%s，请勿重复升级','mobantu'), $erphp_life_name);
		}else{
			if($userType >5 && $userType < 12){
				$okMoney=erphpGetUserOkMoney();
				$okMoney=sprintf("%.2f",$okMoney);
				$priceArr=array('6'=>'erphp_day_price','7'=>'erphp_month_price','8'=>'erphp_quarter_price','9'=>'erphp_year_price','10'=>'erphp_life_price','11'=>'erphp_super_price');
				$priceType=$priceArr[$userType];
				$price=get_option($priceType);

				$vip_update_pay = get_option('vip_update_pay');
				if($vip_update_pay){
					$erphp_quarter_price = get_option('erphp_quarter_price');
					$erphp_month_price  = get_option('erphp_month_price');
					$erphp_day_price  = get_option('erphp_day_price');
					$erphp_year_price    = get_option('erphp_year_price');
					$erphp_life_price  = get_option('erphp_life_price');
					$erphp_super_price  = get_option('erphp_super_price');

					if($userType == 7){
						if($oldUserType == 6){
                     		$price = $erphp_month_price - $erphp_day_price;
                     	}
					}elseif($userType == 8){
						if($oldUserType == 6){
                     		$price = $erphp_quarter_price - $erphp_day_price;
                     	}elseif($oldUserType == 7){
                     		$price = $erphp_quarter_price - $erphp_month_price;
                     	}
					}elseif($userType == 9){
						if($oldUserType == 6){
                     		$price = $erphp_year_price - $erphp_day_price;
                     	}elseif($oldUserType == 7){
                     		$price = $erphp_year_price - $erphp_month_price;
                     	}elseif($oldUserType == 8){
                     		$price = $erphp_year_price - $erphp_quarter_price;
                     	}
					}elseif($userType == 10){
						if($oldUserType == 6){
                     		$price = $erphp_life_price - $erphp_day_price;
                     	}elseif($oldUserType == 7){
                     		$price = $erphp_life_price - $erphp_month_price;
                     	}elseif($oldUserType == 8){
                     		$price = $erphp_life_price - $erphp_quarter_price;
                     	}elseif($oldUserType == 9){
                     		$price = $erphp_life_price - $erphp_year_price;
                     	}
					}elseif($userType == 11){
						if($oldUserType == 6){
                     		$price = $erphp_super_price - $erphp_day_price;
                     	}elseif($oldUserType == 7){
                     		$price = $erphp_super_price - $erphp_month_price;
                     	}elseif($oldUserType == 8){
                     		$price = $erphp_super_price - $erphp_quarter_price;
                     	}elseif($oldUserType == 9){
                     		$price = $erphp_super_price - $erphp_year_price;
                     	}elseif($oldUserType == 10){
                     		$price = $erphp_super_price - $erphp_life_price;
                     	}
					}
				}

				if(_MBT('vip_default_pay') && !_MBT('vip_only_pay')){//静默使用余额
					if($price <= 0){
						$error = 1;$msg = __('VIP价格错误','mobantu');
					}elseif($okMoney < $price){
						if(_MBT('vip_just_pay')){
							$error = 3;$msg = __('余额不足，请直接在线支付','mobantu');

							$userTypeName = $erphp_vip_name;
							if($userType == 6){
								$userTypeName = $erphp_day_name;
							}elseif($userType == 7){
								$userTypeName = $erphp_month_name;
							}elseif($userType == 8){
								$userTypeName = $erphp_quarter_name;
							}elseif($userType == 9){
								$userTypeName = $erphp_year_name;
							}elseif($userType == 10){
								$userTypeName = $erphp_life_name;
							}elseif($userType == 11){
								$userTypeName = $erphp_super_name;
							}

							$moneyVipName = get_option('ice_name_alipay');
							if(_MBT('vip_only_pay') && _MBT('vip_only_pay_rmb')){
								$price = $price/get_option('ice_proportion_alipay');
								$moneyVipName = '元';
							}

							$payment .= '<div class="erphpdown-type-desc"><div class="type-name">'.$userTypeName.'</div><div class="type-price"><span data-price="'.$price.'">'.$price.'</span>'.$moneyVipName.'</div></div>';
							$payment .= '<div class="erphpdown-type-payment">';
							
							if(get_option('ice_weixin_mchid')){
								if(erphpdown_is_weixin() && get_option('ice_weixin_app')){
									$payment .= '<a href="https://open.weixin.qq.com/connect/oauth2/authorize?appid='.get_option('ice_weixin_appid').'&redirect_uri='.urlencode(constant("erphpdown")).'payment%2Fweixin.php%3Fice_type%3D'.$userType.'&response_type=code&scope=snsapi_base&state=STATE&connect_redirect=1#wechat_redirect" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}else{
									$payment .= '<a href="'.constant("erphpdown").'payment/weixin.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
							}
							if(get_option('ice_ali_partner') || get_option('ice_ali_app_id')){
								$payment .= '<a href="'.constant("erphpdown").'payment/alipay.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
							}
							if(get_option('erphpdown_f2fpay_id') && !get_option('erphpdown_f2fpay_alipay')){
								$payment .= '<a href="'.constant("erphpdown").'payment/f2fpay.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
							}
							if(get_option('erphpdown_payjs_appid')){
								if(!get_option('erphpdown_payjs_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/payjs.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_payjs_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/payjs.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_xhpay_appid32')){
								$payment .= '<a href="'.constant("erphpdown").'payment/xhpay3.php?ice_type='.$userType.'&type=1&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
							}
							if(get_option('erphpdown_xhpay_appid31')){
								$payment .= '<a href="'.constant("erphpdown").'payment/xhpay3.php?ice_type='.$userType.'&type=2&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
							}
							if(get_option('erphpdown_codepay_appid')){
								if(!get_option('erphpdown_codepay_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/codepay.php?ice_type='.$userType.'&type=1&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
								if(!get_option('erphpdown_codepay_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/codepay.php?ice_type='.$userType.'&type=3&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_codepay_qqpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/codepay.php?ice_type='.$userType.'&type=2&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-qqpay" target="_blank"><i class="icon icon-qq"></i> '.__('QQ钱包','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_paypy_key')){
								if(!get_option('erphpdown_paypy_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/paypy.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_paypy_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/paypy.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_epay_id')){
								if(!get_option('erphpdown_epay_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/epay.php?ice_type='.$userType.'&type=wxpay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_epay_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/epay.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
								if(!get_option('erphpdown_epay_qqpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/epay.php?ice_type='.$userType.'&type=qqpay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-qqpay" target="_blank"><i class="icon icon-qq"></i> '.__('QQ钱包','mobantu').'</a>';
								}
								if(get_option('erphpdown_epay_bank')){
									$payment .= '<a href="'.constant("erphpdown").'payment/epay.php?ice_type='.$userType.'&type=bank&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-credit-card" target="_blank"><i class="icon icon-credit-card"></i> '.__('网银支付','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_easepay_id')){
								if(!get_option('erphpdown_easepay_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/easepay.php?ice_type='.$userType.'&type=wxpay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_easepay_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/easepay.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
								if(!get_option('erphpdown_easepay_usdt')){
									$payment .= '<a href="'.constant("erphpdown").'payment/easepay.php?ice_type='.$userType.'&type=usdt&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-ut" target="_blank"><i class="icon icon-usdt"></i> '.__('USDT','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_rpay_id')){
								if(!get_option('erphpdown_rpay_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=wxpay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_rpay_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
								if(!get_option('erphpdown_rpay_stripe')){
									$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=stripe&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-stripe" target="_blank"><i class="icon icon-credit-card"></i> '.__('信用卡','mobantu').'</a>';
								}
								if(!get_option('erphpdown_rpay_paypal')){
									$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=paypal&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-paypal" target="_blank"><i class="icon icon-paypal"></i> Paypal</a>';
								}
							}
							if(get_option('erphpdown_vpay_key')){
								if(!get_option('erphpdown_vpay_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/vpay.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_vpay_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/vpay.php?ice_type='.$userType.'&type=2&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_stripe_pk')){
								$payment .= '<a href="'.home_url('?epd_v64='.base64_encode('stripe-'.$userType.'-'.time())).'" class="erphpdown-type-link erphpdown-type-stripe" target="_blank"><i class="icon icon-credit-card"></i> '.__('信用卡','mobantu').'</a>';
							}
							if(get_option('ice_payapl_api_uid')){
								$payment .= '<a href="'.constant("erphpdown").'payment/paypal.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-paypal" target="_blank"><i class="icon icon-paypal"></i> Paypal</a>';
							}
							if(function_exists('plugin_check_ecpay') && plugin_check_ecpay() && get_option('erphpdown_ecpay_MerchantID')){
								$payment .= '<a href="'.ERPHPDOWN_ECPAY_URL.'/ecpay.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-ecpay" target="_blank"><i class="icon icon-ecpay"></i> '.__('新台币','mobantu').'</a>';
							}
							if(get_option('erphpdown_usdt_address')){
								$payment .= '<a href="'.home_url('?epd_v64='.base64_encode('usdt-'.$userType.'-'.time())).'" class="erphpdown-type-link erphpdown-type-ut" target="_blank"><i class="icon icon-usdt"></i> USDT</a>';
							}
							$payment .= '</div>';
						}else{
							$error = 2;$msg = __('余额不足，请先充值','mobantu');
						}

						if(function_exists('erphpdown_vipcard_install')){
							$payment .= '<a href="'.add_query_arg('action','vip',get_permalink(MBThemes_page("template/user.php"))).'" class="erphpdown-type-link erphpdown-type-card" target="_blank"><i class="icon icon-wallet"></i> '.__('激活码升级','mobantu').'</a>';
						}

						if(get_option('erphp_promo')){
							$payment .= '<div class="erphpdown-promo">'.__('优惠码：','mobantu').'<input type="text" id="erphp_promo" /> <a href="javascript:;" class="erphp-promo-do erphp-promo-do-vip">'.__('使用','mobantu').'</a></div>';
						}
					}elseif($okMoney >=$price){
						if(erphpSetUserMoneyXiaoFei($price)){
							if(function_exists('addUserMoneyLog')){
                                addUserMoneyLog($current_user->ID, '-'.$price, __('升级VIP','mobantu'));
                            }
							if(userPayMemberSetData($userType)){
								addVipLog($price, $userType);

								_mbt_add_notice($current_user->ID, __('您好，您已成功升级成为VIP。','mobantu'), 'vip', $userType);

								_mbt_add_activity($current_user->ID,'vip','',$userType);
								
								$EPD = new EPD();
								$EPD->doAff($price, $uid);

								if(get_option('erphp_remind')){
									$headers = 'Content-Type: text/html; charset=' . get_option('blog_charset') . "\n";
									$typeName = getVipTypeName($userType);
									wp_mail(get_option('admin_email'), '['.get_bloginfo('name').']'.__('VIP订单提醒 - ','mobantu').$typeName, sprintf(__('用户%s消费%s购买了%s','mobantu'), $current_user->user_login,$price.get_option('ice_name_alipay'),$typeName), $headers);
								}
							}else{
								$error = 1;$msg = __('升级失败','mobantu');
							}
						}else{
							$error = 1;$msg = __('升级失败','mobantu');
						}
					}else{
						$error = 1;$msg = __('升级失败','mobantu');
					}
				}else{
					if($price <= 0){
						$error = 1;$msg = __('VIP价格错误','mobantu');
					}else{
						$userTypeName = $erphp_vip_name;
						if($userType == 6){
							$userTypeName = $erphp_day_name;
						}elseif($userType == 7){
							$userTypeName = $erphp_month_name;
						}elseif($userType == 8){
							$userTypeName = $erphp_quarter_name;
						}elseif($userType == 9){
							$userTypeName = $erphp_year_name;
						}elseif($userType == 10){
							$userTypeName = $erphp_life_name;
						}elseif($userType == 11){
							$userTypeName = $erphp_super_name;
						}

						$moneyVipName = get_option('ice_name_alipay');
						if(_MBT('vip_only_pay') && _MBT('vip_only_pay_rmb')){
							$price = $price/get_option('ice_proportion_alipay');
							$moneyVipName = '元';
						}
							
						$error = 3;$msg = __('请选择支付方式','mobantu');
						if(_MBT('vip_just_pay') || _MBT('vip_only_pay')){

							$payment .= '<div class="erphpdown-type-desc"><div class="type-name">'.$userTypeName.'</div><div class="type-price"><span data-price="'.$price.'">'.$price.'</span>'.$moneyVipName.'</div></div>';
							$payment .= '<div class="erphpdown-type-payment">';
							if(get_option('ice_weixin_mchid')){
								if(erphpdown_is_weixin() && get_option('ice_weixin_app')){
									$payment .= '<a href="https://open.weixin.qq.com/connect/oauth2/authorize?appid='.get_option('ice_weixin_appid').'&redirect_uri='.urlencode(constant("erphpdown")).'payment%2Fweixin.php%3Fice_type%3D'.$userType.'&response_type=code&scope=snsapi_base&state=STATE&connect_redirect=1#wechat_redirect" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}else{
									$payment .= '<a href="'.constant("erphpdown").'payment/weixin.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
							}
							if(get_option('ice_ali_partner') || get_option('ice_ali_app_id')){
								$payment .= '<a href="'.constant("erphpdown").'payment/alipay.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
							}
							if(get_option('erphpdown_f2fpay_id') && !get_option('erphpdown_f2fpay_alipay')){
								$payment .= '<a href="'.constant("erphpdown").'payment/f2fpay.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
							}
							if(get_option('erphpdown_payjs_appid')){
								if(!get_option('erphpdown_payjs_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/payjs.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_payjs_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/payjs.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_xhpay_appid32')){
								$payment .= '<a href="'.constant("erphpdown").'payment/xhpay3.php?ice_type='.$userType.'&type=1&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
							}
							if(get_option('erphpdown_xhpay_appid31')){
								$payment .= '<a href="'.constant("erphpdown").'payment/xhpay3.php?ice_type='.$userType.'&type=2&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
							}
							if(get_option('erphpdown_codepay_appid')){
								if(!get_option('erphpdown_codepay_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/codepay.php?ice_type='.$userType.'&type=1&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
								if(!get_option('erphpdown_codepay_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/codepay.php?ice_type='.$userType.'&type=3&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_codepay_qqpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/codepay.php?ice_type='.$userType.'&type=2&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-qqpay" target="_blank"><i class="icon icon-qq"></i> '.__('QQ钱包','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_paypy_key')){
								if(!get_option('erphpdown_paypy_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/paypy.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_paypy_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/paypy.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_epay_id')){
								if(!get_option('erphpdown_epay_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/epay.php?ice_type='.$userType.'&type=wxpay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_epay_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/epay.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
								if(!get_option('erphpdown_epay_qqpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/epay.php?ice_type='.$userType.'&type=qqpay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-qqpay" target="_blank"><i class="icon icon-qq"></i> '.__('QQ钱包','mobantu').'</a>';
								}
								if(get_option('erphpdown_epay_bank')){
									$payment .= '<a href="'.constant("erphpdown").'payment/epay.php?ice_type='.$userType.'&type=bank&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-credit-card" target="_blank"><i class="icon icon-credit-card"></i> '.__('网银支付','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_easepay_id')){
								if(!get_option('erphpdown_easepay_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/easepay.php?ice_type='.$userType.'&type=wxpay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_easepay_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/easepay.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
								if(!get_option('erphpdown_easepay_usdt')){
									$payment .= '<a href="'.constant("erphpdown").'payment/easepay.php?ice_type='.$userType.'&type=usdt&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-ut" target="_blank"><i class="icon icon-usdt"></i> '.__('USDT','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_rpay_id')){
								if(!get_option('erphpdown_rpay_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=wxpay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_rpay_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
								if(!get_option('erphpdown_rpay_stripe')){
									$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=stripe&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-stripe" target="_blank"><i class="icon icon-credit-card"></i> '.__('信用卡','mobantu').'</a>';
								}
								if(!get_option('erphpdown_rpay_paypal')){
									$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=paypal&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-paypal" target="_blank"><i class="icon icon-paypal"></i> Paypal</a>';
								}
							}
							if(get_option('erphpdown_vpay_key')){
								if(!get_option('erphpdown_vpay_wxpay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/vpay.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="icon icon-wxpay-color"></i> '.__('微信支付','mobantu').'</a>';
								}
								if(!get_option('erphpdown_vpay_alipay')){
									$payment .= '<a href="'.constant("erphpdown").'payment/vpay.php?ice_type='.$userType.'&type=2&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="icon icon-alipay-color"></i> '.__('支付宝','mobantu').'</a>';
								}
							}
							if(get_option('erphpdown_stripe_pk')){
								$payment .= '<a href="'.home_url('?epd_v64='.base64_encode('stripe-'.$userType.'-'.time())).'" class="erphpdown-type-link erphpdown-type-stripe" target="_blank"><i class="icon icon-credit-card"></i> '.__('信用卡','mobantu').'</a>';
							}
							if(get_option('ice_payapl_api_uid')){
								$payment .= '<a href="'.constant("erphpdown").'payment/paypal.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-paypal" target="_blank"><i class="icon icon-paypal"></i> Paypal</a>';
							}	
							if(function_exists('plugin_check_ecpay') && plugin_check_ecpay() && get_option('erphpdown_ecpay_MerchantID')){
								$payment .= '<a href="'.ERPHPDOWN_ECPAY_URL.'/ecpay.php?ice_type='.$userType.'&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-ecpay" target="_blank"><i class="icon icon-ecpay"></i> '.__('新台币','mobantu').'</a>';
							}
							if(get_option('erphpdown_usdt_address')){
								$payment .= '<a href="'.home_url('?epd_v64='.base64_encode('usdt-'.$userType.'-'.time())).'" class="erphpdown-type-link erphpdown-type-ut" target="_blank"><i class="icon icon-usdt"></i> USDT</a>';
							}
							$payment .= '</div>';
						}
						if(!_MBT('vip_only_pay')){
							if(!_mbt('vip_just_pay')){
								$payment .= '<div class="erphpdown-type-desc"><div class="type-name">'.$userTypeName.'</div><div class="type-price"><span data-price="'.$price.'">'.$price.'</span>'.get_option('ice_name_alipay').'</div></div>';
							}
							$payment .= '<a href="javascript:;" class="erphpdown-type-link erphpdown-type-credit" data-type="'.$userType.'"><i class="icon icon-ticket"></i> '.__('余额支付','mobantu').'</a>';
						}
						
						if(function_exists('erphpdown_vipcard_install')){
							$payment .= '<a href="'.add_query_arg('action','vip',get_permalink(MBThemes_page("template/user.php"))).'" class="erphpdown-type-link erphpdown-type-card" target="_blank"><i class="icon icon-wallet"></i> '.__('激活码升级','mobantu').'</a>';
						}

						if(get_option('erphp_promo')){
							$payment .= '<div class="erphpdown-promo">'.__('优惠码：','mobantu').'<input type="text" id="erphp_promo" /> <a href="javascript:;" class="erphp-promo-do erphp-promo-do-vip">'.__('使用','mobantu').'</a></div>';
						}
					}
				}
			}else{
				$error = 1;$msg = __('升级失败','mobantu');
			}
		}
		

		$arr=array(
			"error"=>$error, 
			"msg"=>$msg,
			"link"=>$link,
			"payment"=>$payment
		); 
		
		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($_POST['action']=='user.vip.cat'){
		$erphp_life_name = get_option('erphp_life_name')?get_option('erphp_life_name'):'终身VIP';
		$error = 0;$msg = '';$link = get_permalink(MBThemes_page("template/user.php"));$payment = '';
		$userType=isset($_POST['userType']) && is_numeric($_POST['userType']) ?intval($_POST['userType']) :0;
		$cat=isset($_POST['cat']) && is_numeric($_POST['cat']) ?intval($_POST['cat']) :0;

		if(get_option('erphp_url_front_recharge')){
			$link = get_option('erphp_url_front_recharge');
		}
		
		$oldUserType = getUsreMemberCatById($uid,$cat);
		if(get_option('vip_update_down') && $oldUserType && $oldUserType > $userType){
			$error = 1;$msg = __('抱歉，暂不允许向下升级续费','mobantu');
		}elseif($oldUserType == '10'){
			$error = 1;$msg = sprintf( __('您已经是此分类的%s，请勿重复升级','mobantu'), $erphp_life_name);
		}else{
			if($userType >5 && $userType < 11){
				$okMoney=erphpGetUserOkMoney();
				$okMoney=sprintf("%.2f",$okMoney);
				$priceArr=array('7'=>'cat_vip_month','8'=>'cat_vip_quarter','9'=>'cat_vip_year','10'=>'cat_vip_life');
				$priceType=$priceArr[$userType];
				$price=get_term_meta($cat,$priceType,true);
				
				if($price <= 0){
					$error = 1;$msg = __('VIP价格错误','mobantu');
				}else{
					$cat_vip_name = get_term_meta($cat,'cat_vip_name',true);
					$userTypeName = $cat_vip_name?$cat_vip_name:get_category($cat)->name;
					if($userType == 7){
						$userTypeName .= __('-包月','mobantu');
					}elseif($userType == 8){
						$userTypeName .= __('-包季','mobantu');
					}elseif($userType == 9){
						$userTypeName .= __('-包年','mobantu');
					}elseif($userType == 10){
						$userTypeName .= __('-终身','mobantu');
					}
						
					$error = 3;$msg = __('请选择支付方式','mobantu');

					$payment .= '<div class="erphpdown-type-desc"><div class="type-name">'.$userTypeName.'</div><div class="type-price"><span data-price="'.$price.'">'.$price.'</span>'.get_option('ice_name_alipay').'</div></div>';
					$payment .= '<center><a href="javascript:;" class="erphpdown-type-link erphpdown-type-full erphpdown-cat-credit" data-cat="'.$cat.'" data-type="'.$userType.'"><i class="icon icon-ticket"></i> '.__('余额支付','mobantu').'</a></center>';

					if(function_exists('erphpdown_catvipcard_install')){
						$payment .= '<a href="'.add_query_arg('action','vip',get_permalink(MBThemes_page("template/user.php"))).'" class="erphpdown-type-link erphpdown-type-card" target="_blank"><i class="icon icon-wallet"></i> '.__('激活码升级','mobantu').'</a>';
					}

					if(get_option('erphp_promo')){
						$payment .= '<div class="erphpdown-promo">'.__('优惠码：','mobantu').'<input type="text" id="erphp_promo" /> <a href="javascript:;" class="erphp-promo-do erphp-promo-do-vip">'.__('使用','mobantu').'</a></div>';
					}
				}
				
			}else{
				$error = 1;$msg = __('升级失败','mobantu');
			}
		}
		

		$arr=array(
			"error"=>$error, 
			"msg"=>$msg,
			"link"=>$link,
			"payment"=>$payment
		); 
		
		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($_POST['action'] == 'user.vip.card'){
		$error = 0;$msg = '';
		if(empty($_POST['nonce']) || empty($_SESSION['erphpcard_nonce']) || trim(strtolower($_POST['nonce'])) != $_SESSION['erphpcard_nonce']){ //!wp_verify_nonce($_POST['nonce'], 'erphpcard_action')
			$error = 1;
			$msg = __('系统超时，请稍后重试','mobantu');
		}else{
			$num = esc_sql(trim($_POST['num']));
			$result = checkDoVipCardResult($num);
			if($result == '2'){
				$error = 1;
				$msg = __('激活码已过期','mobantu');
			}elseif($result == '0'){
				$error = 1;
				$msg = __('激活码已被使用，请换一个试试','mobantu');
			}elseif($result == '3'){
				$error = 1;
				$msg = __('激活码不存在','mobantu');
			}elseif($result == '1'){
				
			}else{
				$error = 1;
				$msg = __("系统超时，请稍后重试",'mobantu');
			}
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);

		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($_POST['action'] == 'user.catvip.card'){
		$error = 0;$msg = '';
		if(empty($_POST['nonce']) || empty($_SESSION['erphpcard_nonce2']) || trim(strtolower($_POST['nonce'])) != $_SESSION['erphpcard_nonce2']){
			$error = 1;
			$msg = __('系统超时，请稍后重试','mobantu');
		}else{
			$num = esc_sql(trim($_POST['num']));
			$result = checkDoCatVipCardResult($num);
			if($result == '2'){
				$error = 1;
				$msg = __('激活码已过期','mobantu');
			}elseif($result == '0'){
				$error = 1;
				$msg = __('激活码已被使用，请换一个试试','mobantu');
			}elseif($result == '3'){
				$error = 1;
				$msg = __('激活码不存在','mobantu');
			}elseif($result == '1'){
				
			}else{
				$error = 1;
				$msg = __("系统超时，请稍后重试",'mobantu');
			}
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);

		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($_POST['action'] == 'user.charge.card'){
		$error = 0;$msg = '';
		if(empty($_POST['nonce']) || empty($_SESSION['erphpcard_nonce']) || trim(strtolower($_POST['nonce'])) != $_SESSION['erphpcard_nonce']){
			$error = 1;
			$msg = __('系统超时，请稍后重试','mobantu');
		}else{
			$num = esc_sql(trim($_POST['num']));
			$result = MBThemes_do_card($num);
			if($result == '5'){
				$error = 1;
				$msg = __('充值卡不存在','mobantu');
			}elseif($result == '0'){
				$error = 1;
				$msg = __('充值卡已被使用，请换一个试试','mobantu');
			}elseif($result == '1'){
				
			}else{
				$error = 1;
				$msg = __("系统超时，请稍后重试",'mobantu');
			}
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);

		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($_POST['action'] == 'user.mycred'){
		$error = 0;$msg = '';
		$erphp_to_mycred = get_option('erphp_to_mycred');
		$epdmycrednum = esc_sql($_POST['num']);
		if(is_numeric($epdmycrednum) && $epdmycrednum > 0 && get_option('erphp_mycred') == 'yes'){
			if(floatval($epdmycrednum*$erphp_to_mycred) < 1 || floor($epdmycrednum*$erphp_to_mycred) != $epdmycrednum*$erphp_to_mycred){
				$mycred_core = get_option('mycred_pref_core');
				$error = 1;
				$msg = __('兑换失败，兑换扣除的'.$mycred_core['name']['plural'].'数需为整数','mobantu');
			}else{
				if(floatval(mycred_get_users_cred( $current_user->ID )) < floatval($epdmycrednum*$erphp_to_mycred)){
					$mycred_core = get_option('mycred_pref_core');
					$error = 1;
					$msg = $mycred_core['name']['plural'].__("不足",'mobantu');
				}else{
					mycred_add( __('兑换','mobantu'), $current_user->ID, '-'.$epdmycrednum*$erphp_to_mycred, __('兑换扣除','mobantu').'%plural%!', date("Y-m-d H:i:s") );
					$money = $epdmycrednum;
					if(addUserMoney($current_user->ID, $money)){
						if(function_exists('addUserMoneyLog')){
	                        addUserMoneyLog($current_user->ID, $money, 'Mycred兑换');
	                    }
						$sql="INSERT INTO $wpdb->icemoney (ice_money,ice_num,ice_user_id,ice_time,ice_success,ice_note,ice_success_time,ice_alipay)
						VALUES ('$money','".date("ymd").mt_rand(10000,99999)."','".$current_user->ID."','".date("Y-m-d H:i:s")."',1,'4','".date("Y-m-d H:i:s")."','')";
						$wpdb->query($sql);
					}else{
						$error = 1;
						$msg = __('兑换失败','mobantu');
					}
				}
			}
		}

		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);

		$jarr=json_encode($arr); 
		echo $jarr; 

	}elseif($_POST['action']=='user.social.cancel'){
		$error = 0;$msg = '';
		if($current_user->user_email){
			if($_POST['type'] == 'weixin'){
				$wpdb->query("update $wpdb->users set weixinid='', weixin_unionid='' where ID=".$current_user->ID);
			}elseif($_POST['type'] == 'weibo'){
				$wpdb->query("update $wpdb->users set sinaid='' where ID=".$current_user->ID);
			}elseif($_POST['type'] == 'qq'){
				$wpdb->query("update $wpdb->users set qqid='' where ID=".$current_user->ID);
			}else{
				$error = 1;
				$msg = __('解绑失败','mobantu');
			}
		}else{
			$error = 1;
			$msg = __('为避免账号无法找回，请先绑定邮箱','mobantu');
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);
		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($_POST['action']=='user.checkin'){
		$error = 0;$msg = '';
		$ice_ali_money_checkin = get_option('ice_ali_money_checkin');
		if($ice_ali_money_checkin){
			if(erphpdown_check_checkin($current_user->ID)){
				$error = 1;
				$msg = __('您今天已经签过到了，请明儿再来','mobantu');
			}else{
				$cdate = date("Y-m-d H:i:s");

				$result = $wpdb->insert( $wpdb->prefix ."checkins", array(
	                'user_id' => $current_user->ID,
	                'create_time' => $cdate
	            ) );

				if($result){
					if(function_exists('addUserMoney')){
						$gift = 0;
						if(_MBT('checkin_random')){
							$gift_min = _MBT('checkin_gift_min')?_MBT('checkin_gift_min'):0;
							$gift_max = _MBT('checkin_gift_max')?_MBT('checkin_gift_max'):0;
							if($gift_min < 1){
								$gnum = $gift_min + mt_rand() / mt_getrandmax() * ($gift_max - $gift_min);
   	 							$gift = sprintf("%.1f",$gnum);
							}else{
								$gift = rand($gift_min,$gift_max);
							}
						}else{
							$gift = $ice_ali_money_checkin;
						}
						addUserMoney($current_user->ID, $gift);
						$wpdb->query("update ".$wpdb->prefix . "checkins set credit ='".$gift."' where user_id=".$current_user->ID." and create_time ='".$cdate."'");
						if(function_exists('addUserMoneyLog')){
                            addUserMoneyLog($current_user->ID, $gift, __('签到奖励','mobantu'));
                        }
                        _mbt_add_activity($current_user->ID,'checkin','',$gift);
						$msg = __('签到成功 +','mobantu').$gift.get_option('ice_name_alipay');
					}
				}else{
					$error = 1;
					$msg = __('签到失败','mobantu');
				}
			}
		}else{
			$error = 1;
			$msg = __('签到失败','mobantu'); 
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action'] == 'user.withdraw' && _MBT('withdraw')){
    	$error = 0;$msg = '';
    	$okMoney = erphpGetUserOkMoney();

    	$erphp_aff_money = get_option('erphp_aff_money');
    	if($erphp_aff_money){
			$okMoney = erphpGetUserOkAff();
		}

    	$ice_alipay = esc_sql($_POST['ice_alipay']);
		$ice_name   = esc_sql($_POST['ice_name']);
		$ice_money  = isset($_POST['ice_money']) && is_numeric($_POST['ice_money']) ?esc_sql($_POST['ice_money']) :0;
		if($ice_money >0){
			if($ice_money<get_option('ice_ali_money_limit'))
			{
				$error = 1;
				$msg = __('提现金额至少得满','mobantu').get_option('ice_ali_money_limit').get_option('ice_name_alipay');
			}
			elseif(empty($ice_name) || empty($ice_alipay))
			{
				$error = 1;
				$msg = __('请输入支付宝帐号和姓名','mobantu');
			}
			elseif($ice_money > $okMoney)
			{
				$error = 1;
				$msg = __('余额不足','mobantu');
			}
			else
			{

				$result = $wpdb->insert( $wpdb->iceget, array(
					'ice_money' => $ice_money,
	                'ice_user_id' => $current_user->ID,
	                'ice_time' => date("Y-m-d H:i:s"),
	                'ice_success' => 0,
	                'ice_success_time' => date("Y-m-d H:i:s"),
	                'ice_note' => '',
	                'ice_name' => $ice_name,
	                'ice_alipay' => $ice_alipay
	            ) );
				if($result)
				{	
					if($erphp_aff_money){
						addUserAffXiaoFei($current_user->ID, $ice_money);
					}else{
						addUserMoney($current_user->ID, '-'.$ice_money);
						if(function_exists('addUserMoneyLog')){
                            addUserMoneyLog($current_user->ID, '-'.$ice_money, __('提现扣除','mobantu'));
                        }
					}
				}
				else
				{
					$error = 1;
					$msg = __("系统超时，请稍后重试",'mobantu');
				}
			}
		}else{
			$error = 1;
			$msg = __('你想干嘛？','mobantu');
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);
		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($_POST['action']=='user.ask'){
		$error = 1;$msg = __('提交失败','mobantu');$link = '';
			
		$post_tougao_time = _MBT('post_tougao_time');
		$last_post = $wpdb->get_var("SELECT post_date FROM $wpdb->posts WHERE post_author='".$current_user->ID."' AND post_type = 'question' and (post_status='pending' or post_status='publish') ORDER BY post_date DESC LIMIT 1");
	    if ( $post_tougao_time && (time() - strtotime($last_post) < $post_tougao_time*60) ){
	        $msg = __('这也太快了吧，先喝杯咖啡？','mobantu');
	    }else{
	    	if(isset($_POST['security_nonce']) && $_POST['security_nonce'] == $_SESSION['security_nonce']){
	    		
				$title =   esc_sql($_POST['title']) ;
				$content =  $_POST['content'] ;

				if($title && $content && $_POST['cat']){
					$cat =  esc_sql($_POST['cat']);
					$post_status = 'pending';
					if(_MBT('question_ask_submit')){
						$post_status = 'publish';
					}
					$submit = array(
						'post_type' => 'question',
						'post_title' => strip_tags($title),
						'post_author' => $current_user->ID,
						'post_content' => $content,
						'post_status' => $post_status
					);
					$status = wp_insert_post( $submit );
					
					if ($status != 0) {
			            wp_set_object_terms($status, get_term_by('id',$cat,'question_category')->slug, 'question_category');
						if(_MBT('question_ask_submit')){
							$msg = __('提交成功','mobantu');
							$link = get_permalink($status);
						}else{
							$msg = __('提交成功，请等待管理员审核！','mobantu');
						}
						$error = 0;
					}else{
						$msg = __('提交失败，请稍后重试！','mobantu');
					}
				}else{
					$msg = __('提交失败，请完善标题、分类、正文！','mobantu');
				}
				
			}else{
				$msg = __('提交失败，请稍后重试！','mobantu');
			}
		}
			
		
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg,
			"link"=>$link
		);
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action']=='user.tougao'){
		$error = 1;$msg = __('提交失败','mobantu');$link = '';

		if(_MBT('post_tougao_ajax')){
			$post_tougao_role = _MBT('post_tougao_role')?_MBT('post_tougao_role'):'read';
			if(!current_user_can($post_tougao_role)){
				$msg = __('抱歉，暂无投稿权限','mobantu');
			}else{
				$post_tougao_time = _MBT('post_tougao_time');
				$last_post = $wpdb->get_var("SELECT post_date FROM $wpdb->posts WHERE post_author='".$current_user->ID."' AND post_type = 'post' and (post_status='pending' or post_status='publish') ORDER BY post_date DESC LIMIT 1");
			    if ( $post_tougao_time && (time() - strtotime($last_post) < $post_tougao_time*60) ){
			        $msg = __('这也太快了吧，先喝杯咖啡？','mobantu');
			    }else{
			    	if(isset($_POST['security_nonce']) && $_POST['security_nonce'] == $_SESSION['security_nonce']){
			    		if(isset($_POST['pid']) && $_POST['pid']){
				    		$thepost = get_post($_POST['pid']);
							$postauthor = $thepost->post_author;
							if($postauthor == $current_user->ID){
								$title =   esc_sql($_POST['title']) ;
								$content =  $_POST['content'] ;
								if($title && $content && $_POST['cat']){
									$cat =  esc_sql($_POST['cat']);
									$post_status = 'pending';
									if(_MBT('post_tougao_submit')){
										$post_status = 'publish';
									}
									$submit = array(
										'ID' => esc_sql($_POST['pid']),
										'post_title' => strip_tags($title),
										'post_author' => $current_user->ID,
										'post_content' => $content,
										'post_category' => array($cat),
										'post_status' => $post_status
									);
									$status = wp_update_post( $submit );
									
									if ($status != 0) {

										/*$taxonomys = get_term_meta($cat,'taxonomys',true);
										if($taxonomys){
											$post_texonomys = explode('|', $taxonomys);
							                foreach ($post_texonomys as $post_texonomy) { 
							                    $post_texonomy = explode(',', $post_texonomy);
							                    wp_set_object_terms( $status, $_POST[$post_texonomy[1]], $post_texonomy[1] );
							                }
							            }*/

										if(isset($_POST['image']) &&  $_POST['image']){
											update_post_meta($status,'_thumbnail_ext_url',esc_sql($_POST['image']));
										}

										if(isset($_POST['video']) &&  $_POST['video']){
											update_post_meta($status,'video',esc_sql($_POST['video']));
										}

										if(!_MBT('post_tougao_erphpdown')){
											update_post_meta($status,'erphp_down',$_POST['erphp_down']);
											if(wp_is_erphpdown_active() && $_POST['erphp_down'] == '1'){
												update_post_meta($status,'start_down',"yes");
												update_post_meta($status,'member_down','1');
										        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
										        update_post_meta($status,'down_url',esc_sql($_POST['down_url']));
										        update_post_meta($status,'hidden_content',esc_sql($_POST['hidden_content'])); 
											}elseif(wp_is_erphpdown_active() && $_POST['erphp_down'] == '2'){
												update_post_meta($status,'start_see',"yes");
												update_post_meta($status,'member_down','1');
										        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
											}elseif(wp_is_erphpdown_active() && $_POST['erphp_down'] == '3'){
												update_post_meta($status,'start_see2',"yes");
												update_post_meta($status,'member_down','1');
										        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
											}

											if(isset($_POST['member_down'])){
												update_post_meta($status,'member_down',$_POST['member_down']);
											}
										}

										if(_MBT('post_tougao_submit')){
											$msg = __('提交成功','mobantu');
											$link = get_permalink($status);
										}else{
											$msg = __('提交成功，请等待管理员审核！','mobantu');
										}
										$error = 0;
									}else{
										$msg = __('提交失败，请稍后重试！','mobantu');
									}
								}else{
									$msg = __('提交失败，请完善标题、分类、正文！','mobantu');
								}
							}else{
								$msg = __('提交失败，请稍后重试！','mobantu');
							}
						}else{
							$title =   esc_sql($_POST['title']) ;
							$content =  $_POST['content'] ;

							if($title && $content && $_POST['cat']){
								$cat =  esc_sql($_POST['cat']);
								$post_status = 'pending';
								if(_MBT('post_tougao_submit')){
									$post_status = 'publish';
								}
								$submit = array(
									'post_title' => strip_tags($title),
									'post_author' => $current_user->ID,
									'post_content' => $content,
									'post_category' => array($cat),
									'post_status' => $post_status
								);
								$status = wp_insert_post( $submit );
								
								if ($status != 0) {

									/*$taxonomys = get_term_meta($cat,'taxonomys',true);
									if($taxonomys){
										$post_texonomys = explode('|', $taxonomys);
						                foreach ($post_texonomys as $post_texonomy) { 
						                    $post_texonomy = explode(',', $post_texonomy);
						                    wp_set_object_terms( $status, $_POST[$post_texonomy[1]], $post_texonomy[1] );
						                }
						            }*/

									if(isset($_POST['image']) &&  $_POST['image']){
										update_post_meta($status,'_thumbnail_ext_url',esc_sql($_POST['image']));
									}

									if(isset($_POST['video']) &&  $_POST['video']){
										update_post_meta($status,'video',esc_sql($_POST['video']));
									}

									if(!_MBT('post_tougao_erphpdown')){
										update_post_meta($status,'erphp_down',$_POST['erphp_down']);
										if(wp_is_erphpdown_active() && $_POST['erphp_down'] == '1'){
											update_post_meta($status,'start_down',"yes");
											update_post_meta($status,'member_down','1');
									        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
									        update_post_meta($status,'down_url',esc_sql($_POST['down_url']));
									        update_post_meta($status,'hidden_content',esc_sql($_POST['hidden_content'])); 
										}elseif(wp_is_erphpdown_active() && $_POST['erphp_down'] == '2'){
											update_post_meta($status,'start_see',"yes");
											update_post_meta($status,'member_down','1');
									        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
										}elseif(wp_is_erphpdown_active() && $_POST['erphp_down'] == '3'){
											update_post_meta($status,'start_see2',"yes");
											update_post_meta($status,'member_down','1');
									        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
										}

										if(isset($_POST['member_down'])){
											update_post_meta($status,'member_down',$_POST['member_down']);
										}
									}

									if(_MBT('post_tougao_mail')){
										$headers = 'Content-Type: text/html; charset=' . get_option('blog_charset') . "\n";
			                			wp_mail(get_option('admin_email'), '[' . get_option('blogname') . ']'.__('有新投稿','mobantu'), '[' . get_option('blogname') . ']'.__('有新投稿','mobantu'), $headers);
									}

									if(_MBT('post_tougao_submit')){
										$msg = __('提交成功','mobantu');
										$link = get_permalink($status);
									}else{
										$msg = __('提交成功，请等待管理员审核！','mobantu');
									}
									$error = 0;
								}else{
									$msg = __('提交失败，请稍后重试！','mobantu');
								}
							}else{
								$msg = __('提交失败，请完善标题、分类、正文！','mobantu');
							}
						}
					}else{
						$msg = __('提交失败，请稍后重试！','mobantu');
					}
				}
			}
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg,
			"link"=>$link
		);
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action']=='user.tougao.draft'){
		$error = 1;$msg = __('保存失败','mobantu');$link = '';
		
		$post_tougao_role = _MBT('post_tougao_role')?_MBT('post_tougao_role'):'read';
		if(!current_user_can($post_tougao_role)){
			$msg = __('抱歉，暂无投稿权限','mobantu');
		}else{
			$post_tougao_time = _MBT('post_tougao_time');
			$last_post = $wpdb->get_var("SELECT post_date FROM $wpdb->posts WHERE post_author='".$current_user->ID."' AND post_type = 'post' and (post_status='pending' or post_status='publish') ORDER BY post_date DESC LIMIT 1");
		    if ( $post_tougao_time && (time() - strtotime($last_post) < $post_tougao_time*60) ){
		        $msg = __('这也太快了吧，先喝杯咖啡？','mobantu');
		    }else{
		    	if(isset($_POST['security_nonce']) && $_POST['security_nonce'] == $_SESSION['security_nonce']){
		    		if(isset($_POST['pid']) && $_POST['pid']){
			    		$thepost = get_post($_POST['pid']);
						$postauthor = $thepost->post_author;
						if($postauthor == $current_user->ID){
							$title =   esc_sql($_POST['title']) ;
							$content =  $_POST['content'] ;
							if($title && $content && $_POST['cat']){
								$cat =  esc_sql($_POST['cat']);
								$post_status = 'draft';
								$submit = array(
									'ID' => esc_sql($_POST['pid']),
									'post_title' => strip_tags($title),
									'post_author' => $current_user->ID,
									'post_content' => $content,
									'post_category' => array($cat),
									'post_status' => $post_status
								);
								$status = wp_update_post( $submit );
								
								if ($status != 0) {

									/*$taxonomys = get_term_meta($cat,'taxonomys',true);
									if($taxonomys){
										$post_texonomys = explode('|', $taxonomys);
						                foreach ($post_texonomys as $post_texonomy) { 
						                    $post_texonomy = explode(',', $post_texonomy);
						                    wp_set_object_terms( $status, $_POST[$post_texonomy[1]], $post_texonomy[1] );
						                }
						            }*/

									if(isset($_POST['image']) &&  $_POST['image']){
										update_post_meta($status,'_thumbnail_ext_url',esc_sql($_POST['image']));
									}

									if(isset($_POST['video']) &&  $_POST['video']){
										update_post_meta($status,'video',esc_sql($_POST['video']));
									}

									if(!_MBT('post_tougao_erphpdown')){
										update_post_meta($status,'erphp_down',$_POST['erphp_down']);
										if(wp_is_erphpdown_active() && $_POST['erphp_down'] == '1'){
											update_post_meta($status,'start_down',"yes");
											update_post_meta($status,'member_down','1');
									        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
									        update_post_meta($status,'down_url',esc_sql($_POST['down_url']));
									        update_post_meta($status,'hidden_content',esc_sql($_POST['hidden_content'])); 
										}elseif(wp_is_erphpdown_active() && $_POST['erphp_down'] == '2'){
											update_post_meta($status,'start_see',"yes");
											update_post_meta($status,'member_down','1');
									        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
										}elseif(wp_is_erphpdown_active() && $_POST['erphp_down'] == '3'){
											update_post_meta($status,'start_see2',"yes");
											update_post_meta($status,'member_down','1');
									        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
										}

										if(isset($_POST['member_down'])){
											update_post_meta($status,'member_down',$_POST['member_down']);
										}
									}

									
									$link = add_query_arg('post_id',$status,get_permalink(MBThemes_page("template/tougao.php")));
									$msg = __('保存成功！','mobantu');
									$error = 0;
								}else{
									$msg = __('保存失败，请稍后重试！','mobantu');
								}
							}else{
								$msg = __('保存失败，请完善标题、分类、正文！','mobantu');
							}
						}else{
							$msg = __('保存失败，请稍后重试！','mobantu');
						}
					}else{
						$title =   esc_sql($_POST['title']) ;
						$content =  $_POST['content'] ;

						if($title && $content && $_POST['cat']){
							$cat =  esc_sql($_POST['cat']);
							$post_status = 'draft';

							$submit = array(
								'post_title' => strip_tags($title),
								'post_author' => $current_user->ID,
								'post_content' => $content,
								'post_category' => array($cat),
								'post_status' => $post_status
							);
							$status = wp_insert_post( $submit );
							
							if ($status != 0) {

								/*$taxonomys = get_term_meta($cat,'taxonomys',true);
								if($taxonomys){
									$post_texonomys = explode('|', $taxonomys);
					                foreach ($post_texonomys as $post_texonomy) { 
					                    $post_texonomy = explode(',', $post_texonomy);
					                    wp_set_object_terms( $status, $_POST[$post_texonomy[1]], $post_texonomy[1] );
					                }
					            }*/

								if(isset($_POST['image']) &&  $_POST['image']){
									update_post_meta($status,'_thumbnail_ext_url',esc_sql($_POST['image']));
								}

								if(isset($_POST['video']) &&  $_POST['video']){
									update_post_meta($status,'video',esc_sql($_POST['video']));
								}

								if(!_MBT('post_tougao_erphpdown')){
									update_post_meta($status,'erphp_down',$_POST['erphp_down']);
									if(wp_is_erphpdown_active() && $_POST['erphp_down'] == '1'){
										update_post_meta($status,'start_down',"yes");
										update_post_meta($status,'member_down','1');
								        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
								        update_post_meta($status,'down_url',esc_sql($_POST['down_url']));
								        update_post_meta($status,'hidden_content',esc_sql($_POST['hidden_content'])); 
									}elseif(wp_is_erphpdown_active() && $_POST['erphp_down'] == '2'){
										update_post_meta($status,'start_see',"yes");
										update_post_meta($status,'member_down','1');
								        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
									}elseif(wp_is_erphpdown_active() && $_POST['erphp_down'] == '3'){
										update_post_meta($status,'start_see2',"yes");
										update_post_meta($status,'member_down','1');
								        update_post_meta($status,'down_price',esc_sql($_POST['down_price']));
									}

									if(isset($_POST['member_down'])){
										update_post_meta($status,'member_down',$_POST['member_down']);
									}
								}

								$link = add_query_arg('post_id',$status,get_permalink(MBThemes_page("template/tougao.php")));
								$msg = __('保存成功！','mobantu');
								$error = 0;
							}else{
								$msg = __('保存失败，请稍后重试！','mobantu');
							}
						}else{
							$msg = __('保存失败，请完善标题、分类、正文！','mobantu');
						}
					}
				}else{
					$msg = __('保存失败，请稍后重试！','mobantu');
				}
			}
		}
		
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg,
			"link"=>$link
		);
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action']=='tougao.tax'){
		$cat = esc_sql($_POST['cat']);
		$taxonomys = get_term_meta($cat,'taxonomys',true);
		if($taxonomys){
			$post_texonomys = explode('|', $taxonomys);
            foreach ($post_texonomys as $post_texonomy) { 
                $post_texonomy = explode(',', $post_texonomy);
                $taxonomy = get_terms( array(
                    'taxonomy' => $post_texonomy[1],
                    'hide_empty' => false,
                ) );
                if($taxonomy){
                	echo '<div class="tougao-select"><select name="'.$post_texonomy[1].'" class="postform"><option value="">选择'.$post_texonomy[0].'</option>';
                    foreach ( $taxonomy as $term ) {
                    	echo '<option value="'.$term->slug.'">'.$term->name .'</option>';
                    }
                    echo '</select></div> ';
                }
            }
		}
	}elseif($_POST['action'] == 'read'){
		$error = 0;$msg = '';
		update_user_meta($current_user->ID,'notice_read_time',date("Y-m-d H:i:s"));
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($_POST['action']=='ticket.new' && _MBT('ticket')){
		$item = trim(htmlspecialchars(esc_sql(trim($_POST['item'])), ENT_QUOTES));
        $content = trim(htmlspecialchars(esc_sql(trim($_POST['content'])), ENT_QUOTES));
        $email = trim(htmlspecialchars(esc_sql(trim($_POST['email'])), ENT_QUOTES));
        $image = $_POST['pic'];
        if(!empty($image)) $image = implode(',',$image);
        $number = createTicketNum();
        $error = 0;$msg = '';
        
        if(_MBT('ticket_new') && checkTicketCreateIsFast($uid) == 1){
            $error = 1;
            $msg = __("您尚有工单未完成，请先完成或直接回复",'mobantu');
        }else{
            if($item && $content){
                createNewTicket($uid,$item,$number,wp_trim_words( MBThemes_strip_tags( $content ), "50","..."),$content,esc_sql($image),$email);
                $to = get_option('admin_email');
                $subject = '[' . get_option('blogname') . ']'.__('有新工单','mobantu');
                $message = '' . "\r\n" . '    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse"><tbody><tr><td><table width="600" cellpadding="0" cellspacing="0" border="0" align="center" style="border-collapse:collapse"><tbody><tr><td><table width="100%" cellpadding="0" cellspacing="0" border="0"><tbody><tr><td width="73" align="left" valign="top" style="border-top:1px solid #d9d9d9;border-left:1px solid #d9d9d9;border-radius:5px 0 0 0"></td><td valign="top" style="border-top:1px solid #d9d9d9"><div style="font-size:14px;line-height:10px"><br><br><br><br></div><div style="font-size:18px;line-height:18px;color:#444;font-family:Microsoft Yahei">Hi, '.__('管理员','mobantu').'<br><br><br></div><div style="font-size:14px;line-height:22px;color:#444;font-weight:bold;font-family:Microsoft Yahei">'.__('刚才有人提交了新工单','mobantu').'</div><div style="font-size:14px;line-height:10px"><br><br></div><div style="font-size:14px;line-height:22px;color:#5DB408;font-weight:bold;font-family:Microsoft Yahei">'.__('工单ID：','mobantu').'</div><div style="font-size:14px;line-height:10px"><br></div><div style="font-size:14px;line-height:22px;color:#666;font-family:Microsoft Yahei">&nbsp; &nbsp;&nbsp; &nbsp; ' . $number . '</div><div style="font-size:14px;line-height:10px"><br><br><br><br></div><div style="text-align:center"><a href="' .add_query_arg(array("action"=>"ticket","id"=>$number),get_permalink(MBThemes_page("template/user.php"))) . '" target="_blank" style="text-decoration:none;color:#fff;display:inline-block;line-height:44px;font-size:18px;background-color:#ff5f33;border-radius:3px;font-family:Microsoft Yahei">&nbsp; &nbsp;&nbsp; &nbsp;'.__('查看工单','mobantu').'&nbsp; &nbsp;&nbsp; &nbsp;</a><br><br></div></td><td width="65" align="left" valign="top" style="border-top:1px solid #d9d9d9;border-right:1px solid #d9d9d9;border-radius:0 5px 0 0"></td></tr><tr><td style="border-left:1px solid #d9d9d9">&nbsp;</td><td align="left" valign="top" style="color:#999"><div style="font-size:8px;line-height:14px"><br><br></div><div style="min-height:1px;font-size:1px;line-height:1px;background-color:#e0e0e0">&nbsp;</div><div style="font-size:12px;line-height:20px;width:425px;font-family:Microsoft Yahei"><br>'.__('此邮件由系统自动发出，请勿回复！','mobantu').'</div></td><td style="border-right:1px solid #d9d9d9">&nbsp;</td></tr><tr><td colspan="3" style="border-bottom:1px solid #d9d9d9;border-right:1px solid #d9d9d9;border-left:1px solid #d9d9d9;border-radius:0 0 5px 5px"><div style="min-height:42px;font-size:42px;line-height:42px">&nbsp;</div></td></tr></tbody></table></td></tr><tr><td><div style="min-height:42px;font-size:42px;line-height:42px">&nbsp;</div></td></tr></tbody></table></td></tr></tbody></table>';
                $headers = 'Content-Type: text/html; charset=' . get_option('blog_charset') . "\n";
                wp_mail($to, $subject, $message, $headers);
                
            }else{
                $error = 1;
                $msg = __("系统超时，请稍后重试",'mobantu');
            }
        }
        
        $arr=array(
            "error"=>$error, 
            "msg"=>$msg,
            "id"=>$number
        ); 
        $jarr=json_encode($arr); 
        echo $jarr;
	}elseif($_POST['action']=='ticket.reply' && _MBT('ticket')){
        $number = trim(htmlspecialchars(esc_sql(trim($_POST['id'])), ENT_QUOTES));
        $content = trim(htmlspecialchars(esc_sql(trim($_POST['content'])), ENT_QUOTES));
        $image = $_POST['pic'];
        if($image) $image = implode(',',$image);
        $error = 0;$msg = '';$admin = 0;
        if($number && $content && checkTicketByNum($number) && checkTicketIsMine($number,$uid)){
            if(checkTicketIsClosed($number)){
                $error = 1;
                $msg = __('抱歉，工单已关闭','mobantu');
            }else{
                if(current_user_can('administrator')){
                    $result = createNewReplyByAdmin($uid,2,$number,$content,esc_sql($image));
                    _mbt_add_notice(getTicketByNum($number)->user_id, __('您好，您的工单有新回复。','mobantu').'<a href="' .add_query_arg(array("action"=>"ticket","id"=>$number),get_permalink(MBThemes_page("template/user.php"))) . '" target="_blank">'.__('查看详情','mobantu').'</a>', 'ticket_reply', $number);
                    $to = getTicketByNum($number)->email;
                    $subject = '[' . get_option('blogname') . ']'.__('您的工单有新回复','mobantu');
                    $message = '' . "\r\n" . '    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse"><tbody><tr><td><table width="600" cellpadding="0" cellspacing="0" border="0" align="center" style="border-collapse:collapse"><tbody><tr><td><table width="100%" cellpadding="0" cellspacing="0" border="0"><tbody><tr><td width="73" align="left" valign="top" style="border-top:1px solid #d9d9d9;border-left:1px solid #d9d9d9;border-radius:5px 0 0 0"></td><td valign="top" style="border-top:1px solid #d9d9d9"><div style="font-size:14px;line-height:10px"><br><br><br><br></div><div style="font-size:18px;line-height:18px;color:#444;font-family:Microsoft Yahei">Hi<br><br><br></div><div style="font-size:14px;line-height:22px;color:#444;font-weight:bold;font-family:Microsoft Yahei">'.__('您的工单有新回复','mobantu').'</div><div style="font-size:14px;line-height:10px"><br><br></div><div style="font-size:14px;line-height:22px;color:#5DB408;font-weight:bold;font-family:Microsoft Yahei">'.__('工单ID：','mobantu').'</div><div style="font-size:14px;line-height:10px"><br></div><div style="font-size:14px;line-height:22px;color:#666;font-family:Microsoft Yahei">&nbsp; &nbsp;&nbsp; &nbsp; ' . $number . '</div><div style="font-size:14px;line-height:10px"><br><br><br><br></div><div style="text-align:center"><a href="' .add_query_arg(array("action"=>"ticket","id"=>$number),get_permalink(MBThemes_page("template/user.php"))) . '" target="_blank" style="text-decoration:none;color:#fff;display:inline-block;line-height:44px;font-size:18px;background-color:#ff5f33;border-radius:3px;font-family:Microsoft Yahei">&nbsp; &nbsp;&nbsp; &nbsp;'.__('查看工单','mobantu').'&nbsp; &nbsp;&nbsp; &nbsp;</a><br><br></div></td><td width="65" align="left" valign="top" style="border-top:1px solid #d9d9d9;border-right:1px solid #d9d9d9;border-radius:0 5px 0 0"></td></tr><tr><td style="border-left:1px solid #d9d9d9">&nbsp;</td><td align="left" valign="top" style="color:#999"><div style="font-size:8px;line-height:14px"><br><br></div><div style="min-height:1px;font-size:1px;line-height:1px;background-color:#e0e0e0">&nbsp;</div><div style="font-size:12px;line-height:20px;width:425px;font-family:Microsoft Yahei"><br>'.__('此邮件由系统自动发出，请勿回复！','mobantu').'</div></td><td style="border-right:1px solid #d9d9d9">&nbsp;</td></tr><tr><td colspan="3" style="border-bottom:1px solid #d9d9d9;border-right:1px solid #d9d9d9;border-left:1px solid #d9d9d9;border-radius:0 0 5px 5px"><div style="min-height:42px;font-size:42px;line-height:42px">&nbsp;</div></td></tr></tbody></table></td></tr><tr><td><div style="min-height:42px;font-size:42px;line-height:42px">&nbsp;</div></td></tr></tbody></table></td></tr></tbody></table>';
                    $headers = 'Content-Type: text/html; charset=' . get_option('blog_charset') . "\n";
                    wp_mail($to, $subject, $message, $headers);
                    $admin = 1;
                }else{
                    $result = createNewReply($uid,1,$number,$content,esc_sql($image));
                    if(_MBT('ticket_reply')){
	                    $to = get_option('admin_email');
	                    $subject = '[' . get_option('blogname') . ']'.__('有工单待回复','mobantu');
	                    $message = '' . "\r\n" . '    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse"><tbody><tr><td><table width="600" cellpadding="0" cellspacing="0" border="0" align="center" style="border-collapse:collapse"><tbody><tr><td><table width="100%" cellpadding="0" cellspacing="0" border="0"><tbody><tr><td width="73" align="left" valign="top" style="border-top:1px solid #d9d9d9;border-left:1px solid #d9d9d9;border-radius:5px 0 0 0"></td><td valign="top" style="border-top:1px solid #d9d9d9"><div style="font-size:14px;line-height:10px"><br><br><br><br></div><div style="font-size:18px;line-height:18px;color:#444;font-family:Microsoft Yahei">Hi<br><br><br></div><div style="font-size:14px;line-height:22px;color:#444;font-weight:bold;font-family:Microsoft Yahei">'.__('有工单待回复','mobantu').'</div><div style="font-size:14px;line-height:10px"><br><br></div><div style="font-size:14px;line-height:22px;color:#5DB408;font-weight:bold;font-family:Microsoft Yahei">'.__('工单ID：','mobantu').'</div><div style="font-size:14px;line-height:10px"><br></div><div style="font-size:14px;line-height:22px;color:#666;font-family:Microsoft Yahei">&nbsp; &nbsp;&nbsp; &nbsp; ' . $number . '</div><div style="font-size:14px;line-height:10px"><br><br><br><br></div><div style="text-align:center"><a href="' .add_query_arg(array("action"=>"ticket","id"=>$number),get_permalink(MBThemes_page("template/user.php"))). '" target="_blank" style="text-decoration:none;color:#fff;display:inline-block;line-height:44px;font-size:18px;background-color:#ff5f33;border-radius:3px;font-family:Microsoft Yahei">&nbsp; &nbsp;&nbsp; &nbsp;'.__('查看工单','mobantu').'&nbsp; &nbsp;&nbsp; &nbsp;</a><br><br></div></td><td width="65" align="left" valign="top" style="border-top:1px solid #d9d9d9;border-right:1px solid #d9d9d9;border-radius:0 5px 0 0"></td></tr><tr><td style="border-left:1px solid #d9d9d9">&nbsp;</td><td align="left" valign="top" style="color:#999"><div style="font-size:8px;line-height:14px"><br><br></div><div style="min-height:1px;font-size:1px;line-height:1px;background-color:#e0e0e0">&nbsp;</div><div style="font-size:12px;line-height:20px;width:425px;font-family:Microsoft Yahei"><br>'.__('此邮件由系统自动发出，请勿回复！','mobantu').'</div></td><td style="border-right:1px solid #d9d9d9">&nbsp;</td></tr><tr><td colspan="3" style="border-bottom:1px solid #d9d9d9;border-right:1px solid #d9d9d9;border-left:1px solid #d9d9d9;border-radius:0 0 5px 5px"><div style="min-height:42px;font-size:42px;line-height:42px">&nbsp;</div></td></tr></tbody></table></td></tr><tr><td><div style="min-height:42px;font-size:42px;line-height:42px">&nbsp;</div></td></tr></tbody></table></td></tr></tbody></table>';
	                    $headers = 'Content-Type: text/html; charset=' . get_option('blog_charset') . "\n";
	                    wp_mail($to, $subject, $message, $headers);
                    }
                }
                if($result){
                    $msg = __('回复成功','mobantu');
                }else{
                    $error = 1;
                    $msg = __('回复失败，请稍后重试','mobantu');
                }
            }
        }else{
            $error = 1;
            $msg = __('回复异常','mobantu');
        }
        
        $arr=array(
            "error"=>$error, 
            "msg"=>$msg,
            "admin"=>$admin,
            "pic"=>explode(',',$image),
            "avatar"=>get_avatar($uid,50),
            "content"=>$content
        ); 
        $jarr=json_encode($arr); 
        echo $jarr;
    }elseif($_POST['action']=='ticket.close' && _MBT('ticket')){
        $error = 0;$msg = '';
        if($_POST['id'] && checkTicketByNum($_POST['id']) && current_user_can('administrator')){
            if(updateTicketClosed(esc_sql(trim($_POST['id'])))){
                $msg = __('提交成功','mobantu');
            }else{
                $error = 1;
                $msg = __('提交失败，请稍后重试','mobantu');
            }
        }else{
            $error = 1;
                $msg = __('工单异常','mobantu');
        }
        
        $arr=array(
            "error"=>$error, 
            "msg"=>$msg
        ); 
        $jarr=json_encode($arr); 
        echo $jarr;
    }elseif($_POST['action']=='ticket.solved' && _MBT('ticket')){
        $error = 0;$msg = '';
        if($_POST['id'] && checkTicketByNum($_POST['id']) && checkTicketIsMine($_POST['id'],$uid)){
            if(updateTicketSolved(esc_sql(trim($_POST['id'])))){
                $msg = __('提交成功','mobantu');
            }else{
                $error = 1;
                $msg = __('提交失败，请稍后重试','mobantu');
            }
        }else{
            $error = 1;
                $msg = __('工单异常','mobantu');
        }
        
        $arr=array(
            "error"=>$error, 
            "msg"=>$msg
        ); 
        $jarr=json_encode($arr); 
        echo $jarr;
    }elseif($_POST['action']=='ticket.upload' && _MBT('ticket')){
        $error = 0;$msg = '';
        if(_MBT('ticket_img')){
            if(is_uploaded_file($_FILES['file']['tmp_name']) && is_user_logged_in()){
                $vname = $_FILES['file']['name'];
                $arrType=array('image/jpg','image/png','image/jpeg','image/gif');
                $uploaded_ext  = substr( $vname, strrpos( $vname, '.' ) + 1);
                $uploaded_type = $_FILES[ 'file' ][ 'type' ];
                $uploaded_tmp  = $_FILES[ 'file' ][ 'tmp_name' ];
                $uploaded_size  = $_FILES[ 'file' ][ 'size' ];

                if ($vname != "") {
                    if ($uploaded_size > 1024*1024) {
                        $msg = __('上传的图片不能大于1M','mobantu');
                        $error = 1;
                    }else{
                        if (in_array($uploaded_type,$arrType) && (strtolower( $uploaded_ext ) == 'jpg' || strtolower( $uploaded_ext ) == 'jpeg' || strtolower( $uploaded_ext ) == 'png' || strtolower( $uploaded_ext ) == 'gif')) {

                            //上传路径
                            $upfile = '../../../../wp-content/uploads/ticket/'.date("ym").'/';
                            if(!file_exists($upfile)){  mkdir($upfile,0777,true);} 

                            $filename = md5(date("His").mt_rand(100,999)).strrchr($vname,'.');

                            $file_path = '../../../../wp-content/uploads/ticket/'.date("ym").'/'. $filename;

                            if( $uploaded_type == 'image/jpeg' ) {
                                $img = imagecreatefromjpeg( $uploaded_tmp );
                                imagejpeg( $img, $file_path, 100);
                            }elseif( $uploaded_type == 'image/gif' ) {
                                $img = imagecreatefromgif( $uploaded_tmp );
                                imagegif( $img, $file_path);
                            }else {
                                $img = imagecreatefrompng( $uploaded_tmp );
                                imagepng( $img, $file_path);
                            }
                            imagedestroy( $img );

                            $image = home_url().'/wp-content/uploads/ticket/'.date("ym").'/'. $filename;

                        }else{
                            $msg = __('图片格式只支持.jpeg .jpg .png .gif','mobantu');
                            $error = 1;
                        }
                    }
                }

            }
            
            $arr=array(
                "error"=>$error, 
                "msg"=>$msg,
                "img"=>$image
            ); 
            $jarr=json_encode($arr); 
            echo $jarr;
        }
    }	
}
exit;