<?php
namespace LskyProPlugin;

if (!defined('ABSPATH')) exit;

use LskyProPlugin\LskyPro;
use LskyProPlugin\LskyAPIV1;
use LskyProPlugin\LskyAPIV2;

class LskyCommon extends LskyPro
{
    const SETTINGS_NONCE_ACTION = 'lsky_save_settings';
    const AJAX_NONCE_ACTION = 'lsky_ajax_action';

    public static function cleanup_legacy_password()
    {
        $setting = maybe_unserialize(get_option('lsky_setting', []));
        if (!is_array($setting) || empty($setting['password'])) {
            return;
        }

        $setting['password'] = '';
        update_option('lsky_setting', $setting);
    }

    private static function delete_local_upload_file($path)
    {
        if (empty($path) || !is_string($path)) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = realpath($upload_dir['basedir']);
        $target = realpath(wp_normalize_path(stripslashes($path)));

        if (!$base_dir || !$target || strpos($target, $base_dir) !== 0 || !is_file($target)) {
            return;
        }

        wp_delete_file($target);
    }

    public static function api_info($para)
    {
        $instance = new self();
        switch ($para) {
            case 'api':
                return $instance->lsky_api;
            case 'token':
                return $instance->lsky_token;
            case 'permission':
                return $instance->lsky_permission;
            case 'switch':
                return $instance->lsky_switch;
            case 'api_version':
                return $instance->lsky_version;
            case 'open_source':
                return $instance->lsky_open_source;
            case 'album_id':
                return $instance->lsky_album_id;
            case 'storage_id':
                return $instance->lsky_storage_id;
            case 'username':
                return $instance->lsky_username;
            case 'password':
                return $instance->lsky_password;
            default:
                return '';
        }
    }


    public static function lsky_menu(){
        return add_menu_page(
            "YAOYUE Image Upload",
            "YAOYUE Image Upload",
            'manage_options',
            "lsky_settings",
            'lsky_display',
            'dashicons-admin-generic',
            100
        );
    }

    public static function lsky_plugin_settings_link($links) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=lsky_settings')) . '">设置</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public static function enqueue_admin_assets()
    {
        wp_enqueue_script(
            'lsky-upload-one',
            plugins_url('../static/post.js', __FILE__),
            ['jquery'],
            '2.0.6',
            true
        );

        wp_localize_script('lsky-upload-one', 'LskyUploadOne', [
            'flag' => 'page',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::AJAX_NONCE_ACTION),
        ]);
    }

    public static function img_del_handle($post_id, $post){
        $data = wp_get_attachment_metadata($post_id);
        if (!is_array($data) || empty($data['key'])) {
            return;
        }

        $sizes = isset($data['sizes']) && is_array($data['sizes']) ? $data['sizes'] : [];
        if (self::api_info('api_version') == 'v1'){
            LskyAPIV1::img_delete($data['key']);
            foreach($sizes as $value){
                if (!empty($value['key'])){
                    LskyAPIV1::img_delete($value['key']);
                    self::delete_local_upload_file($value['ori_path'] ?? '');
                }
            }
            self::delete_local_upload_file($data['ori_path'] ?? '');
        }else{
            LskyAPIV2::img_delete($data['key']);
            foreach($sizes as $value){
                if (!empty($value['key'])){
                    LskyAPIV2::img_delete($value['key']);
                    self::delete_local_upload_file($value['ori_path'] ?? '');
                }
            }
            self::delete_local_upload_file($data['ori_path'] ?? '');
        }
    }
    
    public static function get_attachment_url($url,$post_id){
        $data = wp_get_attachment_metadata($post_id);
        if (is_array($data) && !empty($data['key'])){
            $url = get_post_field( 'guid', $post_id );
        }
        return $url;
    }

    private static function build_lsky_srcset_urls($image_meta, $attachment_id)
    {
        if (!is_array($image_meta) || empty($image_meta['key'])) {
            return [];
        }

        $urls = [];
        $guid = get_post_field('guid', $attachment_id);
        if (!empty($guid)) {
            $urls[wp_basename($guid)] = $guid;
        }

        if (!empty($image_meta['file']) && !empty($guid)) {
            $urls[wp_basename($image_meta['file'])] = $guid;
        }

        $sizes = isset($image_meta['sizes']) && is_array($image_meta['sizes']) ? $image_meta['sizes'] : [];
        foreach ($sizes as $size_meta) {
            if (empty($size_meta['key']) || empty($size_meta['file'])) {
                continue;
            }

            $filename = wp_basename($size_meta['file']);
            $urls[$filename] = self::get_lsky_size_url($size_meta, $guid);
        }

        return array_filter($urls);
    }

    private static function get_lsky_size_url($size_meta, $guid)
    {
        if (!empty($size_meta['url'])) {
            return $size_meta['url'];
        }

        if (!empty($size_meta['pathname'])) {
            $scheme = wp_parse_url($guid, PHP_URL_SCHEME);
            $host = wp_parse_url($guid, PHP_URL_HOST);
            if (!empty($scheme) && !empty($host)) {
                return $scheme . '://' . $host . '/' . ltrim($size_meta['pathname'], '/');
            }
        }

        if (!empty($guid) && !empty($size_meta['file'])) {
            return preg_replace('#/[^/]*(?:\?.*)?$#', '/', $guid) . ltrim(wp_basename($size_meta['file']), '/');
        }

        return '';
    }

    private static function get_url_pathname($url)
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        return $path ? ltrim($path, '/') : '';
    }

    private static function is_lsky_attachment_metadata($image_meta)
    {
        return is_array($image_meta) && !empty($image_meta['key']);
    }

    private static function is_upload_path($path)
    {
        if (empty($path) || !is_string($path)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = wp_normalize_path($upload_dir['basedir']);
        $target = wp_normalize_path(stripslashes($path));

        return $base_dir && strpos($target, trailingslashit($base_dir)) === 0;
    }

    private static function repair_lsky_attachment_metadata($attachment_id)
    {
        $image_meta = wp_get_attachment_metadata($attachment_id);
        if (!self::is_lsky_attachment_metadata($image_meta)) {
            return [
                'is_lsky' => false,
                'changed' => false,
                'sizes_changed' => 0,
            ];
        }

        $changed = false;
        $sizes_changed = 0;
        $guid = get_post_field('guid', $attachment_id);
        $sizes = isset($image_meta['sizes']) && is_array($image_meta['sizes']) ? $image_meta['sizes'] : [];
        foreach ($sizes as $key => $size_meta) {
            if (empty($size_meta['key']) || empty($size_meta['file'])) {
                continue;
            }

            $remote_url = self::get_lsky_size_url($size_meta, $guid);
            $size_changed = false;
            if (!empty($remote_url) && (($size_meta['url'] ?? '') !== $remote_url)) {
                $image_meta['sizes'][$key]['url'] = $remote_url;
                $size_changed = true;
            }

            if (!empty($remote_url) && empty($size_meta['pathname'])) {
                $pathname = self::get_url_pathname($remote_url);
                if ($pathname !== '') {
                    $image_meta['sizes'][$key]['pathname'] = $pathname;
                    $size_changed = true;
                }
            }

            if ($size_changed) {
                $changed = true;
                $sizes_changed++;
            }
        }

        $attached_file = get_attached_file($attachment_id, true);
        if (!empty($attached_file) && self::is_upload_path($attached_file)) {
            $attached_file = wp_normalize_path($attached_file);
            $current_ori_path = isset($image_meta['ori_path']) ? wp_normalize_path(stripslashes($image_meta['ori_path'])) : '';
            $current_is_remote_filename = !empty($guid) && wp_basename($current_ori_path) === wp_basename($guid);
            if ($current_ori_path !== $attached_file && (empty($current_ori_path) || !file_exists($current_ori_path) || $current_is_remote_filename)) {
                $image_meta['ori_path'] = $attached_file;
                $changed = true;
            }
        }

        if ($changed) {
            wp_update_attachment_metadata($attachment_id, $image_meta);
        }

        return [
            'is_lsky' => true,
            'changed' => $changed,
            'sizes_changed' => $sizes_changed,
        ];
    }

    private static function get_img_attribute($tag, $attribute)
    {
        $attribute = preg_quote($attribute, '#');
        if (preg_match('#\s' . $attribute . '\s*=\s*([\'"])(.*?)\1#i', $tag, $matches)) {
            return $matches[2];
        }

        if (preg_match('#\s' . $attribute . '\s*=\s*([^\s>]+)#i', $tag, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private static function get_img_attribute_int($tag, $attribute)
    {
        $value = self::get_img_attribute($tag, $attribute);
        return $value !== '' ? absint($value) : 0;
    }

    private static function set_img_attribute($tag, $attribute, $value)
    {
        $quoted_attribute = preg_quote($attribute, '#');
        $replacement = ' ' . $attribute . '="' . esc_attr($value) . '"';
        if (preg_match('#\s' . $quoted_attribute . '\s*=\s*([\'"]).*?\1#i', $tag)) {
            return preg_replace('#\s' . $quoted_attribute . '\s*=\s*([\'"]).*?\1#i', $replacement, $tag, 1);
        }

        if (preg_match('#\s' . $quoted_attribute . '\s*=\s*[^\s>]+#i', $tag)) {
            return preg_replace('#\s' . $quoted_attribute . '\s*=\s*[^\s>]+#i', $replacement, $tag, 1);
        }

        $closing = preg_match('#\s*/>$#', $tag) ? ' />' : '>';
        return preg_replace('#\s*/?>$#', $replacement . $closing, $tag, 1);
    }

    private static function remove_img_attribute($tag, $attribute)
    {
        $attribute = preg_quote($attribute, '#');
        $tag = preg_replace('#\s' . $attribute . '\s*=\s*([\'"]).*?\1#i', '', $tag, 1);
        return preg_replace('#\s' . $attribute . '\s*=\s*[^\s>]+#i', '', $tag, 1);
    }

    private static function get_content_image_size($tag)
    {
        if (preg_match('/\bsize-([a-z0-9_-]+)/i', $tag, $matches)) {
            return sanitize_key($matches[1]);
        }

        return 'full';
    }

    private static function repair_legacy_content_images($content, &$stats)
    {
        return preg_replace_callback('/<img\b[^>]*\bwp-image-(\d+)[^>]*>/i', function ($matches) use (&$stats) {
            $attachment_id = absint($matches[1]);
            $image_meta = wp_get_attachment_metadata($attachment_id);
            if (!self::is_lsky_attachment_metadata($image_meta)) {
                return $matches[0];
            }

            $tag = $matches[0];
            $updated_tag = $tag;
            $size = self::get_content_image_size($tag);
            $image_src = wp_get_attachment_image_src($attachment_id, $size);
            $src = is_array($image_src) ? $image_src[0] : wp_get_attachment_url($attachment_id);
            $width = is_array($image_src) ? absint($image_src[1]) : self::get_img_attribute_int($tag, 'width');
            $height = is_array($image_src) ? absint($image_src[2]) : self::get_img_attribute_int($tag, 'height');
            if (!$width && !empty($image_meta['width'])) {
                $width = absint($image_meta['width']);
            }
            if (!$height && !empty($image_meta['height'])) {
                $height = absint($image_meta['height']);
            }

            $current_src = self::get_img_attribute($tag, 'src');
            if (!empty($src) && false !== strpos($current_src, 'wp-content/uploads')) {
                $updated_tag = self::set_img_attribute($updated_tag, 'src', $src);
                $stats['content_srcs_repaired']++;
            }

            $current_srcset = self::get_img_attribute($tag, 'srcset');
            if (false !== strpos($current_srcset, 'wp-content/uploads')) {
                $new_srcset = ($width && $height && !empty($src)) ? wp_calculate_image_srcset([$width, $height], $src, $image_meta, $attachment_id) : false;
                if (!empty($new_srcset)) {
                    $updated_tag = self::set_img_attribute($updated_tag, 'srcset', $new_srcset);
                } else {
                    $updated_tag = self::remove_img_attribute($updated_tag, 'srcset');
                }
                $stats['content_srcsets_repaired']++;
            }

            return $updated_tag;
        }, $content);
    }

    private static function repair_legacy_post_content(&$stats)
    {
        $post_types = array_values(get_post_types(['public' => true], 'names'));
        $post_types = array_values(array_unique(array_merge($post_types, ['wp_block', 'wp_template', 'wp_template_part', 'wp_navigation'])));
        $paged = 1;

        do {
            $query = new \WP_Query([
                'post_type' => $post_types,
                'post_status' => 'any',
                'posts_per_page' => 100,
                'paged' => $paged,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
            ]);

            foreach ($query->posts as $post_id) {
                $content = get_post_field('post_content', $post_id);
                if (false === strpos($content, 'wp-image-') || false === strpos($content, 'wp-content/uploads')) {
                    continue;
                }

                $stats['content_posts_scanned']++;
                $before_srcsets = $stats['content_srcsets_repaired'];
                $before_srcs = $stats['content_srcs_repaired'];
                $new_content = self::repair_legacy_content_images($content, $stats);
                if ($new_content !== $content) {
                    wp_update_post([
                        'ID' => $post_id,
                        'post_content' => $new_content,
                    ]);
                    $stats['content_posts_repaired']++;
                } else {
                    $stats['content_srcsets_repaired'] = $before_srcsets;
                    $stats['content_srcs_repaired'] = $before_srcs;
                }
            }

            $paged++;
        } while ($paged <= (int) $query->max_num_pages);
    }

    public static function repair_legacy_srcset_data()
    {
        $stats = [
            'attachments_scanned' => 0,
            'lsky_attachments' => 0,
            'attachment_metadata_repaired' => 0,
            'sizes_repaired' => 0,
            'content_posts_scanned' => 0,
            'content_posts_repaired' => 0,
            'content_srcsets_repaired' => 0,
            'content_srcs_repaired' => 0,
        ];

        $paged = 1;
        do {
            $query = new \WP_Query([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'posts_per_page' => 100,
                'paged' => $paged,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
            ]);

            foreach ($query->posts as $attachment_id) {
                $stats['attachments_scanned']++;
                $result = self::repair_lsky_attachment_metadata((int) $attachment_id);
                if (empty($result['is_lsky'])) {
                    continue;
                }

                $stats['lsky_attachments']++;
                if (!empty($result['changed'])) {
                    $stats['attachment_metadata_repaired']++;
                }
                $stats['sizes_repaired'] += (int) $result['sizes_changed'];
            }

            $paged++;
        } while ($paged <= (int) $query->max_num_pages);

        self::repair_legacy_post_content($stats);

        return $stats;
    }

    public static function calculate_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        if (!is_array($sources)) {
            return $sources;
        }

        $lsky_urls = self::build_lsky_srcset_urls($image_meta, $attachment_id);
        if (empty($lsky_urls)) {
            return $sources;
        }

        foreach ($sources as $width => $source) {
            if (empty($source['url'])) {
                continue;
            }

            $filename = wp_basename($source['url']);
            if (isset($lsky_urls[$filename])) {
                $sources[$width]['url'] = $lsky_urls[$filename];
            } else {
                unset($sources[$width]);
            }
        }

        return $sources;
    }

    public static function img_datahandle($imgname){
        if (empty($imgname) || !is_readable($imgname)) {
            return new \WP_Error('lsky_file_not_readable', '本地图片文件不存在或不可读');
        }

        if (self::api_info('api_version') == 'v1'){
            $res = LskyAPIV1::img_upload($imgname);
            if (empty($res['status']) || true !== $res['status'] || empty($res['data']['links']['url'])) {
                return new \WP_Error('lsky_upload_failed', $res['message'] ?? '上传到 LskyPro（兰空图床）失败');
            }
            $url = $res['data']['links']['url'];
            $tmpname = explode("/",$url);
            $filename = $tmpname[count($tmpname)-1];
            $img = array(
                "file" => $filename,
                "url" => $url,
                "type" => $res['data']['mimetype'],
                "name" => $res['data']['name'],
                "size" => $res['data']['size'],
                "key" => $res['data']['key'],
                "pathname" => $res['data']['pathname']
            );
        }else{
            $res = LskyAPIV2::img_upload($imgname);
            if (empty($res['status']) || 'success' !== $res['status'] || empty($res['data']['public_url'])) {
                return new \WP_Error('lsky_upload_failed', $res['message'] ?? '上传到 LskyPro（兰空图床）失败');
            }
            $tmpname = explode("/",$res['data']['pathname']);
            $filename = $tmpname[count($tmpname)-1];
            $img = array(
                "name" => $filename,
                "url" => $res['data']['public_url'],
                "type" => $res['data']['mimetype'],
                "file" => $res['data']['filename'],
                "key" => $res['data']['id'],
                "pathname" => $res['data']['pathname']
            );
        }
        return $img;
    }

    public static function  insert_attachment_data($data, $postarr, $unsanitized_postarr, $update){
        if (!$update && !empty($data['post_mime_type']) && strpos($data['post_mime_type'], 'image') !== false && !empty($unsanitized_postarr['file'])){
            $filepath = $unsanitized_postarr['file'];
            $urldata = self::img_datahandle($filepath);
            if (is_wp_error($urldata)) {
                return $data;
            }

            $data['post_mime_type'] = $urldata['type'];
            $data['guid'] = $urldata['url'];
            $data['post_content'] = $urldata['key'];
            $data['post_title'] = $urldata['name'];
        }
        return $data;
    }

    public static function generate_attachment_metadata($image_meta,$attachment_id,$context){
        if ($context == 'update'){
            return $image_meta;
        }
        $post = get_post($attachment_id);
        if ($post && strpos($post->post_mime_type, 'image') !== false){
            $upload_dir = wp_upload_dir();
            $sizes = isset($image_meta['sizes']) && is_array($image_meta['sizes']) ? $image_meta['sizes'] : [];
            foreach($sizes as $key => $value){
                $filename = $value['file'];
                $urldata = self::img_datahandle($upload_dir['path'].'/'.$filename);
                if (is_wp_error($urldata)) {
                    continue;
                }

                if (self::api_info('api_version') == 'v1'){
                    $image_meta['sizes'][$key]['filesize'] = $urldata['size'];
                }
                $image_meta['sizes'][$key]['file'] = $urldata['name'];
                $image_meta['sizes'][$key]['url'] = $urldata['url'];
                $image_meta['sizes'][$key]['mime-type'] = $urldata['type'];
                $image_meta['sizes'][$key]['key'] = $urldata['key'];
                $image_meta['sizes'][$key]['pathname'] = $urldata['pathname'];
                $image_meta['sizes'][$key]['ori_name'] = $filename;
                $image_meta['sizes'][$key]['ori_path'] = addslashes($upload_dir['path']).'/'.$filename;
            }
            $original_file = $image_meta['file'];
            $image_meta['file'] = $post->post_title;
            $image_meta['ori_file'] = $post->post_name;
            $image_meta['key'] = $post->post_content;
            $image_meta['ori_path'] = addslashes($upload_dir['basedir'].'/'.$original_file);
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_content' => '',
                'post_title' => $post->post_name
            ));
        }
        return $image_meta;
    }

    public static function attachment_editor($form_fields, $post)
    {
        $post_id = absint($post->ID);
        $form_fields["upload-to-lsky"] = array(
            "label" => esc_html__("图床替换", "yaoyue-image-upload-for-lskypro"),
            "input" => "html",
            "html" => '<button type="button" class="button-secondary lsky-upload-one" data-post-id="' . esc_attr($post_id) . '">' . esc_html__("一键替换", "yaoyue-image-upload-for-lskypro") . '</button>',
            "helps" => esc_html__("实现一键将该图片上传到外部图床并替换数据库信息。", "yaoyue-image-upload-for-lskypro")

          );
        return $form_fields;
    }

    public static function update_to_lsky($post_id){
        $post_id = absint($post_id);
        if (!$post_id) {
            return new \WP_Error('lsky_invalid_attachment', '附件 ID 无效');
        }

        $post = get_post($post_id,ARRAY_A);
        if($post && strpos($post['post_mime_type'], 'image') !== false){
            $data = wp_get_attachment_metadata($post_id);
            if (!is_array($data) || empty($data['file'])) {
                return new \WP_Error('lsky_missing_metadata', '附件元数据不完整');
            }

            $upload_dir = wp_upload_dir();
            if (empty($data['key'])){
                $basefile = $upload_dir['basedir'].'/'.$data['file'];
                $baseres = self::img_datahandle($basefile);
                if (is_wp_error($baseres)) {
                    return $baseres;
                }

                $new_post = array(
                    'ID' => $post_id,
                    'guid' => $baseres['url'],
                    'post_mime_type' => $baseres['type'],
                    'post_content' => $baseres['key'],
                    'post_title' => $baseres['name']
                );
                wp_update_post($new_post);
                clean_post_cache($post_id);
                $data = self::generate_attachment_metadata($data,$post_id,'upload');
                wp_update_attachment_metadata($post_id,$data);
            }

            return true;
        }

        return new \WP_Error('lsky_not_image', '只支持替换图片附件');
    }

    public static function replaced_one(){
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => '无权操作该附件'], 403);
        }

        $result = self::update_to_lsky($post_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }

        wp_send_json_success(['message' => '替换成功']);
    } 

    public static function lsky_fetch_v2_meta() {
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '无权读取接口信息'], 403);
        }

        $api = isset($_POST['api']) ? esc_url_raw(wp_unslash($_POST['api'])) : '';
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $albums = [];
        $album_resp = LskyAPIV2::get_album($api, $token);
        if (isset($album_resp['status']) && $album_resp['status'] == 'success' && !empty($album_resp['data']['data'])) {
            foreach ($album_resp['data']['data'] as $item) {
                $albums[] = ['id' => $item['id'], 'name' => $item['name']];
            }
        } 
        $storages = [];
        $storage_resp = LskyAPIV2::get_storage($api, $token);
        if (isset($storage_resp['status']) && $storage_resp['status'] == 'success' && !empty($storage_resp['data']['storages'])) {
            foreach ($storage_resp['data']['storages'] as $item) {
                $storages[] = ['id' => $item['id'], 'name' => $item['name']];
            }
        }

        wp_send_json_success([
            'albums' => $albums,
            'storages' => $storages
        ]);
    }



}
