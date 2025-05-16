<?php

namespace src;
use src\LskyCommon;
use src\Utils;

class LskyAPIV1
{

    public static function generate_token($username,$password)
    {
        $lsky_api = LskyCommon::api_info('api');
        $url = $lsky_api . '/tokens';
        $post_data = [
            'email' => $username,
            'password' => $password
        ];
        $headers = [
            'Content-Type: application/json'
        ];
        $response = Utils::curl_post($url, json_encode($post_data), $headers);
        if (true == $response['status']){
            return $response['data']['token'];
        }else{
            return $response['message'];
        }
    }

    public static function removealltoken(){
        $lsky_api = LskyCommon::api_info('api');
        $url = $lsky_api . '/tokens';
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . LskyCommon::api_info('token')
        ];
        $response = Utils::curl_delete($url, $headers);
        return $response['status'];
    }

    public static function refreash_token(){
        if (self::removealltoken() == true){
            $username = LskyCommon::api_info('username');
            $password = LskyCommon::api_info('password');
            $token = self::generate_token($username,$password);
        }else{
            $token = LskyCommon::api_info('token');
        }
        return $token;
    }

    public static function img_upload($imgname){
        $data['file'] = $imgname;
        $data['api'] = LskyCommon::api_info('api');
        $data['token'] = LskyCommon::api_info('token');
        $url = $data["api"] . '/upload';
        $finfo = finfo_open(FILEINFO_MIME); 
        $mimetype = finfo_file($finfo, $data["file"]); 
        finfo_close($finfo);
        $image = curl_file_create( $data["file"], $mimetype, $data["filename"] );
        $post_data = array( 'file' => $image );
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
        $response = Utils::curl_delete($url, $headers);
        return $response;
    }
    


}