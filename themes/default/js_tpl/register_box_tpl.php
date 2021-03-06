<script type="text/template" id="register_box_tpl">
    <form id="register_form" class="form" method="post">
		<ul>
            <li><label for="email"><?php echo T('user_email');?></label><input id="email" type="text" name="email" value=""/>
              <p class="help-block">eg：example@example.com。</p>
            </li>

            <li><label for="nickname"><?php echo T('user_nickname');?></label><input id="nickname" type="text" name="nickname" value=""/>
              <p class="help-block"><?php echo T('nick_not_valid');?></p>
            </li>
            <li><label for="password"><?php echo T('user_password');?></label><input id="password" type="password" name="password"/></li>
            
            <li><label for="passconf"><?php echo T('re_user_password');?></label>
				<input type="password" class="span3" id="passconf" name="passconf">
              <p class="help-block"><?php echo T('re_user_password');?></p>
			</li>

			<li class="def">
			<label class="checkbox">
 				<input type="checkbox" id="terms" name="terms" value="1" checked="checked"/>
               	 <?php echo T('i_agree');?> <a href="<?php echo spUrl('faq','agreement');?>" target="_blank"><?php echo T('user_treatment');?> </a>
			</label>

            </li>
            <li>
                <button type="submit" class="btn btn_red"><?php echo T('register');?></button>
				<span id="ajax_message" class="ajax_error"></span>
            </li>
        </ul>
    </form>
    <ul class="other">
        <li><strong><?php echo T('already_register');?>？<a href="javascript:void(0);" data-action="openLoginDialog"><?php echo T('direct_login');?></a></strong></li>
		<?php if($settings['api_setting']['QQ']['APPKEY']):?>
		<li><a href="<?php echo spUrl('social','go',array('vendor'=>'QQ'));?>"><i class="login_icon_qq"></i>QQ账号</a></li>
		<?php endif;?>
		<?php if($settings['api_setting']['Sina']['APPKEY']):?>
		<li><a href="<?php echo spUrl('social','go',array('vendor'=>'Sina'));?>"><i class="login_icon_sina"></i>新浪微博</a></li>
		<?php endif;?>
		<?php if($settings['api_setting']['Renren']['APPKEY']):?>        
		<li><a href="<?php echo spUrl('social','go',array('vendor'=>'Renren'));?>"><i class="login_icon_renren"></i>人人账号</a></li>
		<?php endif;?>
		<?php if($settings['api_setting']['Taobao']['APPKEY']):?>    
    	<li><a href="<?php echo spUrl('social','go',array('vendor'=>'Taobao'));?>"><i class="login_icon_taobao"></i>淘宝账号</a></li>
		<?php endif;?>   
	</ul>
</script>