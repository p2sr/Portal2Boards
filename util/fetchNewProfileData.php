<?php
    include(__DIR__ . "/../loader.php");

    $data = Database::query("SELECT usersnew.profile_number AS player_id, IFNULL(steamname, boardname) as displayName FROM usersnew");

    print_r("Starting...\n");

    $numRows = mysqli_num_rows($data);
    $i = 1;
    while ($row = $data->fetch_assoc()) {
        User::updateProfileData($row["player_id"]);

        if ($i % 500 == 0)
            print_r("Processed " . $i . "/" . $numRows . "\n");

        $i++;
    }

    print_r("Processed " . $i . " users\n");

    Leaderboard::cacheLeaderboard();
