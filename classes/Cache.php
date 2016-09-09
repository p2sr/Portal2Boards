<?php

class Cache {

    public static function get($boardName) {
        $driver = new Stash\Driver\FileSystem();
        $options = array('path' => '/tmp/myCache/');
        $driver->setOptions($options);
        $pool = new Stash\Pool($driver);

        $item = $pool->getItem($boardName);
        return $item->get();
    }

    public static function set($boardName, $board) {
        $driver = new Stash\Driver\FileSystem();
        $options = array('path' => '/tmp/myCache/');
        $driver->setOptions($options);
        $pool = new Stash\Pool($driver);

        $item = $pool->getItem($boardName);
        $item->set($board);
    }
}
