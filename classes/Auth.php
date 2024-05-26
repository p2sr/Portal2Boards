<?php

class Auth {
    public static function gen_auth_hash(string $profile_number) {
        // Create Auth hash
        $auth_hash = Util::random_str(32);

        // Save to db
        Database::query(
            "UPDATE usersnew 
             SET usersnew.auth_hash = ?
             WHERE usersnew.profile_number = ?",
            "ss",
            [
                $auth_hash,
                $profile_number,
            ]
        );

        return $auth_hash;
    }

    public static function test_auth_hash(string $auth_hash) {
        $row = Database::findOne(
            "SELECT usersnew.profile_number
             FROM usersnew
             WHERE usersnew.auth_hash = ?",
            "s",
            [
                $auth_hash,
            ]
        );

        return $row ? strval($row["profile_number"]) : null;
    }

    public static function get_auth_hash(string $profile_number) {
        $row = Database::findOne(
            "SELECT usersnew.auth_hash
             FROM usersnew
             WHERE profile_number = ?",
            "s",
            [
                $profile_number,
            ]
        );

        return $row ? strval($row["auth_hash"]) : null;
    }

    public static function del_auth_hash(string $profile_number) {
        Database::query(
            "UPDATE usersnew 
             SET usersnew.auth_hash = NULL
             WHERE usersnew.profile_number = ?",
            "s",
            [
                $profile_number,
            ]
        );
    }
}
