<?php 
$webpath = dirname(__FILE__);
require_once "$webpath/../../../../wp-config.php";
require_once "$webpath/../../../../wp-settings.php";
?>
<!DOCTYPE html>
<html>
	         <head>
	     <meta http-equiv="refresh" content="2">
	     </head>
	     <body>
	     <div style="margin: 4px; padding: 8px; border: 1px solid gray; background: #EAEAEA; width: 500px">
	         <div><font color="gray">图片替换进度：</font></div>
	        <div style="padding: 0; background-color: white; border: 1px solid navy; width: 500px">
	            <div id="progress" style="padding: 0; background-color: #FFCC66; border: 0; width: 0px; text-align: center;   height: 16px">
	            </div>
	        </div>
	     <div id="status"></div>
	     <div id="percent" style="position: relative; top: -30px; text-align: center; font-weight: bold; font-size: 8pt"></div>
	     </div>
	     </body>
	     </html>
<?php 
$script = '<script>document.getElementById("percent").innerText="%u%%";document.getElementById("progress").style.width="%upx";document.getElementById("status").innerText="%s";</script>';
$procnum = get_option('lsky_setting_process')['procnum'];
$mes = get_option('lsky_setting_process')['mes'];
echo sprintf($script, intval($procnum*1), intval($procnum*5), $mes);
?>