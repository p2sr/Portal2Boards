<?php
    include(__DIR__ . "/../loader.php");

    Debug::initializeFileLogging();
    Debug::$loggingToOutput = true;

    ini_set('memory_limit', '-1');
    ignore_user_abort(true);
	set_time_limit(0);

    $rankLimits = Leaderboard::getRankLimits($chamber);

    Debug::log("Receiving new leaderboard data");
    $newBoardData = Leaderboard::getNewScoresLegacy($rankLimits);
    Debug::log("Receiving new leaderboard data done");

    if (!empty($newBoardData)) {
        $oldBoards = Leaderboard::getBoard(array("chamber" => $chamber));
        Leaderboard::saveScores($newBoardData, $oldBoards);
    	Leaderboard::cacheLeaderboard();
    }
?>
