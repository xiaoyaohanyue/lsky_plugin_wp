<?php

namespace src;
use src\LskyCommon;

add_action('wp_ajax_lsky_fetch_v2_meta', array(LskyCommon::class,'lsky_fetch_v2_meta'));
if (LskyCommon::api_info('switch') == 'enable'){
    add_action('wp_ajax_lsky_upload_one',array(LskyCommon::class,'replaced_one'));
    add_filter('attachment_fields_to_edit', array(LskyCommon::class,'attachment_editor'), 10, 2);
    add_action('delete_attachment',array(LskyCommon::class,'img_del_handle'),10,2);
    add_filter('wp_insert_attachment_data', array(LskyCommon::class,'insert_attachment_data'),10,4);
    add_filter('wp_generate_attachment_metadata',array(LskyCommon::class,'generate_attachment_metadata'),10,3);
    add_filter('wp_get_attachment_url',array(LskyCommon::class,'get_attachment_url'),10,2);
}


?>