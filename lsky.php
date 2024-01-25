<?php
/*
Plugin Name: 媒体库插件
Description: 对接lsky图床
Version: 1.0.0
Author: YaoYue
*/
?>
<?php

function lsky_menu(){
    add_plugins_page(
        "图床插件",
        "图床插件",
        'manage_options',
        "lsky",
        'lsky_display'
    );
}

function debugtofile($log){
    $plugin_path = plugin_dir_path(__FILE__);
    $log_path = $plugin_path.'log.log';
	$fp = fopen($log_path,'a');
	fwrite($fp,$log."\r\n");
	fclose($fp);
}
add_action('admin_menu','lsky_menu');

require 'src/display.php';
require 'src/function.php';
?>