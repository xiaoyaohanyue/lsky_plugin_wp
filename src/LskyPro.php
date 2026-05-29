<?php
namespace src;

if (!defined('ABSPATH')) exit;

abstract class LskyPro
{
    public $lsky_api;
    public $lsky_token;
    public $lsky_permission;
    public $lsky_switch;
    public $lsky_version;
    public $lsky_open_source;
    public $lsky_album_id;
    public $lsky_storage_id;
    public $lsky_username;
    public $lsky_password;

    public static $lsky_dir = '';
    public static $lsky_log_dir = '';

    public function __construct()
    {
        self::$lsky_dir = trailingslashit(dirname(__DIR__));
        self::$lsky_log_dir = self::$lsky_dir . 'logs/';

        $setting = maybe_unserialize(get_option('lsky_setting', []));
        if (!is_array($setting)) {
            $setting = [];
        }

        $this->lsky_api = $setting['api'] ?? '';
        $this->lsky_token = $setting['tokens'] ?? '';
        $this->lsky_permission = $setting['permission'] ?? '';
        $this->lsky_switch = $setting['switch'] ?? 'disable';
        $this->lsky_version = $setting['api_version'] ?? 'v1';
        $this->lsky_open_source = $setting['open_source'] ?? 'no';
        $this->lsky_album_id = $setting['album_id'] ?? '';
        $this->lsky_storage_id = $setting['storage_id'] ?? '';
        $this->lsky_username = $setting['username'] ?? '';
        $this->lsky_password = '';
    }
}
