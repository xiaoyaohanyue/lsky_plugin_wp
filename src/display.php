<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/LskyCommon.php';
require_once __DIR__ . '/LskyAPIV1.php';

use LskyProPlugin\LskyCommon;
use LskyProPlugin\LskyAPIV1;
// LSKY_GITHUB_CHANNEL_BEGIN
use LskyProPlugin\SelfHostedUpdater;
// LSKY_GITHUB_CHANNEL_END
use LskyProPlugin\Utils;

function lsky_normalize_api_base($api) {
    $api = trim((string) $api);
    if ($api === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $api)) {
        $api = 'https://' . $api;
    }

    $api = preg_replace('#/api/v[12]/?$#i', '', $api);
    $api = preg_replace('#/api/?$#i', '', $api);
    return esc_url_raw(rtrim($api, '/'));
}

function lsky_build_api_endpoint($api, $api_version) {
    $base = lsky_normalize_api_base($api);
    $version = $api_version === 'v2' ? 'v2' : 'v1';

    if ($base === '') {
        return '';
    }

    return $base . '/api/' . $version;
}

// LSKY_GITHUB_CHANNEL_BEGIN
function lsky_has_update_channel_settings() {
    return defined('LSKY_SELF_HOSTED_UPDATES') && LSKY_SELF_HOSTED_UPDATES && class_exists(SelfHostedUpdater::class);
}

function lsky_apply_update_channel_settings(&$datas) {
    if (!lsky_has_update_channel_settings()) {
        return;
    }

    $defaults = SelfHostedUpdater::defaults();
    $channel = isset($_POST['update_channel'])
        ? SelfHostedUpdater::sanitize_channel(wp_unslash($_POST['update_channel']))
        : $defaults['channel'];
    $custom_url = isset($_POST['custom_update_url'])
        ? SelfHostedUpdater::sanitize_manifest_url(wp_unslash($_POST['custom_update_url']))
        : '';
    $github_repo = isset($_POST['github_update_repo'])
        ? SelfHostedUpdater::sanitize_github_repo(wp_unslash($_POST['github_update_repo']))
        : $defaults['github_repo'];

    if ($channel === SelfHostedUpdater::CHANNEL_CUSTOM && $custom_url === '') {
        $channel = SelfHostedUpdater::CHANNEL_FJWR;
    }

    $datas['update_channel'] = $channel;
    $datas['custom_update_url'] = $custom_url;
    $datas['github_update_repo'] = $github_repo;
}
// LSKY_GITHUB_CHANNEL_END

function lsky_display() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('无权访问该设置页。', 'lskypro'));
    }

    $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
    if ($action) {
        check_admin_referer(LskyCommon::SETTINGS_NONCE_ACTION, 'lsky_settings_nonce');
    }

    if ($action == 'save') {
        $datas['api_version'] = isset($_POST['api_version']) ? sanitize_key(wp_unslash($_POST['api_version'])) : 'v1';
        $datas['open_source'] = isset($_POST['open_source']) ? sanitize_key(wp_unslash($_POST['open_source'])) : 'no';
        $datas['api_version'] = in_array($datas['api_version'], ['v1', 'v2'], true) ? $datas['api_version'] : 'v1';
        $datas['open_source'] = in_array($datas['open_source'], ['yes', 'no'], true) ? $datas['open_source'] : 'no';
        $permission_input = isset($_POST['permission']) ? sanitize_text_field(wp_unslash($_POST['permission'])) : '0';
        $api_input = isset($_POST['api']) ? esc_url_raw(wp_unslash($_POST['api'])) : '';
        $datas['permission'] = (string) $permission_input === '1' ? '1' : '0';
        $datas['username'] = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        $password = isset($_POST['password']) ? sanitize_text_field(wp_unslash($_POST['password'])) : '';
        $datas['password'] = '';
        $datas['api'] = lsky_build_api_endpoint($api_input, $datas['api_version']);
        $can_save = true;
        if ($datas['open_source'] == 'yes' && $datas['api_version'] == 'v1') {
            if (empty($datas['username']) && empty($password)) {
                $can_save = false;
                echo '<div id="message" class="error fade">用户名或密码不能为空！</div>';
            } else {
                $datas['tokens'] = LskyAPIV1::generate_token($datas['username'], $password, $datas['api']);
                if (empty($datas['tokens']) || $datas['tokens'] === 'Token 获取失败' || $datas['tokens'] === '请先填写 API 地址') {
                    $can_save = false;
                    echo '<div id="message" class="error fade">Token 获取失败，请检查 API 地址、用户名和密码。</div>';
                }
            }
        } else {
            $datas['tokens'] = isset($_POST['tokens']) ? sanitize_text_field(wp_unslash($_POST['tokens'])) : '';
        }
        $datas['album_id'] = isset($_POST['album_id']) ? absint(wp_unslash($_POST['album_id'])) : '';
        $datas['storage_id'] = isset($_POST['storage_id']) ? absint(wp_unslash($_POST['storage_id'])) : '';
        $datas['album_id'] = $datas['album_id'] ? (string) $datas['album_id'] : '';
        $datas['storage_id'] = $datas['storage_id'] ? (string) $datas['storage_id'] : '';
        $datas['switch'] = isset($_POST['switch']) && $_POST['switch'] === 'enable' ? 'enable' : 'disable';
        // LSKY_GITHUB_CHANNEL_BEGIN
        lsky_apply_update_channel_settings($datas);
        // LSKY_GITHUB_CHANNEL_END
        if ($can_save) {
            update_option('lsky_setting', $datas);
            echo '<div id="message" class="updated fade">设置已保存！</div>';
        }
    }

    if ($action == 'updateTokens') {
        $datas = maybe_unserialize(get_option('lsky_setting', []));
        if (!is_array($datas)) {
            $datas = [];
        }
        $api_version_for_token = isset($_POST['api_version']) ? sanitize_key(wp_unslash($_POST['api_version'])) : ($datas['api_version'] ?? 'v1');
        $api_input_for_token = isset($_POST['api']) ? esc_url_raw(wp_unslash($_POST['api'])) : '';
        $api_for_token = $api_input_for_token !== '' ? lsky_build_api_endpoint($api_input_for_token, $api_version_for_token) : ($datas['api'] ?? '');
        $datas['api_version'] = $api_version_for_token;
        $datas['api'] = $api_for_token;
        $datas['username'] = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : ($datas['username'] ?? '');
        $password = isset($_POST['password']) ? sanitize_text_field(wp_unslash($_POST['password'])) : '';
        $datas['password'] = '';
        // LSKY_GITHUB_CHANNEL_BEGIN
        lsky_apply_update_channel_settings($datas);
        // LSKY_GITHUB_CHANNEL_END
        if (empty($datas['username']) || empty($password)) {
            echo '<div id="message" class="error fade">更新 Tokens 需要填写用户名和密码，密码不会保存。</div>';
        } else {
            $datas['tokens'] = LskyAPIV1::refreash_token(
                $datas['api'],
                $datas['username'],
                $password,
                $datas['tokens'] ?? ''
            );
            Utils::writeLog($datas);
            update_option('lsky_setting', $datas);
            echo '<div id="message" class="updated fade">Token已更新！</div>';
        }
    }

    $api_version = LskyCommon::api_info('api_version');
    $open_source = LskyCommon::api_info('open_source');
    $saved_album_id = LskyCommon::api_info('album_id');
    $saved_storage_id = LskyCommon::api_info('storage_id');
    $is_enabled = LskyCommon::api_info('switch') === 'enable';
    $api_base = lsky_normalize_api_base(LskyCommon::api_info('api'));
    $show_v1 = $api_version === 'v1';
    $show_v2 = $api_version === 'v2';
    $show_free = $show_v1 && $open_source === 'yes';
    $show_paid = !$show_free;
    // LSKY_GITHUB_CHANNEL_BEGIN
    $has_update_channel_settings = lsky_has_update_channel_settings();
    $update_settings = $has_update_channel_settings ? SelfHostedUpdater::get_settings() : [];
    // LSKY_GITHUB_CHANNEL_END
    wp_enqueue_script(
        'lsky-settings',
        plugins_url('../static/settings.js', __FILE__),
        [],
        '2.0.5',
        true
    );
    wp_localize_script('lsky-settings', 'LskySettings', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'savedAlbumId' => $saved_album_id,
        'savedStorageId' => $saved_storage_id,
        'nonce' => wp_create_nonce(LskyCommon::AJAX_NONCE_ACTION),
    ]);
?>
<style>
    .lsky-settings-page {
        max-width: 1120px;
    }

    .lsky-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin: 20px 0 16px;
    }

    .lsky-page-title {
        margin: 0;
        font-size: 24px;
        line-height: 1.3;
        font-weight: 600;
        color: #1d2327;
    }

    .lsky-page-subtitle {
        margin: 6px 0 0;
        color: #646970;
        font-size: 13px;
    }

    .lsky-status-group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
    }

    .lsky-pill {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 0 10px;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        background: #fff;
        color: #3c434a;
        font-size: 12px;
        font-weight: 600;
    }

    .lsky-pill.is-active {
        border-color: #00a32a;
        background: #edfaef;
        color: #006b1b;
    }

    .lsky-pill.is-inactive {
        border-color: #dcdcde;
        background: #f6f7f7;
        color: #646970;
    }

    .lsky-settings-panel {
        overflow: hidden;
        margin-bottom: 20px;
        border: 1px solid #dcdcde;
        border-radius: 6px;
        background: #fff;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    }

    .lsky-section {
        padding: 22px 24px;
        border-bottom: 1px solid #f0f0f1;
    }

    .lsky-section:last-of-type {
        border-bottom: 0;
    }

    .lsky-section-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 18px;
        font-size: 15px;
        line-height: 1.4;
        color: #1d2327;
    }

    .lsky-section-title .dashicons {
        width: 18px;
        height: 18px;
        font-size: 18px;
        color: #2271b1;
    }

    .lsky-field-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(240px, 1fr));
        gap: 18px 22px;
    }

    .lsky-field {
        min-width: 0;
    }

    .lsky-field.is-wide {
        grid-column: 1 / -1;
    }

    .lsky-field label,
    .lsky-field legend {
        display: block;
        margin: 0 0 7px;
        padding: 0;
        font-size: 13px;
        font-weight: 600;
        color: #1d2327;
    }

    .lsky-field input[type="text"],
    .lsky-field input[type="password"],
    .lsky-field select {
        width: 100%;
        max-width: 520px;
        min-height: 36px;
    }

    .lsky-inline-control {
        display: flex;
        align-items: center;
        gap: 8px;
        max-width: 620px;
    }

    .lsky-inline-control input {
        flex: 1 1 auto;
        min-width: 160px;
    }

    .lsky-help {
        margin: 6px 0 0;
        color: #646970;
        font-size: 12px;
        line-height: 1.5;
    }

    .lsky-radio-group {
        display: inline-flex;
        overflow: hidden;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        background: #fff;
    }

    .lsky-radio-group label {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        margin: 0;
        padding: 0 13px;
        border-right: 1px solid #dcdcde;
        font-weight: 500;
        cursor: pointer;
    }

    .lsky-radio-group label:last-child {
        border-right: 0;
    }

    .lsky-radio-group input {
        margin: 0 6px 0 0;
    }

    .lsky-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 24px;
        border-top: 1px solid #dcdcde;
        background: #f6f7f7;
    }

    .lsky-actions-note {
        margin: 0;
        color: #646970;
        font-size: 12px;
    }

    .lsky-actions .button-primary {
        min-height: 36px;
        padding: 0 18px;
    }

    .lsky-field[hidden] {
        display: none !important;
    }

    /* LSKY_GITHUB_CHANNEL_BEGIN */
    .lsky-update-channel-options {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .lsky-update-channel-card {
        position: relative;
        display: block;
        min-height: 104px;
        margin: 0;
        padding: 14px 14px 14px 42px;
        border: 1px solid #dcdcde;
        border-radius: 6px;
        background: #fff;
        cursor: pointer;
    }

    .lsky-update-channel-card input {
        position: absolute;
        top: 18px;
        left: 14px;
    }

    .lsky-update-channel-card strong {
        display: block;
        margin-bottom: 6px;
        color: #1d2327;
    }

    .lsky-update-channel-card span {
        display: block;
        color: #646970;
        font-size: 12px;
        line-height: 1.5;
    }

    .lsky-update-channel-card:has(input:checked) {
        border-color: #2271b1;
        background: #f0f6fc;
        box-shadow: 0 0 0 1px #2271b1;
    }
    /* LSKY_GITHUB_CHANNEL_END */

    @media (max-width: 782px) {
        .lsky-page-header,
        .lsky-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .lsky-status-group {
            justify-content: flex-start;
        }

        .lsky-field-grid {
            grid-template-columns: 1fr;
        }

        /* LSKY_GITHUB_CHANNEL_BEGIN */
        .lsky-update-channel-options {
            grid-template-columns: 1fr;
        }
        /* LSKY_GITHUB_CHANNEL_END */

        .lsky-section,
        .lsky-actions {
            padding-right: 16px;
            padding-left: 16px;
        }

        .lsky-inline-control {
            align-items: stretch;
            flex-direction: column;
        }

        .lsky-inline-control .button {
            width: fit-content;
        }
    }
</style>
<div class="wrap lsky-settings-page">
    <div class="lsky-page-header">
        <div>
            <h1 class="lsky-page-title">LskyPro（兰空图床）设置</h1>
            <p class="lsky-page-subtitle">配置 LskyPro（兰空图床）API 连接、认证方式和媒体上传策略。</p>
        </div>
        <div class="lsky-status-group">
            <span class="lsky-pill <?php echo $is_enabled ? 'is-active' : 'is-inactive'; ?>">
                <?php echo $is_enabled ? '已启用' : '已禁用'; ?>
            </span>
            <span class="lsky-pill">API <?php echo esc_html(strtoupper($api_version)); ?></span>
        </div>
    </div>

    <form method="post" action="">
        <div class="lsky-settings-panel">
            <?php wp_nonce_field(LskyCommon::SETTINGS_NONCE_ACTION, 'lsky_settings_nonce'); ?>
            <input type="hidden" name="action" id="form_action" value="save" />

            <section class="lsky-section">
                <h2 class="lsky-section-title"><span class="dashicons dashicons-admin-links"></span>连接配置</h2>
                <div class="lsky-field-grid">
                    <div class="lsky-field">
                        <label for="api_version">API 版本</label>
                        <select name="api_version" id="api_version">
                            <option value="v1" <?php selected($api_version, 'v1'); ?>>v1</option>
                            <option value="v2" <?php selected($api_version, 'v2'); ?>>v2</option>
                        </select>
                    </div>
                    <div class="lsky-field v1-only" <?php echo $show_v1 ? '' : 'hidden'; ?>>
                        <label for="open_source">版本类型</label>
                        <select name="open_source" id="open_source" <?php disabled(!$show_v1); ?>>
                            <option value="no" <?php selected($open_source, 'no'); ?>>商业版</option>
                            <option value="yes" <?php selected($open_source, 'yes'); ?>>开源版</option>
                        </select>
                    </div>
                    <div class="lsky-field is-wide">
                        <label for="api">LskyPro 域名</label>
                        <input type="text" id="api" name="api" value="<?php echo esc_attr($api_base); ?>" placeholder="https://example.com" required />
                        <p class="lsky-help">无需填写 /api/v1 或 /api/v2，实际接口：<code id="lsky-api-preview"></code></p>
                    </div>
                </div>
            </section>

            <section class="lsky-section">
                <h2 class="lsky-section-title"><span class="dashicons dashicons-lock"></span>认证信息</h2>
                <div class="lsky-field-grid">
                    <div class="lsky-field free_only" <?php echo $show_free ? '' : 'hidden'; ?>>
                        <label for="username">用户名</label>
                        <input type="text" id="username" name="username" value="<?php echo esc_attr(LskyCommon::api_info('username')); ?>" autocomplete="username" <?php disabled(!$show_free); ?> />
                    </div>
                    <div class="lsky-field free_only" <?php echo $show_free ? '' : 'hidden'; ?>>
                        <label for="password">密码</label>
                        <input type="password" id="password" name="password" value="" autocomplete="current-password" <?php disabled(!$show_free); ?> />
                        <p class="lsky-help">密码只用于本次获取 Tokens，不会保存到数据库。</p>
                    </div>
                    <div class="lsky-field is-wide free_only" <?php echo $show_free ? '' : 'hidden'; ?>>
                        <label for="tokens_free">Tokens</label>
                        <div class="lsky-inline-control">
                            <input type="text" id="tokens_free" name="tokens" value="<?php echo esc_attr(LskyCommon::api_info('token')); ?>" readonly <?php disabled(!$show_free); ?> />
                            <input class="button" type="submit" value="更新 Tokens" onclick="document.getElementById('form_action').value='updateTokens';" <?php disabled(!$show_free); ?> />
                        </div>
                    </div>
                    <div class="lsky-field is-wide paid_only" <?php echo $show_paid ? '' : 'hidden'; ?>>
                        <label for="tokens_paid">Tokens</label>
                        <input type="text" id="tokens_paid" name="tokens" value="<?php echo esc_attr(LskyCommon::api_info('token')); ?>" <?php disabled(!$show_paid); ?> />
                    </div>
                </div>
            </section>

            <section class="lsky-section">
                <h2 class="lsky-section-title"><span class="dashicons dashicons-format-image"></span>上传策略</h2>
                <div class="lsky-field-grid">
                    <div class="lsky-field">
                        <label for="permission">隐私设置</label>
                        <input type="text" id="permission" name="permission" value="<?php echo esc_attr(LskyCommon::api_info('permission')); ?>" />
                        <p class="lsky-help">1 = 公开，0 = 私有</p>
                    </div>
                    <div class="lsky-field v2-only" <?php echo $show_v2 ? '' : 'hidden'; ?>>
                        <label for="album_id">相册 ID</label>
                        <select name="album_id" id="album_id" <?php disabled(!$show_v2); ?>>
                            <option value="">请选择相册</option>
                        </select>
                    </div>
                    <div class="lsky-field v2-only" <?php echo $show_v2 ? '' : 'hidden'; ?>>
                        <label for="storage_id">存储策略 ID</label>
                        <select name="storage_id" id="storage_id" <?php disabled(!$show_v2); ?>>
                            <option value="">请选择存储策略</option>
                        </select>
                    </div>
                    <fieldset class="lsky-field">
                        <legend>是否启用</legend>
                        <div class="lsky-radio-group">
                            <label><input type="radio" name="switch" value="enable" <?php checked(LskyCommon::api_info('switch'), 'enable'); ?>> 启用</label>
                            <label><input type="radio" name="switch" value="disable" <?php checked(LskyCommon::api_info('switch'), 'disable'); ?>> 禁用</label>
                        </div>
                    </fieldset>
                </div>
            </section>

            <?php // LSKY_GITHUB_CHANNEL_BEGIN ?>
            <?php if ($has_update_channel_settings) : ?>
                <section class="lsky-section">
                    <h2 class="lsky-section-title"><span class="dashicons dashicons-update"></span>更新渠道</h2>
                    <div class="lsky-update-channel-options">
                        <label class="lsky-update-channel-card">
                            <input type="radio" name="update_channel" value="<?php echo esc_attr(SelfHostedUpdater::CHANNEL_FJWR); ?>" <?php checked($update_settings['channel'], SelfHostedUpdater::CHANNEL_FJWR); ?>>
                            <strong>fjwr.xyz 更新服务</strong>
                            <span>默认渠道。读取 fjwr.xyz 的 update.json，并下载 GitHub Release 中的 github 包。</span>
                        </label>
                        <label class="lsky-update-channel-card">
                            <input type="radio" name="update_channel" value="<?php echo esc_attr(SelfHostedUpdater::CHANNEL_GITHUB); ?>" <?php checked($update_settings['channel'], SelfHostedUpdater::CHANNEL_GITHUB); ?>>
                            <strong>GitHub Source code</strong>
                            <span>读取 GitHub latest release，并下载 GitHub 自动生成的 Source code zip。</span>
                        </label>
                        <label class="lsky-update-channel-card">
                            <input type="radio" name="update_channel" value="<?php echo esc_attr(SelfHostedUpdater::CHANNEL_CUSTOM); ?>" <?php checked($update_settings['channel'], SelfHostedUpdater::CHANNEL_CUSTOM); ?>>
                            <strong>自定义 Manifest</strong>
                            <span>使用你自己维护的 update.json，适合私有镜像或自托管发布。</span>
                        </label>
                    </div>
                    <div class="lsky-field-grid">
                        <div class="lsky-field">
                            <label for="github_update_repo">GitHub 仓库</label>
                            <input type="text" id="github_update_repo" name="github_update_repo" value="<?php echo esc_attr($update_settings['github_repo']); ?>" placeholder="xiaoyaohanyue/lsky_plugin_wp" />
                            <p class="lsky-help">仅选择 GitHub Source code 渠道时使用。</p>
                        </div>
                        <div class="lsky-field is-wide">
                            <label for="custom_update_url">自定义 Manifest 地址</label>
                            <input type="text" id="custom_update_url" name="custom_update_url" value="<?php echo esc_attr($update_settings['manifest_url']); ?>" placeholder="https://example.com/wp-plugin-updates/lsky_plugin_wp/update.json" />
                            <p class="lsky-help">仅选择自定义 Manifest 渠道时使用，格式需与 update.example.json 一致。</p>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
            <?php // LSKY_GITHUB_CHANNEL_END ?>

            <div class="lsky-actions">
                <p class="lsky-actions-note">保存后，新上传的图片会按当前策略处理。</p>
                <input class="button-primary" type="submit" value="保存设置" onclick="document.getElementById('form_action').value='save';" />
            </div>
        </div>
    </form>
</div>

<?php
}
?>
