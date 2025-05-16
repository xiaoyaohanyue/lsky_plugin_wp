<?php

require_once 'LskyCommon.php';
require_once 'LskyAPIV1.php';

use src\LskyCommon;
use src\LskyAPIV1;
use src\Utils;

error_reporting(0);
function lsky_display() {
    if ($_POST['action'] == 'save') {
        $datas['api_version'] = sanitize_text_field(trim($_POST['api_version']));
        $datas['open_source'] = sanitize_text_field(trim($_POST['open_source']));
        $datas['permission'] = sanitize_text_field(trim($_POST['permission']));
        $datas['username'] = sanitize_text_field(trim($_POST['username']));
        $datas['password'] = sanitize_text_field(trim($_POST['password']));
        if ($datas['open_source'] == 'yes' && $datas['api_version'] == 'v1') {
            if (empty($datas['username']) && empty($datas['password'])) {
                echo '<div id="message" class="error fade">用户名或密码不能为空！</div>';
            } else {
                $datas['tokens'] = LskyAPIV1::generate_token($datas['username'], $datas['password']);
            }
        } else {
            $datas['tokens'] = sanitize_text_field(trim($_POST['tokens']));
        }
        $datas['api'] = sanitize_text_field(trim($_POST['api']));
        $datas['album_id'] = sanitize_text_field(trim($_POST['album_id']));
        $datas['storage_id'] = sanitize_text_field(trim($_POST['storage_id']));
        $datas['switch'] = sanitize_text_field(trim($_POST['switch']));
        $datas = serialize($datas);
        update_option('lsky_setting', $datas);

        echo '<div id="message" class="updated fade">设置已保存！</div>';
    }

    if ($_POST['action'] == 'updateTokens') {
        $datas = maybe_unserialize(get_option('lsky_setting'));
        $datas['tokens'] = LskyAPIV1::refreash_token();
        $datas = serialize($datas);
        Utils::writeLog($datas);
        update_option('lsky_setting', $datas);
        echo '<div id="message" class="updated fade">Token已更新！</div>';
    }

    $api_version = LskyCommon::api_info('api_version');
    $open_source = LskyCommon::api_info('open_source');
    $saved_album_id = LskyCommon::api_info('album_id');
    $saved_storage_id = LskyCommon::api_info('storage_id');
?>
<style>
#message {
    margin: 1em 0;
    padding: .5em;
}
#lsky-setting {
    margin-bottom: 20px;
    padding: 10px;
    background-color: #ffffff;
    border: 1px solid #e6e6e6;
    box-shadow: 0 0 3px #e3e3e3;
}
</style>
<div class="wrap">
    <h2>接口设置</h2>
    <div id="lsky-setting">
        <form method="post" action="">
            <input type="hidden" name="action" id="form_action" value="save" />
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="api_version">API 版本</label></th>
                        <td>
                            <select name="api_version" id="api_version">
                                <option value="v1" <?php selected($api_version, 'v1'); ?>>v1</option>
                                <option value="v2" <?php selected($api_version, 'v2'); ?>>v2</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="v1-only">
                        <th><label for="open_source">是否开源版本</label></th>
                        <td>
                            <select name="open_source" id="open_source">
                                <option value="no" <?php selected($open_source, 'no'); ?>>否（商业版）</option>
                                <option value="yes" <?php selected($open_source, 'yes'); ?>>是（开源版）</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="api">API 地址</label></th>
                        <td><input size="40" type="text" name="api" value="<?php echo esc_attr(LskyCommon::api_info('api')); ?>" required /></td>
                    </tr>
                    <tr class="free_only">
                        <th><label for="username">用户名</label></th>
                        <td><input size="40" type="text" name="username" value="<?php echo esc_attr(LskyCommon::api_info('username')); ?>" /></td>
                    </tr>
                    <tr class="free_only">
                        <th><label for="password">密码</label></th>
                        <td><input size="40" type="password" name="password" value="<?php echo esc_attr(LskyCommon::api_info('password')); ?>" /></td>
                    </tr>
                    <tr class="free_only">
                        <th><label for="tokens">Tokens</label></th>
                        <td>
                            <input size="40" type="text" name="tokens" value="<?php echo esc_attr(LskyCommon::api_info('token')); ?>" readonly />
                            <input class="button" type="submit" value="更新 Tokens" onclick="document.getElementById('form_action').value='updateTokens';"/>
                        </td>
                    </tr>
                    <tr class="paid_only">
                        <th><label for="tokens">Tokens</label></th>
                        <td><input size="40" type="text" name="tokens" value="<?php echo esc_attr(LskyCommon::api_info('token')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="permission">隐私设置</label></th>
                        <td><input size="35" type="text" name="permission" value="<?php echo esc_attr(LskyCommon::api_info('permission')); ?>" />(1=公开，0=私有)</td>
                    </tr>
                    <tr class="v2-only">
                        <th><label for="album_id">相册 ID</label></th>
                        <td>
                            <select name="album_id" id="album_id">
                                <option value="">请选择相册</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="v2-only">
                        <th><label for="storage_id">存储策略 ID</label></th>
                        <td>
                            <select name="storage_id" id="storage_id">
                                <option value="">请选择存储策略</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>是否启用：</label></th>
                        <td>
                            <label><input type="radio" name="switch" value="enable" <?php checked(LskyCommon::api_info('switch'), 'enable'); ?>> 启用</label>
                            <label><input type="radio" name="switch" value="disable" <?php checked(LskyCommon::api_info('switch'), 'disable'); ?>> 禁用</label>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p><input class="button-primary" type="submit" value="保存设置" onclick="document.getElementById('form_action').value='save';"/></p>
        </form>
    </div>
</div>

<script type="text/javascript">
    var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
    var savedAlbumId = "<?php echo esc_js($saved_album_id); ?>";
    var savedStorageId = "<?php echo esc_js($saved_storage_id); ?>";
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    function toggleV2Fields() {
        const version = document.getElementById('api_version').value;
        const isFree = document.getElementById('open_source').value;

        document.querySelectorAll('.v2-only').forEach(el => {
            el.style.display = (version === 'v2') ? 'table-row' : 'none';
        });

        document.querySelectorAll('.v1-only').forEach(el => {
            el.style.display = (version === 'v1') ? 'table-row' : 'none';
        });

        document.querySelectorAll('.free_only').forEach(el => {
            el.style.display = (isFree === 'yes' && version === 'v1') ? 'table-row' : 'none';
        });

        document.querySelectorAll('.paid_only').forEach(el => {
            el.style.display = (isFree === 'no' || version === 'v2') ? 'table-row' : 'none';
        });

        if (version === 'v2') {
            fetchV2Data();
        }
    }

    async function fetchV2Data() {
    const api = document.querySelector('[name="api"]').value;
    const token = document.querySelector('[name="tokens"]').value;

    const albumSelect = document.getElementById('album_id');
    const storageSelect = document.getElementById('storage_id');

    albumSelect.innerHTML = '<option>加载中...</option>';
    storageSelect.innerHTML = '<option>加载中...</option>';
    albumSelect.disabled = true;
    storageSelect.disabled = true;

    try {
        const res = await fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'lsky_fetch_v2_meta',
                api: api,
                token: token
            })
        });

        const data = await res.json();
        albumSelect.innerHTML = '<option value="">请选择相册</option>';
        storageSelect.innerHTML = '<option value="">请选择存储策略</option>';

        if (data.status) {
            data.albums.forEach(item => {
                const selected = (item.id == savedAlbumId) ? 'selected' : '';
                albumSelect.innerHTML += `<option value="${item.id}" ${selected}>${item.name}</option>`;
            });

            data.storages.forEach(item => {
                const selected = (item.id == savedStorageId) ? 'selected' : '';
                storageSelect.innerHTML += `<option value="${item.id}" ${selected}>${item.name}</option>`;
            });
        } else {
            albumSelect.innerHTML = '<option value="">无法加载相册</option>';
            storageSelect.innerHTML = '<option value="">无法加载存储策略</option>';
        }
    } catch (error) {
        albumSelect.innerHTML = '<option value="">加载失败</option>';
        storageSelect.innerHTML = '<option value="">加载失败</option>';
    } finally {
        albumSelect.disabled = false;
        storageSelect.disabled = false;
    }
}


    document.getElementById('api_version').addEventListener('change', toggleV2Fields);
    document.getElementById('open_source').addEventListener('change', toggleV2Fields);
    toggleV2Fields();
});
</script>
<?php
}
?>
