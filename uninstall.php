<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function lsky_plugin_wp_delete_logs($plugin_dir) {
    $logs_dir = trailingslashit($plugin_dir) . 'logs';
    $real_logs_dir = realpath($logs_dir);
    $real_plugin_dir = realpath($plugin_dir);

    if (!$real_logs_dir || !$real_plugin_dir || strpos($real_logs_dir, $real_plugin_dir) !== 0 || !is_dir($real_logs_dir)) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    global $wp_filesystem;
    if ($wp_filesystem instanceof WP_Filesystem_Base) {
        $wp_filesystem->delete($real_logs_dir, true);
    }
}

if (is_multisite()) {
    $lsky_site_ids = get_sites([
        'fields' => 'ids',
        'number' => 0,
    ]);

    foreach ($lsky_site_ids as $lsky_site_id) {
        switch_to_blog($lsky_site_id);
        delete_option('lsky_setting');
        restore_current_blog();
    }
} else {
    delete_option('lsky_setting');
}

lsky_plugin_wp_delete_logs(__DIR__);
