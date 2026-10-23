<?php

class Cache {

    private static function pool() {
        $driver = new Stash\Driver\FileSystem(array('path' => '/var/www/html/cache/'));
        return new Stash\Pool($driver);
    }

    public static function get($boardName) {
        $item = self::pool()->getItem($boardName);
        return $item->get();
    }

    public static function set($boardName, $board) {
        $pool = self::pool();
        $item = $pool->getItem($boardName);
        $item->set($board);
        // Since Stash 0.13 (PSR-6) set() only changes the item; save() writes it.
        $pool->save($item);
    }
}
