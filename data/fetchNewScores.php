<?php
    include(__DIR__ . "/../loader.php");

    Debug::initializeFileLogging();
    Debug::$loggingToOutput = true;

    ini_set('memory_limit', '-1');
    ignore_user_abort(true);
	set_time_limit(0);

    Leaderboard::fetchNewData();
    Leaderboard::cacheLeaderboard();
?>
