<?php
    include(__DIR__ . "/../loader.php");

    Debug::initializeFileLogging();
    Debug::$loggingToOutput = true;

    ini_set('memory_limit', '-1');
    ignore_user_abort(true);
    set_time_limit(0);

    $profile_number = $_GET['profile_number'];
    if (!$profile_number || !is_numeric($profile_number)) {
        http_response_code(400);
        exit;
    }

    Leaderboard::fixupScoresForUser($profile_number);
?>
