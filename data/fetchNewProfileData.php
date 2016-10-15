<?php
  include(__DIR__ . "/../loader.php");

    $data = Database::query("SELECT usersnew.profile_number AS player_id, IFNULL(steamname, boardname) as displayName FROM usersnew");
//    $data = Database::query("SELECT usersnew.profile_number AS player_id, IFNULL(steamname, boardname) as displayName FROM usersnew WHERE profile_number = 76561198057533306");

    $numRows = mysqli_num_rows($data);
    $i = 1;
    while ($row = $data->fetch_assoc()) {
        User::updateProfileData($row["player_id"]);

        if ($i % 100 == 0)
            print_r("Processed " . $i . "/" . $numRows . "\n");

        $i++;
    }

    Leaderboard::cacheLeaderboard();
