<?php

namespace LskyProPlugin;

if (!defined('ABSPATH')) exit;

class Utils extends LskyPro {

    public static function writeLog($message, $logFile_name = 'app.log') {
        $message = self::redact_sensitive_data($message);
        $logFile_name = sanitize_file_name($logFile_name);
        if ($logFile_name === '') {
            $logFile_name = 'app.log';
        }

        $log_dir = self::$lsky_log_dir ?: trailingslashit(dirname(__DIR__)) . 'logs/';
        if (!self::prepare_filesystem()) {
            return;
        }

        global $wp_filesystem;
        if (!$wp_filesystem->is_dir($log_dir) && !$wp_filesystem->mkdir($log_dir, FS_CHMOD_DIR)) {
            return;
        }

        $log_file = trailingslashit($log_dir) . $logFile_name;
        $timestamp = current_time('mysql');
        if (is_array($message) || is_object($message)) {
            $message = wp_json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $previous = $wp_filesystem->exists($log_file) ? $wp_filesystem->get_contents($log_file) : '';
        $wp_filesystem->put_contents($log_file, $previous . "[$timestamp] $message" . PHP_EOL, FS_CHMOD_FILE);
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

    public static function http_get($url, $header = array()) {
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

    public static function http_post($url, $data, $header = array()) {
        if (!self::is_safe_http_url($url)) {
            return [
                'status' => false,
                'message' => '请求地址不合法',
            ];
        }

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => self::normalize_headers($header),
            'body' => $data,
        ]);

        if (is_wp_error($response)) {
            return [
                'status' => false,
                'message' => $response->get_error_message(),
            ];
        }

        return self::decode_response(wp_remote_retrieve_body($response), '接口返回不是有效 JSON');
    }

    public static function multipart_post($url, $fields, $file_field, $file_path, $mime_type, $header = array()) {
        if (!self::is_safe_http_url($url)) {
            return [
                'status' => false,
                'message' => '请求地址不合法',
            ];
        }

        if (!self::prepare_filesystem()) {
            return [
                'status' => false,
                'message' => '文件系统不可用',
            ];
        }

        global $wp_filesystem;
        if (!$wp_filesystem->exists($file_path) || !$wp_filesystem->is_readable($file_path)) {
            return [
                'status' => false,
                'message' => '上传文件不存在或不可读',
            ];
        }

        $boundary = 'lsky-' . wp_generate_password(24, false, false);
        $body = '';
        foreach ((array) $fields as $name => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . self::escape_multipart_name($name) . '"' . "\r\n\r\n";
            $body .= (string) $value . "\r\n";
        }

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . self::escape_multipart_name($file_field) . '"; filename="' . self::escape_multipart_name(basename($file_path)) . '"' . "\r\n";
        $body .= 'Content-Type: ' . $mime_type . "\r\n\r\n";
        $body .= $wp_filesystem->get_contents($file_path) . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        $headers = self::normalize_headers($header);
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

        $response = wp_remote_post($url, [
            'timeout' => 60,
            'headers' => $headers,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return [
                'status' => false,
                'message' => $response->get_error_message(),
            ];
        }

        return self::decode_response(wp_remote_retrieve_body($response), '接口返回不是有效 JSON');
    }

    public static function http_delete($url, $header = array(), $data = null) {
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

    private static function prepare_filesystem() {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;

        return $wp_filesystem instanceof \WP_Filesystem_Base;
    }

    private static function escape_multipart_name($name) {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $name);
    }
}
