<?php
namespace src;

require_once 'LskyPro.php';

class LskyCommon extends LskyPro
{
    public static function writeLog($message, $logFile_name = 'app.log') {
        // 确保消息中包含时间戳
        $logFile = self::$lsky_log_dir . $logFile_name;
        date_default_timezone_set('Asia/Shanghai');
        $timestamp = date('Y-m-d H:i:s');
        // 检查消息是否为数组或对象，并转换为字符串
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true); // 转换为字符串
        }
        $logMessage = "[$timestamp] $message" . PHP_EOL;
    
        // 将日志消息写入文件
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    public static function api_info($para)
    {
        $instance = new self();
        if ($para == 'api') {
            return $instance->lsky_api;
        } else if ($para == 'token') {
            return $instance->lsky_token;
        } else if ($para == 'permission') {
            return $instance->lsky_permission;
        } else if ($para == 'switch') {
            return $instance->lsky_switch;
        }
    }

    public static function lsky_menu(){
        return add_plugins_page(
            "图床插件",
            "图床插件",
            'manage_options',
            "lsky",
            'lsky_display'
        );
    }

    public static function img_del_handle($post_id, $post){
        $data = wp_get_attachment_metadata($post_id);
        if (!empty($data['key'])){
            self::img_delete($data['key']);
            self::writeLog('删除了'.$data['ori_path']);
            foreach($data['sizes'] as $key => $value){
                if (!empty($value['key'])){
                    self::img_delete($value['key']);
                    self::writeLog('删除了'.$value['ori_path']);
                    @unlink($value['ori_path']);
                }
            }
        }
    }

    public static function img_delete($key ){
        $api = self::api_info('api');
        $token = self::api_info('token');
        $url = $api . '/images/' . $key;
        $header = array('Content-Type: application/json',
         'Authorization: Bearer ' . $token);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        curl_close($ch);
    }

    public static function get_attachment_url($url,$post_id){
        $data = wp_get_attachment_metadata($post_id);
        if ( !empty($data['key'])){
            $url = get_post_field( 'guid', $post_id );
        }
        return $url;
    }

    public static function img_upload($imgname){
        $data['file'] = $imgname;
        $data['api'] = self::api_info('api');
        $data['token'] = self::api_info('token');
        $url = $data["api"] . '/upload';
        $finfo = finfo_open(FILEINFO_MIME); 
        $mimetype = finfo_file($finfo, $data["file"]); 
        finfo_close($finfo);
        $image = curl_file_create( $data["file"], $mimetype, $data["filename"] );
        $post_data = array( 'file' => $image );
        $header[] = 'Content-Type: multipart/form-data';
        $header[] = 'Authorization: Bearer ' . $data["token"];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode( $output, true );
    }

    public static function img_datahandle($imgname){
        $res = self::img_upload($imgname);
        if ( true === $res['status'] ){
            $url = $res['data']['links']['url'];
        }
        $tmpname = explode("/",$url);
        $filename = $tmpname[count($tmpname)-1];
        $img = array(
            "file" => $filename,
            "url" => $url,
            "type" => 'image/webp',
            "name" => $res['data']['name'],
            "size" => $res['data']['size'],
            "key" => $res['data']['key'],
            "pathname" => $res['data']['pathname']
        );
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
                $image_meta['sizes'][$key]['filesize'] = $urldata['size'];
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
        LskyCommon::update_to_lsky($post_id);
    } 
}