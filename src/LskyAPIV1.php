<?php

namespace src;
if (!defined('ABSPATH')) exit;

use src\LskyCommon;
use src\Utils;

class LskyAPIV1
{
    private static function close_finfo($finfo)
    {
        if (PHP_VERSION_ID < 80500) {
            finfo_close($finfo);
        }

        unset($finfo);
    }

    public static function generate_token($username,$password,$api = '')
    {
        $lsky_api = $api ?: LskyCommon::api_info('api');
        if (empty($lsky_api)) {
            return '请先填写 API 地址';
        }

        $url = $lsky_api . '/tokens';
        $post_data = [
            'email' => $username,
            'password' => $password
        ];
        $headers = [
            'Content-Type: application/json'
        ];
        $response = Utils::curl_post($url, json_encode($post_data), $headers);
        if (isset($response['status']) && true == $response['status'] && !empty($response['data']['token'])){
            return $response['data']['token'];
        }else{
            return $response['message'] ?? 'Token 获取失败';
        }
    }

    public static function removealltoken($api = '', $token = ''){
        $lsky_api = $api ?: LskyCommon::api_info('api');
        $lsky_token = $token ?: LskyCommon::api_info('token');
        $url = $lsky_api . '/tokens';
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $lsky_token
        ];
        $response = Utils::curl_delete($url, $headers);
        return $response['status'] ?? false;
    }

    public static function refreash_token($api = '', $username = '', $password = '', $old_token = ''){
        if (empty($username) || empty($password)) {
            return $old_token ?: LskyCommon::api_info('token');
        }

        if (self::removealltoken($api, $old_token) == true){
            $token = self::generate_token($username,$password,$api);
        }else{
            $token = $old_token ?: LskyCommon::api_info('token');
        }
        return $token;
    }

    public static function img_upload($imgname){
        $data['file'] = $imgname;
        $data['api'] = LskyCommon::api_info('api');
        $data['token'] = LskyCommon::api_info('token');
        if (empty($data['api']) || empty($data['token'])) {
            return [
                'status' => false,
                'message' => '请先配置 API 地址和 Token',
            ];
        }

        if (!function_exists('curl_file_create')) {
            return [
                'status' => false,
                'message' => '当前 PHP 环境未启用 cURL 文件上传支持',
            ];
        }

        if (!function_exists('finfo_open')) {
            return [
                'status' => false,
                'message' => '当前 PHP 环境未启用 fileinfo 扩展',
            ];
        }

        $url = $data["api"] . '/upload';
        $finfo = finfo_open(FILEINFO_MIME); 
        $mimetype = finfo_file($finfo, $data["file"]); 
        self::close_finfo($finfo);
        $image = curl_file_create( $data["file"], $mimetype, $data["file"] );
        if (LskyCommon::api_info('open_source') == 'no'){
            $post_data = [
                'file' => $image,
                'permission' => LskyCommon::api_info('permission')
            ];
        }else{
            $post_data = array( 'file' => $image );
        }
        $headers = [
            'Content-Type: multipart/form-data',
            'Authorization: Bearer ' . $data["token"]
        ];
        $response = Utils::curl_post($url, $post_data, $headers);
        return $response;
    }

    public static function img_delete($key ){
        $api = LskyCommon::api_info('api');
        $token = LskyCommon::api_info('token');
        $url = $api . '/images/' . $key;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ];
        $response = Utils::curl_delete($url, $headers,null);
        return $response;
    }
    


}
