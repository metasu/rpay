<?php
/*
	template name: 在线充值
	description: template for mobantu.com monster8 theme 
*/
if(!is_user_logged_in()){
	wp_redirect(get_permalink(_themer_page('template/login.php')));
}
global $current_user;
if(isset($_POST['paytype']) && $_POST['paytype']){
	$paytype=intval($_POST['paytype']);
	$doo = 1;
	
	if($paytype==1)
	{
		$url=constant("erphpdown")."payment/alipay.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
	}
	elseif($paytype==2)
	{
		$url=constant("erphpdown")."payment/f2fpay.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
	}
	elseif($paytype==3)
	{
		if(erphpdown_is_weixin() && get_option('ice_weixin_app')){
			$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.get_option('ice_weixin_appid').'&redirect_uri='.urlencode(constant("erphpdown")).'payment%2Fweixin.php%3Fice_money%3D'.esc_sql($_POST['ice_money']).'&response_type=code&scope=snsapi_base&state=STATE&connect_redirect=1#wechat_redirect';
		}else{
			$url=constant("erphpdown")."payment/weixin.php?ice_money=".esc_sql($_POST['ice_money']);
		}
	}
	elseif($paytype==4)
	{
		$url=constant("erphpdown")."payment/paypal.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
	}
	elseif($paytype==52)
	{
		$url=constant("erphpdown")."payment/paypy.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
	}
	elseif($paytype==51)
	{
		$url=constant("erphpdown")."payment/paypy.php?ice_money=".$_POST['ice_money']."&type=alipay"."&timestamp=".time();
	}
	elseif($paytype==61)
	{
		$url=constant("erphpdown")."payment/xhpay3.php?ice_money=".$_POST['ice_money']."&type=2"."&timestamp=".time();
	}
	elseif($paytype==62)
	{
		$url=constant("erphpdown")."payment/xhpay3.php?ice_money=".$_POST['ice_money']."&type=1"."&timestamp=".time();
	}elseif($paytype==71)
    {
        $url=constant("erphpdown")."payment/codepay.php?ice_money=".$_POST['ice_money']."&type=1"."&timestamp=".time();
    }elseif($paytype==72)
    {
        $url=constant("erphpdown")."payment/codepay.php?ice_money=".$_POST['ice_money']."&type=3"."&timestamp=".time();
    }elseif($paytype==73)
    {
        $url=constant("erphpdown")."payment/codepay.php?ice_money=".$_POST['ice_money']."&type=2"."&timestamp=".time();
    }elseif($paytype==81)
	{
		$url=constant("erphpdown")."payment/epay.php?ice_money=".$_POST['ice_money']."&type=alipay"."&timestamp=".time();
	}elseif($paytype==82)
	{
		$url=constant("erphpdown")."payment/epay.php?ice_money=".$_POST['ice_money']."&type=wxpay"."&timestamp=".time();
	}elseif($paytype==83)
	{
		$url=constant("erphpdown")."payment/epay.php?ice_money=".$_POST['ice_money']."&type=qqpay"."&timestamp=".time();
	}elseif($paytype==101)
	{
		$url=constant("erphpdown")."payment/vpay.php?ice_money=".$_POST['ice_money']."&type=2"."&timestamp=".time();
	}elseif($paytype==102)
	{
		$url=constant("erphpdown")."payment/vpay.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
	}elseif($paytype==92)
	{
		$url=constant("erphpdown")."payment/payjs.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
	}elseif($paytype==91)
	{
		$url=constant("erphpdown")."payment/payjs.php?ice_money=".$_POST['ice_money']."&type=alipay"."&timestamp=".time();
	}elseif($paytype==100)
	{
		$url=home_url('?epd_r64='.base64_encode('stripe-'.$_POST['ice_money'].'-'.time()));
	}elseif($paytype==111)
	{
		$url=constant("erphpdown")."payment/easepay.php?ice_money=".$_POST['ice_money']."&type=alipay"."&timestamp=".time();
	}elseif($paytype==112)
	{
		$url=constant("erphpdown")."payment/easepay.php?ice_money=".$_POST['ice_money']."&type=wxpay"."&timestamp=".time();
	}elseif($paytype==113)
	{
		$url=constant("erphpdown")."payment/easepay.php?ice_money=".$_POST['ice_money']."&type=usdt"."&timestamp=".time();
	}elseif($paytype==120)
	{
		$url=home_url('?epd_r64='.base64_encode('usdt-'.$_POST['ice_money'].'-'.time()));
	}elseif($paytype==141)
	{
		$url=constant("erphpdown")."payment/rpay.php?ice_money=".$_POST['ice_money']."&type=alipay"."&timestamp=".time();
	}elseif($paytype==142)
	{
		$url=constant("erphpdown")."payment/rpay.php?ice_money=".$_POST['ice_money']."&type=wxpay"."&timestamp=".time();
	}elseif($paytype==143)
	{
		$url=constant("erphpdown")."payment/rpay.php?ice_money=".$_POST['ice_money']."&type=stripe"."&timestamp=".time();
	}elseif($paytype==144)
	{
		$url=constant("erphpdown")."payment/rpay.php?ice_money=".$_POST['ice_money']."&type=paypal"."&timestamp=".time();
	}elseif(function_exists('plugin_check_ecpay') && plugin_check_ecpay() && $paytype==130)
	{
		$url=ERPHPDOWN_ECPAY_URL."/ecpay.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
	}
	else{
		
	}
	if($doo) wp_redirect($url);
	exit;
}
get_header();
$moneyName = get_option('ice_name_alipay');
?>
<ui-view>
	<account-settings>
		<div id="page">   
			<div class="page-account-settings">       
				<div class="section">           
					<div class="inset-header naked">               
						<div class="container mobile-fluid">                   
							<div class="wrapper"> 
								<h2 class="title"><?php the_title();?></h2>                   
							</div>
						</div>           
					</div> 
					<div class="section-content container mobile-fluid">                   
						<div class="accordian">      
							<?php if(!_themer('recharge_default')){?>    
							<div class="pane pane-profile default-pane pane-active">            
								<h3 class="pane-title">支付充值 <span class="icon"><svg-icon-arrow-down><svg width="16px" height="16px" viewBox="0 0 32 32" version="1.1">    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">        <g fill="#303030">            <path d="M22,14 L12.001382,3.99666359 L8.00138199,7.99666359 L16.0004718,15.9995282 L8,24 L12,28 L24,16 L22,14 Z" transform="translate(16.000000, 15.998332) rotate(-270.000000) translate(-16.000000, -15.998332) "></path>        </g>    </g></svg></svg-icon-arrow-down></span></h3>
								<div class="pane-contents">              
									<form method="POST" class="inputs ng-pristine ng-valid ng-valid-required ng-valid-email ng-valid-pattern ng-valid-maxlength focused loaded animate">             
										<div class="input focused loaded animate" style="margin-bottom:10px;">                  
											<label for="ice_money">金额（1 元 = <?php echo get_option('ice_proportion_alipay')?> <?php echo $moneyName;?>）</label>  
											<span class="error"></span>                
											<input type="text" name="ice_money" id="ice_money" class="ng-pristine ng-untouched ng-valid ng-empty" value="">
											<p class="desc">1 元 = <?php echo get_option('ice_proportion_alipay')?> <?php echo $moneyName;?></p>             
										</div>
										<div class="input focused loaded animate" style="background: transparent;margin-bottom:0">
											<!--label>方式</label-->
											<div class="methods">
												<?php 
													$erphpdown_recharge_order = get_option('erphpdown_recharge_order');
			            							if($erphpdown_recharge_order){
			            								$erphpdown_recharge_order_arr = explode(',', str_replace('，', ',', trim($erphpdown_recharge_order)));
								            			$pi = 0;
								            			foreach ($erphpdown_recharge_order_arr as $payment) {
								            				if($pi == 0) $checked = ' checked'; else $checked = '';
								            				switch ($payment) {
								            					case 'alipay':
								            						echo '<input type="radio" id="paytype1"'.$checked.' class="paytype" name="paytype" value="1" /> 支付宝';
								            						break;
								            					case 'wxpay':
								            						echo '<input type="radio" id="paytype3" class="paytype"'.$checked.' name="paytype" value="3" /> 微信支付';
								            						break;
								            					case 'f2fpay':
								            						echo '<input type="radio" id="paytype2" class="paytype"'.$checked.' name="paytype" value="2" /> 支付宝';
								            						break;
								            					case 'paypal':
								            						echo '<input type="radio" id="paytype4" class="paytype"'.$checked.' name="paytype" value="4" /> Paypal';
								            						break;
								            					case 'paypy-wx':
								            						echo '<input type="radio" id="paytype52" class="paytype" name="paytype" value="52"'.$checked.' /> 微信支付';
								            						break;
								            					case 'paypy-ali':
								            						echo '<input type="radio" id="paytype51" class="paytype" name="paytype" value="51"'.$checked.' /> 支付宝';
								            						break;
								            					case 'payjs-wx':
								            						echo '<input type="radio" id="paytype92" class="paytype" name="paytype" value="92"'.$checked.' /> 微信支付';
								            						break;
								            					case 'payjs-ali':
								            						echo '<input type="radio" id="paytype91" class="paytype" name="paytype" value="91"'.$checked.' /> 支付宝';
								            						break;
								            					case 'xhpay-wx':
								            						echo '<input type="radio" id="paytype61" class="paytype" name="paytype" value="61"'.$checked.' /> 微信支付';
								            						break;
								            					case 'xhpay-ali':
								            						echo '<input type="radio" id="paytype62" class="paytype" name="paytype" value="62"'.$checked.' /> 支付宝';
								            						break;
								            					case 'codepay-wx':
								            						echo '<input type="radio" id="paytype72" class="paytype" name="paytype" value="72"'.$checked.' /> 微信支付';
								            						break;
								            					case 'codepay-ali':
								            						echo '<input type="radio" id="paytype71" class="paytype" name="paytype" value="71"'.$checked.' /> 支付宝';
								            						break;
								            					case 'codepay-qq':
								            						echo '<input type="radio" id="paytype73" class="paytype" name="paytype" value="73"'.$checked.' /> QQ钱包';
								            						break;
								            					case 'epay-wx':
								            						echo '<input type="radio" id="paytype82" class="paytype" name="paytype" value="82"'.$checked.' /> 微信支付';
								            						break;
								            					case 'epay-ali':
								            						echo '<input type="radio" id="paytype81" class="paytype" name="paytype" value="81"'.$checked.' /> 支付宝';
								            						break;
								            					case 'epay-qq':
								            						echo '<input type="radio" id="paytype83" class="paytype" name="paytype" value="83"'.$checked.' /> QQ钱包';
								            						break;
								            					case 'easepay-wx':
								            						echo '<input type="radio" id="paytype112" class="paytype" name="paytype" value="112"'.$checked.' /> 微信支付';
								            						break;
								            					case 'easepay-ali':
								            						echo '<input type="radio" id="paytype111" class="paytype" name="paytype" value="111"'.$checked.' /> 支付宝';
								            						break;
								            					case 'easepay-usdt':
										echo '<input type="radio" id="paytype113" class="paytype" name="paytype" value="113"'.$checked.' /> USDT';
										break;
									case 'rpay-ali':
										echo '<input type="radio" id="paytype141" class="paytype" name="paytype" value="141"'.$checked.' /> 支付宝';
										break;
									case 'rpay-wx':
								echo '<input type="radio" id="paytype142" class="paytype" name="paytype" value="142"'.$checked.' /> 微信支付';
								break;
							case 'rpay-stripe':
								echo '<input type="radio" id="paytype143" class="paytype" name="paytype" value="143"'.$checked.' /> 信用卡';
								break;
							case 'rpay-paypal':
								echo '<input type="radio" id="paytype144" class="paytype" name="paytype" value="144"'.$checked.' /> Paypal';
								break;
							case 'vpay-wx':
								            						echo '<input type="radio" id="paytype102" class="paytype" name="paytype" value="102"'.$checked.' /> 微信支付';
								            						break;
								            					case 'vpay-ali':
								            						echo '<input type="radio" id="paytype101" class="paytype" name="paytype" value="101"'.$checked.' /> 支付宝';
								            						break;
								            					case 'stripe':
								            						echo '<input type="radio" id="paytype100" class="paytype" name="paytype" value="100"'.$checked.' /> 信用卡';
								            						break;
								            					case 'usdt':
								            						echo '<input type="radio" id="paytype120" class="paytype" name="paytype" value="120"'.$checked.' /> USDT';
								            						break;
								            					case 'ecpay':
								            						echo '<input type="radio" id="paytype130" class="paytype" name="paytype" value="130"'.$checked.' /> ECPAY';
								            						break;
								            					default:
								            						break;
								            				}
								            				$pi ++;
								            			}
								            		}else{
												?>
													<?php if(get_option('ice_payapl_api_uid')){?> 
								                    <input type="radio" id="paytype4" class="paytype" name="paytype" value="4" checked /> Paypal (美元汇率：<?php echo get_option('ice_payapl_api_rmb')?>)
								                    <?php }?> 
								                    <?php if(get_option('erphpdown_usdt_address')){?> 
								                    <input type="radio" id="paytype120" class="paytype" checked name="paytype" value="120" /> USDT
								                    <?php }?>
								                    <?php if(function_exists('plugin_check_ecpay') && plugin_check_ecpay() && get_option('erphpdown_ecpay_MerchantID')){?> 
								                    <input type="radio" id="paytype130" class="paytype" checked name="paytype" value="130" /> ECPAY
								                    <?php }?>
								                    <?php if(get_option('erphpdown_stripe_pk')){?>
									                <input type="radio" id="paytype100" class="paytype" name="paytype" value="100" checked /> 信用卡
									                <?php }?>
													<?php if(get_option('ice_weixin_mchid')){?> 
								                    <input type="radio" id="paytype3" class="paytype" checked name="paytype" value="3" /> 微信支付
								                    <?php }?>
								                    <?php if(get_option('ice_ali_partner') || get_option('ice_ali_app_id')){?> 
								                    <input type="radio" id="paytype1" class="paytype" checked name="paytype" value="1" /> 支付宝
								                    <?php }?>
								                    <?php if(get_option('erphpdown_f2fpay_id') && !get_option('erphpdown_f2fpay_alipay')){?> 
								                    <input type="radio" id="paytype2" class="paytype" checked name="paytype" value="2" /> 支付宝
								                    <?php }?>
									                <?php if(get_option('erphpdown_xhpay_appid32')){?> 
									                <input type="radio" id="paytype62" class="paytype" name="paytype" value="62" checked /> 支付宝
									                <?php }?>
									                <?php if(get_option('erphpdown_xhpay_appid31')){?> 
									                <input type="radio" id="paytype61" class="paytype" name="paytype" value="61" checked /> 微信支付
									                <?php }?>
									                <?php if(get_option('erphpdown_codepay_appid')){?> 
									                <?php if(!get_option('erphpdown_codepay_alipay')){?><input type="radio" id="paytype71" class="paytype" name="paytype" value="71" checked /> 支付宝<?php }?>
									                <?php if(!get_option('erphpdown_codepay_wxpay')){?><input type="radio" id="paytype72" class="paytype" name="paytype" value="72" /> 微信支付<?php }?>
									                <?php if(!get_option('erphpdown_codepay_qqpay')){?><input type="radio" id="paytype73" class="paytype" name="paytype" value="73" /> QQ钱包<?php }?>
									                <?php }?>
									                <?php if(get_option('erphpdown_paypy_key')){?> 
													<?php if(!get_option('erphpdown_paypy_alipay')){?><input type="radio" id="paytype51" class="paytype" name="paytype" value="51" checked /> 支付宝<?php }?>
									                <?php if(!get_option('erphpdown_paypy_wxpay')){?><input type="radio" id="paytype52" class="paytype" name="paytype" value="52" checked /> 微信支付<?php }?>
													<?php }?>
													<?php if(get_option('erphpdown_epay_id')){?>
													<?php if(!get_option('erphpdown_epay_alipay')){?><input type="radio" id="paytype81" class="paytype" name="paytype" value="81" checked /> 支付宝<?php }?>
													<?php if(!get_option('erphpdown_epay_wxpay')){?><input type="radio" id="paytype82" class="paytype" name="paytype" value="82" /> 微信支付<?php }?>
													<?php if(!get_option('erphpdown_epay_qqpay')){?><input type="radio" id="paytype83" class="paytype" name="paytype" value="83" /> QQ钱包<?php }?>
													<?php }?>
													<?php if(get_option('erphpdown_easepay_id')){?>
													<?php if(!get_option('erphpdown_easepay_alipay')){?><input type="radio" id="paytype111" class="paytype" name="paytype" value="111" checked /> 支付宝<?php }?>
													<?php if(!get_option('erphpdown_easepay_wxpay')){?><input type="radio" id="paytype112" class="paytype" name="paytype" value="112" /> 微信支付<?php }?>
													<?php if(!get_option('erphpdown_easepay_usdt')){?><input type="radio" id="paytype113" class="paytype" name="paytype" value="113" /> USDT<?php }?>
													<?php } ?>
													<?php if(get_option('erphpdown_rpay_id')){?>
										<?php if(!get_option('erphpdown_rpay_alipay')){?><input type="radio" id="paytype141" class="paytype" name="paytype" value="141" checked /> 支付宝<?php }?>
										<?php if(!get_option('erphpdown_rpay_wxpay')){?><input type="radio" id="paytype142" class="paytype" name="paytype" value="142" /> 微信支付<?php }?>
										<?php if(!get_option('erphpdown_rpay_stripe')){?><input type="radio" id="paytype143" class="paytype" name="paytype" value="143" /> 信用卡<?php }?>
										<?php if(!get_option('erphpdown_rpay_paypal')){?><input type="radio" id="paytype144" class="paytype" name="paytype" value="144" /> Paypal<?php }?>
										<?php }?>
													<?php if(get_option('erphpdown_vpay_key')){?>
													<?php if(!get_option('erphpdown_vpay_alipay')){?><input type="radio" id="paytype101" class="paytype" name="paytype" value="101" checked /> 支付宝<?php }?>
													<?php if(!get_option('erphpdown_vpay_wxpay')){?><input type="radio" id="paytype102" class="paytype" name="paytype" value="102" checked /> 微信支付<?php }?>
													<?php }?>
													<?php if(get_option('erphpdown_payjs_appid')){?> 
													<?php if(!get_option('erphpdown_payjs_wxpay')){?><input type="radio" id="paytype92" class="paytype" name="paytype" value="92" checked /> 微信支付<?php }?> 
													<?php if(!get_option('erphpdown_payjs_alipay')){?><input type="radio" id="paytype91" class="paytype" name="paytype" value="91" checked /> 支付宝<?php }?>      
													<?php }?>
								                    
								                <?php }?>
						                 	</div>
										</div>                                       
										<div class="input submit">                  
											<button class="btn btn-green">立即充值</button>                
										</div>              
									</form>
									<?php if(_themer("recharge_tips")){?>
									<p class="tips" style="margin-top:65px;margin-bottom: 0;color:#afafaf"><?php echo _themer("recharge_tips");?></p>     
									<?php }?>   
								</div>          
							</div> 
							<?php }?>

							<?php if(function_exists("checkDoCardResult")){?>
							<div class="pane pane-profile<?php if(_themer('recharge_default')) echo ' pane-active';?>">            
								<h3 class="pane-title">充值卡充值 <span class="icon"><svg-icon-arrow-down><svg width="16px" height="16px" viewBox="0 0 32 32" version="1.1">    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">        <g fill="#303030">            <path d="M22,14 L12.001382,3.99666359 L8.00138199,7.99666359 L16.0004718,15.9995282 L8,24 L12,28 L24,16 L22,14 Z" transform="translate(16.000000, 15.998332) rotate(-270.000000) translate(-16.000000, -15.998332) "></path>        </g>    </g></svg></svg-icon-arrow-down></span></h3>
								<div class="pane-contents">              
									<form method="POST" class="inputs ng-pristine ng-valid ng-valid-required ng-valid-email ng-valid-pattern ng-valid-maxlength focused loaded animate">             
										<div class="input focused loaded animate">                  
											<label for="card_num">卡号</label>  
											<span class="error"></span>                
											<input type="text" name="card_num" id="card_num" class="ng-pristine ng-untouched ng-valid ng-empty" value="">            
										</div>
										<div class="input focused loaded animate">                  
											<label for="card_pass">卡密</label>  
											<span class="error"></span>                
											<input type="text" name="card_pass" id="card_pass" class="ng-pristine ng-untouched ng-valid ng-empty" value="">            
										</div>                                     
										<div class="input submit">                  
											<button class="btn btn-green" type="button" id="card-button">立即充值</button>   
											<?php if(_themer('recharge_card_title')){
												echo '<a href="'._themer('recharge_card_link').'" target="_blank" class="erphpdown-card-link" style="float:right">'._themer('recharge_card_title').'</a>';
											}?>             
										</div>              
									</form>        
								</div>          
							</div>
							<?php }?>

							<?php if(function_exists('erphpdown_vipcard_install')){?>
							<div class="pane pane-profile">            
								<h3 class="pane-title">VIP充值卡 <span class="icon"><svg-icon-arrow-down><svg width="16px" height="16px" viewBox="0 0 32 32" version="1.1">    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">        <g fill="#303030">            <path d="M22,14 L12.001382,3.99666359 L8.00138199,7.99666359 L16.0004718,15.9995282 L8,24 L12,28 L24,16 L22,14 Z" transform="translate(16.000000, 15.998332) rotate(-270.000000) translate(-16.000000, -15.998332) "></path>        </g>    </g></svg></svg-icon-arrow-down></span></h3>
								<div class="pane-contents">              
									<form method="POST" class="inputs ng-pristine ng-valid ng-valid-required ng-valid-email ng-valid-pattern ng-valid-maxlength focused loaded animate">             
										<div class="input focused loaded animate">                  
											<label for="card_vip_num">卡号</label>  
											<span class="error"></span>                
											<input type="text" name="card_vip_num" id="card_vip_num" class="ng-pristine ng-untouched ng-valid ng-empty" value="">            
										</div>                                    
										<div class="input submit">                  
											<button class="btn btn-green" type="button" id="card-vip-button">立即充值</button>                
										</div>              
									</form>        
								</div>          
							</div>
							<?php }?>

							<div class="pane pane-table">            
								<h3 class="pane-title">充值记录 <span class="icon"><svg-icon-arrow-down><svg width="16px" height="16px" viewBox="0 0 32 32" version="1.1">    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">        <g fill="#303030">            <path d="M22,14 L12.001382,3.99666359 L8.00138199,7.99666359 L16.0004718,15.9995282 L8,24 L12,28 L24,16 L22,14 Z" transform="translate(16.000000, 15.998332) rotate(-270.000000) translate(-16.000000, -15.998332) "></path>        </g>    </g></svg></svg-icon-arrow-down></span></h3>
								<div class="pane-contents">              
									<?php 
								  	    $totallists = $wpdb->get_var("SELECT count(*) FROM $wpdb->icemoney WHERE ice_success=1 and ice_user_id=".$current_user->ID);
										$perpage = 20;
										$pagess = ceil($totallists / $perpage);
										if (!get_query_var('paged')) {
											$paged = 1;
										}else{
											$paged = $wpdb->escape(get_query_var('paged'));
										}
										$offset = $perpage*($paged-1);
										$lists = $wpdb->get_results("SELECT * FROM $wpdb->icemoney where ice_success=1 and ice_user_id=".$current_user->ID." order by ice_time DESC limit $offset,$perpage");
								  ?>
						          <?php if($lists) {?>
						          <table class="table">
						          	  <thead>
						              	  <tr><th><?php echo $moneyName;?></th><th>时间</th><th>方式</th></tr>
						              </thead>
						              <tbody>
						              <?php foreach($lists as $value){?>
						            	  <tr><td><?php echo $value->ice_money;?></td><td><?php echo $value->ice_time;?></td><?php 
						            	  	if(intval($value->ice_note)==0)
											{
												echo "<td>在线充值</td>";
											}elseif(intval($value->ice_note)==1)
											{
												echo "<td>后台充值</td>";
											}
											elseif(intval($value->ice_note)==4)
											{
												echo "<td>积分兑换</td>";
											}elseif(intval($value->ice_note)==6)
											{
												echo "<td>充值卡</td>";
											}else{
												echo "<td>未知</td>";
											}
						            	  ?></tr>
								      <?php }?>
						              </tbody>
						          </table> 
						      	  <?php }?>
								</div>          
							</div>          

						</div>                                  
					</div>                         
				</div>   
			</div>
		</div>
	</account-settings>
</ui-view>
<?php
get_footer();