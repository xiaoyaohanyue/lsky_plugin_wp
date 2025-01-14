<?php

require_once 'src/LskyCommon.php';

use src\LskyCommon;

/*
Plugin Name: 媒体库插件
Description: 对接lsky图床
Version: 2.0.0
Author: 妖月
*/

// 添加菜单
add_action('admin_menu',array(LskyCommon::class,'lsky_menu'));

require 'src/display.php';
require 'src/function.php';
?>