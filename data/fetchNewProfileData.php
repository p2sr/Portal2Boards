<?php
  include(__DIR__ . "/../loader.php");

    $data = Database::query("SELECT usersnew.profile_number AS player_id, IFNULL(steamname, boardname) as displayName FROM usersnew");
    //$data = Database::query("SELECT usersnew.profile_number AS player_id, IFNULL(steamname, boardname) as displayName FROM usersnew WHERE profile_number = 76561198047900528");

    while ($row = $data->fetch_assoc()) {
    User::updateProfileData($row["player_id"]);
    print "Processed profile " . $row["displayName"] . " \n";
    }

    Leaderboard::cacheLeaderboard();
