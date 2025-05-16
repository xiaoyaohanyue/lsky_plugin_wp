<?php


/**
 * Plugin Name: Lsky 图床插件
 * Plugin URI: https://github.com/xiaoyaohanyue/lsky_plugin_wp
 * Description: 将 WordPress 媒体上传至 Lsky 图床
 * Version: 1.0.0
 * Author: 妖月
 * Author URI: https://fjwr.xyz
 */

if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . '/autoload.php';

use src\Update;
use src\LskyCommon;

if (is_admin()) {
    new Update(__FILE__);
}




add_action('admin_menu',array(LskyCommon::class,'lsky_menu'));

require 'src/display.php';
require 'src/function.php';
?>