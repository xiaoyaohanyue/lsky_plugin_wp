<?php
namespace src;

abstract class LskyPro
{
    public $lsky_api;
    public $lsky_token;
    public $lsky_permission;
    public $lsky_switch;
    public static $lsky_dir = WP_PLUGIN_DIR . '/lsky-pro/';
    public static $lsky_log_dir = WP_PLUGIN_DIR . '/lsky-pro/logs/';
    public function __construct()
    {
        $setting = unserialize(get_option('lsky_setting'));
        $this->lsky_api = $setting['api'];
        $this->lsky_token = $setting['tokens'];
        $this->lsky_permission = $setting['permission'];
        $this->lsky_switch = $setting['switch'];
    } 

}