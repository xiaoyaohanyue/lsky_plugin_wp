<?php
error_reporting(0);
function lsky_display(){
    if($_POST['action'] == 'save' ) {
		$datas['permission'] = sanitize_text_field(trim($_POST['permission']));
		$datas['tokens'] = sanitize_text_field(trim($_POST['tokens']));
        $datas['api'] = sanitize_text_field(trim($_POST['api']));
		$datas = serialize($datas);
		update_option('lsky_setting', $datas);
		echo '<div id="message" class="updated fade">设置已保存！</div>';  
	}

?>
<style type="text/css">
#message {
	margin: 1em 0;
	padding: .5em;
}
#lsky-setting {
	margin-bottom: 20px;
	padding: 10px;
	background-color: #ffffff;
	border: 1px solid #e6e6e6;
	box-shadow: 0 0 3px #e3e3e3;
}
</style>
<script>var yaoyue_js_flag="setting";
</script>
<div class="wrap">
	<p><h2>接口设置</h2></p>
	<div id="lsky-setting">
	<iframe id="bane_iframe" name="bane_iframe" style="width:550px;" src="<?php echo plugins_url('../src/res.php',__FILE__); ?>"></iframe>
		<form method="post" action="">
			<table class="form-table">
				<tbody>
                    <tr>
						<th scope="row"><label for="api">API</label></th>
						<td><input size="35" type="text" id="api" name="api" value="<?php echo unserialize(get_option('lsky_setting'))['api']; ?>" />(必填)</td>
					</tr>
					<tr>
						<th scope="row"><label for="tokens">Tokens</label></th>
						<td><input size="35" type="text" id="tokens" name="tokens" value="<?php echo unserialize(get_option('lsky_setting'))['tokens']; ?>" />(必填)</td>
					</tr>
					<tr>
						<th scope="row"><label for="permission">隐私设置</label></th>
						<td><input size="35" type="text" id="permission" name="permission" value="<?php echo unserialize(get_option('lsky_setting'))['permission']; ?>" />(1=公开，0=私有)</td>
					</tr>
				</tbody>
			</table>
			<p><input class="button-primary" type="submit" value="保存设置" /><input type="hidden" name="action" value="save" /></p>
		</form>
		 <a class='button-primary' id='yaoyue-library-test-button' href="javascript:;">一键替换</a> 
	</div>
	<hr />
	By.<a href="">YaoYue</a>|Version|<a href="">点击获取最新版本&详细说明</a><br />
</div>
<script src="<?php echo plugins_url('../static/post.js',__FILE__); ?>"></script>
<?php
}
?>