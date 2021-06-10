<?php

DEFINE('DEBUG_FILENAME', ROOT_PATH . '/debug.txt');

class Debug
{
    static $loggingToOutput = false;
    static $loggingToFile = true;

    public static function initializeFileLogging() {
        self::$loggingToFile = true;

        if (filesize(DEBUG_FILENAME) > 2000000) {
            file_put_contents(DEBUG_FILENAME, "");
        }
    }

    public static function log($str) {
        if (self::$loggingToOutput) {
            print_r($str . "\n");
        }

        if (self::$loggingToFile) {
            $logFile = fopen(DEBUG_FILENAME, "a") or die("Unable to open file!");
            fwrite($logFile, "[" . date('m/d/Y H:i:s', time()) . "] " . $str . "\n");
            fclose($logFile);
        }
    }

}