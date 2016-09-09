<?php

    include(__DIR__ . "/../loader.php");

    $dbData = Database::query("SELECT * FROM changelog");

    $ar = [];
    $numPairs = 0;
    while($row = $dbData->fetch_assoc()) {

        if (!isset($ar[$row["profile_number"]][$row["map_id"]]))
            $numPairs++;

        $ar[$row["profile_number"]][$row["map_id"]] = true;
    }

    $i = 1;
    foreach ($ar as $profileNumber => $mapData) {
        foreach ($mapData as $map => $bool) {
            Leaderboard::resolveScore($profileNumber, $map);
            print_r("Processing id: {$profileNumber}, map: {$map}, progress: {$i} / {$numPairs} \n");
            $i++;
        }
    }



//    $dbData = Database::query("SELECT * FROM changelog WHERE (MOD(score, 10) = 2 OR MOD(score, 10) = 7)");
//
//    $ar = [];
//    $numRows = $dbData->num_rows;
//    $i = 1;
//
//    while ($row = $dbData->fetch_assoc()) {
//        print_r("Processing id: {$row["id"]}, progress: {$i} / {$numRows} \n");
//        Database::query("UPDATE changelog SET banned = 1 WHERE id = {$row["id"]}");
//        Leaderboard::resolveScore($row["profile_number"], $row["map_id"]);
//        $i++;
//    }