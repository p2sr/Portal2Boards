<?php
class Database {

    static $instance;

    public static function authorize() {
        $config = Config::get();

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $db = new mysqli(
            $config->database_host,
            $config->database_user,
            $config->database_pass,
            $config->database_name,
            $config->database_port,
        );

        if ($db->connect_errno) {
            trigger_error($db->connect_error);
        }

        $db->set_charset('utf8mb4');

        self::$instance = $db;
    }

    public static function query($query, $resultmode = MYSQLI_STORE_RESULT) {
        if (!isset(self::$instance))
            self::authorize();

        $bob = self::$instance->query($query, $resultmode);
        if(!$bob) {
            trigger_error(self::$instance->error);
        }
        return $bob;
    }

    public static function getMysqli() {
        if (!isset(self::$instance))
            self::authorize();

        return self::$instance;
    }

    public static function affectedRows() {
        return mysqli_affected_rows(self::$instance);
    }

}

