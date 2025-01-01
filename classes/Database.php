<?php

class Database {
    /** @var \mysqli */
    static $instance;

    public static function authorize() {
        Debug::log("Connecting to DB");
        $config = Config::get();

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $db = new mysqli(
            $config->database_host,
            $config->database_user,
            $config->database_pass,
            $config->database_name,
            $config->database_port,
        );

        if ($db->connect_error) {
            Debug::log("DB Error: " + $db->connect_error);
            //trigger_error($db->connect_error);
        }

        $db->set_charset('utf8mb4');

        self::$instance = $db;
    }

    public static function disconnect() {
        mysqli_close(self::$instance);
    }

    public function reconnect() {
        Debug::log("Reconnecting to database as connection has broken.");
        self::disconnect();
        self::authorize();
    }

    public static function unsafe_raw(string $query, int $result_mode = MYSQLI_STORE_RESULT) {
        $db = self::getMysqli();
        $result = $db->query($query, $result_mode);
        if (!$result) {
            trigger_error($db->error);
        }
        return $result;
    }

    public static function query(string $query, string $types, array $params) {
        $inputCount = substr_count($query, '?');
        if (!$inputCount) {
            throw new \Exception('Invalid query without input parameters! Use Database::unsafe_raw at your own risk.');
        }

        $typesLen = strlen($types);
        if (!$typesLen) {
            throw new \Exception('Missing types!');
        }

        if ($typesLen !== $inputCount) {
            throw new \Exception('Query input count does not match with the provided types!');
        }

        $paramsCount = count($params);
        if (!$paramsCount) {
            throw new \Exception('Missing params!');
        }

        if ($typesLen !== $paramsCount) {
            throw new \Exception('Params count does not match with the provided types!');
        }

        if (!mysqli_ping(self::getMysqli())) self::reconnect();

        $query = self::getMysqli()->prepare($query);
        $query->bind_param($types, ...$params);

        if (!$query->execute()) {
            trigger_error($query->error);
            throw new \Exception('Failed to execute query!');
        }

        return $query->get_result();
    }

    public static function findOne(string $query, string $types, array $params) {
        $result = self::query($query, $types, $params);
        if ($result === false) {
            return null;
        }

        if ($result->num_rows > 1) {
            throw new \Exception("Retrieved more than one row!");
        }

        $row = $result->fetch_assoc();
        if ($row === false) {
            return null;
        }

        return $row;
    }

    public static function findMany(string $query, string $types, array $params) {
        $result = self::query($query, $types, $params);
        return $result !== false ? $result->fetch_all(MYSQLI_ASSOC) : [];
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
