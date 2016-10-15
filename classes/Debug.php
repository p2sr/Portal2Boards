<?php

class Debug
{

    static $logging = true;

    public static function log($str) {
        if (self::$logging) {
            print_r($str . "\n");
        }
    }

}