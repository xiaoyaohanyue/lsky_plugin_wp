document.addEventListener('DOMContentLoaded', function () {
    const config = window.LskySettings || {};

    function toggleFieldGroup(selector, visible) {
        document.querySelectorAll(selector).forEach((el) => {
            el.hidden = !visible;
            el.querySelectorAll('input, select, textarea, button').forEach((control) => {
                control.disabled = !visible;
            });
        });
    }

    function buildApiEndpoint(api, version) {
        let base = api.trim();
        if (!base) {
            return '';
        }

        if (!/^https?:\/\//i.test(base)) {
            base = `https://${base}`;
        }

        base = base
            .replace(/\/api\/v[12]\/?$/i, '')
            .replace(/\/api\/?$/i, '')
            .replace(/\/+$/, '');

        return `${base}/api/${version === 'v2' ? 'v2' : 'v1'}`;
    }

    function updateApiPreview() {
        const preview = document.getElementById('lsky-api-preview');
        const apiField = document.querySelector('[name="api"]');
        const versionField = document.getElementById('api_version');
        if (!preview || !apiField || !versionField) {
            return;
        }

        preview.textContent = buildApiEndpoint(apiField.value, versionField.value) || '未填写';
    }

    async function fetchV2Data() {
        const versionField = document.getElementById('api_version');
        const apiField = document.querySelector('[name="api"]');
        const albumSelect = document.getElementById('album_id');
        const storageSelect = document.getElementById('storage_id');

        if (!versionField || !apiField || !albumSelect || !storageSelect) {
            return;
        }

        const api = buildApiEndpoint(apiField.value, versionField.value);
        const tokenField = document.querySelector('[name="tokens"]:not(:disabled)');
        const token = tokenField ? tokenField.value : '';

        function resetSelect(select, label, value = '') {
            select.replaceChildren(new Option(label, value));
        }

        resetSelect(albumSelect, '加载中...');
        resetSelect(storageSelect, '加载中...');
        albumSelect.disabled = true;
        storageSelect.disabled = true;

        try {
            const res = await fetch(config.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'lsky_fetch_v2_meta',
                    api,
                    token,
                    nonce: config.nonce || '',
                }),
            });

            const data = await res.json();
            resetSelect(albumSelect, '请选择相册');
            resetSelect(storageSelect, '请选择存储策略');

            if (data.success) {
                data.data.albums.forEach((item) => {
                    const option = new Option(item.name, item.id);
                    option.selected = String(item.id) === String(config.savedAlbumId || '');
                    albumSelect.add(option);
                });

                data.data.storages.forEach((item) => {
                    const option = new Option(item.name, item.id);
                    option.selected = String(item.id) === String(config.savedStorageId || '');
                    storageSelect.add(option);
                });
            } else {
                resetSelect(albumSelect, '无法加载相册');
                resetSelect(storageSelect, '无法加载存储策略');
            }
        } catch (error) {
            resetSelect(albumSelect, '加载失败');
            resetSelect(storageSelect, '加载失败');
        } finally {
            albumSelect.disabled = false;
            storageSelect.disabled = false;
        }
    }

    function toggleV2Fields() {
        const versionField = document.getElementById('api_version');
        const sourceField = document.getElementById('open_source');
        if (!versionField || !sourceField) {
            return;
        }

        const version = versionField.value;
        const isFree = sourceField.value;

        toggleFieldGroup('.v2-only', version === 'v2');
        toggleFieldGroup('.v1-only', version === 'v1');
        toggleFieldGroup('.free_only', isFree === 'yes' && version === 'v1');
        toggleFieldGroup('.paid_only', isFree === 'no' || version === 'v2');

        if (version === 'v2') {
            fetchV2Data();
        }
    }

    const versionField = document.getElementById('api_version');
    const apiField = document.getElementById('api');
    const sourceField = document.getElementById('open_source');

    if (versionField) {
        versionField.addEventListener('change', toggleV2Fields);
        versionField.addEventListener('change', updateApiPreview);
    }

    if (apiField) {
        apiField.addEventListener('input', updateApiPreview);
    }

    if (sourceField) {
        sourceField.addEventListener('change', toggleV2Fields);
    }

    toggleV2Fields();
    updateApiPreview();
});
