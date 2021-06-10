<?php
    include(__DIR__ . "/../loader.php");

    Debug::$logging = false;

    $start = (int) $argv[1];
    $end = (int) $argv[2];

    print_r("start: " . $start . "\n");
    print_r("end: " . $end . "\n");

    $data = Database::query("SELECT usersnew.profile_number AS player_id, IFNULL(steamname, boardname) as displayName 
        FROM usersnew 
        ORDER BY usersnew.profile_number
        LIMIT " . $start . ", " .  $end);


    echo "starting...\n";

    $numRows = mysqli_num_rows($data);
    $i = 0;
    while ($row = $data->fetch_assoc()) {
        User::updateProfileData($row["player_id"]);

        $i++;

        if ($i % (($end - $start) / 10) == 0)
            print_r("Processed " . $i . "/" . $numRows . "\n");        
    }

    echo "finished\n";

    Leaderboard::cacheLeaderboard();

    echo "cached";