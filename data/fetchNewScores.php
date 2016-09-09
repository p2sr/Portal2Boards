<?php
    include(__DIR__ . "/../loader.php");

    ini_set('memory_limit', '-1');
    Leaderboard::fetchNewData();
    Leaderboard::cacheLeaderboard();
?>
