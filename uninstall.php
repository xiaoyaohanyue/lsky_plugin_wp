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

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($real_logs_dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            wp_delete_file($file->getPathname());
        }
    }

    rmdir($real_logs_dir);
}

if (is_multisite()) {
    $site_ids = get_sites([
        'fields' => 'ids',
        'number' => 0,
    ]);

    foreach ($site_ids as $site_id) {
        switch_to_blog($site_id);
        delete_option('lsky_setting');
        restore_current_blog();
    }
} else {
    delete_option('lsky_setting');
}

lsky_plugin_wp_delete_logs(__DIR__);
