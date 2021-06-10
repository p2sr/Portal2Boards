<?php

    include(__DIR__ . "/../loader.php");

    function rankCalculationReliable($date) {
        return ($date != NULL)
            ? strtotime($date) < strtotime("2016-07-20 00:00:00")
            : false;
    }

    function resolvePostRank($id) {

        $dbData = Database::query("SELECT changelog.*, usersnew.banned as playerBanned
                FROM changelog 
                INNER JOIN usersnew on changelog.profile_number = usersnew.profile_number
                WHERE id = {$id}");
        $row = $dbData->fetch_assoc();
        $affected = 0;

        if (rankCalculationReliable($row["time_gained"])) {
            Database::query("UPDATE changelog
                INNER JOIN (
                    SELECT sc.profile_number, sc.score,
                        @rownum := @rownum + 1 as rowNum,
                        IF( @prevScore <> sc.score, @displayRank := @rownum,  @displayRank ) AS player_rank,
                        @prevScore := sc.score
                    FROM (
                        SELECT profile_number, min(score) as score
                        FROM changelog
                        JOIN(
                            SELECT map_id as theMap, time_gained as theDate
                            FROM changelog 
                            WHERE id = {$id}
                        ) as theChange
                        WHERE time_gained <= theDate
                        AND map_id = theMap
                        AND banned = 0
                        AND profile_number IN (SELECT profile_number FROM usersnew WHERE banned = 0)
                        GROUP BY profile_number
                    ) as sc
                    JOIN (SELECT @rownum := 0, @prevScore := 0) AS r
                    ORDER BY sc.score ASC
                ) as ranks on (ranks.profile_number = changelog.profile_number AND ranks.score = changelog.score)
                SET changelog.post_rank = ranks.player_rank
                WHERE changelog.id = {$id}");

            $affected = Database::affectedRows();
        }

        if ($row["banned"] == 1 || $row["playerBanned"] == 1) {
            Database::query("UPDATE changelog 
                      SET post_rank = NULL         
                      WHERE id = {$id}");

            $affected += Database::affectedRows();
        }

        return $affected > 0;
    }

    function resolvePreRank($id) {

        $dbData = Database::query("SELECT changelog.*, usersnew.banned as playerBanned
                FROM changelog 
                INNER JOIN usersnew on changelog.profile_number = usersnew.profile_number
                WHERE id = {$id}");
        $row = $dbData->fetch_assoc();
        $affected = 0;

        if ($row["previous_id"] != NULL && rankCalculationReliable($row["time_gained"])) {


            Database::query("UPDATE changelog
                INNER JOIN (
                    SELECT id, player_rank
                    FROM changelog
                    INNER JOIN (
                        SELECT sc.profile_number, sc.score,
                            @rownum := @rownum + 1 as rowNum,
                            IF( @prevScore <> sc.score, @displayRank := @rownum,  @displayRank ) AS player_rank,
                            @prevScore := sc.score
                        FROM (
                            SELECT profile_number, id, min(score) as score
                            FROM changelog
                            JOIN(
                                SELECT map_id as theMap, time_gained as theDate
                                FROM changelog 
                                WHERE id = {$id}
                            ) as theChange
                            WHERE (time_gained < theDate || id = {$row["previous_id"]})
                                AND map_id = theMap
                                AND banned = 0
                                AND profile_number IN (SELECT profile_number FROM usersnew WHERE banned = 0)
                            GROUP BY profile_number
                        ) as sc
                        JOIN (SELECT @rownum := 0, @prevScore := 0) AS r
                        ORDER BY sc.score ASC
                    ) as ranks on (ranks.profile_number = changelog.profile_number AND ranks.score = changelog.score)
                    WHERE changelog.id = {$row["previous_id"]}
                ) as ranks2 on (ranks2.id = changelog.previous_id)
                SET changelog.pre_rank = ranks2.player_rank");

            $affected += Database::affectedRows();
        }

        if ($row["banned"] == 1 || $row["playerBanned"] == 1) {
            Database::query("UPDATE changelog 
                      SET pre_rank = NULL
                      WHERE id = {$id}");

            $affected += Database::affectedRows();
        }

        return $affected > 0;
    }


    // $dbData = Database::query("UPDATE changelog
    //      SET pre_rank = NULL, post_rank = NULL
    //      WHERE time_gained IS NOT NULL AND time_gained < '2016-07-20 00:00:00'");

    $dbData = Database::query("SELECT * FROM changelog
      WHERE banned = 0 AND profile_number IN (SELECT profile_number FROM usersnew WHERE banned = 0)
      AND time_gained IS NOT NULL  AND time_gained < '2016-07-20 00:00:00'");

     $numRows = mysqli_num_rows($dbData);

     $affectedPostRanks1 = 0;
     $affectedPreRanks1 = 0;
     $i = 1;
     while ($row = $dbData->fetch_assoc()) {
         $id = $row["id"];
         print_r("Processing change ". $row["id"]. " " . $i . "/" . $numRows."\n");

         //post ranks
         $affectedPostRanks1 += resolvePostRank($id);

         //pre ranks
         $affectedPreRanks1 += resolvePreRank($id);

         $i++;
     }



//   $dbData = Database::query("UPDATE changelog
//     SET pre_rank = NULL, post_rank = NULL
//     WHERE time_gained IS NOT NULL AND time_gained <= '2016-09-03 00:00:00' AND time_gained >= '2016-07-20 00:00:00'");
//
//
//   $dbData = Database::query("SELECT * FROM changelog
//     WHERE banned = 0 AND profile_number IN (SELECT profile_number FROM usersnew WHERE banned = 0)
//     AND time_gained IS NOT NULL AND time_gained <= '2016-09-03 00:00:00' AND time_gained >= '2016-07-20 00:00:00'");
//
//   $numRows = mysqli_num_rows($dbData);
//   $affectedPostRanks2 = 0;
//   $affectedPreRanks2 = 0;
//   $i = 1;
//   while ($row = $dbData->fetch_assoc()) {
//       $id = $row["id"];
//       print_r("Processing change ". $row["id"]. " " . $i . "/" . $numRows."\n");
//
//       Database::query("UPDATE changelog
//           INNER JOIN (
//               SELECT sc.profile_number, sc.score,
//                   @rownum := @rownum + 1 as rowNum,
//                   IF (@makeFollowingRanksNull = 1 AND @prevScore <> sc.score, @displayRank := NULL, IF( @prevScore <> sc.score, @displayRank := @rownum,  @displayRank )) AS player_rank,
//                   IF (time_gained IS NULL, @makeFollowingRanksNull := 1, @makeFollowingRanksNull := @makeFollowingRanksNull) as followingRanksNull,
//                   @prevScore := sc.score
//               FROM (
//                 SELECT changelog.score, time_gained, changelog.profile_number
//                 FROM changelog
//                 INNER JOIN (
//                       SELECT profile_number, min(score) as score
//                       FROM changelog
//                       JOIN(
//                           SELECT map_id as theMap, time_gained as theDate
//                           FROM changelog
//                           WHERE id = {$id}
//                       ) as theChange
//                       WHERE (time_gained <= theDate OR time_gained IS NULL OR id = {$id})
//                       AND map_id = theMap
//                       AND banned = 0
//                       AND profile_number IN (SELECT profile_number FROM usersnew WHERE banned = 0)
//                       GROUP BY profile_number
//                   ) as minScores on (minScores.profile_number = changelog.profile_number AND minScores.score = changelog.score)
//                   WHERE map_id = {$row["map_id"]}
//               ) as sc
//               JOIN (SELECT @rownum := 0, @prevScore := 0, @rank := 0, @displayRank := 0, @makeFollowingRanksNull := 0) AS r
//               ORDER BY sc.score ASC
//           ) as ranks on (ranks.profile_number = changelog.profile_number AND ranks.score = changelog.score)
//           SET changelog.post_rank = ranks.player_rank
//           WHERE changelog.id = {$id}");
//
//
//       $affectedPostRanks2 += Database::affectedRows();
//
//       $previousId = $row["previous_id"];
//
//       if ($previousId != NULL) {
//           Database::query("UPDATE changelog
//           INNER JOIN (
//               SELECT id, player_rank
//               FROM changelog
//               INNER JOIN (
//                   SELECT sc.profile_number, sc.score,
//                     @rownum := @rownum + 1 as rowNum,
//                     IF (@makeFollowingRanksNull = 1 AND @prevScore <> sc.score, @displayRank := NULL, IF( @prevScore <> sc.score, @displayRank := @rownum,  @displayRank )) AS player_rank,
//                     IF (time_gained IS NULL, @makeFollowingRanksNull := 1, @makeFollowingRanksNull := @makeFollowingRanksNull) as followingRanksNull,
//                     @prevScore := sc.score
//                   FROM (
//                     SELECT changelog.score, time_gained, changelog.profile_number
//                     FROM changelog
//                     INNER JOIN (
//                           SELECT profile_number, min(score) as score
//                           FROM changelog
//                           JOIN(
//                               SELECT map_id as theMap, time_gained as theDate
//                               FROM changelog
//                               WHERE id = {$id}
//                           ) as theChange
//                           WHERE (time_gained < theDate OR time_gained IS NULL or id = {$previousId})
//                           AND map_id = theMap
//                           AND banned = 0
//                           AND profile_number IN (SELECT profile_number FROM usersnew WHERE banned = 0)
//                           GROUP BY profile_number
//                       ) as minScores on (minScores.profile_number = changelog.profile_number AND minScores.score = changelog.score)
//                       WHERE map_id = {$row["map_id"]}
//                   ) as sc
//                   JOIN (SELECT @rownum := 0, @prevScore := 0, @rank := 0, @displayRank := 0, @makeFollowingRanksNull := 0) AS r
//                   ORDER BY sc.score ASC
//               ) as ranks on (ranks.profile_number = changelog.profile_number AND ranks.score = changelog.score)
//               WHERE changelog.id = {$previousId}
//           ) as ranks2 on (ranks2.id = changelog.previous_id)
//           SET changelog.pre_rank = ranks2.player_rank");
//
//           $affectedPreRanks2 += Database::affectedRows();
//       }
//
//       $i++;
//   }


    echo "Old times - Rows for which we change pre rank: " . $affectedPreRanks1 . "\n";
    echo "Old times - Rows for which we change post rank: " . $affectedPostRanks1 . "\n";

   //echo "Transitional times - Rows for which we change pre rank: " . $affectedPreRanks2 . "\n";
   //echo "Transitional times - Rows for which we change post rank: " . $affectedPostRanks2 . "\n";
