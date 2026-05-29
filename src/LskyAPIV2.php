<?php

namespace LskyProPlugin;
if (!defined('ABSPATH')) exit;

use LskyProPlugin\LskyCommon;
use LskyProPlugin\Utils;

class LskyAPIV2
{
    private static function close_finfo($finfo)
    {
        if (PHP_VERSION_ID < 80500) {
            finfo_close($finfo);
        }

        unset($finfo);
    }

    public static function get_album($api, $token){
        if (empty($api) || empty($token)) {
            return [
                'status' => false,
                'message' => '请先填写 API 地址和 Token',
            ];
        }

        $url = $api . '/user/albums';
        $headers = [
            'Content-Type: application/json',
            'User-Agent: yaoyue/lsky-api-client',
            'Authorization: Bearer ' . $token
        ];
        $response = Utils::http_get($url, $headers);
        return $response;
    }

    public static function get_storage($api, $token){
        if (empty($api) || empty($token)) {
            return [
                'status' => false,
                'message' => '请先填写 API 地址和 Token',
            ];
        }

        $url = $api . '/group';
        $headers = [
            'Content-Type: application/json',
            'User-Agent: yaoyue/lsky-api-client',
            'Authorization: Bearer ' . $token
        ];
        $response = Utils::http_get($url, $headers);
        return $response;
    }

    public static function img_upload($imgname){
        $data['file'] = $imgname;
        $data['api'] = LskyCommon::api_info('api');
        $data['token'] = LskyCommon::api_info('token');
        $data['storage_id'] = LskyCommon::api_info('storage_id');
        $data['album_id'] = LskyCommon::api_info('album_id');
        $data['is_public'] = LskyCommon::api_info('permission');
        if (empty($data['api']) || empty($data['token'])) {
            return [
                'status' => false,
                'message' => '请先配置 API 地址和 Token',
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
        $fields = [
            'album_id' => $data["album_id"],
            'is_public' => $data["is_public"],
            'storage_id' => $data["storage_id"]
        ];
        $headers = [
            'Authorization: Bearer ' . $data["token"]
        ];
        $response = Utils::multipart_post($url, $fields, 'file', $data["file"], $mimetype, $headers);
        return $response;
    }

    public static function img_delete($key ){
        $api = LskyCommon::api_info('api');
        $token = LskyCommon::api_info('token');
        $url = $api . '/user/photos';
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ];
        $response = Utils::http_delete($url, $headers,'['.$key.']');
        return $response;
    }
    



}
