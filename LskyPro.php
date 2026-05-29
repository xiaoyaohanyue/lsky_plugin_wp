<?php


/**
 * Plugin Name: LskyPro（兰空图床）插件
 * Plugin URI: https://github.com/xiaoyaohanyue/lsky_plugin_wp
 * Description: 将 WordPress 媒体上传至 LskyPro（兰空图床）
 * Version: 2.0.5
 * Author: 妖月
 * Author URI: https://fjwr.xyz
 * Requires PHP: 7.4
 * Text Domain: lsky-plugin-wp
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://fjwr.xyz/wp-plugin-updates/lsky_plugin_wp
 */

if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . '/autoload.php';

use src\LskyCommon;
use src\SelfHostedUpdater;

LskyCommon::cleanup_legacy_password();
if (class_exists(SelfHostedUpdater::class)) {
    defined('LSKY_SELF_HOSTED_UPDATES') || define('LSKY_SELF_HOSTED_UPDATES', true);
    new SelfHostedUpdater(__FILE__);
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(LskyCommon::class,'lsky_plugin_settings_link'));
add_action('admin_menu',array(LskyCommon::class,'lsky_menu'));

require plugin_dir_path(__FILE__) . 'src/display.php';
require plugin_dir_path(__FILE__) . 'src/function.php';
?>
