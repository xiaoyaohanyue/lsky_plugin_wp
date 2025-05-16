<?php

namespace src;
use src\LskyCommon;
use src\Utils;

class LskyAPIV2
{

    public static function get_album($api, $token){
        $url = $api . '/user/albums';
        $headers = [
            'Content-Type: application/json',
            'User-Agent: yaoyue/lsky-api-client',
            'Authorization: Bearer ' . $token
        ];
        $response = Utils::curl_get($url, $headers);
        return $response;
    }

    public static function get_storage($api, $token){
        $url = $api . '/group';
        $headers = [
            'Content-Type: application/json',
            'User-Agent: yaoyue/lsky-api-client',
            'Authorization: Bearer ' . $token
        ];
        $response = Utils::curl_get($url, $headers);
        return $response;
    }

    public static function img_upload($imgname){
        $data['file'] = $imgname;
        $data['api'] = LskyCommon::api_info('api');
        $data['token'] = LskyCommon::api_info('token');
        $data['storage_id'] = LskyCommon::api_info('storage_id');
        $data['album_id'] = LskyCommon::api_info('album_id');
        $data['is_public'] = LskyCommon::api_info('permission');
        $url = $data["api"] . '/upload';
        $finfo = finfo_open(FILEINFO_MIME); 
        $mimetype = finfo_file($finfo, $data["file"]); 
        finfo_close($finfo);
        $image = curl_file_create( $data["file"], $mimetype, $data["filename"] );
        $post_data = [ 
            'file' => $image,
            'album_id' => $data["album_id"],
            'is_public' => $data["is_public"],
            'storage_id' => $data["storage_id"]
        ];
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
        $url = $api . '/user/photos';
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ];
        $response = Utils::curl_delete($url, $headers,'['.$key.']');
        return $response;
    }
    



}