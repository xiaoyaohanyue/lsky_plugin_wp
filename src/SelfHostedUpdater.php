<?php
namespace LskyProPlugin;

if (!defined('ABSPATH')) exit;

class SelfHostedUpdater
{
    const CHANNEL_FJWR = 'fjwr';
    const CHANNEL_GITHUB = 'github_source';
    const CHANNEL_CUSTOM = 'custom_manifest';

    private $plugin_file;
    private $plugin_slug;
    private $plugin_dir;
    private $default_update_uri = 'https://fjwr.xyz/wp-plugin-updates/lsky_plugin_wp';
    private $default_manifest_url = 'https://fjwr.xyz/wp-plugin-updates/lsky_plugin_wp/update.json';
    private $default_github_repo = 'xiaoyaohanyue/lsky_plugin_wp';

    public function __construct($plugin_file)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->plugin_dir = dirname($this->plugin_slug);

        add_filter('update_plugins_fjwr.xyz', [$this, 'check_for_update'], 10, 4);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'fix_update_folder'], 10, 4);
    }

    public static function defaults()
    {
        return [
            'channel' => self::CHANNEL_FJWR,
            'manifest_url' => 'https://fjwr.xyz/wp-plugin-updates/lsky_plugin_wp/update.json',
            'github_repo' => 'xiaoyaohanyue/lsky_plugin_wp',
        ];
    }

    public static function allowed_channels()
    {
        return [self::CHANNEL_FJWR, self::CHANNEL_GITHUB, self::CHANNEL_CUSTOM];
    }

    public static function sanitize_channel($channel)
    {
        $channel = sanitize_key((string) $channel);
        return in_array($channel, self::allowed_channels(), true) ? $channel : self::CHANNEL_FJWR;
    }

    public static function sanitize_manifest_url($url)
    {
        $url = esc_url_raw(trim((string) $url), ['http', 'https']);
        return wp_http_validate_url($url) ? $url : '';
    }

    public static function sanitize_github_repo($repo)
    {
        $repo = trim((string) $repo);
        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
            return self::defaults()['github_repo'];
        }

        return $repo;
    }

    public static function get_settings()
    {
        $defaults = self::defaults();
        $settings = maybe_unserialize(get_option('lsky_setting', []));
        if (!is_array($settings)) {
            $settings = [];
        }

        $channel = self::sanitize_channel($settings['update_channel'] ?? $defaults['channel']);
        $manifest_url = self::sanitize_manifest_url($settings['custom_update_url'] ?? '');
        $github_repo = self::sanitize_github_repo($settings['github_update_repo'] ?? $defaults['github_repo']);

        if ($channel === self::CHANNEL_CUSTOM && $manifest_url === '') {
            $channel = self::CHANNEL_FJWR;
        }

        return [
            'channel' => $channel,
            'manifest_url' => $manifest_url !== '' ? $manifest_url : $defaults['manifest_url'],
            'github_repo' => $github_repo,
        ];
    }

    private function get_update_uri($settings)
    {
        if ($settings['channel'] === self::CHANNEL_GITHUB) {
            return 'https://github.com/' . $settings['github_repo'];
        }

        if ($settings['channel'] === self::CHANNEL_CUSTOM) {
            return untrailingslashit(dirname($settings['manifest_url']));
        }

        return $this->default_update_uri;
    }

    private function get_manifest()
    {
        $settings = self::get_settings();

        if ($settings['channel'] === self::CHANNEL_GITHUB) {
            return $this->get_github_release_manifest($settings['github_repo']);
        }

        $manifest_url = $settings['channel'] === self::CHANNEL_CUSTOM
            ? $settings['manifest_url']
            : $this->default_manifest_url;

        return $this->get_json_manifest($manifest_url);
    }

    private function get_json_manifest($manifest_url)
    {
        $response = wp_remote_get($manifest_url, [
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

    private function get_github_release_manifest($github_repo)
    {
        $github_repo = self::sanitize_github_repo($github_repo);
        $response = wp_remote_get('https://api.github.com/repos/' . $github_repo . '/releases/latest', [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/'),
            ],
        ]);

        if (is_wp_error($response)) {
            Utils::writeLog('[Update] GitHub Release 请求失败: ' . $response->get_error_message());
            return false;
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            Utils::writeLog('[Update] GitHub Release HTTP 状态异常: ' . wp_remote_retrieve_response_code($response));
            return false;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($release) || empty($release['tag_name']) || empty($release['zipball_url'])) {
            Utils::writeLog('[Update] GitHub Release 格式不正确');
            return false;
        }

        return [
            'version' => ltrim((string) $release['tag_name'], 'v'),
            'homepage' => $release['html_url'] ?? 'https://github.com/' . $github_repo,
            'download_url' => $release['zipball_url'],
            'description' => !empty($release['body']) ? $release['body'] : '暂无版本说明。',
        ];
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

        $settings = self::get_settings();
        $update_uri = $this->get_update_uri($settings);

        return [
            'id' => $update_uri,
            'slug' => $this->plugin_dir,
            'plugin' => $this->plugin_slug,
            'version' => $manifest['version'],
            'url' => $manifest['homepage'] ?? $update_uri,
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

        $settings = self::get_settings();
        $update_uri = $this->get_update_uri($settings);

        return (object) [
            'name' => 'YAOYUE Image Upload for LskyPro',
            'slug' => $this->plugin_dir,
            'version' => $manifest['version'],
            'author' => '妖月',
            'homepage' => $manifest['homepage'] ?? $update_uri,
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

        $settings = self::get_settings();
        if ($settings['channel'] === self::CHANNEL_GITHUB) {
            $github_repo = self::sanitize_github_repo($settings['github_repo']);
            $repo_name = substr($github_repo, strpos($github_repo, '/') + 1);
            if (strpos(basename($source), $repo_name) === false) {
                return $source;
            }
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
