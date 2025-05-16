<?php

namespace src;
use src\LskyPro;

class Utils extends LskyPro {

    public static function writeLog($message, $logFile_name = 'app.log') {
        $logDir = self::$lsky_log_dir;
        if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            error_log("无法创建日志目录: $logDir");
            return;
        }
        }
        $logFile = $logDir . $logFile_name;
        date_default_timezone_set('Asia/Shanghai');
        $timestamp = date('Y-m-d H:i:s');
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true); 
        }
        $logMessage = "[$timestamp] $message" . PHP_EOL;
    
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    public static function curl_get($url, $header = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode( $output, true );
    }

    public static function curl_post($url, $data, $header = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode( $output, true );
        }
    
        public static function curl_delete($url, $header = array(), $data = null) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            
            $output = curl_exec($ch);
            curl_close($ch);
            return json_decode( $output, true );
        }
}