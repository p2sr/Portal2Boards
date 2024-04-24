<?php

    include(__DIR__ . "/../loader.php");

    $dbData = Database::query("SELECT * FROM changelog WHERE (map_id = 47804 OR map_id = 47817)");

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
            
            if ($i % 300 == 0) {
                print_r("Processing id: {$profileNumber}, map: {$map}, progress: {$i} / {$numPairs} \n");
            }
            
            $i++;
        }
    }
