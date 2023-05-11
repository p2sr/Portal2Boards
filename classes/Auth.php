<?php

class Auth {

    public static function gen_auth_hash($userId)
    {
        if (!ctype_alnum($userId)) return null;

        // Create Auth hash
        $auth_hash = Util::random_str(32);
        //Debug::log("User id: ".$userId." - Hash: ".$auth_hash);
        // Save to db
        Database::query("UPDATE usersnew 
                                SET usersnew.auth_hash = '{$auth_hash}'
                                WHERE usersnew.profile_number = '{$userId}'");
        // return auth hash
        return $auth_hash;
    }

    public static function test_auth_hash($auth_hash){
        if (!ctype_alnum($auth_hash)) return null;

        $data = Database::query("SELECT usersnew.profile_number FROM usersnew
                                WHERE usersnew.auth_hash = '{$auth_hash}'");

        $userId = null;
        while ($row = $data->fetch_assoc()) {
            $userId = $row["profile_number"];
        }
        return $userId;
    }

    public static function get_auth_hash($userId) {
        if (!ctype_alnum($userId)) return null;
        $data = Database::query("SELECT usersnew.auth_hash FROM usersnew WHERE profile_number = {$userId}");
        $auth_hash = null;
        while ($row = $data->fetch_assoc()) {
            $auth_hash = $row["auth_hash"];
        }
        return $auth_hash;
    }

    public static function del_auth_hash($userId){
        if (!ctype_alnum($userId)) return null;
        Database::query("UPDATE usersnew 
                                SET usersnew.auth_hash = NULL
                                WHERE usersnew.profile_number = ".$userId);
    }
}
