<?php
class Leaderboard
{

    const numTrackedPlayerRanks = 500;
    const rankForPoints = 200;
    const proofBonusPointsPercentage = 0;


    public static function fetchNewData(string $chamber = "")
    {
        Debug::log("Start retrieving rank limits per chamber");
        $rankLimits = self::getRankLimits($chamber);
        Debug::log($rankLimits);
        Debug::log("Finished retrieving rank limits per chamber");

        Debug::log("Start retrieving evidence requirements");
        $evidenceRequirements = self::getEvidenceRequirements();
        Debug::log($evidenceRequirements);
        Debug::log("Finished retrieving evidence requirements");

        Debug::log("Receiving new leaderboard data");
        $newBoardData = self::getNewScores($rankLimits);
        Debug::log("Receiving new leaderboard data done");

        if (!empty($newBoardData)) {
            $oldBoards = self::getBoard(array("chamber" => $chamber));
            self::saveScores($newBoardData, $oldBoards, $evidenceRequirements);
        }
    }

    public static function cacheLeaderboard()
    {
        //TODO: don't cache maps (and other unnecessary stuff) every caching cycle
        //Debug::log("Start caching maps");
        $maps = self::getMaps();
        Cache::set("maps", $maps);
        //Debug::log("Done caching maps");

        $maps = Cache::get("maps");

        //Debug::log("Start caching scores");
        $SPChamberBoard = self::getBoard(array("mode" => "0", "pending" => "0"));
        $COOPChamberBoard = self::getBoard(array("mode" => "1", "pending" => "0"));

        //echo json_encode($SPChamberBoard);

        Cache::set("SPChamberBoard", $SPChamberBoard);
        Cache::set("COOPChamberBoard", $COOPChamberBoard);
        $fullBoard = $SPChamberBoard + $COOPChamberBoard; //TODO: may cause chapter id collisions if data would be organized by mode
        self::cacheChamberBoards($fullBoard);
        //Debug::log("Done caching scores");

        //Debug::log("Start caching points");
        $SPChamberPointBoard = self::makeChamberPointBoard($SPChamberBoard);
        $COOPChamberPointBoard = self::makeChamberPointBoard($COOPChamberBoard);
        Cache::set("SPChamberPointBoard", $SPChamberPointBoard);
        Cache::set("COOPChamberPointBoard", $COOPChamberPointBoard);
        //Debug::log("Done caching points");

        //Debug::log("Start caching point boards");
        $generalSPPointBoard = self::makePointBoard($SPChamberPointBoard);
        $generalCOOPPointBoard = self::makePointBoard($COOPChamberPointBoard);
        $SPPointBoard = $generalSPPointBoard["board"];
        $COOPPointBoard = $generalCOOPPointBoard["board"];
        Cache::set("SPPointBoard", $SPPointBoard);
        Cache::set("COOPPointBoard", $COOPPointBoard);
        Cache::set("globalPointBoard", self::makeGlobalPointBoard($SPPointBoard, $COOPPointBoard, false, false));

        $SPchapterPointBoards = $generalSPPointBoard["chapter"];
        $COOPchapterPointBoards = $generalCOOPPointBoard["chapter"]; //Per chapter caching?
        Cache::set("chapterPointBoards", $SPchapterPointBoards + $COOPchapterPointBoards);
        foreach (array_keys($maps["chapters"]) as $chapter) {
            if (isset($SPchapterPointBoards[$chapter])) {
                Cache::set("chapterPointBoard".$chapter, $SPchapterPointBoards[$chapter]);
            } else if (isset($COOPchapterPointBoards[$chapter])) {
                Cache::set("chapterPointBoard".$chapter, $COOPchapterPointBoards[$chapter]);
            } else {
                Cache::set("chapterPointBoard".$chapter, array());
            }
        }
        //Debug::log("Done caching point boards");

        //Debug::log("Start caching time boards");
        $generalSPTimeBoard = self::makeTimeBoard($SPChamberBoard);
        $generalCOOPTimeBoard = self::makeTimeBoard($COOPChamberBoard);
        $SPTimeBoard = $generalSPTimeBoard["board"];
        $COOPTimeBoard = $generalCOOPTimeBoard["board"];
        $globalTimeBoard = self::makeGlobalPointBoard($SPTimeBoard, $COOPTimeBoard, true, true);
        Cache::set("SPTimeBoard", $SPTimeBoard);
        Cache::set("COOPTimeBoard", $COOPTimeBoard);
        Cache::set("globalTimeBoard", $globalTimeBoard);

        $SPchapterTimeBoards = $generalSPTimeBoard["chapter"];
        $COOPchapterTimeBoards = $generalCOOPTimeBoard["chapter"];
        Cache::set("chapterTimeBoards", $SPchapterTimeBoards + $COOPchapterTimeBoards);
        foreach (array_keys($maps["chapters"]) as $chapter) {
            if (isset($SPchapterTimeBoards[$chapter])) {
                Cache::set("chapterTimeBoard".$chapter, $SPchapterTimeBoards[$chapter]);
            } else if (isset($COOPchapterTimeBoards[$chapter])) {
                Cache::set("chapterTimeBoard".$chapter, $COOPchapterTimeBoards[$chapter]);
            } else {
                Cache::set("chapterTimeBoard".$chapter, array());
            }
        }
        //Debug::log("Done caching time boards");

        //Debug::log("Start caching Youtube IDs");
        $SPids = self::getYoutubeIDs(0);
        $COOPids = self::getYoutubeIDs(1);
        $allIds = $SPids + $COOPids;
        Cache::set("SPyoutubeIDs", $SPids);
        Cache::set("COOPyoutubeIDs", $COOPids);
        foreach (array_keys($maps["chapters"]) as $chapter) {
            foreach ($maps["chapters"][$chapter]["maps"] as $map) {
                if (isset($allIds[$chapter][$map])) {
                    Cache::set("youtubeIDs".$map, $allIds[$chapter][$map]);
                } else {
                    Cache::set("youtubeIDs".$map, array());
                }
            }
        }
        //Debug::log("Done caching Youtube IDs");

        //Debug::log("Start caching user identification data");
        self::cacheProfileURLData();
        //Debug::log("Finished caching user identification data");

        Debug::log("Finished caching");
    }

    // this is dumb as fuck, but sometimes the scores table can get out of sync
    // so i hacked this bullshit in to automate the fix because i was too scared
    // to touch all the other code
    public static function fixupScoresForUser($profile_number)
    {
        Debug::log("Begin score fixup for profile $profile_number");

        $data = Database::unsafe_raw(
            "SELECT steam_id
             FROM maps"
        );

        $maps = array();
        while ($row = $data->fetch_row()) {
            $maps[] = $row[0];
        }

        Debug::log("Fetched " . strval(count($maps)) . " maps");

        foreach ($maps as $steam_id) {
            Debug::log("Resolving score for map $steam_id");
            self::resolveScore(strval($profile_number), strval($steam_id));
        }

        Debug::log("Fixed up scores for profile $profile_number");
    }

    //TODO: generalize map list to id's instead of steam time id's
    public static function getMaps()
    {
        $data = Database::unsafe_raw(
            "SELECT maps.id
                  , steam_id, is_coop
                  , name, chapter_id
                  , chapters.chapter_name
                  , is_public
                  , lp_id
             FROM maps
             INNER JOIN chapters ON maps.chapter_id = chapters.id
             ORDER BY is_coop
                    , maps.id"
        );
    
        while ($row = $data->fetch_assoc()) {
            if ($row["is_coop"] == 1) {
                $mode = "coop";
            } else {
                $mode = "sp";
            }
            $maps["modes"][$mode][$row["chapter_id"]] = $row["chapter_id"];

            $maps["chapters"][$row["chapter_id"]]["chapterName"] = $row["chapter_name"];
            $maps["chapters"][$row["chapter_id"]]["maps"][] = $row["steam_id"];

            $maps["maps"][$row["steam_id"]]["isPublic"] = $row["is_public"];
            $maps["maps"][$row["steam_id"]]["mapName"] = $row["name"];
            $maps["maps"][$row["steam_id"]]["chapterId"] = $row["chapter_id"];

            if ($row["lp_id"] != NULL) {
                $maps["lpMaps"][$row["lp_id"]] = $row["steam_id"];
            }

        }
        return $maps;
    }

    public static function convertToTime($time)
    {
        if ($time != NULL) {
            $time = abs($time);
            if (strlen($time) > 2) {
                $reversed = strrev($time);
                $miliseconds = strrev(substr($reversed, 0, 2));
                $rest_of_it = strrev(substr($reversed, 2, 6));
                $minutes = floor($rest_of_it / 60);
                if ($minutes > 0) {
                    $correct_seconds = $rest_of_it - (60 * $minutes);
                    if ($correct_seconds < 10) {
                        $correct_seconds = "0" . $correct_seconds;
                    }
                    $time = $minutes . ":" . $correct_seconds . "." . $miliseconds;
                } else {
                    $time = $rest_of_it . "." . $miliseconds;
                }
            } else {
                if (strlen($time) == 1) {
                    $time = "0.0" . $time;
                } else {
                    $time = "0." . $time;
                }
            }
        }
        return $time;
    }

    public static function getNewScores($rankLimits = array())
    {
        $leaderboard = array();
        $xml_total = 0;
        
        $badConnection = false;
        $mapsHandled = 0;

        $rankLimits = Util::shuffle_assoc($rankLimits);

        foreach ($rankLimits as $mapID => $amount) {

            $handle = curl_init();
            curl_setopt($handle, CURLOPT_URL, "https://steamcommunity.com/stats/Portal2/leaderboards/" . $mapID . "?xml=1&start=1&end=" . $amount . "&time=" . time());
            curl_setopt($handle, CURLOPT_FRESH_CONNECT, TRUE);
            curl_setopt($handle, CURLOPT_HEADER, 0);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_HTTPHEADER, array(
                'Connection: Keep-Alive',
                'Keep-Alive: 10',
                "Cache-Control: no-cache"
            ));
            curl_setopt($handle, CURLOPT_TIMEOUT, 10);
            curl_setopt($handle, CURLOPT_DNS_CACHE_TIMEOUT, 10);

            $xmlContent = curl_exec($handle);
            $http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

            if ($xmlContent && $http_code == 200) {

                $xml = microtime(true);

                try {
                    $mapLeaderboard = simplexml_load_string(utf8_encode($xmlContent));
                } 
                catch (Exception $e) {
                    throw new Exception("SimpleXML error: " . $e);
                }

                libxml_use_internal_errors(true);
                
                $sxe = simplexml_load_string($mapLeaderboard);
                if ($sxe === false) {
                    foreach (libxml_get_errors() as $error) {
                        throw new Exception ("SimpleXML error: " . $error->message . '\n');
                    }
                }

                if (count($mapLeaderboard->entries) == 0) {
                    Debug::log("No leaderboard data found for chamber: " . $mapID);
                } else {
                    foreach ($mapLeaderboard->entries as $key2 => $val2) {
                        
                        //Debug::log(count($val2) . " entries fetched for chamber: " . $mapID);
                        
                        foreach ($val2 as $d => $b) {
                            $steamid = $b->steamid;
                            $score = $b->score;
                            $leaderboard[$mapID][(string)$steamid] = (string)$score;
                            //Debug::log("map ID: " . $mapID . " player steam id: " . $steamid . " score: " . $score);
                        }
                    }

                    //Debug::log("Successfully fetched scores for map: " . $mapID);

                    $tt = microtime(true) - $xml;
                    $xml_total = $xml_total + $tt;
                    $mapsHandled++;
                }
            } else {
                Debug::log("Can't fetch scores for map: " . $mapID . ". HTTP code: " . $http_code);

                if ($http_code == 0) {
                    $badConnection = true;
                }
            }

            curl_close($handle);

            if ($badConnection) {
                Debug::log("Bad connection detected. Skipping all other maps.");
                break;
            }

            $sleepSeconds = (0.5 + (rand(0, 2000) / 1000));
            usleep($sleepSeconds * 1000000);
        }

        Debug::log("Maps handled: " . $mapsHandled);

        return $leaderboard;
    }

    public static function saveScores($newScores, $oldBoards, $evidenceRequirements)
    {
        $maps = self::getMaps();
        $changes = array();
        $highestEvidenceRank = !empty($evidenceRequirements) ? max(array_column($evidenceRequirements, 'rank')) : false;

        Debug::log("Saving new leaderboard data");

        $db_data = Database::unsafe_raw(
            "SELECT id
                  , profile_number
                  , score
                  , map_id
             FROM changelog"
        );

        $oldChangelog = array();
        while ($row = $db_data->fetch_assoc()) {
            $oldChangelog[$row["map_id"]][$row["profile_number"]][$row["score"]] = true; //true has no meaning
        }
        Debug::log("Obtained current scores");

        $users = User::getAllUserData();
        Debug::log("Obtained all current users");

        $userInsertions = array();
        $scoreUpdates = array();
        $scoreInsertions = array();
        foreach ($newScores as $chamber => $chamber_val) {
            foreach ($chamber_val as $player => $score) {
                if (!isset($users[$player]) && !isset($userInsertions[$player])) {
                    $userInsertions[$player] = true;
                }

                $change = array();
                $chapter = $maps["maps"][$chamber]["chapterId"];

                $freshMapScore = !isset($oldChangelog[$chamber][$player]);
                $newChange = !isset($oldChangelog[$chamber][$player][$score]);
                $improvement = isset($oldBoards[$chapter][$chamber][$player]) ? $score < $oldBoards[$chapter][$chamber][$player]["scoreData"]["score"] : true;

                if ($freshMapScore) {
                    Debug::log("Fresh map score found. Player: ".$player." Map: ".$chamber." Score: ".$score);
                    $change["profileNumber"] = $player;
                    $change["score"] = $score;
                    $change["mapId"] = $chamber;
                    $scoreInsertions[] = $change;
                } else if ($newChange && $improvement) {
                    Debug::log("Updated map score found. Player: ".$player." Map: ".$chamber." Score: ".$score);
                    $change["profileNumber"] = $player;
                    $change["score"] = $score;
                    $change["mapId"] = $chamber;
                    $scoreUpdates[] = $change;
                }
            }
        }

        Debug::log("Inserting new users");

        foreach (array_keys($userInsertions) as $profile_number) {
            Database::query(
                "INSERT INTO usersnew (
                    profile_number
                 ) VALUES (
                    ?
                 )",
                "s",
                [
                    $profile_number,
                ]
            );
        }

        $userInsertionsKeys = array_keys($userInsertions);
        $userInsertionsTotal = count($userInsertionsKeys);
        Debug::log("Processing profiles: $userInsertionsTotal");
        $userInsertionsCount = 0;

        foreach (array_chunk($userInsertionsKeys, 100) as $chunk) {
            [$success, $failed] = User::updateProfiles($chunk);

            $userInsertionsCount += $success;

            foreach ($failed as $steamId) {
                Debug::log("Failed to update profile $steamId");
            }

            Debug::log("Processed $userInsertionsCount/$userInsertionsTotal");
        }

        Debug::log("Finished inserting new users");

        Debug::log("Starting saving changelog entries");

        $updates = 0;
        foreach ($scoreInsertions + $scoreUpdates as $change) {
            $chapter = $maps["maps"][$change["mapId"]]["chapterId"];
            $mapData = $oldBoards[$chapter][$change["mapId"]];

            $wr = 0;
            $diff = 0;
            $keys = array_keys($mapData);
            if ($change["score"] <= $mapData[$keys[0]]["scoreData"]["score"]) {
                $wr = 1;
                $diff = abs($change["score"] - $mapData[$keys[0]]["scoreData"]["score"]);
            }

            $previousId = isset($oldBoards[$chapter][$change["mapId"]][$change["profileNumber"]])
                ? $oldBoards[$chapter][$change["mapId"]][$change["profileNumber"]]["scoreData"]["changelogId"]
                : "NULL";
            $preRank = isset($oldBoards[$chapter][$change["mapId"]][$change["profileNumber"]])
                ? $oldBoards[$chapter][$change["mapId"]][$change["profileNumber"]]["scoreData"]["playerRank"]
                : "NULL";

            Debug::log("Inserting change. Player: ".$change["profileNumber"]." Map: ".$change["mapId"]." Score: ".$change["score"]);

            Database::query(
                "INSERT INTO changelog(
                    id
                  , profile_number
                  , score
                  , map_id
                  , wr_gain
                  , previous_id
                  , pre_rank
                 ) VALUES (
                    NULL
                  , ?
                  , ?
                  , ?
                  , ?
                  , ?
                  , ?",
                "sisiii",
                [
                    $change["profileNumber"],
                    $change["score"],
                    $change["mapId"],
                    $wr,
                    $previousId,
                    $preRank,
                ]
            );

            $id = Database::getMysqli()->insert_id;
            $changes[$id] = $change;

            Database::query(
                "INSERT IGNORE INTO scores(
                    profile_number
                  , map_id
                  , changelog_id
                 ) VALUES (
                    ?
                  , ?
                  , ?
                 )",
                "ssi",
                [
                    $change["profileNumber"],
                    $change["mapId"],
                    $id,
                ]
            );

            Database::query(
                "UPDATE scores
                 SET changelog_id = ?
                 WHERE profile_number = ?
                 AND map_id = ?",
                "iss",
                [
                    $id,
                    $change["profileNumber"],
                    $change["mapId"],
                ]
            );

            $updates++;
        }

        $newBoards = self::getBoard();
        Debug::log("Highest Evidence Rank: ".$highestEvidenceRank);
        foreach ($changes as $id => $change) {
            Debug::log("Updating change for id: ".$id);
            $chapter = $maps["maps"][$change["mapId"]]["chapterId"];
            $postRank = isset($newBoards[$chapter][$change["mapId"]][$change["profileNumber"]])
                ? $newBoards[$chapter][$change["mapId"]][$change["profileNumber"]]["scoreData"]["playerRank"]
                : "NULL";

            $pending = 0;
            if ($postRank != "NULL" && $postRank <= $highestEvidenceRank) {
                $pending = 1;
            }

            Debug::log("Pending: ".$pending);
            Debug::log("Updating rank of new changelog entry. Player: ".$change["profileNumber"]." Map: ".$change["mapId"]." Score: ".$change["score"]." Rank: ".$postRank." Pending: ".$pending);

            Database::query(
                "UPDATE changelog
                 SET post_rank = ?
                   , pending = ?
                 WHERE id = ?",
                "iii",
                [
                    $postRank,
                    $pending,
                    $id,
                ]
            );

            if ($pending) {
                Debug::log("Reseting resolved score back to previous value");
                self::resolveScore(strval($change["profileNumber"]), strval($change["mapId"]));
            }
        }

        Debug::log("Finished saving changelog entries");
        
        echo "processed " . $updates . " updates\n";
    }

    //TODO: use cache for determining the limits
    //TODO: cleaner and more extensible parameters
    public static function getRankLimits(string $chamber = "")
    {
        $rankLimits = array();

        $query = "SELECT maps.steam_id
                       , IFNULL(scorecount, 0) AS cheatedScoreAmount
                  FROM maps
                  LEFT JOIN (
                      SELECT scores.map_id
                          , COUNT(scores.changelog_id) AS scorecount
                      FROM scores
                      INNER JOIN changelog ON scores.changelog_id = changelog.id
                      INNER JOIN usersnew ON scores.profile_number = usersnew.profile_number
                      WHERE (
                          changelog.banned = 1
                          OR usersnew.banned = 1
                      )
                      GROUP BY scores.map_id
                  ) as scores1
                  ON scores1.map_id = maps.steam_id
                  WHERE maps.is_public = 1";

        $data = $chamber !== ""
            ? Database::query(
                "$query AND maps.steam_id = ?",
                "s",
                [
                    $chamber,
                ]
            )
            : Database::unsafe_raw($query);

        while ($row = $data->fetch_assoc()) {
            $rankLimits[$row["steam_id"]] = $row["cheatedScoreAmount"];
        }

        foreach ($rankLimits as $map => $amount) {
            $rankLimits[$map] += self::numTrackedPlayerRanks;
        }

        return $rankLimits;
    }

    //TODO: remove limitation on characters used in parameters
    //TODO: remove indexing by chapter id. Chamber id is sufficient.
    public static function getBoard($unsafe_parameters = array())
    {
        $param = array("chamber" => "" , "mode" => "", "pending" => "");

        foreach ($unsafe_parameters as $key => $val) {
            if (array_key_exists($key, $param)) {
                $param[$key] = preg_replace("/[^a-zA-Z0-9]+\s/", "", $unsafe_parameters[$key]);
            }
        }

        /**
         * ranks.profile_number, u.avatar, IFNULL(u.boardname, u.steamname) as boardname,
         * chapters.id as chapterid, maps.steam_id as mapid,
        * ranks.profile_number, ranks.changelog_id, ranks.score, ranks.player_rank, ranks.score_rank, DATE_FORMAT(ranks.time_gained, '%Y-%m-%dT%TZ') as date, has_demo, youtube_id, ranks.note,
         *        ranks.submission, ranks.pending, ranks.autorender_id
*             FROM usersnew as u
  *           JOIN (
    *             SELECT sc.changelog_id, sc.profile_number, sc.score, sc.map_id, sc.time_gained, sc.has_demo, sc.youtube_id, sc.submission, sc.note, sc.pending
      *                , sc.autorender_id
        *              , RANK() OVER (PARTITION BY sc.map_id ORDER BY sc.score) as player_rank
          *            , DENSE_RANK() OVER (PARTITION BY sc.map_id ORDER BY sc.score) as score_rank
            *     FROM (
              *       SELECT changelog.submission, scores.changelog_id, scores.profile_number, scores.map_id, changelog.score, changelog.time_gained, changelog.youtube_id, changelog.has_demo, changelog.note, changelog.pending
                *          , changelog.autorender_id
         */

        $query = Database::query(
            "SELECT ranks.profile_number
                  , u.avatar
                  , IFNULL(u.boardname, u.steamname) as boardname
                  , chapters.id as chapterid
                  , maps.steam_id as mapid
                  , ranks.profile_number
                  , ranks.changelog_id
                  , ranks.score
                  , ranks.player_rank
                  , ranks.score_rank
                  , DATE_FORMAT(ranks.time_gained, '%Y-%m-%dT%TZ') as date
                  , has_demo
                  , youtube_id
                  , ranks.note
                  , ranks.submission
                  , ranks.pending
                  , ranks.autorender_id
             FROM usersnew as u
             JOIN (
                SELECT sc.changelog_id
                     , sc.profile_number
                     , sc.score
                     , sc.map_id
                     , sc.time_gained
                     , sc.has_demo
                     , sc.youtube_id
                     , sc.submission
                     , sc.note
                     , sc.pending
                     , sc.autorender_id
                     , RANK() OVER (PARTITION BY sc.map_id ORDER BY sc.score) as player_rank
                     , DENSE_RANK() OVER (PARTITION BY sc.map_id ORDER BY sc.score) as score_rank
                FROM (
                    SELECT changelog.submission
                         , scores.changelog_id
                         , scores.profile_number
                         , scores.map_id
                         , changelog.score
                         , changelog.time_gained
                         , changelog.youtube_id
                         , changelog.has_demo
                         , changelog.note
                         , changelog.pending
                         , changelog.autorender_id
                    FROM scores
                    INNER JOIN changelog ON scores.changelog_id = changelog.id
                    WHERE scores.profile_number IN (
                        SELECT profile_number
                        FROM usersnew
                        WHERE banned = 0
                    )
                    AND scores.map_id IN (
                        SELECT steam_id
                        FROM maps
                        WHERE is_coop LIKE ?
                        AND steam_id LIKE ?
                    )
                    AND changelog.banned = 0
                    AND changelog.pending LIKE ?
                ) as sc
                ORDER BY sc.map_id
                       , sc.score
                       , sc.time_gained
                       , sc.profile_number ASC
             ) as ranks ON u.profile_number = ranks.profile_number
             JOIN maps ON ranks.map_id = maps.steam_id
             JOIN chapters ON maps.chapter_id = chapters.id
             AND player_rank <= ?
             ORDER BY map_id
                    , score
                    , time_gained
                    , profile_number ASC",
            "sssi",
            [
                "%{$param["mode"]}%",
                "%{$param["chamber"]}",
                "%{$param["pending"]}%",
                self::numTrackedPlayerRanks,
            ]
        );

        $board = array();
        while ($row = $query->fetch_assoc()) {
            $profileNumber = $row["profile_number"];
            $chapterId = $row["chapterid"];
            $mapId = $row["mapid"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["note"] = $row["note"] != NULL ? htmlspecialchars($row["note"]) : NULL;
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["submission"] = $row["submission"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["changelogId"] = $row["changelog_id"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["playerRank"] = $row["player_rank"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["scoreRank"] = $row["score_rank"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["score"] = $row["score"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["date"] = $row["date"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["hasDemo"] = $row["has_demo"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["youtubeID"] = $row["youtube_id"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["pending"] = $row["pending"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["autorender_id"] = $row["autorender_id"];
            $board[$chapterId][$mapId][$profileNumber]["userData"]["boardname"] = htmlspecialchars($row["boardname"]);
            $board[$chapterId][$mapId][$profileNumber]["userData"]["avatar"] = $row["avatar"];
        }

        return $board;
    }

    public static function cacheChamberBoards($board) {
        foreach ($board as $chapter => $chapterData) {
            foreach ($chapterData as $map => $mapData) {
                Cache::set("chamberBoard" . $map, $board[$chapter][$map]);
            }
        }
    }

    //TODO: remove limitation on characters used in parameters
    //TODO: replace day amount with date range
    //TODO: allow for fetching scores of banned players
    //TODO: clean up ugly where clauses
    public static function getChangelog($unsafe_parameters = array())
    {
        $param = array(
            "chamber" => "",
            "chapter" => "",
            "boardName" => "",
            "profileNumber" => "",
            "type" => "",
            "sp" => "1",
            "coop" => "1",
            "wr" => "",
            "demo" => "",
            "yt" => "",
            "maxDaysAgo" => "",
            "hasDate" => "",
            "startDate" => "",
            "endDate" => "",
            "startRank" => "",
            "endRank" => "",
            "submission" => "",
            "banned" => "",
            "pending" => "2",
            "id" => "");

        foreach ($unsafe_parameters as $key => $val) {
            if (array_key_exists($key, $param)) {
                $param[$key] = preg_replace("/[^a-zA-Z0-9]+\s/", "", $unsafe_parameters[$key]);
            }
        }

        $whereClause = "";
        $whereTypes = "";
        $whereParams = [];

        // Time Span of leaderboard
        if ($param['startDate'] != "") {
            $whereClause .= "time_gained >= DATE(?) AND ";
            $whereTypes .= "s";
            $whereParams[] = $param['startDate'];
        } else {
            if ($param['maxDaysAgo'] != "") {
                $whereClause .= "time_gained >= DATE_SUB(CONCAT(CURDATE(), ' ', '00:00:00'), INTERVAL ? DAY) AND ";
                $whereTypes .= "i";
                $whereParams[] = $param['maxDaysAgo'];
            }
        }
        if ($param['endDate'] != "") {
            $whereClause .= "time_gained <= DATE(?) AND ";
            $whereTypes .= "s";
            $whereParams[] = $param['endDate'];
        }

        // Ranks
        if ($param['startRank'] != "") {
            $whereClause .= "post_rank >= ? AND ";
            $whereTypes .= "i";
            $whereParams[] = $param['startRank'];
        }
        if ($param['endRank'] != "") {
            $whereClause .= "post_rank <= ? AND ";
            $whereTypes .= "i";
            $whereParams[] = $param['endRank'];
        }

        if ($param['yt'] != "") {
            if ($param['yt'] == "1") {
                $whereClause .= "youtube_id IS NOT NULL AND ";
            } else if ($param['yt'] == "0") {
                $whereClause .= "youtube_id IS NULL AND ";
            }
        }

        if ($param['submission'] != "") {
            // retrieve both manual and automatic submissions
            $whereClause .= "submission != 0 AND ";
        }

        $whereClause .= (($param["hasDate"] == "1") ? "time_gained IS NOT NULL AND " : "");

        if ($param["wr"] != "") {
            $whereClause .= "wr_gain = ? AND ";
            $whereTypes .= "i";
            $whereParams[] = $param["wr"];
        }

        if ($param["banned"] != "") {
            $whereClause .= "banned = ? AND ";
            $whereTypes .= "i";
            $whereParams[] = $param["banned"];
        }

        if ($param["id"] != "") {
            $whereClause .= "id = ? AND ";
            $whereTypes .= "i";
            $whereParams[] = $param["id"];
        }

        if ($param["pending"] != "") {
            if ($param['pending'] == "0") { // None Pending
                $whereClause .= "pending = 0 AND ";
            } else if ($param['pending'] == "1") { // Just Pending
                $whereClause .= "pending = 1 AND ";
            } else if ($param['pending'] == "2") { // Both Pending and none Pending
                $whereClause .= "pending >= 0 AND ";
            }
        } else {
            $whereClause .= "pending >= 0 AND ";
        }

        $changelog_data = Database::query(
            "SELECT IFNULL(usersnew.boardname, usersnew.steamname) AS player_name
                  , usersnew.avatar
                  , ch.profile_number
                  , ch.score
                  , ch.id
                  , ch.pre_rank
                  , ch.post_rank
                  , ch.wr_gain
                  , DATE_FORMAT(ch.time_gained, '%Y-%m-%dT%TZ') as time_gained
                  , ch.has_demo as hasDemo
                  , ch.youtube_id as youtubeID
                  , ch.note
                  , ch.banned
                  , ch.submission
                  , ch.pending
                  , ch.autorender_id
                  , ch_previous.score as previous_score
                  , maps.name as chamberName
                  , chapters.id as chapterId
                  , maps.steam_id AS mapid
            FROM (
                SELECT *
                FROM changelog
                WHERE $whereClause
                map_id LIKE ? 
                AND profile_number LIKE ?
                AND has_demo LIKE ?
                ORDER BY time_gained DESC, score ASC, profile_number ASC
                ) as ch
                LEFT JOIN changelog as ch_previous ON (ch_previous.id = ch.previous_id)
                INNER JOIN usersnew ON ch.profile_number = usersnew.profile_number
                INNER JOIN maps ON ch.map_id = maps.steam_id
                INNER JOIN chapters ON maps.chapter_id = chapters.id
                WHERE  usersnew.banned = 0
                AND maps.is_coop LIKE ?
                AND chapters.id LIKE ?
                AND IFNULL(usersnew.boardname, usersnew.steamname) LIKE ?
                ORDER BY time_gained DESC, score ASC, profile_number ASC",
            "{$whereTypes}ssssss",
            [
                ...$whereParams,
                "%{$param['chamber']}%",
                "%{$param['profileNumber']}%",
                "%{$param['demo']}%",
                "%{$param['type']}%",
                "%{$param['chapter']}%",
                "%{$param['boardName']}%",
            ],
        );

        $changelog = array();
        while ($row = $changelog_data->fetch_assoc()) {
            $row["player_name"] = htmlspecialchars($row["player_name"]);
            $row["note"] = $row["note"] != NULL ? htmlspecialchars($row["note"]) : NULL;

            $row["improvement"] = null;
            $row["rank_improvement"] = null;

            $row["pre_points"] = null;
            $row["post_point"] = null;
            $row["point_improvement"] = null;

            if ($row["previous_score"] != NULL) {
                $row["improvement"] = ($row["previous_score"] - $row["score"]);
            }
            if ($row["pre_rank"] != NULL && $row["post_rank"] != NULL) {
                $row["rank_improvement"] = ($row["pre_rank"] - $row["post_rank"]);
                // $row["pre_points"] = self::getPoints($row["pre_rank"]);
                // $row["post_points"] = self::getPoints($row["post_rank"]);
                //$row["point_improvement"] = $row["post_points"] - $row["pre_points"];
            }
            $changelog[] = $row;
        }

        return $changelog;
    }

    public static function getChange($id) {
        $changelog = self::getChangelog(array("id" => $id));
        return $changelog[0];
    }

    public static function getYoutubeIDs(int $mode) {
        $data = Database::query(
            "SELECT changelog.profile_number as profileNumber
                  , score
                  , map_id as mapId
                  , youtube_id as youtubeID
                  , maps.chapter_id
                  , IFNULL(usersnew.boardname, usersnew.steamname) AS player_name
             FROM changelog
             INNER JOIN usersnew ON changelog.profile_number = usersnew.profile_number
             INNER JOIN maps ON changelog.map_id = maps.steam_id
             WHERE changelog.banned = 0
             AND usersnew.banned = 0
             AND maps.is_coop = ?
             AND youtube_id IS NOT NULL
             ORDER BY map_id
                    , score
                    , time_gained
                    , changelog.profile_number ASC",
            "i",
            [
                $mode,
            ]
        );

        $youtubeIDs = array();
        while ($row = $data->fetch_assoc()) {
            $youtubeIDs[$row["chapter_id"]][$row["mapId"]][] = $row;
        }

        return $youtubeIDs;
    }

    public static function makeChamberPointBoard($board)
    {
        foreach ($board as $chapter => $chapterData) {
            foreach ($chapterData as $map => $mapData) {
                foreach ($mapData as $user => $userScoreData) {
                    $pointBoard[$chapter][$map][$user]["userData"] = $userScoreData["userData"];

                    $points = self::getPoints($userScoreData["scoreData"]["playerRank"]);

                    $bonusPoints = 0;
                    if ($userScoreData["scoreData"]["youtubeID"] != NULL || $userScoreData["scoreData"]["hasDemo"] != 0)
                        $bonusPoints = (self::proofBonusPointsPercentage / 100) * $points;

                    $pointBoard[$chapter][$map][$user]["scoreData"]["score"] = $points + $bonusPoints;
                }
            }
        }
        return $pointBoard;
    }

    public static function getPoints($rank) {
        if ($rank > Leaderboard::rankForPoints) {
            return 0;
        }
        return max(1, pow(Leaderboard::rankForPoints - ($rank - 1), 2) / Leaderboard::rankForPoints);
    }

    //TODO: combine sorting functions
    public static function descScoreSort($a, $b) {
        $scoreA = $a["scoreData"]["score"];
        $scoreB = $b["scoreData"]["score"];
        if ($scoreA == $scoreB) {
            return 0;
        } else {
            return ($scoreA < $scoreB) ? 1 : -1;
        }
    }

    public static function ascScoreSort($a, $b) {
        $scoreA = $a["scoreData"]["score"];
        $scoreB = $b["scoreData"]["score"];
        if ($scoreA == $scoreB) {
            return 0;
        } else {
            return ($scoreA > $scoreB) ? 1 : -1;
        }
    }

    public static function makePointBoard($board)
    {
        foreach ($board as $chapter => $chapterData) {
            foreach ($chapterData as $map => $mapData) {
                foreach ($mapData as $player => $playerData) {
                    $points["board"][$player]["scoreData"]["score"] =
                        (isset($points["board"][$player]["scoreData"]["score"]))
                            ? ($points["board"][$player]["scoreData"]["score"] + $playerData["scoreData"]["score"])
                            : $playerData["scoreData"]["score"];

                    $points["chapter"][$chapter][$player]["scoreData"]["score"] =
                        (isset($points["chapter"][$chapter][$player]["scoreData"]["score"]))
                            ? ($points["chapter"][$chapter][$player]["scoreData"]["score"] + $playerData["scoreData"]["score"])
                            : $playerData["scoreData"]["score"];

                    $points["board"][$player]["userData"] = $mapData[$player]["userData"]; //is perhaps a bit redundant
                    $points["chapter"][$chapter][$player]["userData"] = $mapData[$player]["userData"];
                }
            }
        }

        foreach ($points["chapter"] as $chapter => $profileNumber) {
            uasort($points["chapter"][$chapter], array("Leaderboard", "descScoreSort"));
            $points["chapter"][$chapter] = self::roundBoardScores($points["chapter"][$chapter]);
            $points["chapter"][$chapter] = self::calculateRanking($points["chapter"][$chapter]);
        }
        uasort($points["board"], array("Leaderboard", "descScoreSort"));
        $points["board"] = self::roundBoardScores($points["board"]);
        $points["board"] = self::calculateRanking($points["board"]);

        return $points;
    }

    public static function roundBoardScores($board) {
        foreach ($board as $profileNumber => $playerData) {
            $board[$profileNumber]["scoreData"]["score"] = round($playerData["scoreData"]["score"]);
        }
        return $board;
    }

    public static function makeGlobalPointBoard($SPScoreBoard, $COOPScoreBoard, $overlap, $ascending)
    {
        $scoreBoard = array();
        foreach ($COOPScoreBoard as $player => $playerData) {
            if (isset($SPScoreBoard[$player])) {
                $scoreBoard[$player] = $playerData;
            } else {
                if (!$overlap) {
                    $scoreBoard[$player] = $playerData;
                }
            }
        }

        foreach ($SPScoreBoard as $player => $playerData) {
            if (isset($COOPScoreBoard[$player])) {
                $oldScore = $scoreBoard[$player]["scoreData"]["score"];
                $scoreBoard[$player] = $playerData;
                $scoreBoard[$player]["scoreData"]["score"] = $oldScore + $playerData["scoreData"]["score"];
            } else {
                if (!$overlap) {
                    $scoreBoard[$player] = $playerData;
                }
            }
        }

        $scoreBoard = self::roundBoardScores($scoreBoard);

        if ($ascending) {
            uasort($scoreBoard, array("Leaderboard", "ascScoreSort"));
        } else {
            uasort($scoreBoard, array("Leaderboard", "descScoreSort"));
        }
        $scoreBoard = self::calculateRanking($scoreBoard);
        return $scoreBoard;
    }

    public static function makeTimeBoard($board) {
        $mapScoreMissing = array();
        $chapterScoreMissing = array();
        $times = array();

        foreach ($board as $chapter => $chapterData) {
            $hasMapTime = array();
            foreach (array_keys($mapScoreMissing) as $user) {
                unset($mapScoreMissing[$user]);
            }

            foreach ($chapterData as $map => $mapData) {
                $mapKeys = array_keys($chapterData);
                $isFirstMap = ($map == $mapKeys[0]);

                foreach (array_keys($hasMapTime) as $user) {
                    $hasMapTime[$user] = false;
                }

                foreach($mapData as $profileNumber => $profileData) {
                    if (!isset($times["chapter"][$chapter][$profileNumber])) {
                        $times["chapter"][$chapter][$profileNumber]["userData"] = $profileData["userData"];
                        $times["chapter"][$chapter][$profileNumber]["scoreData"]["score"] = 0;
                    }
                    $times["chapter"][$chapter][$profileNumber]["scoreData"]["score"] += $profileData["scoreData"]["score"];

                    $hasMapTime[$profileNumber] = true;
                    if ($isFirstMap) {
                        $hasTimeOnFirstMap[$profileNumber] = true;  //true has no meaning here. We are just setting the variable
                    }
                }

                foreach (array_keys($hasMapTime + $hasTimeOnFirstMap) as $user) {
                    $contiguousMapScoreSequence = isset($hasMapTime[$user]) ? $hasMapTime[$user] : false;
                    if (!$contiguousMapScoreSequence || !isset($hasTimeOnFirstMap[$user])) {
                        $mapScoreMissing[$user] = true;         //true has no meaning here. We are just setting the variable
                        $chapterScoreMissing[$user] = true;     //true has no meaning here. We are just setting the variable
                    }
                }
            }
            foreach (array_keys($hasMapTime) as $user) {
                if (isset($mapScoreMissing[$user])) {
                    unset($times["chapter"][$chapter][$user]);
                }
                if (!isset($chapterScoreMissing[$user])) {
                    if (!isset($times["board"][$user])) {
                        $times["board"][$user]["userData"] = $times["chapter"][$chapter][$user]["userData"];
                        $times["board"][$user]["scoreData"]["score"] = 0;
                    }
                    $times["board"][$user]["scoreData"]["score"] += $times["chapter"][$chapter][$user]["scoreData"]["score"];
                } else {
                    unset($times["board"][$user]);
                }
            }
        }

        foreach (array_keys($chapterScoreMissing) as $user) {
            unset($times["board"][$user]);
        }

        foreach ($times["chapter"] as $chapter => $profileNumber) {
            uasort($times["chapter"][$chapter], array("Leaderboard", "ascScoreSort"));
            $times["chapter"][$chapter] = self::calculateRanking($times["chapter"][$chapter]);
        }
        uasort($times["board"], array("Leaderboard", "ascScoreSort"));
        $times["board"] = self::calculateRanking($times["board"]);

        return $times;
    }

    public static function calculateRanking($sortedBoard) {
        $boardWithNewRanks = array();
        $keys = array_keys($sortedBoard);
        $rank = 1;
        $entryNum = 1;
        $displayRank = 1;
        foreach ($keys as $index => $profileNumber) {
            $score = $sortedBoard[$profileNumber]["scoreData"]["score"];
            if ($index > 0) {
                if ($score != $sortedBoard[$keys[$index - 1]]["scoreData"]["score"]) {
                    $rank++;
                    $displayRank = $entryNum;
                }
            }
            $entryNum++;
            $boardWithNewRanks[$profileNumber]["userData"] = $sortedBoard[$profileNumber]["userData"];
            $boardWithNewRanks[$profileNumber]["scoreData"]["score"] = $score;
            $boardWithNewRanks[$profileNumber]["scoreData"]["playerRank"] = $displayRank;
            $boardWithNewRanks[$profileNumber]["scoreData"]["scoreRank"] = $rank;
        }
        return $boardWithNewRanks;
    }

    public static function cacheProfileURLData()
    {
        $data = Database::unsafe_raw(
            "SELECT boardname AS nickname
                  , profile_number
             FROM usersnew"
        );

        $profileNumbers = [];
        $nicknames = [];

        while ($row = $data->fetch_assoc()) {
            $nickname = str_replace(" ", "", $row["nickname"]);
            if (strlen($row["nickname"])) {
                $nicknames[$row["profile_number"]] = $nickname;
                $profileNumbers[strtolower($nickname)] = $row["profile_number"];
            }
        }

        Cache::set("boardnames", $nicknames);
        Cache::set("profileNumbers", $profileNumbers);
    }

    public static function setDemo(int $changelogId, int $hasDemo) {
        Debug::log("Setting Demo for changelog id: ".$changelogId);
        $change = self::getChange($changelogId);
        $pending = $hasDemo ? 0 : self::isPendingRequired($changelogId, isset($change['youtubeID']) ? 1 : 0);
        $profile_number = strval($change['profile_number']);
        $map_id = strval($change['mapid']);

        Debug::log("HadDemo: ".$hasDemo." Pending: ".$pending." profile_number: ".$profile_number." map_id: ".$map_id);


        Database::query(
            "UPDATE changelog
             SET has_demo = ?
               , pending = ?
             WHERE changelog.id = ?",
            "iii",
            [
                $hasDemo,
                $pending,
                $changelogId,
            ]
        );

        if (self::isBest($profile_number, $map_id, $changelogId) && $hasDemo == 1) {
            // TODO - Check on removed if we need to go back to old value and sent as pending
            Debug::log("Is latest");
            self::wrCheck($changelogId);
            self::setScoreTable($profile_number, $map_id, $changelogId);
        }
        if ($hasDemo == 0) {
            // setting back to last non pending score
            // TODO - Worry about pending if inside ranks
            self::resolveScore($profile_number, $map_id);
        }
    }

    public static function deleteYoutubeID(int $changelogId) {
        $change = self::getChange($changelogId);
        $pending = self::isPendingRequired($changelogId, 1, 1);
        $profile_number = $change['profile_number'];
        $map_id = $change['mapid'];

        Database::query(
            "UPDATE changelog
             SET youtube_id = NULL
               , pending = ?
             WHERE changelog.id = ?",
            "ii",
            [
                $pending,
                $changelogId,
            ]
        );

        if ($pending) {
            self::resolveScore(strval($profile_number), strval($map_id));
        }
    }

    public static function setYoutubeID(int $changelogId, string $youtubeID)
    {
        if ($youtubeID == null || $youtubeID == "") {
            Debug::log("Ignoring setYoutubeID({$changelogId}, null)");
            return;
        }

        Debug::log("Setting Demo for changelog id: ".$changelogId);
        $change = self::getChange($changelogId);
        $pending = self::isPendingRequired($changelogId, 1);
        $profile_number = strval($change['profile_number']);
        $map_id = strval($change['mapid']);

        Database::query(
            "UPDATE changelog
             SET youtube_id = ?
               , pending = ?
             WHERE changelog.id = ?",
            "sii",
            [
                $youtubeID,
                $pending,
                $changelogId,
            ]
        );

        if (self::isBest($profile_number, $map_id, $changelogId) && !$pending) {
            // TODO - Check on removed if we need to go back to old value and sent as pending
            self::setScoreTable($profile_number, $map_id, $changelogId);
        }
    }

    public static function setScoreBanStatus(int $changelogId, int $banned)
    {
        Database::query(
            "UPDATE changelog
             SET banned = ?
             WHERE id = ?",
            "ii",
            [
                $banned,
                $changelogId,
            ]
        );

        $row = Database::findOne(
            "SELECT profile_number
                  , map_id
             FROM changelog
             WHERE id = ?",
            "i",
            [
                $changelogId,
            ]
        );

        self::resolveScore(strval($row["profile_number"]), strval($row["map_id"]));
    }

    public static function setProfileBanStatus(string $profileNumber, int $banned) 
    {
        Database::query(
            "UPDATE usersnew
             SET banned = ?
             WHERE profile_number = ?",
            "is",
            [
                $banned,
                $profileNumber,
            ]
        );
    }

    public static function setAutorender(int $changelog_id, string $autorender_id)
    {
        $stmt = Database::getMysqli()->prepare("UPDATE changelog SET autorender_id = ? WHERE id = ?");
        $stmt->bind_param('si', $autorender_id, $changelog_id);

        if (!$stmt->execute()) {
            trigger_error($stmt->error);
        }

        return $stmt->affected_rows;
    }

    //updating score with lowest non banned changelog entry
    //note that we sort the changelog by descending date such that we guarantee that in the scenario that there are
    //two changelog entries with the same score for whatever reason, the newest entry is picked
    public static function resolveScore(string $profileNumber, string $mapId) {
        $minScoreRows = Database::query(
            "SELECT score
                  , id
             FROM changelog
             WHERE banned = 0
             AND pending = 0
             AND map_id = ?
             AND profile_number = ?
             ORDER BY changelog.score ASC
                    , time_gained DESC
             LIMIT 1",
            "ss",
            [
                $mapId,
                $profileNumber,
            ]
        );

        if ($minScoreRows->num_rows > 0) {
            $row = $minScoreRows->fetch_assoc();
            $minScoreId = $row["id"];
            
            $dbData = Database::query(
                "SELECT *
                 FROM scores
                 WHERE profile_number = ?
                 AND map_id = ?",
                "ss",
                [
                    $profileNumber,
                    $mapId,
                ]
            );

            if ($dbData->num_rows > 0) {
                Database::query(
                    "UPDATE scores
                     SET scores.changelog_id = ?
                     WHERE profile_number = ?
                     AND map_id = ?",
                    "iss",
                    [
                        $minScoreId,
                        $profileNumber,
                        $mapId,
                    ]
                );

                if (Database::affectedRows() > 0)
                    Debug::log("Reconfigured score for id: {$profileNumber}, map: {$mapId}");
            } else {
                Database::query(
                    "INSERT INTO scores(
                        profile_number
                      , map_id
                      , changelog_id
                     ) VALUES (
                        ?
                      , ?
                      , ?
                     )",
                    "ssi",
                    [
                        $profileNumber,
                        $mapId,
                        $minScoreId,
                    ]
                );

                if (Database::affectedRows() > 0)
                    Debug::log("Inserted score for id: {$profileNumber}, map: {$mapId}");
            }
        } else {
            Database::query(
                "DELETE
                 FROM scores
                 WHERE profile_number = ?
                 AND map_id = ?",
                "ss",
                [
                    $profileNumber,
                    $mapId,
                ]
            );

            if (Database::affectedRows() > 0)
                Debug::log("Deleted score for id: {$profileNumber}, map: {$mapId}");
        }
    }

    public static function submitChange(
        string $profileNumber,
        string $chamber,
        int $score,
        ?string $youtubeID,
        ?string $comment,
        bool $auto)
    {
        Debug::log("Starting Submit Change");
        $maps = Cache::get("maps");
        $chapter = $maps["maps"][$chamber]["chapterId"];

        $oldBoards = self::getBoard(array("chamber" => $chamber));
        $oldChamberBoard = $oldBoards[$chapter][$chamber];

        Debug::log("Checking if WR");
        $wr = 0;
        $diff = 0;
        $keys = array_keys($oldChamberBoard);
        if ($score <= $oldChamberBoard[$keys[0]]["scoreData"]["score"]) {
            Debug::log("WR = TRUE");
            $wr = 1;
            $diff = abs($score - $oldChamberBoard[$keys[0]]["scoreData"]["score"]);
        }

        $comment = Database::getMysqli()->real_escape_string($comment);
        $preRank = isset($oldChamberBoard[$profileNumber])
            ? $oldChamberBoard[$profileNumber]["scoreData"]["playerRank"]
            : "NULL";
        $previousId = isset($oldChamberBoard[$profileNumber])
            ? $oldChamberBoard[$profileNumber]["scoreData"]["changelogId"]
            : "NULL";

        Debug::log("Submitting change to Change LOG");

        Database::query(
            "INSERT INTO changelog(
                id
              , profile_number
              , score
              , map_id
              , wr_gain
              , previous_id
              , pre_rank
              , submission
              , note
              , pending
             ) VALUES (
                NULL
              , ?
              , ?
              , ?
              , ?
              , ?
              , ?
              , ?
              , ?
              , ?
             )",
            "sisiiiisi",
            [
                $profileNumber,
                $score,
                $chamber,
                $wr,
                $previousId,
                $preRank,
                $auto ? 2 : 1,
                $comment,
                1,
            ]
        );

        $id = intval(Database::getMysqli()->insert_id);
        self::setScoreTable($profileNumber, $chamber, $id);

        $newBoards = self::getBoard(array("chamber" => $chamber));
        $newChamberBoard = $newBoards[$chapter][$chamber];

        $postRank = isset($newChamberBoard[$profileNumber])
            ? $newChamberBoard[$profileNumber]["scoreData"]["playerRank"]
            : "NULL";

        Debug::log("Updating post rank");

        Database::query(
            "UPDATE changelog
             SET post_rank = ?
               , pending = 1
             WHERE id = ?",
             "ii",
             [
                $postRank,
                $id,
            ]
        );

        self::resolveScore($profileNumber, $chamber);
        self::setYoutubeID($id, strval($youtubeID));

        return $id;
    }

    public static function deleteSubmission(int $id) {
        Database::query(
            "UPDATE changelog as ch1
             INNER JOIN (
                SELECT *
                FROM changelog
                WHERE changelog.id = ?
             ) as ch2 on ch1.previous_id = ch2.id
             SET ch1.previous_id = ch2.previous_id",
            "i",
            [
                $id,
            ]
        );

        $change = self::getChange($id);

        Database::query(
            "DELETE
             FROM changelog
             WHERE id = ?",
            "i",
            [
              $id,
            ]
        );

        self::resolveScore(strval($change["profile_number"]), strval($change["mapid"]));
    }

    public static function deleteComment(int $id)
    {
        Database::query(
            "UPDATE changelog
             SET note = NULL
             WHERE changelog.id = ?",
            "i",
            [
                $id,
            ]
        );
    }

    public static function setComment(int $id, string $comment)
    {
        if ($comment != null && $comment != "") {
            print_r($comment);
            print_r($id);

            Database::query(
                "UPDATE changelog
                 SET note = ?
                 WHERE changelog.id = ?",
                "si",
                [
                    $comment,
                    $id,
                ]
            );
        }
    }

    public static function getEvidenceRequirements($active = true) {
        $evidenceRequirements = array();

        $data = Database::unsafe_raw(
            "SELECT id
                  , rank
                  , demo
                  , video
                  , active
                  , timestamp
                  , closed_timestamp
             FROM evidence_requirements"
        );

        while ($row = $data->fetch_assoc()) {
            $evidenceRequirements[$row["id"]] = $row;
        }
        if ($active) {
            return array_filter($evidenceRequirements, function ($var) {
                return ($var['active'] == true);
            });
        }
        return $evidenceRequirements;
    }

    private static function isPendingRequired($changeLogId, $video = 0, $removed = 0) {
        // Getting change log data
        $result = self::getChange($changeLogId);
        // Getting requirements (Old/inactive as well)
        $requirements = self::getEvidenceRequirements(false);
        $dateTime = $result['time_gained'];

        // if video or demo required
        if ($video == 1) {
            // Check if demo exists for changelog
            if ($result['hasDemo']) {
                // Demo exists therefore pending = false
                return 0;
            }

            // Check if video is inbetween demo and video
            // If below demo requirement then pending = true
            // If below video requirement then pending = false
            // If below video requirement and video has been removed then pending = true
            // If above video requirement then pending = false

            // Getting Highest video requirement
            $videoBeforeDate = array_filter($requirements, function ($var) use ($dateTime) {
                if ($var['video'] == 1) {
                    if ($var['timestamp'] < $dateTime & isset($var['closed_timestamp']) ? $var['closed_timestamp'] > $dateTime : true) {
                        return true;
                    }
                }
                return false;
            });

            $videoRequirement = !empty($videoBeforeDate) ? max(array_column($videoBeforeDate, 'rank')) : false;
            Debug::log("Video Requirement: ".$videoRequirement." > ".$result['post_rank']);

            // Getting Highest Demo Requirement
            $demoBeforeDate = array_filter($requirements, function ($var) use ($dateTime) {
                if ($var['demo'] == 1 && $var['video'] == 0) {
                    Debug::log("Demo Only");
                    if ($var['timestamp'] < $dateTime & isset($var['closed_timestamp']) ? $var['closed_timestamp'] > $dateTime : true) {
                        return true;
                    }
                }
                return false;
            });
            $demoRequirement = !empty($demoBeforeDate) ? max(array_column($demoBeforeDate, 'rank')) : false;
            Debug::log("Demo Requirement: ".$demoRequirement." > ".$result['post_rank']);

            if ($demoRequirement && $demoRequirement >= $result['post_rank']) {
                // Rank under Demo Requirement
                return 1;
            }

            if ($videoRequirement && $videoRequirement >= $result['post_rank'] && $removed) {
                // Rank under video requirement and video removed
                return 1;
            }

            return 0;
        }

        // Getting Highest Demo Requirement
        $demoBeforeDate = array_filter($requirements, function ($var) use ($dateTime) {
            if ($var['demo'] == 1 && $var['video'] == 0) {
                Debug::log("Demo Only");
                if ($var['timestamp'] < $dateTime & isset($var['closed_timestamp']) ? $var['closed_timestamp'] > $dateTime : true) {
                    return true;
                }
            }
            return false;
        });
        $demoRequirement = !empty($demoBeforeDate) ? max(array_column($demoBeforeDate, 'rank')) : false;
        Debug::log("Demo Requirement: ".$demoRequirement." > ".$result['post_rank']);

        return ($demoRequirement && $demoRequirement >= $result['post_rank']) ? 1 : 0;
    }

    private static function setScoreTable(string $profileNumber, string $chamber, int $id) {
        Database::query(
            "INSERT IGNORE INTO scores(
                profile_number
              , map_id
              , changelog_id
             ) VALUES (
                ?
              , ?
              , ?
             )",
            "ssi",
            [
                $profileNumber,
                $chamber,
                $id,
            ]
        );

        Database::query(
            "UPDATE scores
             SET changelog_id = ?
             WHERE profile_number = ?
             AND map_id = ?",
            "iss",
            [
                $id,
                $profileNumber,
                $chamber,
            ]
        );
    }

    private static function wrCheck($changeLogId) {
        Debug::log("Starting WR check");
        $result = self::getChange($changeLogId);
        $chamber = $result['mapid'];
        $profileNumber = $result['profile_number'];
        $score = $result['score'];

        $maps = Cache::get("maps");
        $chapter = $maps["maps"][$chamber]["chapterId"];
        $oldBoards = self::getBoard(array("chamber" => $chamber));
        $oldChamberBoard = $oldBoards[$chapter][$chamber];

        $wr = 0;
        $diff = 0;
        $keys = array_keys($oldChamberBoard);
        Debug::log( $oldChamberBoard[$keys[0]]["scoreData"]["score"]);
        if ($score <= $oldChamberBoard[$keys[0]]["scoreData"]["score"]) {
            $wr = 1;
            $diff = abs($score - $oldChamberBoard[$keys[0]]["scoreData"]["score"]);
        }

        Debug::log("diff: ".$diff." WR: ".$wr);

        if ($wr == 1) {
            $user = new User($profileNumber);
            $data = [
                'id' => $changeLogId,
                'timestamp' => new DateTime(),
                'map_id' => $chamber,
                'player_id' => $profileNumber,
                'player' => $user->userData->displayName,
                'player_avatar' => $user->userData->avatar,
                'map' => $maps["maps"][$chamber]["mapName"],
                'score' => Util::formatScoreTime($score),
                'wr_diff' => Util::formatScoreTime($diff)
            ];
            Debug::log("SEND WEBHOOK FOR WR");
            Discord::sendWebhook($data);
        }
    }

    public static function getLatestPb(string $profile_number, string $map_id) {
        return Database::findOne(
            "SELECT *
             FROM changelog
             WHERE profile_number = ?
             AND map_id = ?
             AND banned = 0
             AND pending = 0
             ORDER BY id DESC
             LIMIT 1",
             "ss",
             [
                $profile_number,
                $map_id,
             ]
        );
    }

    private static function isBest(string $profile_number, string $map_id, int $changelogId) {
        Debug::log("Profile Number: ".$profile_number." Map Id: ".$map_id." Changelog Id:".$changelogId);

        $row = Database::findOne(
            "SELECT id
             FROM changelog
             WHERE banned = 0
             AND pending = 0
             AND profile_number = ?
             AND map_id = ?
             ORDER BY changelog.score ASC
                    , time_gained DESC
             LIMIT 1",
             "ss",
             [
                $profile_number,
                $map_id,
            ]
        );

        Debug::log($row["id"]);

        return $changelogId == $row["id"];
    }

    public static function getActiveRunners(int $months) {
        return Database::findMany(
            "SELECT usersnew.profile_number
             FROM usersnew
             INNER JOIN changelog USING (profile_number)
             WHERE changelog.time_gained > NOW() - INTERVAL ? MONTH
             GROUP BY usersnew.profile_number",
            "i",
            [
                $months,
            ]
        );
    }
}
