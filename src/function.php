<?php


function yaoyueupload($imgname){
	$data['file'] = $imgname;
    $data['api'] = unserialize(get_option('lsky_setting'))['api'];
	$data['token'] = unserialize(get_option('lsky_setting'))['tokens'];
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

function yaoyueget($imgname,$act){
	$data['file'] = $imgname;
    $data['api'] = unserialize(get_option('lsky_setting'))['api'];
	$data['token'] = unserialize(get_option('lsky_setting'))['tokens'];
	if ( $act == 'delete' ){
		$url = $data["api"] . '/images/' . $data['file'];
		$post_data = ['key' => $data['file'] ];
	}
    else {
        $url = $data["api"] . '/images?keyword=' . $data['file'];
    }
    $header[] = 'Authorization: Bearer ' . $data["token"];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	if ( $act == 'delete' ){
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	}
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode( $output, true );
}

function yaoyuedelete($key){
    $status = yaoyueget($key,'delete');
}

function datahandle($imgname){
    $res = yaoyueupload($imgname);
    if ( true === $res['status'] ){
        $url = $res['data']['links']['url'];
    }
	$tmpname = explode("/",$url);
    $filename = $tmpname[count($tmpname)-1];
	$imgurl = array(
        "file" => $filename,
		"url" => $url,
		"type" => 'image/webp',
        "name" => $res['data']['name'],
        "size" => $res['data']['size'],
        "key" => $res['data']['key'],
        "pathname" => $res['data']['pathname']
	);
    return $imgurl;
}

function my_insert_attachment_data($data){
    if (strpos($data['post_mime_type'],'image') !== false){
    $guid = explode('/',$data['guid']);;
    $upload_dir = wp_upload_dir();
    $lengh = count($guid);
    $filepath = $upload_dir['path'] . '/' . $guid[$lengh-1];
    $urldata = datahandle($filepath);
    $data['post_mime_type'] = $urldata['type'];
    $data['guid'] = $urldata['url'];
    $data['post_content'] = $urldata['key'];
    $data['post_title'] = $urldata['name'];
    }
    return $data;
}

function my_generate_attachment_metadata($image_meta,$attachment_id){
    global $wpdb;
    $post = get_post($attachment_id);
    if(strpos($post->post_mime_type,'image') !== false){
    $upload_dir = wp_upload_dir();
    $file_arry = array_keys($image_meta['sizes']);
    $len = count($file_arry);
    for ($i=0;$i<$len;$i++){
        $filename = $image_meta['sizes'][$file_arry[$i]]['file'];
        $urldata = datahandle($upload_dir['path'].'/'.$filename);
        unlink($upload_dir['path'].'/'.$filename);
        $image_meta['sizes'][$file_arry[$i]]['filesize'] = $urldata['size'];
        $image_meta['sizes'][$file_arry[$i]]['file'] = $urldata['name'];
        $image_meta['sizes'][$file_arry[$i]]['mime-type'] = $urldata['type'];
        $image_meta['sizes'][$file_arry[$i]]['key'] = $urldata['key'];
    }
    $image_meta['file'] = $post->post_title;
    $data = get_post($attachment_id);
    $image_meta['key'] = $data->post_content;
    $wpdb->update('wp_posts',array(
        'post_content' => '',
        'post_title' => $post->post_name
    ),array(
        'ID' => $attachment_id
    ));
    $name = get_post_meta( $post_id, '_wp_attached_file', true );
    unlink($upload_dir['basedir'].'/'.$name);
    }
    return $image_meta;
}

function my_get_attachment_url($url,$post_id){
    $data = wp_get_attachment_metadata($post_id);
    if ( !empty($data['key'])){
        $url = get_post( $post_id )->guid;
    }
    return $url;
}

function my_delete_attachment($post_id){
    $name = get_post_meta( $post_id, '_wp_attached_file', true );
    $data = wp_get_attachment_metadata($post_id);
    if (!empty($data['key'])){
    $data_arry = array_keys($data['sizes']);
    $len = count($data_arry);
    for ($i=0;$i<$len;$i++){
	    yaoyuedelete($data['sizes'][$data_arry[$i]]['key']);
    }
	yaoyuedelete($data['key']);
    }
}
function attachment_editor($form_fields, $post)
    {
        $post_id = $post->ID;
        $link = 'not used';
        $form_fields["yaoyue-img-test"] = array(
            "label" => esc_html__("图床替换", "yaoyue-img-test"),
            "input" => "html",
            "html" => '<script>var yaoyue_js_flag="page";var link="' . $link . '";var post_id="' . $post_id . '";</script>' . "<a class='button-secondary' id='yaoyue-img-test-button' href=\"javascript:;\">" . esc_html__("一键替换", "yaoyue-img-test") . "</a>" . '<script src="' . plugins_url('../static/post.js',__FILE__) . '"></script>', 
            "helps" => esc_html__("PostID:$post_id 实现一键将该图片上传到外部图床并替换数据库信息。", "yaoyue-img-test")

          );
        return $form_fields;
    }

function b_update_array($new = array(),$old = array(),$action = 'write'){
    if ($action == 'write'){
        $new_arry = array_keys($new);
        $len = count($new_arry);
        for ($i=0;$i<$len;$i++){
            $old[$new_arry[$i]] = $new[$new_arry[$i]];
        }
        return $old;
    }
}

function update_to_lsky($post_id){
    global $wpdb;
    $post = get_post($post_id,ARRAY_A);
    if(strpos($post['post_mime_type'],'image') !== false){
    $data = wp_get_attachment_metadata($post_id);
    $upload_dir = wp_upload_dir();
    if (empty($data['key'])){
        $oldpath = explode("/",$data['file']);
        $basefile = $upload_dir['basedir'].'/'.$data['file'];
        echo $basefile;
        $baseres = datahandle($basefile);
        unlink($basefile);
        echo $baseres['url'];
        $data['key'] = $baseres['key'];
        $data['file'] = $baseres['name'];
        $test_data = array(
            'ID' => $post_id,
            'guid' => $baseres['url'],
            'post_mime_type' => $baseres['type']
        );
        $wpdb->update('wp_posts',$test_data,array(
            'ID' => $post_id
        ));
        $path = $oldpath[0].'/'.$oldpath[1];
        $tmp_guid = $post['guid'];
        $file_arry = array_keys($data['sizes']);
        $len = count($file_arry);
        for ($i=0;$i<$len;$i++){
            $filename = $upload_dir['basedir'].'/'.$path.'/'.$data['sizes'][$file_arry[$i]]['file'];
            $fileupload = datahandle($filename);
            unlink($filename);
            $data['sizes'][$file_arry[$i]] = b_update_array(
                array('key' => $fileupload['key'],
                      'mime-type' => $fileupload['type'],
                      'filesize' => $fileupload['size'],
                      'file' => $fileupload['name']
        ),$data['sizes'][$file_arry[$i]],'write');
        }
        $new_post = b_update_array($test_data,$post,'write');
        $cur_post = get_post($post_id);
        $cur_guid = $cur_post->guid;
        $wpdb->query("UPDATE wp_posts SET post_content=REPLACE (post_content,'".$tmp_guid."','".$cur_guid."')");
        // $wpdb->query("UPDATE wp_postmeta SET meta_value=REPLACE (meta_value,'".$tmp_guid."','".$cur_guid."')");
        // $wpdb->query("UPDATE wp_options SET option_value=REPLACE (option_value,'".$tmp_guid."','".$cur_guid."')");
        wp_update_attachment_metadata($post_id,$data);
    }
    }
}

function replaced_one(){
    $post_id = $_POST['yaoyue'];
    update_to_lsky($post_id);
} 

function _ajaxReturntwo($code, $msg, $data=array())
{
    $data = is_null($data) ? array() : $data;
    $data = array(
        "code" => $code,
        "msg" => $msg,
        "data" => json_encode($data)
    );
    header('Cache-Control:no-cache,must-revalidate');
    header('Pragma:no-cache');
    header('Content-Type:application/json; charset=utf-8');
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers:x-requested-with,content-type");
    echo json_encode($data);
}

function replaced_all(){
    global $wpdb;
    $datas['procnum'] = 0;
	$datas['mes'] = " ";
	$dates['status'] = 'start';
	update_option('lsky_setting_process', $datas);
    $num = 0;
    $width = 500;
    $sql = "SELECT * FROM wp_posts WHERE post_type = 'attachment'";
    $attachment_datas = $wpdb->get_results($sql);
    $num_total = count($attachment_datas);
    $pix = $width / $num_total;
    $progress = 0;
    foreach ($attachment_datas as $attachment_data){
        $num++;
        $mes = "进度：".intval(($num/$num_total)*100)."%，已转换：".$num." 张，还剩：".($num_total - $num)."张，ID：".$attachment_data->ID."已处理";
        $iwidth = min($width, intval($progress));
        $datas['procnum'] = intval(($num/$num_total)*100);
        $datas['mes'] = $mes;
        $post = get_post($attachment_data->ID,ARRAY_A);
        update_option('lsky_setting_process', $datas);
        if(strpos($post['post_mime_type'],'svg') == false){
        update_to_lsky($attachment_data->ID);
        }
        $progress += $pix;  
    } 
    $datas['procnum'] = 0;
        $datas['mes'] = " ";
        $dates['status'] = 'end';
        update_option('lsky_setting_process', $datas);
} 

function replaced_all_ajax(){
    ob_end_clean();
    ob_start();    
    echo str_repeat(' ', 65536); 
    _ajaxReturntwo(200,'操作成功');
    $size = ob_get_length();
    header("Content-Length: ".$size);
    header("Connection: close");
    header("HTTP/1.1 200 OK");
    header('Content-Type:application/json; charset=utf-8');
    ob_end_flush();
    if(ob_get_length())
    ob_flush();
    flush();
    if (function_exists("fastcgi_finish_request")) { 
        fastcgi_finish_request(); 
    }
    ignore_user_abort(true);
    set_time_limit(0); 
        echo ob_get_clean();    
        flush();
        debugtofile(get_option('lsky_setting_process')['procnum']);
    if(get_option('lsky_setting_process')['procnum'] == 0){
        sleep(1);
        if(get_option('lsky_setting_process')['procnum'] == 0){
            replaced_all();
        }
    }
    }
add_action('wp_ajax_yaoyue_image_replace','replaced_all_ajax');
add_action('wp_ajax_yaoyue_test','replaced_one');
add_filter('attachment_fields_to_edit', 'attachment_editor', 10, 2);
add_action('delete_attachment','my_delete_attachment');
add_filter('wp_insert_attachment_data', 'my_insert_attachment_data');
add_filter('wp_update_attachment_metadata','my_update_attachment_metadata');
add_filter('wp_generate_attachment_metadata','my_generate_attachment_metadata',10,2);
add_filter('wp_get_attachment_url','my_get_attachment_url',10,2);
?>