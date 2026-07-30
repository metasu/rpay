<?php
/*
	template name: 个人中心
	description: template for mobantu.com modown theme 
*/
if (!session_id()) session_start();
if(!is_user_logged_in()){
	header("Location:".get_permalink(MBThemes_page("template/login.php")));
	exit;
}
get_header();
global $current_user;
$moneyName = get_option('ice_name_alipay');
$okMoney = erphpGetUserOkMoney();
$userTypeId=getUsreMemberType();
$erphp_super_name    = get_option('erphp_super_name')?get_option('erphp_super_name'):'全站通VIP';
$erphp_life_name    = get_option('erphp_life_name')?get_option('erphp_life_name'):'终身VIP';
$erphp_year_name    = get_option('erphp_year_name')?get_option('erphp_year_name'):'包年VIP';
$erphp_quarter_name = get_option('erphp_quarter_name')?get_option('erphp_quarter_name'):'包季VIP';
$erphp_month_name  = get_option('erphp_month_name')?get_option('erphp_month_name'):'包月VIP';
$erphp_day_name  = get_option('erphp_day_name')?get_option('erphp_day_name'):'体验VIP';
$erphp_vip_name  = get_option('erphp_vip_name')?get_option('erphp_vip_name'):'VIP';

$erphp_life_days    = get_option('erphp_life_days');
$erphp_year_days    = get_option('erphp_year_days');
$erphp_quarter_days = get_option('erphp_quarter_days');
$erphp_month_days  = get_option('erphp_month_days');
$erphp_day_days  = get_option('erphp_day_days');

$mobile = $wpdb->get_var("select mobile from $wpdb->users where ID=".$current_user->ID);

$wap_ui = 0;
if(modown_is_mobile() || wp_is_mobile()){
	$wap_ui = _MBT('user_wap_ui');
}
?>
<?php if($wap_ui){?>
<style>
	.container-user{padding-left: 0 !important;}
	.userside{display: none;}
</style>
<?php }?>
<div class="main">
	<?php 
		if(_MBT('user_force_email') && !$current_user->user_email){
			echo '<div class="container container-user-warning"><div class="warning"><i class="icon icon-smile"></i> '.__('为了确保账号安全，请先绑定邮箱再进行其他操作！','mobantu').'</div></div>';
		}elseif(_MBT('user_force_mobile') && _MBT('oauth_sms') && !$mobile){
			echo '<div class="container container-user-warning"><div class="warning"><i class="icon icon-smile"></i> '.__('为了确保账号安全，请先绑定手机号再进行其他操作！','mobantu').'</div></div>';
		}
	?>
	<?php do_action("modown_main");?>
	<div class="container container-user">
	  <div class="userside">
	    <div class="usertitle"> 
	    	<a href="javascript:;" class="edit-avatar"<?php if(!_MBT('user_avatar')){?> evt="user.avatar.submit"<?php }?> title="<?php if(!_MBT('user_avatar')) echo __('点击修改头像','mobantu'); else echo __('暂未开放上传头像功能','mobantu');?>"><?php echo get_avatar($current_user->ID,50);?></a>
	        <h2><?php echo $current_user->nickname;?></h2>
	        <?php
	        	$ice_ali_money_checkin = get_option('ice_ali_money_checkin');
	        	if($ice_ali_money_checkin) {
	        		$checkin_money = '';
	        		if(_MBT('checkin_random')){
	        			$checkin_money = _MBT('checkin_gift_min').'-'._MBT('checkin_gift_max');
	        		}else{
	        			$checkin_money = $ice_ali_money_checkin;
	        		}
			        if(erphpdown_check_checkin($current_user->ID)){
			      		echo '<div class="mobantu-check"><a href="javascript:;" class="usercheck active">'.__('已签到','mobantu').'</a><p>'.__('签到送','mobantu').$checkin_money.$moneyName.'</p></div>';
			        }else{
			      		echo '<div class="mobantu-check"><a href="javascript:;" class="usercheck checkin">'.__('今日签到','mobantu').'</a><p>'.__('签到送','mobantu').$checkin_money.$moneyName.'</p></div>';
			        }
			    }
	        ?>
	        <?php if(!_MBT('user_avatar')){?>
	        <form id="uploadphoto" action="<?php echo get_bloginfo('template_url').'/action/photo.php';?>" method="post" enctype="multipart/form-data" style="display:none;">
	            <input type="file" id="avatarphoto" name="avatarphoto" accept="image/png, image/jpeg">
	        </form>
	    	<?php }?>
	    </div>
	    <div class="usermenus">
	      <ul class="usermenu">
	      	<?php if ( class_exists( 'WooCommerce', false ) ) {?><li class="usermenu-cart"><a href="<?php echo wc_get_page_permalink( 'myaccount' );?>"><i class="icon icon-cart"></i> <?php _e('我的购物','mobantu');?></a></li><?php }?>
	        <li class="usermenu-charge <?php if((isset($_GET['action']) && ($_GET['action'] == 'charge' || $_GET['action'] == 'moneylog')) || !isset($_GET['action'])) echo 'active';?>"><a href="<?php echo add_query_arg('action','charge',get_permalink())?>"><i class="icon icon-money"></i> <?php _e('在线充值','mobantu');?></a></li>
	        <?php if(!_MBT('vip_hidden')){?><li class="usermenu-vip <?php if(isset($_GET['action']) && ($_GET['action'] == 'vip' || $_GET['action'] == 'vipdown' || $_GET['action'] == 'viplog')) echo 'active';?>"><a href="<?php echo add_query_arg('action','vip',get_permalink())?>"><i class="icon icon-crown"></i> <?php _e('升级VIP','mobantu');?></a></li><?php }?>
	        <li class="usermenu-history <?php if(isset($_GET['action']) && $_GET['action'] == 'history') echo 'active';?>"><a href="<?php echo add_query_arg('action','history',get_permalink())?>"><i class="icon icon-wallet"></i> <?php _e('充值记录','mobantu');?></a></li>
	        <?php if(plugin_check_cred() && get_option('erphp_mycred') == 'yes'){ $mycred_core = get_option('mycred_pref_core');?>
	        <li class="usermenu-mycred <?php if(isset($_GET['action']) && $_GET['action'] == 'mycred') echo 'active';?>"><a href="<?php echo add_query_arg('action','mycred',get_permalink())?>"><i class="icon icon-gift"></i> <?php _e('积分记录','mobantu');?></a></li>
	    	<?php }?>
	        <li class="usermenu-order <?php if(isset($_GET['action']) && $_GET['action'] == 'order') echo 'active';?>"><a href="<?php echo add_query_arg('action','order',get_permalink())?>"><i class="icon icon-order2"></i> <?php _e('购买资源','mobantu');?></a></li>
	        <?php if(_MBT('user_sell')){?>
	        <li class="usermenu-sell <?php if(isset($_GET['action']) && $_GET['action'] == 'sell') echo 'active';?>"><a href="<?php echo add_query_arg('action','sell',get_permalink())?>"><i class="icon icon-order-menu"></i> <?php _e('销售订单','mobantu');?></a></li>
	    	<?php }?>
	    	<?php if(function_exists('erphpdown_tuan_install')){?>
			<li class="usermenu-tuan <?php if(isset($_GET['action']) && $_GET['action'] == 'tuan') echo 'active';?>"><a href="<?php echo add_query_arg('action','tuan',get_permalink())?>"><i class="icon icon-tuan"></i> <?php _e('我的拼团','mobantu');?></a></li>
			<?php }?>
	    	<?php if(!_MBT('user_aff')){?>
	        <li class="usermenu-aff <?php if(isset($_GET['action']) && $_GET['action'] == 'aff') echo 'active';?>"><a href="<?php echo add_query_arg('action','aff',get_permalink())?>"><i class="icon icon-aff"></i> <?php _e('我的推广','mobantu');?></a></li>
	    	<?php }?>
	        <?php if(_MBT('withdraw')){?>
	        <li class="usermenu-withdraw <?php if(isset($_GET['action']) && ($_GET['action'] == 'withdraw' || $_GET['action'] == 'withdraws')) echo 'active';?>"><a href="<?php echo add_query_arg('action','withdraw',get_permalink())?>"><i class="icon icon-withdraws"></i> <?php _e('站内提现','mobantu');?></a></li>
	    	<?php }?>
	        <li class="usermenu-user <?php if(isset($_GET['action']) && $_GET['action'] == 'info') echo 'active';?>"><a href="<?php echo add_query_arg('action','info',get_permalink())?>"><i class="icon icon-info"></i> <?php _e('我的资料','mobantu');?></a></li>
	        <?php if(class_exists( 'AnsPress' )){?>
	        <li class="usermenu-question <?php if(isset($_GET['action']) && $_GET['action'] == 'question') echo 'active';?>"><a href="<?php echo add_query_arg('action','question',get_permalink())?>"><i class="icon icon-help"></i> <?php _e('我的提问','mobantu');?></a></li>
	        <li class="usermenu-answer <?php if(isset($_GET['action']) && $_GET['action'] == 'answer') echo 'active';?>"><a href="<?php echo add_query_arg('action','answer',get_permalink())?>"><i class="icon icon-comment"></i> <?php _e('我的回答','mobantu');?></a></li>
	        <?php }?>
	        <?php if(function_exists('QAPress_scripts')){?>
	        <li class="usermenu-faqs <?php if(isset($_GET['action']) && $_GET['action'] == 'faq') echo 'active';?>"><a href="<?php echo add_query_arg('action','faq',get_permalink())?>"><i class="icon icon-help"></i> <?php _e('我的提问','mobantu');?></a></li>
	        <?php }?>
			<li class="usermenu-comments <?php if(isset($_GET['action']) && $_GET['action'] == 'comment') echo 'active';?>"><a href="<?php echo add_query_arg('action','comment',get_permalink())?>"><i class="icon icon-comments"></i> <?php _e('我的评论','mobantu');?></a></li>
			<?php if(_MBT('user_sell')){?>
			<li class="usermenu-post <?php if(isset($_GET['action']) && $_GET['action'] == 'post') echo 'active';?>"><a href="<?php echo add_query_arg('action','post',get_permalink())?>"><i class="icon icon-posts"></i> <?php _e('我的投稿','mobantu');?></a></li>
			<?php }?>
			<?php if(function_exists('erphp_task_scripts')){?>
			<li class="usermenu-post <?php if(isset($_GET['action']) && $_GET['action'] == 'task') echo 'active';?>"><a href="<?php echo add_query_arg('action','task',get_permalink())?>"><i class="icon icon-posts"></i> <?php _e('我的任务','mobantu');?></a></li>
			<?php }?>
			<?php if(_MBT('post_collect') || _MBT('post_sidefav')){?><li class="usermenu-collect <?php if(isset($_GET['action']) && $_GET['action'] == 'collect') echo 'active';?>"><a href="<?php echo add_query_arg('action','collect',get_permalink())?>"><i class="icon icon-stars"></i> <?php _e('我的收藏','mobantu');?></a></li><?php }?>
			<?php if(_MBT('ticket')){?>
			<li class="usermenu-ticket <?php if(isset($_GET['action']) && $_GET['action'] == 'ticket') echo 'active';?>"><a href="<?php echo add_query_arg('action','ticket',get_permalink())?>"><i class="icon icon-temp-new"></i> <?php _e('提交工单','mobantu');?></a></li>
			<?php if(current_user_can('administrator')){?>
			<li class="usermenu-tickets <?php if(isset($_GET['action']) && $_GET['action'] == 'tickets') echo 'active';?>"><a href="<?php echo add_query_arg('action','tickets',get_permalink())?>"><i class="icon icon-temp"></i> <?php _e('管理工单','mobantu');?></a></li>
			<?php }else{?>
			<li class="usermenu-tickets <?php if(isset($_GET['action']) && $_GET['action'] == 'tickets') echo 'active';?>"><a href="<?php echo add_query_arg('action','tickets',get_permalink())?>"><i class="icon icon-temp"></i> <?php _e('我的工单','mobantu');?></a></li>
			<?php }?>
			<?php }?>
			<?php if(function_exists('erphpad_install')){?>
			<li class="usermenu-ad <?php if(isset($_GET['action']) && $_GET['action'] == 'ad') echo 'active';?>"><a href="<?php echo add_query_arg('action','ad',get_permalink())?>"><i class="icon icon-data"></i> <?php _e('我的广告','mobantu');?></a></li>
			<?php }?>
	        <li class="usermenu-signout"><a href="<?php echo wp_logout_url(get_bloginfo("url"));?>"><i class="icon icon-signout"></i> <?php _e('安全退出','mobantu');?></a></li>
	      </ul>
	    </div>
	  </div>
	  <div class="content" id="contentframe">
	    <div class="user-main">
	      <?php if(isset($_GET['action']) && $_GET['action'] == 'ad'){ ?>
	      	  <?php 
	      	  		global $erphpad_table;
			  	    $totallists = $wpdb->get_var("SELECT count(id) FROM $erphpad_table WHERE user_id=".$current_user->ID." and order_status=1");
					$perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT * FROM $erphpad_table where user_id=".$current_user->ID." and order_status=1 order by order_time DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	          			<th><?php _e('广告位','mobantu');?></th>
	          			<th class="pc"><?php _e('价格','mobantu');?></th>
	                    <th><?php _e('生效时间','mobantu');?></th>
	                    <th><?php _e('周期','mobantu');?></th>
	                    <th><?php _e('状态','mobantu');?></th>
	                    <th><?php _e('说明','mobantu');?></th>
	                    <th><?php _e('操作','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              <?php foreach($lists as $value){?>
	            	  <tr>
	                  	<td><?php echo erphpad_get_pos_name($value->pos_id);?></td>
	                  	<td class="pc"><?php echo $value->order_price;?></td>
	                  	<td><?php echo $value->order_time;?></td>
	                  	<td><?php echo $value->order_cycle;?>天</td>
	                  	<td><?php echo $value->order_status == 1?__('正常','mobantu'):__('过期','mobantu');?></td>
	                  	<td><?php echo erphpad_get_pos($value->pos_id)->pos_tips;?></td>
	                  	<td><a href="javascript:;" data-id="<?php echo $value->id;?>" class="erphpad-edit-loader"><?php _e('修改广告','mobantu');?></a></td>
	                  </tr>
			      <?php }?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	          <?php if(function_exists('erphpad_install')){?>
		        <form id="uploadad" action="<?php echo ERPHPAD_URL.'/action/ad.php';?>" method="post" enctype="multipart/form-data" style="display:none;">
		            <input type="file" id="adimage" name="adimage" accept="image/png, image/jpeg, image/gif">
		        </form>
		    	<?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'tuan'){ ?>
	      	  <?php 
			  	    $totallists = $wpdb->get_var("SELECT count(ice_id) FROM ".$wpdb->prefix."ice_tuan_order WHERE ice_user_id=".$current_user->ID." and ice_status>0");
					$perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."ice_tuan_order where ice_user_id=".$current_user->ID." and ice_status>0 order by ice_time DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	          			<th style="text-align: left;"><?php _e('名称','mobantu');?></th>
	          			<th class="pc"><?php _e('订单号','mobantu');?></th>
	                    <th><?php _e('价格','mobantu');?></th>
	                    <th><?php _e('时间','mobantu');?></th>
	                    <th><?php _e('进度','mobantu');?></th>
	                    <th><?php _e('状态','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              <?php foreach($lists as $value){?>
	            	  <tr>
	            	  	<td style="text-align: left;"><a target="_blank" href="<?php echo get_permalink($value->ice_post);?>"><?php echo get_post($value->ice_post)->post_title;?></a></td>
	                  	<td class="pc"><?php echo $value->ice_num;?></td>
	                  	<td><?php echo $value->ice_price;?></td>
	                  	<td><?php echo $value->ice_time;?></td>
	                  	<td><?php echo get_erphpdown_tuan_percent($value->ice_post,$value->ice_tuan_num);?>%</td>
	                  	<td><?php echo $value->ice_status == 1?__('进行中','mobantu'):__('已完成','mobantu');?></td>
	                  </tr>
			      <?php }?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'vip'){
	      	$erphp_year_price    = get_option('erphp_year_price');
            $erphp_quarter_price = get_option('erphp_quarter_price');
            $erphp_month_price  = get_option('erphp_month_price');
            $erphp_day_price  = get_option('erphp_day_price');
            $erphp_life_price  = get_option('erphp_life_price');
            $erphp_super_price  = get_option('erphp_super_price');
            $moneyVipName = $moneyName;

            if(_MBT('vip_only_pay') && _MBT('vip_only_pay_rmb')){
            	if($erphp_life_price) $erphp_life_price = $erphp_life_price/get_option('ice_proportion_alipay');
            	if($erphp_year_price) $erphp_year_price = $erphp_year_price/get_option('ice_proportion_alipay');
            	if($erphp_quarter_price) $erphp_quarter_price = $erphp_quarter_price/get_option('ice_proportion_alipay');
            	if($erphp_month_price) $erphp_month_price = $erphp_month_price/get_option('ice_proportion_alipay');
            	if($erphp_day_price) $erphp_day_price = $erphp_day_price/get_option('ice_proportion_alipay');
            	$moneyVipName = '元';
            }
            $vip_update_pay = 0;$oldUserType = 0;
		    if(get_option('vip_update_pay')){
		        $vip_update_pay = 1;
		        $oldUserType = getUsreMemberTypeById($current_user->ID);
		    }
	      ?>
	          <div class="charge vip">
	                <div class="charge-header clearfix">
	                	<div class="item">
	                		<b class="color"><?php echo sprintf("%.2f",$okMoney);?></b><?php echo ' '.$moneyName;?>
	                		<p><?php _e('可用余额','mobantu');?></p>
	                	</div>
	                	<div class="item item-pc">
	                		<?php 
		                    if($userTypeId==6){
		                        echo "<b>".$erphp_day_name."</b>";
		                    }elseif($userTypeId==7){
		                        echo "<b>".$erphp_month_name."</b>";
		                    }elseif ($userTypeId==8){
		                        echo "<b>".$erphp_quarter_name."</b>";
		                    }elseif ($userTypeId==9){
		                        echo "<b>".$erphp_year_name."</b>";
		                    }elseif ($userTypeId==10){
		                        echo "<b>".$erphp_life_name."</b>";
		                    }elseif ($userTypeId==11){
		                        echo "<b>".$erphp_super_name."</b>";
		                    }elseif(function_exists('getUsreMemberCatStatus') && getUsreMemberCatStatus()){
		                    	echo '<b>'.__('分类VIP','mobantu').'</b>';
		                    }else {
		                        echo '<b>'.__('普通用户','mobantu').'</b>';
		                    }
		                    echo ($userTypeId>0&&$userTypeId<10) ?'<span class="tips">'.sprintf(__('%s到期','mobantu'), getUsreMemberTypeEndTime()) .'</span>':'';
		                    ?>
	                		<p><?php _e('当前权限','mobantu');if(_MBT('user_vip_logs')){ echo '&nbsp;&nbsp;&nbsp;&nbsp;<a href="'.add_query_arg('action','viplog',get_permalink()).'">'.__("VIP记录","mobantu").'</a>';}?></p>
	                	</div>
	                	<div class="item item-tablet">
	                		<?php 
	                			if($userTypeId){
	                				$life_times_includes_free    = get_option('erphp_life_times_free');
									$year_times_includes_free    = get_option('erphp_year_times_free');
									$quarter_times_includes_free = get_option('erphp_quarter_times_free');
									$month_times_includes_free  = get_option('erphp_month_times_free');
									$day_times_includes_free  = get_option('erphp_day_times_free');

								    $erphp_life_times    = get_option('erphp_life_times');
									$erphp_year_times    = get_option('erphp_year_times');
									$erphp_quarter_times = get_option('erphp_quarter_times');
									$erphp_month_times  = get_option('erphp_month_times');
									$erphp_day_times  = get_option('erphp_day_times');

									$vip_times_see = get_option('vip_times_see');
									$erphp_life_times2    = get_option('erphp_life_times2');
									$erphp_year_times2    = get_option('erphp_year_times2');
									$erphp_quarter_times2 = get_option('erphp_quarter_times2');
									$erphp_month_times2  = get_option('erphp_month_times2');
									$erphp_day_times2  = get_option('erphp_day_times2');

									$cc = getSeeCount($current_user->ID);
									if(function_exists('getDownCount')){
										$cc = getDownCount($current_user->ID);
									}

									if($userTypeId == 6 && $erphp_day_times > 0){
										if($day_times_includes_free){
											echo '<b>'.($erphp_day_times-getSeeCountNoVip($current_user->ID)).'</b> / '.$erphp_day_times;
										}else{
											echo '<b>'.($erphp_day_times-$cc).'</b> / '.$erphp_day_times;
										}
									}elseif($userTypeId == 7 && $erphp_month_times > 0){
										if($month_times_includes_free){
											echo '<b>'.($erphp_month_times-getSeeCountNoVip($current_user->ID)).'</b> / '.$erphp_month_times;
										}else{
											echo '<b>'.($erphp_month_times-$cc).'</b> / '.$erphp_month_times;
										}
									}elseif($userTypeId == 8 && $erphp_quarter_times > 0){
										if($quarter_times_includes_free){
											echo '<b>'.($erphp_quarter_times-getSeeCountNoVip($current_user->ID)).'</b> / '.$erphp_quarter_times;
										}else{
											echo '<b>'.($erphp_quarter_times-$cc).'</b> / '.$erphp_quarter_times;
										}
									}elseif($userTypeId == 9 && $erphp_year_times > 0){
										if($year_times_includes_free){
											echo '<b>'.($erphp_year_times-getSeeCountNoVip($current_user->ID)).'</b> / '.$erphp_year_times;
										}else{
											echo '<b>'.($erphp_year_times-$cc).'</b> / '.$erphp_year_times;
										}
									}elseif($userTypeId >= 10 && $erphp_life_times > 0){
										if($life_times_includes_free){
											echo '<b>'.($erphp_life_times-getSeeCountNoVip($current_user->ID)).'</b> / '.$erphp_life_times;
										}else{
											echo '<b>'.($erphp_life_times-$cc).'</b> / '.$erphp_life_times;
										}
									}else{
										echo '<b>'.__('无限制','mobantu').'</b>';
									}


									if($vip_times_see){
										echo ' '.__("下载",'mobantu').'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
										if($userTypeId == 6 && $erphp_day_times2 > 0){
											
												echo '<b>'.($erphp_day_times2-getSeeCount($current_user->ID)).'</b> / '.$erphp_day_times2;
											
										}elseif($userTypeId == 7 && $erphp_month_times2 > 0){
											
												echo '<b>'.($erphp_month_times2-getSeeCount($current_user->ID)).'</b> / '.$erphp_month_times2;
											
										}elseif($userTypeId == 8 && $erphp_quarter_times2 > 0){
											
												echo '<b>'.($erphp_quarter_times2-getSeeCount($current_user->ID)).'</b> / '.$erphp_quarter_times2;
											
										}elseif($userTypeId == 9 && $erphp_year_times2 > 0){
											
												echo '<b>'.($erphp_year_times2-getSeeCount($current_user->ID)).'</b> / '.$erphp_year_times2;
											
										}elseif($userTypeId >= 10 && $erphp_life_times2 > 0){
											
												echo '<b>'.($erphp_life_times2-getSeeCount($current_user->ID)).'</b> / '.$erphp_life_times2;
											
										}else{
											echo '<b>'.__('无限制','mobantu').'</b>';
										}
										echo ' '.__("查看",'mobantu');
									}
								}else{
									echo '<b>'.__('无','mobantu').'</b>';
								}
	                		?>
	                		<p><?php echo __('今日剩余免费下载查看数','mobantu');?>&nbsp;&nbsp;&nbsp;&nbsp;<a href="<?php echo add_query_arg('action','vipdown',get_permalink())?>"><?php _e("下载记录","mobantu");?></a></p>
	                	</div>
	                </div>

	                <div class="vip-content">
		    			<div class="page-vip-content clearfix">
			    			<?php if(_MBT('vip_no')){?>
			                <div class="vip-item item-no">
			                    <h6><?php _e('普通用户','mobantu');?></h6>
			                    <span class="price"><?php _e('免费','mobantu');?></span>
			                    <p class="border-decor no"><span></span></p>
			                    <?php echo _MBT('vip_no');?>
			                	<a href="javascript:;" class="btn btn-small disabled"><?php _e('立即升级','mobantu');?></a>
			                </div>  
			            	<?php }?>

			    			<?php if($erphp_day_price){?>
			                <div class="vip-item item-0">
			                    <h6><?php echo $erphp_day_name;?></h6>
			                    <span class="price"><?php echo $erphp_day_price;?><small><?php echo $moneyVipName;?></small></span>
			                    <p class="border-decor"><span><?php echo sprintf(__('%s天','mobantu'), $erphp_day_days?$erphp_day_days:'1'); ?></span></p>
			                    <?php echo _MBT('vip_day');?>
			                    <a href="javascript:;" data-type="6" class="btn btn-small btn-vip-action"><?php _e('立即升级','mobantu');?></a>
			                </div>  
			            	<?php }?>

			    			<?php if($erphp_month_price){$canbu = 0;?>
			                <div class="vip-item item-1">
			                    <h6><?php echo $erphp_month_name;?></h6>
			                    <span class="price"><?php
				                    if($vip_update_pay){
				                    	if($oldUserType == 6 && $erphp_day_price){
				                    		$canbu = 1;
				                    		echo '<del>'.$erphp_month_price.'</del>';
				                     		echo $erphp_month_price - $erphp_day_price;
				                     	}else{
				                     		echo $erphp_month_price;
				                     	}
				                    }else{
				                     	echo $erphp_month_price;
				                    }
			             		?><small><?php echo $moneyVipName;?></small></span>
			                    <p class="border-decor"><span><?php echo sprintf(__('%s天','mobantu'), $erphp_month_days?$erphp_month_days:'30'); ?></span></p>
			                    <?php echo _MBT('vip_month');?>
			                    <a href="javascript:;" data-type="7" class="btn btn-small btn-vip-action"><?php _e('立即升级','mobantu');?></a>
			                	<?php if($canbu) echo '<span class="buca">'.__('补差价','mobantu').'</span>';?>
			                </div>  
			            	<?php }?>

			            	<?php if($erphp_quarter_price){$canbu = 0;?>
			                <div class="vip-item item-2">
			                    <h6><?php echo $erphp_quarter_name;?></h6>
			                    <span class="price"><?php
				                    if($vip_update_pay){
				                    	if($oldUserType == 6 && $erphp_day_price){
				                    		$canbu = 1;
				                    		echo '<del>'.$erphp_quarter_price.'</del>';
				                     		echo $erphp_quarter_price - $erphp_day_price;
				                     	}elseif($oldUserType == 7 && $erphp_month_price){
				                     		$canbu = 1;
				                     		echo '<del>'.$erphp_quarter_price.'</del>';
				                     		echo $erphp_quarter_price - $erphp_month_price;
				                     	}else{
				                     		echo $erphp_quarter_price;
				                     	}
				                    }else{
				                     	echo $erphp_quarter_price;
				                    }
			             		?><small><?php echo $moneyVipName;?></small></span>
			                    <p class="border-decor"><span><?php echo sprintf(__('%s个月','mobantu'), $erphp_quarter_days?$erphp_quarter_days:'3'); ?></span></p>
			                    <?php echo _MBT('vip_quarter');?>
			                    <a href="javascript:;" data-type="8" class="btn btn-small btn-vip-action"><?php _e('立即升级','mobantu');?></a>
			                	<?php if($canbu) echo '<span class="buca">'.__('补差价','mobantu').'</span>';?>
			                </div>  
			            	<?php }?>

			            	<?php if($erphp_year_price){$canbu = 0;?>
			                <div class="vip-item item-3">
			                    <h6><?php echo $erphp_year_name;?></h6>
			                    <span class="price"><?php
				                    if($vip_update_pay){
				                    	if($oldUserType == 6 && $erphp_day_price){
				                    		$canbu = 1;
				                    		echo '<del>'.$erphp_year_price.'</del>';
				                     		echo $erphp_year_price - $erphp_day_price;
				                     	}elseif($oldUserType == 7 && $erphp_month_price){
				                     		$canbu = 1;
				                     		echo '<del>'.$erphp_year_price.'</del>';
				                     		echo $erphp_year_price - $erphp_month_price;
				                     	}elseif($oldUserType == 8 && $erphp_quarter_price){
				                     		$canbu = 1;
				                     		echo '<del>'.$erphp_year_price.'</del>';
				                     		echo $erphp_year_price - $erphp_quarter_price;
				                     	}else{
				                     		echo $erphp_year_price;
				                     	}
				                    }else{
				                     	echo $erphp_year_price;
				                    }
			             		?><small><?php echo $moneyVipName;?></small></span>
			                    <p class="border-decor"><span><?php echo sprintf(__('%s个月','mobantu'), $erphp_year_days?$erphp_year_days:'12'); ?></span></p>
			                    <?php echo _MBT('vip_year');?>
			                    <a href="javascript:;" data-type="9" class="btn btn-small btn-vip-action"><?php _e('立即升级','mobantu');?></a>
			                	<?php if($canbu) echo '<span class="buca">'.__('补差价','mobantu').'</span>';?>
			                </div>  
			            	<?php }?>

			            	<?php if($erphp_life_price){$canbu = 0;?>
			                <div class="vip-item item-4">
			                    <h6><?php echo $erphp_life_name;?></h6>
			                    <span class="price"><?php
				                    if($vip_update_pay){
				                    	if($oldUserType == 6 && $erphp_day_price){
				                    		$canbu = 1;
				                    		echo '<del>'.$erphp_life_price.'</del>';
				                     		echo $erphp_life_price - $erphp_day_price;
				                     	}elseif($oldUserType == 7 && $erphp_month_price){
				                     		$canbu = 1;
				                     		echo '<del>'.$erphp_life_price.'</del>';
				                     		echo $erphp_life_price - $erphp_month_price;
				                     	}elseif($oldUserType == 8 && $erphp_quarter_price){
				                     		$canbu = 1;
				                     		echo '<del>'.$erphp_life_price.'</del>';
				                     		echo $erphp_life_price - $erphp_quarter_price;
				                     	}elseif($oldUserType == 9 && $erphp_year_price){
				                     		$canbu = 1;
				                     		echo '<del>'.$erphp_life_price.'</del>';
				                     		echo $erphp_life_price - $erphp_year_price;
				                     	}else{
				                     		echo $erphp_life_price;
				                     	}
				                    }else{
				                     	echo $erphp_life_price;
				                    }
			             		?><small><?php echo $moneyVipName;?></small></span>
			                    <p class="border-decor"><span><?php echo $erphp_life_days?(sprintf(__('%s年','mobantu'), $erphp_life_days)):__('永久','mobantu');?></span></p>
			                    <?php echo _MBT('vip_life');?>
			                    <a href="javascript:;" data-type="10" class="btn btn-small btn-vip-action"><?php _e('立即升级','mobantu');?></a>
			                	<?php if($canbu) echo '<span class="buca">'.__('补差价','mobantu').'</span>';?>
			                </div>  
			            	<?php }?>

			            	<?php if($erphp_super_price){$canbu = 0;?>
			                <div class="vip-item item-5">
			                    <h6><?php echo $erphp_super_name;?></h6>
			                    <span class="price"><?php
				                    if($vip_update_pay){
				                    	if($oldUserType == 6 && $erphp_day_price){
				                    		$canbu = 1;
				                    		echo '<del>'.$erphp_super_price.'</del>';
				                     		echo $erphp_super_price - $erphp_day_price;
				                     	}elseif($oldUserType == 7 && $erphp_month_price){
				                     		$canbu = 1;
				                     		echo '<del>'.$erphp_super_price.'</del>';
				                     		echo $erphp_super_price - $erphp_month_price;
				                     	}elseif($oldUserType == 8 && $erphp_quarter_price){
				                     		$canbu = 1;
				                     		echo '<del>'.$erphp_super_price.'</del>';
				                     		echo $erphp_super_price - $erphp_quarter_price;
				                     	}elseif($oldUserType == 9 && $erphp_year_price){
				                     		$canbu = 1;
				                     		echo '<del>'.$erphp_super_price.'</del>';
				                     		echo $erphp_super_price - $erphp_year_price;
				                     	}elseif($oldUserType == 10 && $erphp_life_price){
				                     		$canbu = 1;
				                     		echo '<del>'.$erphp_super_price.'</del>';
				                     		echo $erphp_super_price - $erphp_life_price;
				                     	}else{
				                     		echo $erphp_super_price;
				                     	}
				                    }else{
				                     	echo $erphp_super_price;
				                    }
			             		?><small><?php echo $moneyVipName;?></small></span>
			                    <p class="border-decor"><span><?php echo $erphp_life_days?(sprintf(__('%s年','mobantu'), $erphp_life_days)):__('永久','mobantu');?></span></p>
			                    <?php echo _MBT('vip_super');?>
			                    <a href="javascript:;" data-type="11" class="btn btn-small btn-vip-action"><?php _e('立即升级','mobantu');?></a>
			                	<?php if($canbu) echo '<span class="buca">'.__('补差价','mobantu').'</span>';?>
			                </div>  
			            	<?php }?>
						</div>
		            </div>

	                <?php 
		    			if(function_exists('getUsreMemberCat')){
		    				$moneyVipName = get_option('ice_name_alipay');
		    			$cat_vips = get_terms('category', array(
						    'hide_empty' => false,
						    'parent' => '0',
						    'meta_query' => array(
							    array(
							       'key'       => 'cat_vip',
							       'value'     => '1',
							       'compare'   => '='
							    )
							)
						) );
						if ( ! empty( $cat_vips ) && ! is_wp_error( $cat_vips ) ){
					?>
					<h3 class="form-title"><span><?php _e('分类VIP','mobantu');?></span></h3>
		    		<div class="vip-content">
		    			<div class="page-vip-content clearfix">
						<?php
							foreach ( $cat_vips as $cat ) {
								$cat_vip_name = get_term_meta($cat->term_id,'cat_vip_name',true);
								$cat_vip_desc = get_term_meta($cat->term_id,'cat_vip_desc',true);
								$cat_vip_month = get_term_meta($cat->term_id,'cat_vip_month',true);
								$cat_vip_quarter = get_term_meta($cat->term_id,'cat_vip_quarter',true);
								$cat_vip_year = get_term_meta($cat->term_id,'cat_vip_year',true);
								$cat_vip_life = get_term_meta($cat->term_id,'cat_vip_life',true);
						?>
							<div class="vip-item item-0">
			                    <a href="<?php echo get_term_link($cat);?>" target="_blank"><h6><?php echo $cat_vip_name?$cat_vip_name:$cat->name;?></h6></a>
			                    <span class="price"></span>
			                    <p class="border-decor border-decor-cat"><span><a href="<?php echo get_term_link($cat);?>" target="_blank"><i class="icon icon-cat"></i> <?php echo $cat->name;?></a></span></p>
			                    <?php if($cat_vip_desc) echo '<p class="vip-cat-desc">'.$cat_vip_desc.'</p>';?>
			                    <ul>
			                    	<?php 
			                    		if($cat_vip_month){ 
			                    			echo '<li><input type="radio" name="catvip'.$cat->term_id.'" id="catvip'.$cat->term_id.'-month" value="7" checked> <label for="catvip'.$cat->term_id.'-month">'.$erphp_month_name.' - '.$cat_vip_month.$moneyVipName.'</label></li>';
			                    		}
			                    		if($cat_vip_quarter){ 
			                    			echo '<li><input type="radio" name="catvip'.$cat->term_id.'" id="catvip'.$cat->term_id.'-quarter" value="8" checked> <label for="catvip'.$cat->term_id.'-quarter">'.$erphp_quarter_name.' - '.$cat_vip_quarter.$moneyVipName.'</label></li>';
			                    		}
			                    		if($cat_vip_year){ 
			                    			echo '<li><input type="radio" name="catvip'.$cat->term_id.'" id="catvip'.$cat->term_id.'-year" value="9" checked> <label for="catvip'.$cat->term_id.'-year">'.$erphp_year_name.' - '.$cat_vip_year.$moneyVipName.'</label></li>';
			                    		}
			                    		if($cat_vip_life){ 
			                    			echo '<li><input type="radio" name="catvip'.$cat->term_id.'" id="catvip'.$cat->term_id.'-life" value="10" checked> <label for="catvip'.$cat->term_id.'-life">'.$erphp_life_name.' - '.$cat_vip_life.$moneyVipName.'</label></li>';
			                    		}
			                    	?>
			                    </ul>
			                    <?php if(is_user_logged_in()){?>
			                    <a href="javascript:;" data-cat="<?php echo $cat->term_id;?>" class="btn btn-small btn-vipcat-action"><?php _e('立即升级','mobantu');?></a>
			                	<?php }else{?>
			                	<a href="javascript:;" class="btn btn-small signin-loader"><?php _e('立即升级','mobantu');?></a>
			                	<?php }?>
			                </div>
						<?php
							}
						?>
						</div>
		    		</div>
					<?php
						}
						}
		    		?>

	                <?php if(_MBT('user_vip_tips')){?><div class="charge-tips vip-tips"><i class="icon icon-horn"></i> <?php echo _MBT('user_vip_tips')?></div><?php }?>

	                <?php if(function_exists('erphpdown_vipcard_install')){
	                	$_SESSION['erphpcard_nonce'] = wp_create_nonce( time().rand(1000,9999) );
	                	?>
	                <style>.layui-layer-content .erphpdown-type-card{display: none}</style>
	                <form id="charge-form2" action="" method="post">
		            	<h3><span><?php _e('VIP激活码升级','mobantu');?></span></h3>
		              	<div class="item">
			                <input type="text" class="form-control input-recharge" id="erphpcard_num" name="erphpcard_num" required="" placeholder="<?php _e('激活码','mobantu');?>">
			            </div>
		                <div class="item">
		                	<?php //wp_nonce_field('erphpcard_action', 'erphpcard_nonce'); ?>
		                	<input type="hidden" id="erphpcard_nonce" name="erphpcard_nonce" value="<?php echo $_SESSION['erphpcard_nonce'];?>">
			              	<input type="button" evt="user.vip.card.submit" value="<?php _e('立即升级','mobantu');?>" class="btn btn-card">
			            </div>
		            </form>
		            <?php $ice_tips_card = get_option('ice_tips_card'); if($ice_tips_card){?><div class="charge-tips"><i class="icon icon-horn"></i> <?php echo $ice_tips_card;?></div><?php }?>
		        	<?php }?>

		        	<?php if(function_exists('erphpdown_catvipcard_install')){
	                	$_SESSION['erphpcard_nonce2'] = wp_create_nonce( time().rand(1000,9999) );
	                	?>
	                <style>.layui-layer-content .erphpdown-type-card{display: none}</style>
	                <form id="charge-form2" action="" method="post">
		            	<h3><span><?php _e('分类VIP激活码升级','mobantu');?></span></h3>
		              	<div class="item">
			                <input type="text" class="form-control input-recharge" id="erphpcard_num2" name="erphpcard_num2" required="" placeholder="<?php _e('激活码','mobantu');?>">
			            </div>
		                <div class="item">
		                	<input type="hidden" id="erphpcard_nonce2" name="erphpcard_nonce2" value="<?php echo $_SESSION['erphpcard_nonce2'];?>">
			              	<input type="button" evt="user.catvip.card.submit" value="<?php _e('立即升级','mobantu');?>" class="btn btn-card">
			            </div>
		            </form>
		            <?php $ice_tips_card = get_option('ice_tips_card'); if($ice_tips_card){?><div class="charge-tips"><i class="icon icon-horn"></i> <?php echo $ice_tips_card;?></div><?php }?>
		        	<?php }?>
	          </div>

	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'viplog'){?>
	      	<?php 
			  	    $totallists = $wpdb->get_var("SELECT count(ice_id) FROM $wpdb->vip WHERE ice_user_id=".$current_user->ID);
					$perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT * FROM $wpdb->vip where ice_user_id=".$current_user->ID." order by ice_time DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	          			<th><?php echo __('VIP类型','erphpdown');?></th>
	                    <th><?php _e('价格','mobantu');?></th>
	                    <th><?php _e('时间','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              <?php foreach($lists as $value){
					if($value->ice_user_type == 6) $typeName = $erphp_day_name;
					if($value->ice_user_type == 11) $typeName = $erphp_super_name;
					else {$typeName=$value->ice_user_type==7 ?$erphp_month_name :($value->ice_user_type==8 ?$erphp_quarter_name : ($value->ice_user_type==10 ?$erphp_life_name : $erphp_year_name));}
					echo "<tr>\n";
					echo "<td>$typeName</td>\n";
					echo "<td>$value->ice_price</td>\n";
					echo "<td>$value->ice_time</td>\n";
					echo "</tr>";
				}?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }?>

	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'vipdown'){ ?>
	          <?php 
			  	    $totallists = $wpdb->get_var("SELECT count(ice_id) FROM $wpdb->down WHERE ice_user_id=".$current_user->ID." and ice_vip");
					$perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT * FROM $wpdb->down where ice_user_id=".$current_user->ID." and ice_vip order by ice_time DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	          			<th style="text-align: left;"><?php _e('名称','mobantu');?></th>
	                    <th><?php _e('时间','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              <?php foreach($lists as $value){
	              	$vpost = get_post($value->ice_post_id);
	              	?>
	            	  <tr>
	                  	<td style="text-align: left;"><?php if($vpost) echo '<a href="'.get_permalink($value->ice_post_id).'" target="_blank">'.$vpost->post_title;?></a></td>
	                  	<td><?php echo $value->ice_time;?></td>
	                  </tr>
			      <?php }?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'withdraw'){ 
	      	$userAli=$wpdb->get_row("select * from ".$wpdb->iceget." where ice_user_id=".$current_user->ID);

	      	$erphp_aff_money = get_option('erphp_aff_money');
	      	if($erphp_aff_money && function_exists('erphpGetUserOkAff')){
				$okMoney = erphpGetUserOkAff();
			}
	      	?>
	      	  <form>
	      	  <ul class="user-meta">
		  		<li><label><?php _e('支付宝','mobantu');?></label>
					<input type="text" class="form-control" id="ice_alipay" name="ice_alipay" value="<?php if($userAli) echo $userAli->ice_alipay;?>">
		  		</li>
		  		<li><label><?php _e('姓名','mobantu');?></label>
					<input type="text" class="form-control" id="ice_name" name="ice_name" value="<?php if($userAli) echo $userAli->ice_name;?>">
		  		</li>
		  		<li><label><?php _e('提现比例','mobantu');?></label>
					<?php echo get_option('ice_proportion_alipay').$moneyName;?> = 1元
		  		</li>
		  		<li><label><?php _e('手续费','mobantu');?></label>
					<?php
					$fee=get_option("ice_ali_money_site");
					$ice_ali_money_site = get_user_meta($current_user->ID,'ice_ali_money_site',true);
					if($ice_ali_money_site != '' && ($ice_ali_money_site || $ice_ali_money_site == 0)){
						$fee = $ice_ali_money_site;
					}
					echo $fee;?>%
		  		</li>
		  		<li><label><?php echo sprintf(__('提现%s','mobantu'), $moneyName);?></label>
					<input type="text" class="form-control" id="ice_money" name="ice_money" value="">( <?php _e('可提现余额：','mobantu');?><?php echo $okMoney.' '.$moneyName;?> )
		  		</li>
		  		<li>
					<input type="button" evt="withdraw.submit" class="btn btn-primary" value="<?php _e('立即提现','mobantu');?>">&nbsp;&nbsp;&nbsp;&nbsp;<a href="<?php echo add_query_arg('action','withdraws',get_permalink())?>"><?php _e('提现记录','mobantu');?></a>
					<input type="hidden" name="action" value="user.withdrawal">
		  		</li>
		  	  </ul>
		  	</form>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'withdraws'){ ?>
	          <?php 
			  	    $totallists = $wpdb->get_var("SELECT count(ice_id) FROM $wpdb->iceget WHERE ice_user_id=".$current_user->ID);
					$perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT * FROM $wpdb->iceget where ice_user_id=".$current_user->ID." order by ice_time DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	          			<th><?php echo $moneyName;?></th>
	          			<th class="pc"><?php _e('实际到账(元)','mobantu');?></th>
	                    <th><?php _e('时间','mobantu');?></th>
	                    <th><?php _e('状态','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              <?php foreach($lists as $value){?>
	            	  <tr>
	                  	<td><?php echo $value->ice_money;?></td>
	                  	<td class="pc"><?php echo ( (100-get_option("ice_ali_money_site")) * $value->ice_money / 100) / get_option('ice_proportion_alipay');?></td>
	                  	<td><?php echo $value->ice_time;?></td>
	                  	<td><?php if($value->ice_success == 1){echo '<span style="color:green">'.__('已完成','mobantu').'</span>';}else{echo __('处理中','mobantu');}?></td>
	                  </tr>
			      <?php }?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'moneylog'){ ?>
	          <?php 
			  	    $totallists = $wpdb->get_var("SELECT count(id) FROM $wpdb->icelog WHERE user_id=".$current_user->ID);
					$perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT * FROM $wpdb->icelog where user_id=".$current_user->ID." order by ice_time DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	          			<th><?php echo $moneyName;?></th>
	          			<th><?php _e('来源','mobantu');?></th>
	                    <th><?php _e('时间','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              <?php foreach($lists as $value){?>
	            	  <tr>
	            	  	<td><?php echo ($value->ice_money>0?'+':'').$value->ice_money;?></td>
	                  	<td><?php echo $value->ice_note;?></td>
	                  	<td><?php echo $value->ice_time;?></td>
	                  </tr>
			      <?php }?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'history'){ ?>
	          <?php 
			  	    $totallists = $wpdb->get_var("SELECT count(*) FROM $wpdb->icemoney WHERE ice_success=1 and ice_user_id=".$current_user->ID);
					$perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT * FROM $wpdb->icemoney where ice_success=1 and ice_user_id=".$current_user->ID." order by ice_time DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr><th style="text-align: left;"><?php _e('时间','mobantu');?></th><th style="text-align: left;"><?php _e('金额','mobantu');?>(<?php echo $moneyName;?>)</th><th class="pc"><?php _e('方式','mobantu');?></th></tr></thead>
	              <tbody>
	              <?php foreach($lists as $value){?>
	            	  <tr><td style="text-align: left;"><?php echo $value->ice_time;?></td><td style="text-align: left;"><dfn><?php echo $value->ice_money;?></dfn></td>
	                  <?php if(intval($value->ice_note)==0){echo "<td class=pc><font color=green>".__('在线充值','mobantu')."</font></td>\n";}elseif(intval($value->ice_note)==1){echo "<td class=pc>".__('后台充值','mobantu')."</td>\n";}elseif(intval($value->ice_note)==4){echo "<td class=pc><font color=orange>".__('Mycred兑换','mobantu')."</font></td>\n";}elseif(intval($value->ice_note)==6){echo "<td class=pc><font color=orange>".__('充值卡','mobantu')."</font></td>\n";}else{echo "<td class=pc>".__('其他','mobantu')."</td>";}?></tr>
			      <?php }?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <div class="user-alerts">
	          	  <h4><?php _e('充值问题：','mobantu');?></h4>
	          	  <ul><li><?php _e('付款后系统会与支付服务方进行交互读取数据，可能会导致到账延迟，一般不会超过2分钟。','mobantu');?></li></ul>
	          </div>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'mycred'){ ?>
	          <?php 
	          		$mycred_get_all_references = mycred_get_all_references();
			  	    $totallists = $wpdb->get_var("SELECT COUNT(id) FROM ".$wpdb->prefix."myCRED_log WHERE user_id=".$current_user->ID);
					$perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."myCRED_log where user_id=$current_user->ID order by time DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	          			<th><?php _e('行为','mobantu');?></th>
	                    <th class=pc><?php _e('时间','mobantu');?></th>
	                    <th><?php _e('积分','mobantu');?></th>
	                    <th class=pc><?php _e('条目','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              <?php foreach($lists as $value){
	              ?>
	            	  <tr>
	            	  	<td><?php echo ($value->ref == '兑换')?'兑换余额': $mycred_get_all_references[$value->ref];?></td>
	                  	<td class=pc><?php echo date("Y-m-d H:i:s",$value->time);?></td>
	                  	<td><?php echo $value->creds;?></td>
	                  	<td class=pc><?php echo str_replace('%plural%', $mycred_core['name']['plural'], $value->entry);?></td>
	                  </tr>
			      <?php }?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess); ?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'order'){ ?>
	          <?php 
			  	    $totallists = $wpdb->get_var("SELECT COUNT(ice_id) FROM $wpdb->icealipay WHERE ice_success>0 and ice_user_id=".$current_user->ID);
					$perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT * FROM $wpdb->icealipay where ice_success=1 and ice_user_id=$current_user->ID order by ice_time DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	                    <th style="text-align: left;"><?php _e('名称','mobantu');?></th>
	                    <th class=pc><?php _e('订单号','mobantu');?></th>
	                    <th class=pc><?php _e('金额','mobantu');?>(<?php echo $moneyName;?>)</th>
	                    <th class=pc><?php _e('时间','mobantu');?></th>
	                    <th><?php _e('操作','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              <?php foreach($lists as $value){
						$erphp_down = get_post_meta( $value->ice_post, 'erphp_down', true );
	              ?>
	            	  <tr>
	                  	<td style="text-align: left;"><a target="_blank" href="<?php echo get_permalink($value->ice_post);?>"><?php echo $value->ice_title;?></a><?php
	                  		if($value->ice_data){
	                  			echo '<p style="font-size:12px">'.__('附加信息：','mobantu').$value->ice_data.'</p>';
	                  		}
	                  		if(isset($value->ice_note) && $value->ice_note){
	                  			echo '<p style="font-size:12px">'.__('发货信息：','mobantu').$value->ice_note.'</p>';
	                  		}
	                  	?></td><td class=pc><?php echo $value->ice_num;?></td><td class=pc><?php echo $value->ice_price;?></td>
	                  	<td class=pc><?php echo $value->ice_time;?></td>
	                  	<?php if($erphp_down == '7'){?>
	                  	<td><?php if(isset($value->ice_note) && $value->ice_note) echo __('已发货','mobantu'); else echo __('未发货','mobantu');?></td>
	                  	<?php }elseif($erphp_down == '1' || $erphp_down == '5'){?>
	                  	<td><a href="<?php echo constant("erphpdown").'download.php?postid='.$value->ice_post.'&index='.$value->ice_index;?>" target="_blank"><?php _e('下载','mobantu');?></a></td>
	                  	<?php }else{?>
	                  	<td><a href="<?php echo get_permalink($value->ice_post);?>" target="_blank"><?php _e('查看','mobantu');?></a></td>
	                  	<?php }?>
	                  </tr>
			      <?php }?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'sell'){ ?>
	          <?php 
			  	    $totallists = $wpdb->get_var("SELECT COUNT(ice_id) FROM $wpdb->icealipay WHERE ice_success>0 and ice_author=".$current_user->ID);
					$perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT * FROM $wpdb->icealipay where ice_success=1 and ice_author=$current_user->ID order by ice_time DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	              	  	<th style="text-align: left;"><?php _e('名称','mobantu');?></th>
	          			<th class=pc><?php _e('订单号','mobantu');?></th>
	                    <th class=pc><?php _e('用户','mobantu');?></th>
	                    <th><?php _e('金额','mobantu');?>(<?php echo $moneyName;?>)</th>
	                    <th class=pc><?php _e('时间','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              <?php foreach($lists as $value){?>
	            	  <tr>
	                  	<td style="text-align: left;"><a target="_blank" href="<?php echo get_permalink($value->ice_post);?>"><?php echo get_post($value->ice_post)->post_title;?></a></td>
	                  	<td class=pc><?php echo $value->ice_num;?></td>
	                  	<td class=pc><?php echo get_the_author_meta( 'user_login', $value->ice_user_id );?></td>
	                  	<td><?php echo $value->ice_price;?></td>
	                  	<td class=pc><?php echo $value->ice_time;?></td>
	                  </tr>
			      <?php }?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6>暂无记录！</h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'aff' && !_MBT('user_aff')){ 
	      	$ice_ali_money_ref = get_option('ice_ali_money_ref')*0.01;
	      	$total_user = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users WHERE father_id=".$current_user->ID);
	      	?>
	          <div class="charge aff">
	          		<div class="charge-header">
	                	<h3><?php _e('您的专属推广链接：','mobantu');?><font color="#5bc0de"><?php bloginfo("url");?>/?aff=<?php echo $current_user->ID;?></font> <a href="javascript:;" data-clipboard-text="<?php bloginfo("url");?>/?aff=<?php echo $current_user->ID;?>" class="article-aff" title="<?php _e('复制链接','mobantu');?>"><i class="icon icon-copy"></i></a> <?php if(_MBT('aff_card')){?><a href="javascript:;" class="user-aff-card" title="<?php _e('推广图片','mobantu');?>"><i class="icon icon-cover"></i><span id="aff-qrcode" data-url="<?php bloginfo("url");?>/?aff=<?php echo $current_user->ID;?>"></span></a><?php }?></h3>
	                	<p style="font-size: 13px;opacity: .7"><?php _e('已推广注册','mobantu');?> <?php echo $total_user?$total_user:0;?> <?php _e('人','mobantu');?></p>
	                </div>
			  </div>
	          <?php 
			  	    $totallists = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users WHERE father_id=".$current_user->ID);
			  	    $perpage = 15;
					$pagess = ceil($totallists / $perpage);
					if (!get_query_var('paged')) {
						$paged = 1;
					}else{
						$paged = esc_sql(get_query_var('paged'));
					}
					$offset = $perpage*($paged-1);
					$lists = $wpdb->get_results("SELECT ID,user_login,user_registered FROM $wpdb->users where father_id=".$current_user->ID." order by user_registered DESC limit $offset,$perpage");
			  ?>
	          <?php if($lists) {?>
	          <table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	          			<th><?php _e('用户','mobantu');?></th>
	                    <th class="pc"><?php _e('注册时间','mobantu');?></th>
	                    <th><?php _e('消费金额','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              <?php foreach($lists as $value){?>
	            	  <tr>
	                  	<td><?php echo $value->user_login;?></td>
	                  	<td class="pc"><?php echo get_date_from_gmt($value->user_registered);?></td>
	                  	<td><?php $tt = erphpGetUserAllXiaofei($value->ID);echo $tt?$tt:"0";?></td>
	                  </tr>
			      <?php }?>
	              </tbody>
	          </table>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          	<table class="table table-striped table-hover user-orders">
	          	  <thead>
	              	  <tr>
	          			<th><?php _e('用户','mobantu');?></th>
	                    <th class="pc"><?php _e('注册时间','mobantu');?></th>
	                    <th><?php _e('消费金额','mobantu');?></th>
	                    <th><?php _e('奖励','mobantu');?></th>
	                  </tr>
	              </thead>
	              <tbody>
	              	<tr><td colspan="4"><?php _e('暂无记录','mobantu');?></td></tr>
	              </tbody>
	            </table>
	          <?php }?>
	          <div class="user-alerts">
	            <h4><?php _e('推广说明：','mobantu');?></h4>
	            <ul>
	            	<?php echo _MBT('user_aff_tips');?>
	                <li><?php _e('推广链接可以是任意页面后加','mobantu');?> <span class="label label-info">?aff=<?php echo $current_user->ID;?></span>；</li>
	            </ul>
	            </div>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'comment'){ ?>
	          <?php 
			  	$perpage = 10;
				if (!get_query_var('paged')) {
					$paged = 1;
				}else{
					$paged = esc_sql(get_query_var('paged'));
				}
				$total_comment = $wpdb->get_var("select count(comment_ID) from $wpdb->comments where comment_approved='1' and user_id=".$current_user->ID);
				$pagess = ceil($total_comment / $perpage);
				$offset = $perpage*($paged-1);
				$results = $wpdb->get_results("select $wpdb->comments.comment_ID,$wpdb->comments.comment_post_ID,$wpdb->comments.comment_content,$wpdb->comments.comment_date,$wpdb->posts.post_title from $wpdb->comments left join $wpdb->posts on $wpdb->comments.comment_post_ID = $wpdb->posts.ID where $wpdb->comments.comment_approved='1' and $wpdb->comments.user_id=".$current_user->ID." order by $wpdb->comments.comment_date DESC limit $offset,$perpage");
				if($results){
			  ?>
	          <ul class="user-commentlist">
	            <?php foreach($results as $result){?>
	          	<li><time><?php echo $result->comment_date;?></time><p class="note"><?php echo $result->comment_content;?></p><p class="text-muted"><?php _e('文章：','mobantu');?><a target="_blank" href="<?php echo get_permalink($result->comment_post_ID);?>"><?php echo $result->post_title;?></a></p></li>
	            <?php }?>
	          </ul>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'post'){
	      		$status = 'publish';
	      		if(isset($_GET['status'])){
	      			$status = $_GET['status'];
	      		}
	      		$totallists = $wpdb->get_var("SELECT count(ID) FROM $wpdb->posts WHERE post_author=".$current_user->ID." and post_status='".$status."' and post_type='post'");
				$perpage = 16;
				$pagess = ceil($totallists / $perpage);
				if (!get_query_var('paged')) {
					$paged = 1;
				}else{
					$paged = esc_sql(get_query_var('paged'));
				}
				$offset = $perpage*($paged-1);
				$results = $wpdb->get_results("SELECT * FROM $wpdb->posts where post_author=".$current_user->ID." and post_status='".$status."' and post_type='post' order by post_date DESC limit $offset,$perpage");
	      ?>
	      		<div class="user-postnav">
	      	  		<?php _e('状态：','mobantu');?><a href="<?php echo add_query_arg(array("action"=>'post',"status"=>'publish',"paged"=>1),get_permalink(MBThemes_page("template/user.php")));?>" class="<?php if(!isset($_GET['status']) || (isset($_GET['status']) && $_GET['status'] == 'publish')) echo 'active';?>"><?php _e('已发布','mobantu');?></a><a href="<?php echo add_query_arg(array("action"=>'post',"status"=>'pending',"paged"=>1),get_permalink(MBThemes_page("template/user.php")));?>" class="<?php if(isset($_GET['status']) && $_GET['status'] == 'pending') echo 'active';?>"><?php _e('审核中','mobantu');?></a><a href="<?php echo add_query_arg(array("action"=>'post',"status"=>'draft',"paged"=>1),get_permalink(MBThemes_page("template/user.php")));?>" class="<?php if(isset($_GET['status']) && $_GET['status'] == 'draft') echo 'active';?>"><?php _e('草稿','mobantu');?></a>
	      	  	</div>
	      	  <?php if($results) {?>
	      	  	<div class="user-gridlist<?php if(_MBT('user_posts_water')) echo ' waterfall';?> clearfix">
	            <?php foreach($results as $result){?>
	          	<div class="item clearfix">
	          		<a href="<?php if(!isset($_GET['status']) || (isset($_GET['status']) && $_GET['status'] == 'publish')) the_permalink($result->ID);?>" target="_blank"><img src="<?php echo MBThemes_thumbnail_post($result->ID,_MBT('user_posts_water')?'1':'0');?>"></a>
	          		<h4><a href="<?php if(!isset($_GET['status']) || (isset($_GET['status']) && $_GET['status'] == 'publish')) the_permalink($result->ID);?>" target="_blank"><?php echo get_the_title($result->ID);?></a></h4>
	          		<time><span><?php _e('投稿于：','mobantu');?></span><?php echo $result->post_date;?></time>
	          		<?php if(_MBT('post_tougao_edit')){ echo '<a class="edit" href="'.add_query_arg('post_id',$result->ID,get_permalink(MBThemes_page("template/tougao.php"))).'" target="_blank">'.__('编辑','mobantu').'</a>';}?>
	          	</div>
	            <?php }?>
	            </div>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'task'){
	      		$totallists = $wpdb->get_var("SELECT count(ID) FROM $wpdb->posts WHERE post_author=".$current_user->ID." and post_status='publish' and post_type='task'");
				$perpage = 10;
				$pagess = ceil($totallists / $perpage);
				if (!get_query_var('paged')) {
					$paged = 1;
				}else{
					$paged = esc_sql(get_query_var('paged'));
				}
				$offset = $perpage*($paged-1);
				$lists = $wpdb->get_results("SELECT * FROM $wpdb->posts where post_author=".$current_user->ID." and post_status='publish' and post_type='task' order by post_date DESC limit $offset,$perpage");
	      ?>
	      	  <?php if($lists) {?>
	          <ul class="user-postlist">
	          	<?php foreach($lists as $value){ $post = get_post($value->ID); setup_postdata($post);?>
	          	<li>
					<h2><a target="_blank" href="<?php the_permalink($value->ID);?>"><?php the_title();?></a></h2>
					<p class="note" style="height: auto;"><?php echo MBThemes_get_excerpt();?></p>
					<p class="text-muted"><?php echo $value->post_date;?></p>
				</li>
	          	<?php }?>
	          </ul>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'faq'){
	      		$totallists = $wpdb->get_var("SELECT count(ID) FROM $wpdb->posts WHERE post_author=".$current_user->ID." and post_status='publish' and post_type='qa_post'");
				$perpage = 10;
				$pagess = ceil($totallists / $perpage);
				if (!get_query_var('paged')) {
					$paged = 1;
				}else{
					$paged = esc_sql(get_query_var('paged'));
				}
				$offset = $perpage*($paged-1);
				$lists = $wpdb->get_results("SELECT * FROM $wpdb->posts where post_author=".$current_user->ID." and post_status='publish' and post_type='qa_post' order by post_date DESC limit $offset,$perpage");
	      ?>
	      	  <?php if($lists) {?>
	          <ul class="user-postlist">
	          	<?php foreach($lists as $value){ $post = get_post($value->ID); setup_postdata($post);?>
	          	<li>
					<h2><a target="_blank" href="<?php the_permalink($value->ID);?>"><?php the_title();?></a></h2>
					<p class="note" style="height: auto;"><?php echo MBThemes_get_excerpt();?></p>
					<p class="text-muted"><?php echo $value->post_date;?></p>
				</li>
	          	<?php }?>
	          </ul>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'question'){
	      		$totallists = $wpdb->get_var("SELECT count(ID) FROM $wpdb->posts WHERE post_author=".$current_user->ID." and post_status='publish' and post_type='question'");
				$perpage = 10;
				$pagess = ceil($totallists / $perpage);
				if (!get_query_var('paged')) {
					$paged = 1;
				}else{
					$paged = esc_sql(get_query_var('paged'));
				}
				$offset = $perpage*($paged-1);
				$lists = $wpdb->get_results("SELECT * FROM $wpdb->posts where post_author=".$current_user->ID." and post_status='publish' and post_type='question' order by post_date DESC limit $offset,$perpage");
	      ?>
	      	  <?php if($lists) {?>
	          <ul class="user-postlist">
	          	<?php foreach($lists as $value){ $post = get_post($value->ID); setup_postdata($post);?>
	          	<li>
					<h2><a target="_blank" href="<?php the_permalink($value->ID);?>"><?php the_title();?></a></h2>
					<p class="note" style="height: auto;"><?php echo MBThemes_get_excerpt();?></p>
					<p class="text-muted"><?php echo $value->post_date;?></p>
				</li>
	          	<?php }?>
	          </ul>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'answer'){
	      		$totallists = $wpdb->get_var("SELECT count(ID) FROM $wpdb->posts WHERE post_author=".$current_user->ID." and post_status='publish' and post_type='answer'");
				$perpage = 10;
				$pagess = ceil($totallists / $perpage);
				if (!get_query_var('paged')) {
					$paged = 1;
				}else{
					$paged = esc_sql(get_query_var('paged'));
				}
				$offset = $perpage*($paged-1);
				$lists = $wpdb->get_results("SELECT * FROM $wpdb->posts where post_author=".$current_user->ID." and post_status='publish' and post_type='answer' order by post_date DESC limit $offset,$perpage");
	      ?>
	      	  <?php if($lists) {?>
	          <ul class="user-commentlist">
	          	<?php foreach($lists as $value){ $post = get_post($value->ID); setup_postdata($post);?>
	          	<li>
	          		<time><?php echo $value->post_date;?></time>
					<p class="note" style="height: auto;"><?php echo MBThemes_get_excerpt();?></p>
					<p class="text-muted"><?php _e('问题：','mobantu');?><a target="_blank" href="<?php echo get_permalink($value->post_name);?>"><?php echo $value->post_title;?></a></p>
				</li>
	          	<?php }?>
	          </ul>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'collect'){ ?>
	          <?php 
			  	$perpage = 16;
				if (!get_query_var('paged')) {
					$paged = 1;
				}else{
					$paged = esc_sql(get_query_var('paged'));
				}
				$total_collect = $wpdb->get_var("select count(ID) from ".$wpdb->prefix."collects where user_id=".$current_user->ID);
				$pagess = ceil($total_collect / $perpage);
				$offset = $perpage*($paged-1);
				$results = $wpdb->get_results("select * from ".$wpdb->prefix."collects where user_id=".$current_user->ID." order by create_time DESC limit $offset,$perpage");
				if($results){
			  ?>
	          <div class="user-gridlist<?php if(_MBT('user_posts_water')) echo ' waterfall';?> clearfix">
	            <?php foreach($results as $result){?>
	          	<div class="item clearfix">
	          		<a href="<?php the_permalink($result->post_id);?>" target="_blank"><img src="<?php echo MBThemes_thumbnail_post($result->post_id,_MBT('user_posts_water')?'1':'0');?>"></a>
	          		<h4><a href="<?php the_permalink($result->post_id);?>" target="_blank"><?php echo get_the_title($result->post_id);?></a></h4>
	          		<time><span><?php _e('收藏于：','mobantu');?></span><?php echo $result->create_time;?></time>
	          		<p class="text-muted"><a href="javascript:;" class="article-collect" data-id="<?php echo $result->post_id;?>"><?php _e('取消收藏','mobantu');?></a></p>
	          	</div>
	            <?php }?>
	          </div>
	          <?php MBThemes_custom_paging($paged,$pagess);?>
	          <?php }else{?>
	          <div class="user-ordernone"><h6><?php _e('暂无记录','mobantu');?></h6></div>
	          <?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'info'){ ?>
	          <form style="margin-bottom: 30px">
	            <ul class="user-meta">
	              <li>
	                <label><?php _e('用户名','mobantu');?></label>
	                <?php echo $current_user->user_login;?> </li>
	              <li>
	                <label><?php _e('注册时间','mobantu');?></label>
	                <?php echo get_date_from_gmt( $current_user->user_registered ); ?>
	                </li>
	              <li>
	                <label><?php _e('昵称','mobantu');?></label>
	                <input type="text" class="form-control" name="nickname" value="<?php echo $current_user->nickname;?>">
	              </li>
	              <li>
	                <label>QQ</label>
	                <input type="text" class="form-control" name="qq" value="<?php echo get_user_meta($current_user->ID, 'qq', true);?>">
	              </li>
	              <li>
	                <label><?php _e('个人简介','mobantu');?></label>
	                <textarea class="form-control" name="description" rows="5" style="height: 80px;padding: 5px 10px;"><?php echo $current_user->description;?></textarea>
	              </li>
	              <li>
	                <input type="button" evt="user.data.submit" class="btn btn-primary" value="<?php _e('修改资料','mobantu');?>">
	                <input type="hidden" name="action" value="user.edit">
	              </li>
	            </ul>
	          </form>
	          <?php if(_MBT('user_idcard_verify')){ 
	          	$verify_user = get_user_meta($current_user->ID,'verify_user',true);
	          	if($verify_user){
	          		$verify_name = get_user_meta($current_user->ID,'verify_name',true);
	          		$verify_idcard = get_user_meta($current_user->ID,'verify_idcard',true);
	          ?>
	          <form style="margin-bottom: 30px;">
	            <ul class="user-meta">
	            <li>
	                <label><?php _e('姓名','mobantu');?></label>
	                <input type="text" class="form-control" name="verify_name" value="<?php echo $verify_name;?>" disabled />
	              </li>
	              <li>
	                <label><?php _e('身份证','mobantu');?></label>
	                <input type="text" class="form-control" name="verify_idcard" value="<?php echo _mbt_substr_cut($verify_idcard);?>" disabled />
	              </li>
	              <li>
	                <input type="button" class="btn btn-primary disabled" value="<?php _e('已实名','mobantu');?>" disabled>
	                <input type="hidden" name="action" value="user.idcard">
	              </li>               
	             </ul>
	          </form>
	          <?php
	          	}else{
	          	$_SESSION['verify_user_nonce'] = wp_create_nonce( time().rand(1000,9999) );
	          ?>
	          <form style="margin-bottom: 30px;">
	            <ul class="user-meta">
	            <li>
	                <label><?php _e('姓名','mobantu');?></label>
	                <input type="text" class="form-control" name="verify_name" />
	              </li>
	              <li>
	                <label><?php _e('身份证','mobantu');?></label>
	                <input type="text" class="form-control" name="verify_idcard" />
	              </li>
	              <li>
	                <input type="button" evt="user.idcard.submit" class="btn btn-primary" value="<?php _e('实名认证','mobantu');?>"> <span class="form-tips"><?php _e('仅有一次认证机会，请认真仔细填写','mobantu');?></span>
	                <input type="hidden" name="action" value="user.idcard">
	                <input type="hidden" name="verify_nonce" value="<?php echo $_SESSION['verify_user_nonce'];?>">
	              </li>               
	             </ul>
	          </form>
	          <?php }}?>
	          <form <?php if(_MBT('user_force_email') && !$current_user->user_email) echo 'class="user-force-bind"';?> id="bind-email" style="margin-bottom: 30px;">
	            <ul class="user-meta">
	            <li>
	                <label><?php _e('邮箱','mobantu');?></label>
	                <input type="email" class="form-control" name="email" value="<?php echo $current_user->user_email;?>">
	              </li>
	              <li>
	                <label><?php _e('验证码','mobantu');?></label>
	                <input type="text" class="form-control" name="captcha" value="" style="width:150px;display:inline-block"> <a evt="user.email.captcha.submit" style="display:inline-block;font-size: 13px;cursor: pointer;"><i class="icon icon-mail"></i> <?php _e('获取验证码','mobantu');?></a>
	              </li>
	              <li>
	                <input type="button" evt="user.email.submit" class="btn btn-primary" value="<?php if($current_user->user_email) echo __('修改邮箱','mobantu'); else echo __('绑定邮箱','mobantu');?>">
	                <input type="hidden" name="action" value="user.email">
	              </li>               
	             </ul>
	          </form>
	          <?php if(_MBT('oauth_sms')){
	          	$mobile = $wpdb->get_var("select mobile from $wpdb->users where ID=".$current_user->ID);
	          	?>
	          <form <?php if(_MBT('user_force_mobile') && !$mobile) echo 'class="user-force-bind"';?> style="margin-bottom: 30px;">
	            <ul class="user-meta">
	            <li>
	                <label><?php _e('手机号','mobantu');?></label>
	                <input type="text" class="form-control" name="mobile" value="<?php echo $mobile;?>">
	              </li>
	              <li>
	                <label><?php _e('验证码','mobantu');?></label>
	                <input type="text" class="form-control" name="captcha" value="" style="width:150px;display:inline-block"> <a evt="user.mobile.captcha.submit" style="display:inline-block;font-size: 13px;cursor: pointer;"><i class="icon icon-mobile"></i> <?php _e('获取验证码','mobantu');?></a>
	              </li>
	              <li>
	                <input type="button" evt="user.mobile.submit" class="btn btn-primary" value="<?php _e('修改手机号','mobantu');?>">
	                <input type="hidden" name="action" value="user.mobile">
	              </li>               
	             </ul>
	          </form>
	          <?php }?>
	          <form style="margin-bottom: 30px">
	            <ul class="user-meta">
	              <li>
	                <label><?php _e('新密码','mobantu');?></label>
	                <input type="password" class="form-control" name="password">
	              </li>
	              <li>
	                <label><?php _e('重复新密码','mobantu');?></label>
	                <input type="password" class="form-control" name="password2">
	              </li>
	              <li>
	                <input type="button" evt="user.data.submit" class="btn btn-primary" value="<?php _e('修改密码','mobantu');?>">
	                <input type="hidden" name="action" value="user.password">
	              </li>
	            </ul>
	          </form>
	          <?php if(_MBT('oauth_socialogin_weixin') || _MBT('oauth_socialogin_weibo') || _MBT('oauth_socialogin_qq') || _MBT('oauth_qq') || _MBT('oauth_weibo') || (_MBT('oauth_weixin') || (_MBT('oauth_weixin_mobile') && modown_is_mobile())) || _MBT('oauth_weixin_mp') && function_exists('ews_login')){?>
	          	<ul class="user-meta">
				<li class="secondItem">
					<?php 
						$userSocial = $wpdb->get_row("select qqid,sinaid,weixinid from $wpdb->users where ID=".$current_user->ID);
					?>
					<label><?php _e('社交账号绑定','mobantu');?></label>
					<?php if(_MBT('oauth_weixin_mp') && function_exists('ews_login')){?>
					<section class="item">
						<section class="platform weixin">
							<i class="icon icon-weixin"></i>
						</section>
						<section class="platform-info">
							<p class="name"><?php _e('微信','mobantu');?></p><p class="status">
							<?php if($userSocial->weixinid){?>
							<span><?php _e('已绑定','mobantu');?></span>
							<a href="javascript:;" evt="user.social.cancel" data-type="weixin"><?php _e('取消绑定','mobantu');?></a>
							<?php }else{?>
							<a href="javascript:;" evt="user.social.ews.bind"><?php _e('立即绑定','mobantu');?></a>
							<div class="erphp-weixin-scan-bind"><?php echo do_shortcode('[erphp_weixin_scan_bind type=1]');?></div>
							<?php }?>
							</p>
						</section>
					</section>
					<?php }?>
					<?php if(_MBT('oauth_weixin') || (_MBT('oauth_weixin_mobile') && modown_is_mobile())){?>
					<section class="item">
						<section class="platform weixin">
							<i class="icon icon-weixin"></i>
						</section>
						<section class="platform-info">
							<p class="name"><?php _e('微信','mobantu');?></p><p class="status">
							<?php if($userSocial->weixinid){?>
							<span><?php _e('已绑定','mobantu');?></span>
							<a href="javascript:;" evt="user.social.cancel" data-type="weixin"><?php _e('取消绑定','mobantu');?></a>
							<?php }else{?>
								<?php if(modown_is_mobile() && _MBT('oauth_weixin_mobile')){?>
								<a class="login-weixin" href="https://open.weixin.qq.com/connect/oauth2/authorize?appid=<?php echo _MBT('oauth_weixinid_mobile');?>&redirect_uri=<?php echo home_url();?>/oauth/weixin/bind.php&response_type=code&scope=snsapi_userinfo&state=MBT_weixin_login#wechat_redirect" rel="nofollow"><?php _e('立即绑定','mobantu');?></a>
								<?php }elseif(_MBT('oauth_weixin')){?>
								<a href="https://open.weixin.qq.com/connect/qrconnect?appid=<?php echo _MBT('oauth_weixinid');?>&redirect_uri=<?php bloginfo("url")?>/oauth/weixin/bind.php&response_type=code&scope=snsapi_login&state=MBT_weixin_login#wechat_redirect" ><?php _e('立即绑定','mobantu');?></a>
								<?php }?>
							<?php }?>
							</p>
						</section>
					</section>
					<?php }?>
					<?php if(_MBT('oauth_socialogin_weixin')){?>
					<section class="item">
						<section class="platform weixin">
							<i class="icon icon-weixin"></i>
						</section>
						<section class="platform-info">
							<p class="name"><?php _e('微信','mobantu');?></p><p class="status">
							<?php if($userSocial->weixinid){?>
							<span><?php _e('已绑定','mobantu');?></span>
							<a href="javascript:;" evt="user.social.cancel" data-type="weixin"><?php _e('取消绑定','mobantu');?></a>
							<?php }else{?>
							<a href="<?php bloginfo("url");?>/oauth/socialogin?act=bind&type=wx&rurl=<?php echo get_permalink(MBThemes_page('template/user.php'));?>?action=info" ><?php _e('立即绑定','mobantu');?></a>
							<?php }?>
							</p>
						</section>
					</section>
					<?php }?>
					<?php if(_MBT('oauth_socialogin_weibo')){?>
					<section class="item">
						<section class="platform weibo">
							<i class="icon icon-weibo"></i>
						</section>
						<section class="platform-info">
							<p class="name"><?php _e('微博','mobantu');?></p><p class="status">
							<?php if($userSocial->sinaid){?>
							<span><?php _e('已绑定','mobantu');?></span>
							<a href="javascript:;" evt="user.social.cancel" data-type="weibo"><?php _e('取消绑定','mobantu');?></a>
							<?php }else{?>
							<a href="<?php bloginfo("url");?>/oauth/socialogin?act=bind&type=sina&rurl=<?php echo get_permalink(MBThemes_page('template/user.php'));?>?action=info" ><?php _e('立即绑定','mobantu');?></a>
							<?php }?>
							</p>
						</section>
					</section>
					<?php }?>
					<?php if(_MBT('oauth_socialogin_qq')){?>
					<section class="item">
						<section class="platform qq">
							<i class="icon icon-qq"></i>
						</section>
						<section class="platform-info">
							<p class="name">QQ</p><p class="status">
							<?php if($userSocial->qqid){?>
							<span><?php _e('已绑定','mobantu');?></span>
							<a href="javascript:;" evt="user.social.cancel" data-type="qq"><?php _e('取消绑定','mobantu');?></a>
							<?php }else{?>
							<a href="<?php bloginfo("url");?>/oauth/socialogin?act=bind&type=qq&rurl=<?php echo get_permalink(MBThemes_page('template/user.php'));?>?action=info" ><?php _e('立即绑定','mobantu');?></a>
							<?php }?>
							</p>
						</section>
					</section>
					<?php }?>
					<?php if(_MBT('oauth_weibo')){?>
					<section class="item">
						<section class="platform weibo">
							<i class="icon icon-weibo"></i>
						</section>
						<section class="platform-info">
							<p class="name"><?php _e('微博','mobantu');?></p><p class="status">
							<?php if($userSocial->sinaid){?>
							<span><?php _e('已绑定','mobantu');?></span>
							<a href="javascript:;" evt="user.social.cancel" data-type="weibo"><?php _e('取消绑定','mobantu');?></a>
							<?php }else{?>
							<a href="<?php bloginfo("url");?>/oauth/weibo/bind.php?rurl=<?php echo get_permalink(MBThemes_page('template/user.php'));?>?action=info" ><?php _e('立即绑定','mobantu');?></a>
							<?php }?>
							</p>
						</section>
					</section>
					<?php }?>
					<?php if(_MBT('oauth_qq')){?>
					<section class="item">
						<section class="platform qq">
							<i class="icon icon-qq"></i>
						</section>
						<section class="platform-info">
							<p class="name">QQ</p><p class="status">
							<?php if($userSocial->qqid){?>
							<span><?php _e('已绑定','mobantu');?></span>
							<a href="javascript:;" evt="user.social.cancel" data-type="qq"><?php _e('取消绑定','mobantu');?></a>
							<?php }else{?>
							<a href="<?php bloginfo("url");?>/oauth/qq/bind.php?rurl=<?php echo get_permalink(MBThemes_page('template/user.php'));?>?action=info" ><?php _e('立即绑定','mobantu');?></a>
							<?php }?>
							</p>
						</section>
					</section>
					<?php }?>
				</li>
				</ul>
				<?php }?>
	      <?php }elseif(isset($_GET['action']) && $_GET['action'] == 'ticket'){ 
	      		modown_ticket_new_html();
	      }elseif(isset($_GET['action']) && $_GET['action'] == 'tickets'){ 
	      		modown_ticket_list_html();
	      }elseif((isset($_GET['action']) && $_GET['action'] == 'charge') || (!isset($_GET['action'])) && !$wap_ui){ 

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
							$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.get_option('ice_weixin_appid').'&redirect_uri='.urlencode(constant("erphpdown")).'payment%2Fweixin.php%3Fice_money%3D'.$_POST['ice_money'].'&response_type=code&scope=snsapi_base&state=STATE&connect_redirect=1#wechat_redirect';
						}else{
							$url=constant("erphpdown")."payment/weixin.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
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
					}elseif($paytype==84)
					{
						$url=constant("erphpdown")."payment/epay.php?ice_money=".$_POST['ice_money']."&type=bank"."&timestamp=".time();
					}elseif($paytype==100)
					{
						$url=home_url('?epd_r64='.base64_encode('stripe-'.$_POST['ice_money'].'-'.time()));
					}
					elseif($paytype==92)
					{
						$url=constant("erphpdown")."payment/payjs.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
					}
					elseif($paytype==91)
					{
						$url=constant("erphpdown")."payment/payjs.php?ice_money=".$_POST['ice_money']."&type=alipay"."&timestamp=".time();
					}
					elseif($paytype==102)
					{
						$url=constant("erphpdown")."payment/vpay.php?ice_money=".$_POST['ice_money']."&timestamp=".time();
					}
					elseif($paytype==101)
					{
						$url=constant("erphpdown")."payment/vpay.php?ice_money=".$_POST['ice_money']."&type=2"."&timestamp=".time();
					}
					elseif($paytype==111)
					{
						$url=constant("erphpdown")."payment/easepay.php?ice_money=".$_POST['ice_money']."&type=alipay"."&timestamp=".time();
					}elseif($paytype==112)
					{
						$url=constant("erphpdown")."payment/easepay.php?ice_money=".$_POST['ice_money']."&type=wxpay"."&timestamp=".time();
					}elseif($paytype==113)
					{
						$url=constant("erphpdown")."payment/easepay.php?ice_money=".$_POST['ice_money']."&type=usdt"."&timestamp=".time();
					}
					elseif($paytype==141)
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
					}
					else{
						$doo = 0;
					}
					
					if($doo) echo "<script>location.href='".$url."'</script>";
					exit;
				}
	      	?>
	          	<div class="charge">
	            	<div class="charge-header clearfix">
	                	<div class="item">
	                		<b class="color"><?php echo sprintf("%.2f",$okMoney);?></b><?php echo ' '.$moneyName;?>
	                		<p><?php _e('可用余额','mobantu');?>&nbsp;&nbsp;&nbsp;&nbsp;<a href="<?php echo add_query_arg('action','moneylog',get_permalink())?>"><?php _e('余额明细','mobantu');?></a></p>
	                		<?php 
	                			$erphp_aff_money = get_option('erphp_aff_money');
				                if(!_MBT('user_aff') && $erphp_aff_money && function_exists('erphpGetUserOkAff')){
				                  $okAffMoney = erphpGetUserOkAff();
				                  echo '<span class="tips">'.__('推广余额：','mobantu').$okAffMoney.$moneyName.'</span>';
				              	}
	                		?>
	                	</div>
	                	<?php if(!_MBT('user_aff')){?>
	                	<div class="item item-pc">
	                		<?php echo '<b>'.MBThemes_aff_money2($current_user->ID).'</b> '.$moneyName;
	                		?>
	                		<p><?php _e('推广奖励','mobantu');?>&nbsp;&nbsp;&nbsp;&nbsp;<a href="<?php echo add_query_arg('action','aff',get_permalink())?>"><?php _e('推广详情','mobantu');?></a></p>
	                		<span class="tips"><?php _e('统计可能偏差','mobantu');?></span>
	                	</div>
	                	<?php }else{
	                		$totalchong = $wpdb->get_var("SELECT SUM(ice_money) FROM $wpdb->icemoney WHERE ice_success=1 and ice_user_id=".$current_user->ID);
	                	?>
	                	<div class="item item-tablet">
	                		<b><?php echo $totalchong?$totalchong:'0';?></b><?php echo ' '.$moneyName;?>
	                		<p><?php _e('累计充值','mobantu');?></p>
	                	</div>
	                	<?php }?>
	                	<div class="item item-tablet">
	                		<?php 
		                    if($userTypeId==6){
		                        echo "<b>".$erphp_day_name."</b>";
		                    }elseif($userTypeId==7){
		                        echo "<b>".$erphp_month_name."</b>";
		                    }elseif ($userTypeId==8){
		                        echo "<b>".$erphp_quarter_name."</b>";
		                    }elseif ($userTypeId==9){
		                        echo "<b>".$erphp_year_name."</b>";
		                    }elseif ($userTypeId==10){
		                        echo "<b>".$erphp_life_name."</b>";
		                    }elseif ($userTypeId==11){
		                        echo "<b>".$erphp_super_name."</b>";
		                    }elseif(function_exists('getUsreMemberCatStatus') && getUsreMemberCatStatus()){
		                    echo '<b>'.__('分类VIP','mobantu').'</b>';
		                    }else {
		                        echo '<b>'.__('普通用户','mobantu').'</b>';
		                    }
		                    echo ($userTypeId>0&&$userTypeId<10) ?'<span class="tips">'.__('到期时间：','mobantu').getUsreMemberTypeEndTime().'</span>':'';
		                    ?>
	                		<p><?php _e('当前权限','mobantu');?></p>
	                	</div>
	                </div>
	                <?php if(!_MBT('recharge_default')){?>
	            	<form id="charge-form" action="" method="post">
		              	<div class="item" style="overflow: hidden;margin-bottom:0">
		              		<?php if(_MBT('recharge_price_s')){
		              			$prices = _MBT('recharge_price');
		              			if($prices){
		              				$price_arr = explode(',',$prices);
		              				echo '<div class="prices">';
		              				foreach ($price_arr as $price) {
		              					echo '<input type="radio" name="ice_money" id="ice_money'.$price.'" value="'.$price.'" checked><label for="ice_money'.$price.'" evt="price.select">'.$price.__('元','mobantu').'</label>';
		              				}
		              				echo '</div>';
		              			}
		              		?>
		              		<input type="submit" value="<?php _e('立即充值','mobantu')?>" class="btn btn-recharge" evt="user.charge.submit">
		              		<?php }else{?>
			                <input type="number" min="0" step="0.01" class="form-control input-recharge" name="ice_money" id="ice_money" required="" placeholder="1 <?php _e('元','mobantu')?> = <?php echo get_option('ice_proportion_alipay');?> <?php echo $moneyName;?>"><input type="submit" value="<?php _e('立即充值','mobantu')?>" class="btn btn-recharge" evt="user.charge.submit"><span class="rmb"><?php _e('元','mobantu')?></span>
			            <?php }?>
			            </div>
			            <div class="item payment-radios">
			            	<?php 
			            		$erphpdown_recharge_order = get_option('erphpdown_recharge_order');
			            		if($erphpdown_recharge_order){
			            			$erphpdown_recharge_order_arr = explode(',', str_replace('，', ',', trim($erphpdown_recharge_order)));
			            			$pi = 0;
			            			foreach ($erphpdown_recharge_order_arr as $payment) {
			            				if($pi == 0) $checked = ' checked'; else $checked = '';
			            				switch ($payment) {
			            					case 'alipay':
			            						echo '<input type="radio" id="paytype1"'.$checked.' class="paytype" name="paytype" value="1" /> <label for="paytype1" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label>';
			            						break;
			            					case 'wxpay':
			            						echo '<input type="radio" id="paytype3" class="paytype"'.$checked.' name="paytype" value="3" /> <label for="paytype3" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label>';
			            						break;
			            					case 'f2fpay':
			            						echo '<input type="radio" id="paytype2" class="paytype"'.$checked.' name="paytype" value="2" /> <label for="paytype2" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label>';
			            						break;
			            					case 'paypal':
			            						echo '<input type="radio" id="paytype4" class="paytype"'.$checked.' name="paytype" value="4" /> <label for="paytype4" class="payment-label payment-paypal-label" title="Paypal"><i class="icon icon-paypal"></i></label>';
			            						break;
			            					case 'paypy-wx':
			            						echo '<input type="radio" id="paytype52" class="paytype" name="paytype" value="52"'.$checked.' /> <label for="paytype52" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label>';
			            						break;
			            					case 'paypy-ali':
			            						echo '<input type="radio" id="paytype51" class="paytype" name="paytype" value="51"'.$checked.' /> <label for="paytype51" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label>';
			            						break;
			            					case 'payjs-wx':
			            						echo '<input type="radio" id="paytype92" class="paytype" name="paytype" value="92"'.$checked.' /><label for="paytype92" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label>';
			            						break;
			            					case 'payjs-ali':
			            						echo '<input type="radio" id="paytype91" class="paytype" name="paytype" value="91"'.$checked.' /><label for="paytype91" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label>';
			            						break;
			            					case 'xhpay-wx':
			            						echo '<input type="radio" id="paytype61" class="paytype" name="paytype" value="61"'.$checked.' /> <label for="paytype61" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label>';
			            						break;
			            					case 'xhpay-ali':
			            						echo '<input type="radio" id="paytype62" class="paytype" name="paytype" value="62"'.$checked.' /> <label for="paytype62" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label>';
			            						break;
			            					case 'codepay-wx':
			            						echo '<input type="radio" id="paytype72" class="paytype" name="paytype" value="72"'.$checked.' /> <label for="paytype72" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label>';
			            						break;
			            					case 'codepay-ali':
			            						echo '<input type="radio" id="paytype71" class="paytype" name="paytype" value="71"'.$checked.' /> <label for="paytype71" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label>';
			            						break;
			            					case 'codepay-qq':
			            						echo '<input type="radio" id="paytype73" class="paytype" name="paytype" value="73"'.$checked.' /> <label for="paytype73" class="payment-label payment-qqpay-label"><i class="icon icon-qq"></i></label>';
			            						break;
			            					case 'epay-wx':
			            						echo '<input type="radio" id="paytype82" class="paytype" name="paytype" value="82"'.$checked.' /> <label for="paytype82" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label>';
			            						break;
			            					case 'epay-ali':
			            						echo '<input type="radio" id="paytype81" class="paytype" name="paytype" value="81"'.$checked.' /> <label for="paytype81" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label>';
			            						break;
			            					case 'epay-qq':
			            						echo '<input type="radio" id="paytype83" class="paytype" name="paytype" value="83"'.$checked.' /> <label for="paytype83" class="payment-label payment-qqpay-label"><i class="icon icon-qq"></i></label>';
			            						break;
			            					case 'epay-bank':
			            						echo '<input type="radio" id="paytype84" class="paytype" name="paytype" value="84"'.$checked.' /> <label for="paytype84" class="payment-label payment-credit-card-label"><i class="icon icon-credit-card"></i></label>';
			            						break;
			            					case 'easepay-wx':
			            						echo '<input type="radio" id="paytype112" class="paytype" name="paytype" value="112"'.$checked.' /> <label for="paytype112" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label>';
			            						break;
			            					case 'easepay-ali':
			            						echo '<input type="radio" id="paytype111" class="paytype" name="paytype" value="111"'.$checked.' /> <label for="paytype111" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label>';
			            						break;
			            					case 'easepay-usdt':
			            						echo '<input type="radio" id="paytype113" class="paytype" name="paytype" value="113"'.$checked.' /> <label for="paytype113" class="payment-label payment-ut-label" title="USDT"><i class="icon icon-usdt"></i></label>';
			            						break;
			            					case 'rpay-ali':
			            						echo '<input type="radio" id="paytype141" class="paytype" name="paytype" value="141"'.$checked.' /> <label for="paytype141" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label>';
			            						break;
			            					case 'rpay-wx':
			            						echo '<input type="radio" id="paytype142" class="paytype" name="paytype" value="142"'.$checked.' /> <label for="paytype142" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label>';
			            						break;
			            					case 'rpay-stripe':
			            						echo '<input type="radio" id="paytype143" class="paytype" name="paytype" value="143"'.$checked.' /> <label for="paytype143" class="payment-label payment-stripe-label" title="Stripe"><i class="icon icon-credit-card"></i></label>';
			            						break;
			            					case 'rpay-paypal':
			            						echo '<input type="radio" id="paytype144" class="paytype" name="paytype" value="144"'.$checked.' /> <label for="paytype144" class="payment-label payment-paypal-label" title="Paypal"><i class="icon icon-paypal"></i></label>';
			            						break;
			            					case 'vpay-wx':
			            						echo '<input type="radio" id="paytype102" class="paytype" name="paytype" value="102"'.$checked.' /> <label for="paytype102" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label>';
			            						break;
			            					case 'vpay-ali':
			            						echo '<input type="radio" id="paytype101" class="paytype" name="paytype" value="101"'.$checked.' /> <label for="paytype101" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label>';
			            						break;
			            					default:
			            						break;
			            				}
			            				$pi ++;
			            			}
			            		}else{
			            	?>
			            			<?php if(get_option('erphpdown_usdt_address')){?> 
				                    <input type="radio" id="paytype120" class="paytype" checked name="paytype" value="120" /> <label for="paytype120" class="payment-label payment-ut-label" title="USDT"><i class="icon icon-usdt"></i></label>
				                    <?php }?>
				                    <?php if(get_option('erphpdown_easepay_id')){?>
					                <?php if(!get_option('erphpdown_easepay_alipay')){?><input type="radio" id="paytype111" class="paytype" name="paytype" value="111" checked /> <label for="paytype111" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label><?php }?>
					                <?php if(!get_option('erphpdown_easepay_wxpay')){?><input type="radio" id="paytype112" class="paytype" name="paytype" value="112" checked /> <label for="paytype112" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label><?php }?>
				                <?php }?>
				                					<?php if(get_option('erphpdown_rpay_id')){?>
					<?php if(!get_option('erphpdown_rpay_alipay')){?><input type="radio" id="paytype141" class="paytype" name="paytype" value="141" checked /> <label for="paytype141" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label><?php }?>
					<?php if(!get_option('erphpdown_rpay_wxpay')){?><input type="radio" id="paytype142" class="paytype" name="paytype" value="142" checked /> <label for="paytype142" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label><?php }?>
					<?php if(!get_option('erphpdown_rpay_stripe')){?><input type="radio" id="paytype143" class="paytype" name="paytype" value="143" checked /> <label for="paytype143" class="payment-label payment-stripe-label" title="Stripe"><i class="icon icon-credit-card"></i></label><?php }?>
					<?php if(!get_option('erphpdown_rpay_paypal')){?><input type="radio" id="paytype144" class="paytype" name="paytype" value="144" checked /> <label for="paytype144" class="payment-label payment-paypal-label" title="Paypal"><i class="icon icon-paypal"></i></label><?php }?>
					<?php }?>
					<?php if(get_option('erphpdown_vpay_key')){?>
					                <?php if(!get_option('erphpdown_vpay_alipay')){?><input type="radio" id="paytype101" class="paytype" name="paytype" value="101" checked /> <label for="paytype101" class="payment-label payment-alipay-label"><i class="icon icon-alipay-color"></i></label><?php }?>
					                <?php if(!get_option('erphpdown_vpay_wxpay')){?><input type="radio" id="paytype102" class="paytype" name="paytype" value="102" checked /> <label for="paytype102" class="payment-label payment-wxpay-label"><i class="icon icon-wxpay-color"></i></label><?php }?>
					                <?php }?>
			            	<?php }?>
		                </div>
		            </form>
		            <?php }?>
		            <?php 
		            	$epd_game_price  = get_option('epd_game_price');
				        if($epd_game_price){
				        	echo '<div class="charge-gift">';
				          	$cnt = count($epd_game_price['buy']);
				          	for($i=0; $i<$cnt;$i++){
				          		if($epd_game_price['buy'][$i]){
					          		echo sprintf( __('<div class="item">充值 <span>%s</span> 元，实际到账 <span>%s</span> %s</div>','mobantu'), $epd_game_price['buy'][$i], $epd_game_price['get'][$i]*get_option('ice_proportion_alipay'), $moneyName);
					          	}
				          	}
				          	echo '</div>';
				        }
		            ?>
		            <?php if(_MBT('user_charge_tips')){?><div class="charge-tips"><i class="icon icon-horn"></i> <?php echo _MBT('user_charge_tips');?></div><?php }?>
		            <?php if(function_exists("checkDoCardResult")){
		            	$_SESSION['erphpcard_nonce'] = wp_create_nonce( time().rand(1000,9999) );
		            ?>
		            <form id="charge-form2" action="" method="post">
		            	<h3><span><?php _e('充值卡充值','mobantu')?></span></h3>
		              	<div class="item">
			                <input type="text" class="form-control input-recharge" id="erphpcard_num" name="erphpcard_num" required="" placeholder="<?php _e('卡号','mobantu')?>">
			            </div>
		                <div class="item">
		                	<input type="hidden" id="erphpcard_nonce" name="erphpcard_nonce" value="<?php echo $_SESSION['erphpcard_nonce'];?>">
			              	<input type="button" evt="user.charge.card.submit" value="<?php _e('立即充值','mobantu')?>" class="btn btn-card">
			            </div>
		            </form>
		            <?php $ice_tips_card = get_option('ice_tips_card'); if($ice_tips_card){?><div class="charge-tips"><i class="icon icon-horn"></i> <?php echo $ice_tips_card;?></div><?php }?>
		            <?php }?>
		            <?php if(plugin_check_cred() && get_option('erphp_mycred') == 'yes'){ $erphp_to_mycred = get_option('erphp_to_mycred');?>
		            <form id="charge-form2" action="" method="post">
		            	<h3><span><?php echo sprintf(__('%s兑换','mobantu'), $mycred_core['name']['plural'])?></span></h3>
		              	<div class="item">
			                <input type="number" min="<?php echo 1/$erphp_to_mycred;?>" step="<?php echo 1/$erphp_to_mycred;?>" class="form-control input-recharge" id="erphpmycred_num" name="erphpmycred_num" required="" placeholder="<?php echo sprintf(__('请输入%s数','mobantu'), $moneyName)?>">
			            </div>
		                <div class="item">
			              	<input type="button" evt="user.mycred.submit" value="<?php _e('立即兑换','mobantu')?>" class="btn btn-card">
			              	<p><?php echo $erphp_to_mycred.' '.$mycred_core['name']['plural'];?> = 1 <?php echo $moneyName;?><?php _e('，可用','mobantu')?><?php echo $mycred_core['name']['plural'];?>：<?php echo mycred_get_users_cred( $current_user->ID )?></p>
			            </div>
		            </form>
		            <?php }?>
	            </div>
	      <?php }elseif($wap_ui){?>
	      	<style>.container-user{background: transparent !important;}</style>
	      	<div class="user-wap-wrap">
	      		<div class="user-wap-box user-wap-box1 clearfix">
	      			<a href="javascript:;" class="edit-avatar"<?php if(!_MBT('user_avatar')){?> evt="user.avatar.submit"<?php }?>><?php echo get_avatar($current_user->ID,50);?></a>
			        <div class="user-name"><?php echo $current_user->nickname;?><?php if($userTypeId) echo ' <i class="icon icon-crown-s" style="color:#fbb715"></i>';?></div>
			        <div class="user-desc"><a href="<?php echo add_query_arg('action','info',get_permalink())?>">编辑个人资料 <i class="icon icon-arrow-right"></i></a></div>
			        <?php
			        	$ice_ali_money_checkin = get_option('ice_ali_money_checkin');
			        	if($ice_ali_money_checkin) {
					        if(erphpdown_check_checkin($current_user->ID)){
					      		echo '<div class="mobantu-check"><a href="javascript:;" class="active"><i class="icon icon-calendar-s"></i> '.__('已签到','mobantu').'</a></div>';
					        }else{
					      		echo '<div class="mobantu-check"><a href="javascript:;" class="checkin"><i class="icon icon-calendar"></i> '.__('签到','mobantu').'</a></div>';
					        }
					    }
			        ?>
			        <?php if(!_MBT('user_avatar')){?>
			        <form id="uploadphoto" action="<?php echo get_bloginfo('template_url').'/action/photo.php';?>" method="post" enctype="multipart/form-data" style="display:none;">
			            <input type="file" id="avatarphoto" name="avatarphoto" accept="image/png, image/jpeg">
			        </form>
			    	<?php }?>
			        
	      		</div>
	      		<div class="user-wap-box user-wap-box2 clearfix">
	      			<div class="item" style="padding-top: 8px;">
	      				<div class="tit"><a href="<?php echo add_query_arg('action','charge',get_permalink());?>"><?php _e('可用余额','mobantu');?> <i class="icon icon-arrow-right"></i></a></div>
                		<b class="color"><?php echo sprintf("%.2f",$okMoney);?></b>
                		<?php 
                			$erphp_aff_money = get_option('erphp_aff_money');
			                if(!_MBT('user_aff') && $erphp_aff_money && function_exists('erphpGetUserOkAff')){
			                  $okAffMoney = erphpGetUserOkAff();
			                  echo '<div class="tips">'.__('推广余额：','mobantu').$okAffMoney.'</div>';
			              	}else{
			              		echo '<div class="tips">'.__('余额可用于消费或提现','mobantu').'</div>';
			              	}
                		?>
                	</div>
                	<div class="item" style="padding-top: 8px;">
                		<div class="tit"><a href="<?php echo add_query_arg('action','vip',get_permalink())?>"><?php _e('VIP权限','mobantu');?> <i class="icon icon-arrow-right"></i></a></a></div>
                		<?php 
	                    if($userTypeId==6){
	                        echo "<b style='color: #fbb715;'>".$erphp_day_name."</b>";
	                    }elseif($userTypeId==7){
	                        echo "<b style='color: #fbb715;'>".$erphp_month_name."</b>";
	                    }elseif ($userTypeId==8){
	                        echo "<b style='color: #fbb715;'>".$erphp_quarter_name."</b>";
	                    }elseif ($userTypeId==9){
	                        echo "<b style='color: #fbb715;'>".$erphp_year_name."</b>";
	                    }elseif ($userTypeId==10){
	                        echo "<b style='color: #fbb715;'>".$erphp_life_name."</b>";
	                    }elseif ($userTypeId==11){
	                        echo "<b style='color: #fbb715;'>".$erphp_super_name."</b>";
	                    }elseif(function_exists('getUsreMemberCatStatus') && getUsreMemberCatStatus()){
		                    echo "<b style='color: #fbb715;'>".__('分类VIP','mobantu')."</b>";
		                }else {
	                        echo '<b>'.__('普通用户','mobantu').'</b>';
	                    }
	                    echo ($userTypeId>0&&$userTypeId<10) ?'<div class="tips">'.__('到期时间：','mobantu').getUsreMemberTypeEndTime().'</div>':'<div class="tips">'.__('升级VIP享受更多特权','mobantu').'</div>';
	                    ?>
                	</div>
                	<?php if(!_MBT('user_aff')){?>
                	<div class="item">
                		<div class="tit"><i class="icon icon-aff"></i> <a href="<?php echo add_query_arg('action','aff',get_permalink())?>"><?php _e('累计推广','mobantu');?> <i class="icon icon-arrow-right"></i></a></div>
                		<div class="items clearfix">
                			<div class="it">
	                			<?php $totalaffs = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users WHERE father_id=".$current_user->ID); echo '<b>'.($totalaffs?$totalaffs:0).'</b>';?>
	                			<div class="itn"><?php _e('人数','mobantu')?></div>
                			</div>
                			<div class="it">
	                			<?php echo '<b>'.MBThemes_aff_money2($current_user->ID).'</b>';?>
	                			<div class="itn"><?php _e('奖励','mobantu')?></div>
                			</div>
                		</div>
                		<span class="tips2"><?php _e('统计可能偏差','mobantu');?></span>
                	</div>
                	<?php }else{
                		$totalchong = $wpdb->get_row("SELECT SUM(ice_money) as cmoney, COUNT(ice_id) as ccount FROM $wpdb->icemoney WHERE ice_success=1 and ice_user_id=".$current_user->ID);
                	?>
                	<div class="item">
                		<div class="tit"><i class="icon icon-wallet"></i> <a href="<?php echo add_query_arg('action','history',get_permalink())?>"><?php _e('累计充值','mobantu');?> <i class="icon icon-arrow-right"></i></a></a></div>
                		<div class="items clearfix">
                			<div class="it">
                				<b><?php echo $totalchong?$totalchong->ccount:'0';?></b>
                				<div class="itn"><?php _e('笔','mobantu')?></div>
                			</div>
                			<div class="it">
                				<b><?php echo $totalchong?$totalchong->cmoney:'0.00';?></b>
                				<div class="itn"><?php _e('金额','mobantu')?></div>
                			</div>
                		</div>
                	</div>
                	<?php }?>
                	<div class="item">
                		<div class="tit"><i class="icon icon-order2"></i> <a href="<?php echo add_query_arg('action','order',get_permalink())?>"><?php _e('累计购买','mobantu');?> <i class="icon icon-arrow-right"></i></a></a></div>
                		<div class="items clearfix">
                			<div class="it">
		                		<b><?php $totallists = $wpdb->get_var("SELECT COUNT(ice_id) FROM $wpdb->icealipay WHERE ice_success>0 and ice_user_id=".$current_user->ID); echo $totallists?$totallists:'0';?></b>
		                		<div class="itn"><?php _e('资源','mobantu')?></div>
		                	</div>
                			<div class="it">
		                		<b><?php $totalprices = $wpdb->get_var("SELECT SUM(ice_price) FROM $wpdb->icealipay WHERE ice_success>0 and ice_user_id=".$current_user->ID); echo $totalprices?$totalprices:'0.00';?></b>
		                		<div class="itn"><?php _e('金额','mobantu')?></div>
		                	</div>
		                </div>
                	</div>
	      		</div>
	      		<div class="user-wap-box user-wap-box22 clearfix">
	      			<ul class="clearfix">
				        <li><a href="<?php echo add_query_arg('action','charge',get_permalink())?>"><i class="icon icon-money"></i><span><?php _e('在线充值','mobantu');?></span></a></li>
				        <li><a href="<?php echo add_query_arg('action','vip',get_permalink())?>"><i class="icon icon-crown"></i><span><?php _e('升级VIP','mobantu');?></span></a></li>
	      			</ul>
	      		</div>
	      		<div class="user-wap-box user-wap-box4 clearfix">
	      			<div class="tit">我的服务</div>
	      			<ul class="clearfix">
	      				<?php if ( class_exists( 'WooCommerce', false ) ) {?><li class="usermenu-cart"><a href="<?php echo wc_get_page_permalink( 'myaccount' );?>"><i class="icon icon-cart"></i><span><?php _e('我的购物','mobantu');?></span></a></li><?php }?>
	      				<li><a href="<?php echo add_query_arg('action','history',get_permalink())?>"><i class="icon icon-wallet"></i><span><?php _e('充值记录','mobantu');?></span></a></li>
	      				<li><a href="<?php echo add_query_arg('action','moneylog',get_permalink())?>"><i class="icon icon-order"></i><span><?php _e('余额明细','mobantu');?></span></a></li>
				        <?php if(plugin_check_cred() && get_option('erphp_mycred') == 'yes'){ $mycred_core = get_option('mycred_pref_core');?>
				        <li><a href="<?php echo add_query_arg('action','mycred',get_permalink())?>"><i class="icon icon-gift"></i><span><?php _e('积分记录','mobantu');?></span></a></li>
				    	<?php }?>
				    	<?php if(_MBT('user_sell')){?>
				        <li><a href="<?php echo add_query_arg('action','sell',get_permalink())?>"><i class="icon icon-order-menu"></i><span><?php _e('销售订单','mobantu');?></span></a></li>
				    	<?php }?>
				    	<?php if(function_exists('erphpdown_tuan_install')){?>
						<li><a href="<?php echo add_query_arg('action','tuan',get_permalink())?>"><i class="icon icon-tuan"></i><span><?php _e('拼团订单','mobantu');?></span></a></li>
						<?php }?>
				    	<?php if(function_exists('erphp_task_scripts')){?>
						<li><a href="<?php echo add_query_arg('action','task',get_permalink())?>"><i class="icon icon-posts"></i><span><?php _e('悬赏任务','mobantu');?></span></a></li>
						<?php }?>
				    	<?php if(function_exists('erphpad_install')){?>
						<li><a href="<?php echo add_query_arg('action','ad',get_permalink())?>"><i class="icon icon-data"></i><span><?php _e('广告订单','mobantu');?></span></a></li>
						<?php }?>
						<?php if(_MBT('withdraw')){?>
				        <li><a href="<?php echo add_query_arg('action','withdraw',get_permalink())?>"><i class="icon icon-withdraws"></i><span><?php _e('站内提现','mobantu');?></span></a></li>
				    	<?php }?>
	      			</ul>
	      		</div>
	      		<div class="user-wap-box user-wap-box4 clearfix">
	      			<div class="tit">我的资料</div>
	      			<ul class="clearfix">
	      				<li><a href="<?php echo add_query_arg('action','info',get_permalink())?>"><i class="icon icon-info"></i><span><?php _e('个人信息','mobantu');?></span></a></li>
				        <?php if(class_exists( 'AnsPress' )){?>
				        <li><a href="<?php echo add_query_arg('action','question',get_permalink())?>"><i class="icon icon-help"></i><span><?php _e('提问记录','mobantu');?></span></a></li>
				        <li><a href="<?php echo add_query_arg('action','answer',get_permalink())?>"><i class="icon icon-comment"></i><span><?php _e('回答记录','mobantu');?></span></a></li>
				        <?php }?>
				        <?php if(function_exists('QAPress_scripts')){?>
				        <li><a href="<?php echo add_query_arg('action','faq',get_permalink())?>"><i class="icon icon-help"></i><span><?php _e('提问记录','mobantu');?></span></a></li>
				        <?php }?>
						<li><a href="<?php echo add_query_arg('action','comment',get_permalink())?>"><i class="icon icon-comments"></i><span><?php _e('文章评论','mobantu');?></span></a></li>
						<?php if(_MBT('user_sell')){?>
						<li><a href="<?php echo add_query_arg('action','post',get_permalink())?>"><i class="icon icon-posts"></i><span><?php _e('文章投稿','mobantu');?></span></a></li>
						<?php }?>
						<?php if(_MBT('post_collect') || _MBT('post_sidefav')){?><li><a href="<?php echo add_query_arg('action','collect',get_permalink())?>"><i class="icon icon-stars"></i><span><?php _e('我的收藏','mobantu');?></span></a></li><?php }?>
						<?php if(_MBT('ticket')){?>
						<li><a href="<?php echo add_query_arg('action','ticket',get_permalink())?>"><i class="icon icon-temp-new"></i><span><?php _e('提交工单','mobantu');?></span></a></li>
						<li><a href="<?php echo add_query_arg('action','tickets',get_permalink())?>"><i class="icon icon-temp"></i><span><?php _e('工单记录','mobantu');?></span></a></li>
						<?php }?>
	      			</ul>
	      		</div>
	      		<div class="user-wap-logout clearfix">
	      			<a href="<?php echo wp_logout_url(get_bloginfo("url"));?>"><?php _e('安全退出','mobantu');?></a>
	      		</div>
	      	</div>
	      <?php }?>
	    </div>
	    <div class="user-tips"></div>
	  </div>
	</div>
	<script src="<?php bloginfo("template_url")?>/static/js/user.js?ver=<?php echo THEME_VER;?>"></script>
</div>
<?php get_footer();?>