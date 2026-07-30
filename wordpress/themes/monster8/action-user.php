<?php
session_start();
require( dirname(__FILE__) . '/../../../../wp-load.php' ); 
date_default_timezone_set('Asia/Shanghai');
if(isset($_POST['action']) && is_user_logged_in()){ 
	global $current_user;
	$action = $_POST['action'];
	$error = 0;$msg = '';
	if($action == 'photo'){
		if(is_uploaded_file($_FILES['avatarphoto']['tmp_name'])){
			$vname = $_FILES['avatarphoto']['name'];
			$arrType=array('image/jpg','image/png','image/jpeg');
			$uploaded_ext  = substr( $vname, strrpos( $vname, '.' ) + 1);
			$uploaded_type = $_FILES[ 'avatarphoto' ][ 'type' ];
			$uploaded_size = $_FILES['avatarphoto']['size'];
			$uploaded_tmp  = $_FILES[ 'avatarphoto' ][ 'tmp_name' ];
			if ($vname != "") {
				if (in_array($uploaded_type,$arrType) && (strtolower( $uploaded_ext ) == 'jpg' || strtolower( $uploaded_ext ) == 'jpeg' || strtolower( $uploaded_ext ) == 'png' )) {

					if ($uploaded_size > 102400) {
						echo "2";
					}elseif(!(in_array($uploaded_type,$arrType) && (strtolower( $uploaded_ext ) == 'jpg' || strtolower( $uploaded_ext ) == 'jpeg' || strtolower( $uploaded_ext ) == 'png' ))){
						echo "3";
					}else{
						//上传路径
						$upfile = '../../../../wp-content/uploads/avatar/';
						if(!file_exists($upfile)){  mkdir($upfile,0777,true);} 

						$userid = wp_get_current_user()->ID;

						$filename = md5($userid).strrchr($vname,'.');

						$file_path = '../../../../wp-content/uploads/avatar/'. $filename;

						if( $uploaded_type == 'image/jpeg' ) {
				            $img = imagecreatefromjpeg( $uploaded_tmp );
				            imagejpeg( $img, $file_path, 100);
				        }else {
				            $img = imagecreatefrompng( $uploaded_tmp );
				            imagepng( $img, $file_path, 9);
				        }
				        imagedestroy( $img );
				        update_user_meta($userid, 'photo', get_bloginfo('siteurl').'/wp-content/uploads/avatar/'.$filename);
				        echo "1";
				    }

				}
			}else{
				echo "0";
			}	
		}
	}elseif($action == 'info'){
		$userdata = array();
		$userdata['ID'] = $current_user->ID;
		$userdata['nickname'] = str_replace(array('<','>','&','"','\'','#','^','*','_','+','$','?','!'), '', esc_sql(trim($_POST['name'])) );
		$userdata['display_name'] = str_replace(array('<','>','&','"','\'','#','^','*','_','+','$','?','!'), '', esc_sql(trim($_POST['name'])) );
		$userdata['description'] = $wpdb->escape(trim($_POST['desc']));
		wp_update_user($userdata);

		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($action == 'pass'){
    	$password = esc_sql($_POST['old']); 
		if ( !wp_check_password( $password, $current_user->data->user_pass, $current_user->ID ) ) {    
			$error = 1;$msg = '旧密码错误';   
		}else{
			$userdata = array();
			$userdata['ID'] = $current_user->ID;
			$userdata['user_pass'] = esc_sql($_POST['new']);
			wp_update_user($userdata);
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($action == 'captcha.email'){
		$email = apply_filters( 'user_registration_email', esc_sql($_POST['email']) );
		if ( $email == '' ) {
			$error = 1;
			$msg = '邮箱不能为空';
		} elseif ( $email == $current_user->user_email) {
			$error = 1;
			$msg = '请输入一个新邮箱账号';
		}elseif ( email_exists( $email ) && $email != $current_user->user_email) {
			$error = 1;
			$msg = '邮箱已被使用';
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
			$_SESSION['Monster8_email_captcha']=strtolower($_dscode);
			$_SESSION['Monster8_email_new']=$email;
			$message = '验证码：'.$_dscode;   
			wp_mail($email, '验证码-修改邮箱-'.get_bloginfo('name'), $message);
		}
		
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($action == 'email'){
		$email = apply_filters( 'user_registration_email', esc_sql($_POST['email']) );
		$captcha = $_POST['captcha'];
		if ( $email == '' ) {
			$error = 1;
			$msg = '邮箱不能为空';
		} elseif ( $email == $current_user->user_email) {
			$error = 1;
			$msg = '请输入一个新邮箱账号';
		}elseif ( email_exists( $email ) && $email != $current_user->user_email) {
			$error = 1;
			$msg = '邮箱已被使用';
		}else{
			if(empty($captcha) || empty($_SESSION['Monster8_email_captcha']) || trim(strtolower($captcha)) != $_SESSION['Monster8_email_captcha']){
				$error = 1;
				$msg .= '验证码错误';
			}elseif($_SESSION['Monster8_email_new'] != $email){
				$error = 1;
				$msg = '验证码错误';
			}else{
				unset($_SESSION['Monster8_email_captcha']);
				unset($_SESSION['Monster8_email_new']);
				$userdata = array();
				$userdata['ID'] = $current_user->ID;
				$userdata['user_email'] = $email;
				wp_update_user($userdata);
			}
		}
		
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($action == 'vip'){
		$payment = '<h4>请选择支付方式</h4>';
		$userType=isset($_POST['type']) && is_numeric($_POST['type']) ?intval($_POST['type']) :0;
		$oldUserType = getUsreMemberTypeById($current_user->ID);
		if($oldUserType == '10'){
			$error = 1;$msg = '您已经是终身VIP，请勿重复升级！';
		}else{
			if($userType >5 && $userType < 11){
				$okMoney=erphpGetUserOkMoney();
				$priceArr=array('6'=>'erphp_day_price','7'=>'erphp_month_price','8'=>'erphp_quarter_price','9'=>'erphp_year_price','10'=>'erphp_life_price');
				$priceType=$priceArr[$userType];
				$price=get_option($priceType);
				if(empty($price) || $price == ''){
					$error = 1;$msg = 'VIP价格错误';
				}elseif($okMoney < $price){
					if(_themer('vip_just_buy')){
						$error = 3;$msg = '余额不足，直接在线支付';
						if(get_option('ice_weixin_mchid')){
							$payment .= '<a href="'.constant("erphpdown").'payment/weixin.php?ice_type='.$userType.'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="iconfont icon-wxpay-color"></i> 微信支付</a>';
						}
						if(get_option('ice_ali_partner') || get_option('ice_ali_app_id')){
							$payment .= '<a href="'.constant("erphpdown").'payment/alipay.php?ice_type='.$userType.'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="iconfont icon-alipay-color"></i> 支付宝</a>';
						}
						if(get_option('erphpdown_f2fpay_id')){
							$payment .= '<a href="'.constant("erphpdown").'payment/f2fpay.php?ice_type='.$userType.'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="iconfont icon-alipay-color"></i> 支付宝</a>';
						}
						if(get_option('erphpdown_xhpay_appid32')){
							$payment .= '<a href="'.constant("erphpdown").'payment/xhpay3.php?ice_type='.$userType.'&type=1" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="iconfont icon-alipay-color"></i> 支付宝</a>';
						}
						if(get_option('erphpdown_xhpay_appid31')){
							$payment .= '<a href="'.constant("erphpdown").'payment/xhpay3.php?ice_type='.$userType.'&type=2" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="iconfont icon-wxpay-color"></i> 微信支付</a>';
						}
						if(get_option('erphpdown_codepay_appid')){
							$payment .= '<a href="'.constant("erphpdown").'payment/codepay.php?ice_type='.$userType.'&type=1" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="iconfont icon-alipay-color"></i> 支付宝</a>';
							$payment .= '<a href="'.constant("erphpdown").'payment/codepay.php?ice_type='.$userType.'&type=3" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="iconfont icon-wxpay-color"></i> 微信支付</a>';
							$payment .= '<a href="'.constant("erphpdown").'payment/codepay.php?ice_type='.$userType.'&type=2" class="erphpdown-type-link erphpdown-type-qqpay" target="_blank"><i class="iconfont icon-qq"></i> QQ钱包</a>';
						}
						if(get_option('erphpdown_paypy_key')){
							if(!get_option('erphpdown_paypy_wxpay')){$payment .= '<a href="'.constant("erphpdown").'payment/paypy.php?ice_type='.$userType.'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="iconfont icon-wxpay-color"></i> 微信支付</a>';}
							if(!get_option('erphpdown_paypy_alipay')){$payment .= '<a href="'.constant("erphpdown").'payment/paypy.php?ice_type='.$userType.'&type=alipay" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="iconfont icon-alipay-color"></i> 支付宝</a>';}
						}
						if(get_option('erphpdown_epay_id')){
							if(!get_option('erphpdown_epay_wxpay')){
								$payment .= '<a href="'.constant("erphpdown").'payment/epay.php?ice_type='.$userType.'&type=wxpay" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="iconfont icon-wxpay-color"></i> 微信支付</a>';
							}
							if(!get_option('erphpdown_epay_alipay')){
								$payment .= '<a href="'.constant("erphpdown").'payment/epay.php?ice_type='.$userType.'&type=alipay" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="iconfont icon-alipay-color"></i> 支付宝</a>';
							}
						}
						if(get_option('erphpdown_rpay_id')){
							if(!get_option('erphpdown_rpay_wxpay')){
								$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=wxpay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="iconfont icon-wxpay-color"></i> 微信支付</a>';
							}
							if(!get_option('erphpdown_rpay_alipay')){
								$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=alipay&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="iconfont icon-alipay-color"></i> 支付宝</a>';
							}
							if(!get_option('erphpdown_rpay_stripe')){
								$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=stripe&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-stripe" target="_blank"><i class="iconfont icon-credit-card"></i> 信用卡</a>';
							}
							if(!get_option('erphpdown_rpay_paypal')){
								$payment .= '<a href="'.constant("erphpdown").'payment/rpay.php?ice_type='.$userType.'&type=paypal&timestamp='.time().'" class="erphpdown-type-link erphpdown-type-paypal" target="_blank"><i class="iconfont icon-paypal"></i> Paypal</a>';
							}
						}
						if(get_option('erphpdown_vpay_key')){
							if(!get_option('erphpdown_vpay_wxpay')){
								$payment .= '<a href="'.constant("erphpdown").'payment/vpay.php?ice_type='.$userType.'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="iconfont icon-wxpay-color"></i> 微信支付</a>';
							}
							if(!get_option('erphpdown_vpay_alipay')){
								$payment .= '<a href="'.constant("erphpdown").'payment/vpay.php?ice_type='.$userType.'&type=2" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="iconfont icon-alipay-color"></i> 支付宝</a>';
							}
						}
						if(get_option('erphpdown_payjs_appid')){
							$payment .= '<a href="'.constant("erphpdown").'payment/payjs.php?ice_type='.$userType.'" class="erphpdown-type-link erphpdown-type-wxpay" target="_blank"><i class="iconfont icon-wxpay-color"></i> 微信支付</a>';
							$payment .= '<a href="'.constant("erphpdown").'payment/payjs.php?ice_type='.$userType.'&type=alipay" class="erphpdown-type-link erphpdown-type-alipay" target="_blank"><i class="iconfont icon-alipay-color"></i> 支付宝</a>';
						}
						if(function_exists('plugin_check_stripe')){
						if(plugin_check_stripe() && get_option('erphpdown_stripe_pk')){
							$payment .= '<a href="'.ERPHPDOWN_STRIPE_URL.'/stripe.php?ice_type='.$userType.'" class="erphpdown-type-link erphpdown-type-stripe" target="_blank"><i class="iconfont icon-credit-card"></i> 信用卡</a>';
						}}
						if(get_option('ice_payapl_api_uid')){
							$payment .= '<a href="'.constant("erphpdown").'payment/paypal.php?ice_type='.$userType.'" class="erphpdown-type-link erphpdown-type-paypal" target="_blank"><i class="iconfont icon-paypal"></i> Paypal</a>';
						}
					}else{
						$error = 1;$msg = '余额不足，请先充值';
					}

				}elseif($okMoney >=$price){
					if(erphpSetUserMoneyXiaoFei($price)){
						if(userPayMemberSetData($userType)){
							addVipLog($price, $userType);

							if(!get_option('erphp_vip_ref_no')){
								$EPD = new EPD();
								$EPD->doAff($price, $current_user->ID);
							}

							if(get_option('erphp_remind')){
								$headers = 'Content-Type: text/html; charset=' . get_option('blog_charset') . "\n";
								$typeName = getVipTypeName($userType);
								wp_mail(get_option('admin_email'), '['.get_bloginfo('name').']VIP订单提醒 - '.$typeName, '用户'.$current_user->user_login.'消费'.$price.get_option('ice_name_alipay').'购买了'.$typeName, $headers);
							}
							
						}else{
							$error = 1;$msg = '升级失败';
						}
					}else{
						$error = 1;$msg = '升级失败';
					}
				}else{
					$error = 1;$msg = '升级失败';
				}
			}else{
				$error = 1;$msg = '升级失败';
			}
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg,
			"payment"=>$payment
		); 
		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($action == 'like'){
		$pid = intval($_POST['pid']);
		if(_themer_check_collect($pid)){
			$sql = $wpdb->prepare("delete from ".$wpdb->prefix ."collects where user_id = %d and post_id = %d", $current_user->ID, $pid);
			$wpdb->query($sql);
			$g=(int)get_post_meta($pid,"like",true);
			if($g < 1)$g=1;
			update_post_meta($pid,"like",$g-1);
			$result = "2";
		}else{
			$sql = $wpdb->prepare("INSERT INTO ".$wpdb->prefix ."collects(user_id,post_id,create_time) VALUES(%d,%d,%s)", $current_user->ID, $pid, date("Y-m-d H:i:s"));
			$wpdb->query($sql);
			$g=(int)get_post_meta($pid,"like",true);
			if(!$g)$g=0;
			update_post_meta($pid,"like",$g+1);
			$result = "1";
		}
		$arr=array(
			"result"=>$result
		); 
		$jarr=json_encode($arr); 
		echo $jarr;
	}elseif($action == 'card'){
		$num = esc_sql($_POST['num']);
		$pass = esc_sql($_POST['pass']);
		$result = checkDoCardResult($num,$pass);
		if($result == '5'){
			$error = 1;
			$msg = '充值卡不存在！';
		}elseif($result == '0'){
			$error = 1;
			$msg = '充值卡已被使用！';
		}elseif($result == '2'){
			$error = 1;
			$msg = '充值卡密码错误！';
		}elseif($result == '1'){
			
		}else{
			$error = 1;
			$msg = '系统错误，请稍后重试！';
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);

		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($action == 'card_vip'){
		$num = esc_sql($_POST['num']);
		$pass = isset($_POST['pass']) ? esc_sql($_POST['pass']) : '';
		$result = checkDoVipCardResult($num,$pass);
		if($result == '3'){
			$error = 1;
			$msg = '充值卡不存在！';
		}elseif($result == '0'){
			$error = 1;
			$msg = '充值卡已被使用！';
		}elseif($result == '2'){
			$error = 1;
			$msg = '充值卡已过期！';
		}elseif($result == '1'){
			
		}else{
			$error = 1;
			$msg = '系统错误，请稍后重试！';
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);

		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($action == 'withdrawal'){
		$error = 0;$msg = '';
    	$okMoney = erphpGetUserOkMoney();
    	$ice_alipay = sanitize_text_field($_POST['alipay']);
		$ice_name   = sanitize_text_field($_POST['name']);
		$ice_money  = isset($_POST['money']) && is_numeric($_POST['money']) ? floatval($_POST['money']) : 0;
		if($ice_money >0){
			if($ice_money<get_option('ice_ali_money_limit'))
			{
				$error = 1;
				$msg = '提现金额至少得满'.get_option('ice_ali_money_limit').get_option('ice_name_alipay');
			}
			elseif(empty($ice_name) || empty($ice_alipay))
			{
				$error = 1;
				$msg = '请输入支付宝帐号和姓名！';
			}
			elseif($ice_money > $okMoney)
			{
				$error = 1;
				$msg = '余额不足';
			}
			else
			{
				$sql = $wpdb->prepare("insert into ".$wpdb->iceget."(ice_money,ice_user_id,ice_time,ice_success,ice_success_time,ice_note,ice_name,ice_alipay) values (%f,%d,%s,%d,%s,%s,%s,%s)", $ice_money, $current_user->ID, date("Y-m-d H:i:s"), 0, date("Y-m-d H:i:s"), '', $ice_name, $ice_alipay);
				if($wpdb->query($sql))
				{	
					addUserMoney($current_user->ID, '-'.$ice_money);
				}
				else
				{
					$error = 1;
					$msg = '系统错误请稍后重试！';
				}
			}
		}else{
			$error = 1;
			$msg = '你想干嘛？';
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);
		$jarr=json_encode($arr); 
		echo $jarr; 

	}elseif($action == 'cart'){
		$error = 0;$repeat = 0;$count = 1;$total = 0;$monster8_cart = $_POST['pid'];
		$pid = esc_sql($_POST['pid']);
		$userType=getUsreMemberType();

		$memberDown=get_post_meta($pid, 'member_down',TRUE);
		if($memberDown == 4 || $memberDown == 15 || $memberDown == 8 || $memberDown == 9){
			$error = 1;
			$msg = '加入购物车失败！';
		}elseif(!$userType && ($memberDown==10 || $memberDown==11 || $memberDown==12)){
			$error = 1;
			$msg = '加入购物车失败！';
		}else{

			if(isset($_COOKIE['monster8_cart'])){
				$monster8_cart = $_COOKIE['monster8_cart'];
				if(!strstr(','.$monster8_cart.',', ','.$pid.',')){
					$monster8_cart = $monster8_cart.','.$pid;
					setCookie('monster8_cart', $monster8_cart, time()+3600*24, '/');
				}
				else
					$repeat = 1;
			}else{
				setCookie('monster8_cart', $monster8_cart, time()+3600*24, '/');
			}

			$arr = explode(',', $monster8_cart);
			$count = count($arr);

			foreach ($arr as $cart_id) {
				$price=get_post_meta($cart_id, 'down_price', true);
				$total += $price;
			}
		}

		$arrj=array(
			"error"=>$error, 
			"msg"=>$msg,
			"total"=>$total,
			"count" =>$count,
			"repeat"=>$repeat,
			"ids"=>$monster8_cart
		);

		$jarr=json_encode($arrj); 
		echo $jarr;
	}elseif($action == 'cart2'){
		$error = 0;$count = 0;$total = 0;$arr = array();$monster8_cart = '';
		$pid = esc_sql($_POST['pid']);
		if(isset($_COOKIE['monster8_cart'])){
			$monster8_cart = $_COOKIE['monster8_cart'];
			$arr = explode(',', $monster8_cart);
			$search = array_search($pid, $arr);
			if($search !== false){
				array_splice($arr, $search, 1);
			}
			$count = count($arr);
			if($count){
				$monster8_cart = implode(',', $arr);
				setCookie('monster8_cart', $monster8_cart, time()+3600*24, '/');
			}else{
				setCookie('monster8_cart', '', time()-1, '/');
			}
		}

		if($arr){
			foreach ($arr as $cart_id) {
				$price=get_post_meta($cart_id, 'down_price', true);
				$total += $price;
			}
		}

		$arrj=array(
			"error"=>$error, 
			"msg"=>$msg,
			"total"=>$total,
			"count" =>$count,
			"ids"=>$monster8_cart
		);

		$jarr=json_encode($arrj); 
		echo $jarr;
	}elseif($action == 'unbind'){
		$error = 0;$msg = '';
		if($_POST['type'] == 'weixin'){
			$wpdb->query("update $wpdb->users set weixinid='' where ID=".$current_user->ID);
		}elseif($_POST['type'] == 'weibo'){
			$wpdb->query("update $wpdb->users set sinaid='' where ID=".$current_user->ID);
		}elseif($_POST['type'] == 'qq'){
			$wpdb->query("update $wpdb->users set qqid='' where ID=".$current_user->ID);
		}else{
			$error = 1;
			$msg = '解绑失败';
		}
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);
		$jarr=json_encode($arr); 
		echo $jarr; 
	}elseif($action == 'read'){
		$error = 0;$msg = '';
		update_user_meta($current_user->ID,'notice_read_time',date("Y-m-d H:i:s"));
		$arr=array(
			"error"=>$error, 
			"msg"=>$msg
		);
		$jarr=json_encode($arr); 
		echo $jarr;
	}
}