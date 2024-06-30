<?php

/**
 * Provides values from the `.config.json` file.
 * 
 * Example: `Config::get()->database_host`
 */
final class Config {
    public bool   $is_using_proxy;
    public string $database_host;
    public int    $database_port;
    public string $database_user;
    public string $database_pass;
    public string $database_name;
    public string $discord_webhook_wr;
    public string $discord_webhook_mdp;
    public string $steam_api_key;
    public string $autorender_api_token;

    private static $_instance;

    public static function get(): Config {
        return self::$_instance ??= new Config();
    }

    private function __construct() {
        foreach (json_decode(file_get_contents(ROOT_PATH . '/.config.json'), true) as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
