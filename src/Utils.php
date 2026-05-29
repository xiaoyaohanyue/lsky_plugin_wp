<?php

namespace src;
if (!defined('ABSPATH')) exit;

use src\LskyPro;

class Utils extends LskyPro {

    public static function writeLog($message, $logFile_name = 'app.log') {
        $message = self::redact_sensitive_data($message);
        $logFile_name = sanitize_file_name($logFile_name);
        if ($logFile_name === '') {
            $logFile_name = 'app.log';
        }

        $logDir = self::$lsky_log_dir;
        if (empty($logDir)) {
            $logDir = trailingslashit(dirname(__DIR__)) . 'logs/';
        }

        if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            error_log("无法创建日志目录: $logDir");
            return;
        }
        }
        $logFile = $logDir . $logFile_name;
        $timestamp = current_time('mysql');
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true); 
        }
        $logMessage = "[$timestamp] $message" . PHP_EOL;
    
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    private static function redact_sensitive_data($message) {
        if (is_array($message)) {
            foreach ($message as $key => $value) {
                if (in_array($key, ['tokens', 'token', 'password'], true)) {
                    $message[$key] = '[redacted]';
                    continue;
                }

                if (is_array($value) || is_object($value)) {
                    $message[$key] = self::redact_sensitive_data($value);
                }
            }
        }

        if (is_object($message)) {
            foreach ($message as $key => $value) {
                if (in_array($key, ['tokens', 'token', 'password'], true)) {
                    $message->{$key} = '[redacted]';
                    continue;
                }

                if (is_array($value) || is_object($value)) {
                    $message->{$key} = self::redact_sensitive_data($value);
                }
            }
        }

        return $message;
    }

    private static function normalize_headers($headers) {
        $normalized = [];
        foreach ((array) $headers as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
                continue;
            }

            if (is_string($value) && strpos($value, ':') !== false) {
                [$name, $header_value] = explode(':', $value, 2);
                $normalized[trim($name)] = trim($header_value);
            }
        }

        return $normalized;
    }

    private static function decode_response($body, $fallback_message = '请求失败') {
        $decoded = json_decode((string) $body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'status' => false,
                'message' => $fallback_message,
                'raw' => $body,
            ];
        }

        return $decoded;
    }

    private static function close_curl_handle($handle) {
        if (PHP_VERSION_ID < 80500) {
            curl_close($handle);
        }

        unset($handle);
    }

    public static function curl_get($url, $header = array()) {
        if (!self::is_safe_http_url($url)) {
            return [
                'status' => false,
                'message' => '请求地址不合法',
            ];
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => self::normalize_headers($header),
        ]);

        if (is_wp_error($response)) {
            return [
                'status' => false,
                'message' => $response->get_error_message(),
            ];
        }

        return self::decode_response(wp_remote_retrieve_body($response), '接口返回不是有效 JSON');
    }

    public static function curl_post($url, $data, $header = array()) {
        if (!self::is_safe_http_url($url)) {
            return [
                'status' => false,
                'message' => '请求地址不合法',
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'status' => false,
                'message' => '当前 PHP 环境未启用 cURL 扩展',
            ];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        if ($output === false) {
            $error = curl_error($ch);
            self::close_curl_handle($ch);
            return [
                'status' => false,
                'message' => $error,
            ];
        }
        self::close_curl_handle($ch);
        return self::decode_response($output, '接口返回不是有效 JSON');
    }
    
    public static function curl_delete($url, $header = array(), $data = null) {
        if (!self::is_safe_http_url($url)) {
            return [
                'status' => false,
                'message' => '请求地址不合法',
            ];
        }

        $args = [
            'method' => 'DELETE',
            'timeout' => 30,
            'headers' => self::normalize_headers($header),
        ];

        if (!empty($data)) {
            $args['body'] = $data;
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return [
                'status' => false,
                'message' => $response->get_error_message(),
            ];
        }

        return self::decode_response(wp_remote_retrieve_body($response), '接口返回不是有效 JSON');
    }

    private static function is_safe_http_url($url) {
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        return in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }
}
