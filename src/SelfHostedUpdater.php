<?php
namespace src;

if (!defined('ABSPATH')) exit;

class SelfHostedUpdater
{
    private $plugin_file;
    private $plugin_slug;
    private $plugin_dir;
    private $update_uri = 'https://fjwr.xyz/wp-plugin-updates/lsky_plugin_wp';
    private $manifest_url = 'https://fjwr.xyz/wp-plugin-updates/lsky_plugin_wp/update.json';

    public function __construct($plugin_file)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->plugin_dir = dirname($this->plugin_slug);

        add_filter('update_plugins_fjwr.xyz', [$this, 'check_for_update'], 10, 4);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'fix_update_folder'], 10, 4);
    }

    private function get_manifest()
    {
        $response = wp_remote_get($this->manifest_url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
            ],
        ]);

        if (is_wp_error($response)) {
            Utils::writeLog('[Update] 更新清单请求失败: ' . $response->get_error_message());
            return false;
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            Utils::writeLog('[Update] 更新清单 HTTP 状态异常: ' . wp_remote_retrieve_response_code($response));
            return false;
        }

        $manifest = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($manifest) || empty($manifest['version']) || empty($manifest['download_url'])) {
            Utils::writeLog('[Update] 更新清单格式不正确');
            return false;
        }

        return $manifest;
    }

    public function check_for_update($update, $plugin_data, $plugin_file, $locales)
    {
        if ($plugin_file !== $this->plugin_slug) {
            return $update;
        }

        $manifest = $this->get_manifest();
        if (!$manifest || !version_compare($manifest['version'], $plugin_data['Version'], '>')) {
            return false;
        }

        return [
            'id' => $this->update_uri,
            'slug' => $this->plugin_dir,
            'plugin' => $this->plugin_slug,
            'version' => $manifest['version'],
            'url' => $manifest['homepage'] ?? $this->update_uri,
            'package' => $manifest['download_url'],
            'requires' => $manifest['requires'] ?? '',
            'tested' => $manifest['tested'] ?? '',
            'requires_php' => $manifest['requires_php'] ?? '7.4',
        ];
    }

    public function plugin_info($res, $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->plugin_dir) {
            return $res;
        }

        $manifest = $this->get_manifest();
        if (!$manifest) {
            return $res;
        }

        return (object) [
            'name' => 'LskyPro（兰空图床）插件',
            'slug' => $this->plugin_dir,
            'version' => $manifest['version'],
            'author' => '妖月',
            'homepage' => $manifest['homepage'] ?? $this->update_uri,
            'short_description' => $manifest['description'] ?? '将 WordPress 媒体上传到 LskyPro（兰空图床）。',
            'sections' => [
                'description' => wp_kses_post($manifest['description'] ?? '将 WordPress 媒体上传到 LskyPro（兰空图床）。'),
                'changelog' => wp_kses_post($manifest['changelog'] ?? ''),
            ],
            'download_link' => $manifest['download_url'],
            'requires' => $manifest['requires'] ?? '',
            'tested' => $manifest['tested'] ?? '',
            'requires_php' => $manifest['requires_php'] ?? '7.4',
        ];
    }

    public function fix_update_folder($source, $remote_source, $upgrader, $hook_extra)
    {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return $source;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;

        $correct = trailingslashit($remote_source) . $this->plugin_dir . '/';
        if ($source === $correct) {
            return $source;
        }

        if ($wp_filesystem->is_dir($correct)) {
            $wp_filesystem->delete($correct, true);
        }

        if ($wp_filesystem->move($source, $correct)) {
            return $correct;
        }

        return new \WP_Error('move_failed', '插件更新失败，无法移动目录');
    }
}
