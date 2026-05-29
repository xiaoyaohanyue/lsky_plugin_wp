<?php
namespace src;

if (!defined('ABSPATH')) exit;

use src\LskyPro;
use src\LskyAPIV1;
use src\LskyAPIV2;

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
            "Lsky Pro设置",
            "Lsky Pro设置",
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
        }else{
            LskyAPIV2::img_delete($data['key']);
            foreach($sizes as $value){
                if (!empty($value['key'])){
                    LskyAPIV2::img_delete($value['key']);
                    self::delete_local_upload_file($value['ori_path'] ?? '');
                }
            }
        }
    }
    
    public static function get_attachment_url($url,$post_id){
        $data = wp_get_attachment_metadata($post_id);
        if (is_array($data) && !empty($data['key'])){
            $url = get_post_field( 'guid', $post_id );
        }
        return $url;
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
                $image_meta['sizes'][$key]['mime-type'] = $urldata['type'];
                $image_meta['sizes'][$key]['key'] = $urldata['key'];
                $image_meta['sizes'][$key]['ori_name'] = $filename;
                $image_meta['sizes'][$key]['ori_path'] = addslashes($upload_dir['path']).'/'.$filename;
            }
            $image_meta['file'] = $post->post_title;
            $image_meta['ori_file'] = $post->post_name;
            $image_meta['key'] = $post->post_content;
            $image_meta['ori_path'] = addslashes($upload_dir['basedir'].'/'.$image_meta['file']);
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
        $config = [
            'flag' => 'page',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::AJAX_NONCE_ACTION),
            'postId' => $post_id,
        ];
        $form_fields["upload-to-lsky"] = array(
            "label" => esc_html__("图床替换", "lsky-plugin-wp"),
            "input" => "html",
            "html" => '<script>window.LskyUploadOne=' . wp_json_encode($config) . ';</script>' . "<button type='button' class='button-secondary' id='lsky-upload-one'>" . esc_html__("一键替换", "lsky-plugin-wp") . "</button>" . '<script src="' . esc_url(plugins_url('../static/post.js',__FILE__)) . '"></script>',
            "helps" => esc_html__("实现一键将该图片上传到外部图床并替换数据库信息。", "lsky-plugin-wp")

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
