<?php
namespace src;

use ParagonIE\Sodium\Core\Util;
use src\LskyPro;
use src\LskyAPIV1;
use src\Utils;
use src\LskyAPIV2;

class LskyCommon extends LskyPro
{
    

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
    $settings_link = '<a href="' . admin_url('admin.php?page=lsky_settings') . '">设置</a>';
    array_unshift($links, $settings_link);
    return $links;
}

    public static function img_del_handle($post_id, $post){
        $data = wp_get_attachment_metadata($post_id);
        if (self::api_info('api_version') == 'v1'){
            if (!empty($data['key'])){
                LskyAPIV1::img_delete($data['key']);
                foreach($data['sizes'] as $key => $value){
                    if (!empty($value['key'])){
                        LskyAPIV1::img_delete($value['key']);
                        @unlink($value['ori_path']);
                    }
                }
            }
        }else{
            if (!empty($data['key'])){
                LskyAPIV2::img_delete($data['key']);
                foreach($data['sizes'] as $key => $value){
                    if (!empty($value['key'])){
                        LskyAPIV2::img_delete($value['key']);
                        @unlink($value['ori_path']);
                    }
                }
            }
        }
    }
    
    public static function get_attachment_url($url,$post_id){
        $data = wp_get_attachment_metadata($post_id);
        if ( !empty($data['key'])){
            $url = get_post_field( 'guid', $post_id );
        }
        return $url;
    }

    public static function img_datahandle($imgname){
        if (self::api_info('api_version') == 'v1'){
            $res = LskyAPIV1::img_upload($imgname);
            if ( true === $res['status'] ){
                $url = $res['data']['links']['url'];
            }
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
            if ( 'success' === $res['status'] ){
                $tmpname = explode("/",$url);
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
        }
        return $img;
    }

    public static function  insert_attachment_data($data, $postarr, $unsanitized_postarr, $update){
        if (substr_count($data['post_mime_type'],'image') > 0 && !$update){
        $filepath = $unsanitized_postarr['file'];
        $urldata = self::img_datahandle($filepath);
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
        if (substr_count($post->post_mime_type,'image') > 0){
            $upload_dir = wp_upload_dir();
            foreach($image_meta['sizes'] as $key => $value){
                $filename = $value['file'];
                $urldata = self::img_datahandle($upload_dir['path'].'/'.$filename);
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
        $post_id = $post->ID;
        $link = 'not used';
        $form_fields["upload-to-lsky"] = array(
            "label" => esc_html__("图床替换", "upload-to-lsky"),
            "input" => "html",
            "html" => '<script>var lsky_js_flag="page";var link="' . $link . '";var post_id="' . $post_id . '";</script>' . "<a class='button-secondary' id='lsky-upload-one' href=\"javascript:;\">" . esc_html__("一键替换", "upload-to-lsky") . "</a>" . '<script src="' . plugins_url('../static/post.js',__FILE__) . '"></script>', 
            "helps" => esc_html__("实现一键将该图片上传到外部图床并替换数据库信息。", "upload-to-lsky")

          );
        return $form_fields;
    }

    public static function update_to_lsky($post_id){
        global $wpdb;
        $post = get_post($post_id,ARRAY_A);
        if(substr_count($post['post_mime_type'],'image') > 0){
            $data = wp_get_attachment_metadata($post_id);
            $upload_dir = wp_upload_dir();
            if (empty($data['key'])){
                $basefile = $upload_dir['basedir'].'/'.$data['file'];
                $baseres = self::img_datahandle($basefile);
                $new_post = array(
                    'guid' => $baseres['url'],
                    'post_mime_type' => $baseres['type'],
                    'post_content' => $baseres['key'],
                    'post_title' => $baseres['name']
                );
                $wpdb->update('wp_posts',$new_post,array('ID' => $post_id));
                clean_post_cache($post_id);
                $data = self::generate_attachment_metadata($data,$post_id,'upload');
                wp_update_attachment_metadata($post_id,$data);
                
            }
        }
    }
    public static function replaced_one(){
        $post_id = $_POST['post_id'];
        self::update_to_lsky($post_id);
    } 

    public static function lsky_fetch_v2_meta() {
        $api = sanitize_text_field($_POST['api']);
        $token = sanitize_text_field($_POST['token']);
        $albums = [];
        $album_resp = LskyAPIV2::get_album($api, $token);
        if ($album_resp['status'] == 'success') {
            foreach ($album_resp['data']['data'] as $item) {
                $albums[] = ['id' => $item['id'], 'name' => $item['name']];
            }
        } 
        $storages = [];
        $storage_resp = LskyAPIV2::get_storage($api, $token);
        if ($storage_resp['status'] == 'success') {
            foreach ($storage_resp['data']['storages'] as $item) {
                $storages[] = ['id' => $item['id'], 'name' => $item['name']];
            }
        }
        $response = [
            'status' => true,
            'albums' => $albums,
            'storages' => $storages
        ];
        if (ob_get_length()) ob_clean();
        echo json_encode($response);
        wp_die();
    }



}