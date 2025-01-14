<?php
require_once 'LskyCommon.php';

use src\LskyCommon;


error_reporting(0);
function lsky_display(){
    if($_POST['action'] == 'save' ) {
		$datas['permission'] = sanitize_text_field(trim($_POST['permission']));
		$datas['tokens'] = sanitize_text_field(trim($_POST['tokens']));
        $datas['api'] = sanitize_text_field(trim($_POST['api']));
		$datas['switch'] = _sanitize_text_fields(trim($_POST['switch']));
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
<script>var lsky_js_flag="setting";
</script>
<div class="wrap">
	<p><h2>接口设置</h2></p>
	<div id="lsky-setting">
		<form method="post" action="">
			<table class="form-table">
				<tbody>
                    <tr>
						<th scope="row"><label for="api">API</label></th>
						<td><input size="35" type="text" id="api" name="api" value="<?php echo LskyCommon::api_info('api'); ?>" />(必填)</td>
					</tr>
					<tr>
						<th scope="row"><label for="tokens">Tokens</label></th>
						<td><input size="35" type="text" id="tokens" name="tokens" value="<?php echo LskyCommon::api_info('token'); ?>" />(必填)</td>
					</tr>
					<tr>
						<th scope="row"><label for="permission">隐私设置</label></th>
						<td><input size="35" type="text" id="permission" name="permission" value="<?php echo LskyCommon::api_info('permission'); ?>" />(1=公开，0=私有)</td>
					</tr>
					<tr>
						<th scope="row"><label>是否启用：</label></th>
						<td>
						<input type="radio" id="enable" name="switch" value="enable" <?php echo (LskyCommon::api_info('switch') == 'enable')? 'checked':null;?>><label for="enable">启用</label>
						<input type="radio" id="disable" name="switch" value="disable" <?php echo (LskyCommon::api_info('switch') == 'disable')? 'checked':null;?>><label for="disable">禁用</label>
						</td>
					</tr>
				</tbody>
			</table>
			<p><input class="button-primary" type="submit" value="保存设置" /><input type="hidden" name="action" value="save" /></p>
		</form>
	</div>
	<hr />
</div>
<script src="<?php echo plugins_url('../static/post.js',__FILE__); ?>"></script>
<script>

</script>
<?php
}
?>