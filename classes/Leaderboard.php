<?php
class Leaderboard
{

    const demoBonusPoints = 5;
    const youTubeBonusPoints = 5;
    const numTrackedPlayerRanks = 200;
    static $logging = true;

    protected static function log($str) {
        if (self::$logging) {
            print_r($str . "\n");
        }
    }

    public static function fetchNewData()
    {
        self::log("Start retrieving rank limits per chamber");
        $rankLimits = self::getRankLimits();
        self::log("Finished retrieving rank limits per chamber");
        self::log("Receiving new leaderboard data");
        $newBoardData = self::getNewScores($rankLimits);
        self::log("Receiving new leaderboard data done");
        self::saveScores($newBoardData);
    }

    public static function cacheLeaderboard()
    {
        //TODO: don't do this every caching cycle
        self::log("Start caching maps");
        $maps = self::getMaps();
        Cache::set("maps", $maps);
        self::log("Done caching maps");

        self::log("Start caching scores");
        $SPChamberBoard = self::getBoard(0);
        $COOPChamberBoard = self::getBoard(1);
        Cache::set("SPChamberBoard", $SPChamberBoard);
        Cache::set("COOPChamberBoard", $COOPChamberBoard);
        $fullBoard = $SPChamberBoard + $COOPChamberBoard;
        self::cacheChamberBoards($fullBoard);
        self::log("Done caching scores");

        self::log("Start caching points");
        $SPChamberPointBoard = self::makeChamberPointBoard($SPChamberBoard);
        $COOPChamberPointBoard = self::makeChamberPointBoard($COOPChamberBoard);
        Cache::set("SPChamberPointBoard", $SPChamberPointBoard);
        Cache::set("COOPChamberPointBoard", $COOPChamberPointBoard);
        self::log("Done caching points");

        self::log("Start caching point boards");
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
            }
            else if (isset($COOPchapterPointBoards[$chapter])) {
                Cache::set("chapterPointBoard".$chapter, $COOPchapterPointBoards[$chapter]);
            }
            else {
                Cache::set("chapterPointBoard".$chapter, array());
            }
        }
        self::log("Done caching point boards");

        self::log("Start caching time boards");
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
            }
            else if (isset($COOPchapterTimeBoards[$chapter])) {
                Cache::set("chapterTimeBoard".$chapter, $COOPchapterTimeBoards[$chapter]);
            }
            else {
                Cache::set("chapterTimeBoard".$chapter, array());
            }
        }
        self::log("Done caching time boards");

        self::log("Start caching Youtube IDs");
        $SPids = self::getYoutubeIDs(0);
        $COOPids = self::getYoutubeIDs(1);
        $allIds = $SPids + $COOPids;
        Cache::set("SPyoutubeIDs", $SPids);
        Cache::set("COOPyoutubeIDs", $COOPids);
        foreach (array_keys($maps["chapters"]) as $chapter) {
            foreach ($maps["chapters"][$chapter]["maps"] as $map) {
                if (isset($allIds[$chapter][$map])) {
                    Cache::set("youtubeIDs".$map, $allIds[$chapter][$map]);
                }
                else {
                    Cache::set("youtubeIDs".$map, array());
                }
            }
        }
        self::log("Done caching Youtube IDs");

        self::log("Start caching changelog");
        $changelog = Leaderboard::getChangelog(array("maxDaysAgo" => 7));
        Cache::set("changelog", $changelog);
        self::log("Done caching changelog");

        self::log("Start caching user identification data");
        self::cacheProfileURLData();
        self::log("Finished caching user identification data");
    }

    public static function getMaps()
    {
        $data = Database::query("SELECT steam_id, is_coop, name, chapter_id, chapters.chapter_name, is_public
                            FROM maps
                            INNER JOIN chapters ON maps.chapter_id = chapters.id
                            ORDER BY  is_coop, maps.id");
        while ($row = $data->fetch_assoc()) {
            if ($row["is_coop"] == 1) {
                $mode = "coop";
            }
            else {
                $mode = "sp";
            }
            $maps["modes"][$mode][$row["chapter_id"]] = $row["chapter_id"];
            $maps["chapters"][$row["chapter_id"]]["chapterName"] = $row["chapter_name"];
            $maps["chapters"][$row["chapter_id"]]["maps"][] = $row["steam_id"];
            $maps["maps"][$row["steam_id"]]["isPublic"] = $row["is_public"];
            $maps["maps"][$row["steam_id"]]["mapName"] = $row["name"];
            $maps["maps"][$row["steam_id"]]["chapterId"] = $row["chapter_id"];
        }
        return $maps;
    }

    public static function getBanList()
    {
        $data = Database::query("SELECT profile_number FROM usersnew WHERE banned = 1");
        $shitlist = array();
        while ($obj = $data->fetch_row()) {
            $shitlist[] = $obj[0];
        }
        return $shitlist;
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

    protected static function getNewScores($rankLimits = array())
    {
        $curl_master = curl_multi_init();
        $curl_handles = array();

        foreach ($rankLimits as $mapID => $amount) {
            $curl_handles[$mapID] = curl_init();
            curl_setopt($curl_handles[$mapID], CURLOPT_URL, "http://steamcommunity.com/stats/Portal2/leaderboards/" . $mapID . "?xml=1&start=1&end=" . $amount);
            curl_setopt($curl_handles[$mapID], CURLOPT_HEADER, 0);
            curl_setopt($curl_handles[$mapID], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_handles[$mapID], CURLOPT_HTTPHEADER, array(
                'Connection: Keep-Alive',
                'Keep-Alive: 300'
            ));
            curl_setopt($curl_handles[$mapID], CURLOPT_SSL_VERIFYPEER, FALSE);

            curl_setopt($curl_handles[$mapID], CURLOPT_TIMEOUT, 30);
            curl_setopt($curl_handles[$mapID], CURLOPT_DNS_CACHE_TIMEOUT, 300);

            curl_multi_add_handle($curl_master, $curl_handles[$mapID]);
        }

        $active = null;
        do {
            $status = curl_multi_exec($curl_master, $active);
            $info = curl_multi_info_read($curl_master);
            if ($info["result"] != 0) {
                throw new Exception ("<b>cURL request failed to this URL: </b>" . curl_getinfo($info['handle'], CURLINFO_EFFECTIVE_URL));
            }
        } while ($status == CURLM_CALL_MULTI_PERFORM);

        while ($active && $status == CURLM_OK) {
            if (curl_multi_select($curl_master) == -1) usleep(100); // u w0t?
            do {
                $status = curl_multi_exec($curl_master, $active);
            } while ($status == CURLM_CALL_MULTI_PERFORM);
        }

        $data = array();

        $xml_total = 0;

        foreach ($rankLimits as $mapID => $amount) {
            curl_multi_remove_handle($curl_master, $curl_handles[$mapID]);
            $curlgetcontent = curl_multi_getcontent($curl_handles[$mapID]);
            if($curlgetcontent) {
                $xml = microtime(true);
                try {
                    $leaderboard = simplexml_load_string(utf8_encode($curlgetcontent));
                } catch (Exception $e) {
                    throw new Exception("SimpleXML error: " . $e);
                }

                libxml_use_internal_errors(true);
                $sxe = simplexml_load_string($leaderboard);
                if ($sxe === false) {
                    foreach (libxml_get_errors() as $error) {
                        throw new Exception ("<b>SimpleXML error: </b>" . $error->message . '\n');
                    }
                }

                foreach ($leaderboard->entries as $key2 => $val2) {
                    foreach ($val2 as $d => $b) {
                        $steamid = $b->steamid;
                        $score = $b->score;
                        $data[$mapID][(string)$steamid] = (string)$score;
                    }
                }
                $tt = microtime(true) - $xml;
                $xml_total = $xml_total + $tt;
            }
        }
        curl_multi_close($curl_master);
        return $data;
    }

    protected static function saveScores($newScores)
    {

        $oldBoards = self::getBoard();
        $maps = self::getMaps();
        $changes = array();

        self::log("Saving new leaderboard data");
        $db_data = Database::query("SELECT id, profile_number, score, map_id FROM changelog");
        $oldChangelog = array();
        while ($row = $db_data->fetch_assoc()) {
            $oldChangelog[$row["map_id"]][$row["profile_number"]][$row["score"]] = true; //true has no meaning
        }
        self::log("Obtained current scores");

        $users = User::getAllUserData();
        self::log("Obtained all current users");

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
                    self::log("Fresh map score found. Player: ".$player." Map: ".$chamber." Score: ".$score);
                    $change["profileNumber"] = $player;
                    $change["score"] = $score;
                    $change["mapId"] = $chamber;
                    $scoreInsertions[] = $change;
                }
                elseif ($newChange && $improvement) {
                    self::log("Updated map score found. Player: ".$player." Map: ".$chamber." Score: ".$score);
                    $change["profileNumber"] = $player;
                    $change["score"] = $score;
                    $change["mapId"] = $chamber;
                    $scoreUpdates[] = $change;
                }
            }
        }

        self::log("Inserting new users");
        $userInsertionRows = array();
        foreach (array_keys($userInsertions) as $user) {
            $userInsertionRows[] = "('" . $user . "')";
        }
        if (count($userInsertionRows) > 0) {
            $rows = implode(",", $userInsertionRows);
            Database::query("INSERT INTO usersnew (profile_number) VALUES " . $rows);
        }
        foreach (array_keys($userInsertions) as $user) {
            self::log("Processing new user ".$user);
            User::updateProfileData($user);
        }
        self::log("Finished inserting new users");


        self::log("Starting saving changelog entries");
        foreach ($scoreInsertions + $scoreUpdates as $change) {
            $chapter = $maps["maps"][$change["mapId"]]["chapterId"];
            $mapData = $oldBoards[$chapter][$change["mapId"]];

            $wr = 0;
            $keys = array_keys($mapData);
            if ($change["score"] <= $mapData[$keys[0]]["scoreData"]["score"]) {
                $wr = 1;
            }

            $previousId = isset($oldBoards[$chapter][$change["mapId"]][$change["profileNumber"]])
                ? $oldBoards[$chapter][$change["mapId"]][$change["profileNumber"]]["scoreData"]["changelogId"]
                : "NULL";
            $preRank = isset($oldBoards[$chapter][$change["mapId"]][$change["profileNumber"]])
                ? $oldBoards[$chapter][$change["mapId"]][$change["profileNumber"]]["scoreData"]["playerRank"]
                : "NULL";

            self::log("Inserting change. Player: ".$change["profileNumber"]." Map: ".$change["mapId"]." Score: ".$change["score"]);
            Database::query("INSERT INTO changelog(id, profile_number, score, map_id, wr_gain, previous_id, pre_rank) 
              VALUES (NULL, '" . $change["profileNumber"] . "','" . $change["score"] . "','" . $change["mapId"] . "','" . $wr . "', ". $previousId .", ".$preRank.")
            ");

            $id = Database::$instance->insert_id;
            $changes[$id] = $change;

            Database::query("INSERT IGNORE INTO scores(profile_number, map_id, changelog_id)
              VALUES ('" . $change["profileNumber"] . "','" . $change["mapId"] . "', ".$id.")
            ");

            Database::query("UPDATE scores
              SET changelog_id = ".$id." 
              WHERE profile_number = ". $change["profileNumber"] . " AND map_id = " . $change["mapId"]);
        }

        $newBoards = self::getBoard();
        foreach ($changes as $id => $change) {
            $chapter = $maps["maps"][$change["mapId"]]["chapterId"];
            $postRank = isset($newBoards[$chapter][$change["mapId"]][$change["profileNumber"]])
                ? $newBoards[$chapter][$change["mapId"]][$change["profileNumber"]]["scoreData"]["playerRank"]
                : "NULL";

            self::log("Updating rank of new changelog entry. Player: ".$change["profileNumber"]." Map: ".$change["mapId"]." Score: ".$change["score"]." Rank: ".$postRank);
            Database::query("UPDATE changelog SET post_rank = ".$postRank." WHERE id = ". $id);
        }

        self::log("Finished saving changelog entries");
    }

    //TODO: use cache for determining the limits
    public static function getRankLimits()
    {
        $rankLimits = array();

        $data = Database::query("
            SELECT maps.steam_id, IFNULL(scorecount, 0) AS cheatedScoreAmount
            FROM maps
            LEFT JOIN (
              SELECT scores.map_id, COUNT(scores.changelog_id) AS scorecount
              FROM scores
              INNER JOIN changelog ON (scores.changelog_id = changelog.id)
              INNER JOIN usersnew ON scores.profile_number = usersnew.profile_number
              WHERE changelog.banned = '1'  OR usersnew.banned = '1'
              GROUP BY scores.map_id) as scores1
            ON scores1.map_id = maps.steam_id");

        while ($row = $data->fetch_assoc()) {
            $rankLimits[$row["steam_id"]] = $row["cheatedScoreAmount"];
        }

        //in case many people are tied at max rank
//        $data = Database::query("SELECT map_id, COUNT(*) as numTrackedScores
//               FROM (
//                   SELECT map_id,
//                   IF( @prevMap <> map_id, @rownum := 1,  @rownum := @rownum + 1 ) as rowNum,
//                   IF( @prevMap <> map_id, @displayRank := 1,  IF( @prevScore <> score, @displayRank := @rownum,  @displayRank ) ) AS player_rank,
//                   @prevMap := map_id, @prevScore := score
//                   FROM scores
//                   JOIN (SELECT @rownum := NULL, @prevMap := 0, @prevScore := 0) AS r
//                   WHERE profile_number IN (SELECT profile_number FROM usersnew WHERE banned = 0)
//                   AND banned = '0'
//                   ORDER BY scores.map_id, scores.score ASC
//                ) as ranks
//                WHERE player_rank <= ". self::numTrackedPlayerRanks . "
//                GROUP BY map_id");

//        while ($row = $data->fetch_assoc()) {
//            $rankLimits[$row["map_id"]] += $row["numTrackedScores"];
//        }

        foreach ($rankLimits as $map => $amount) {
            $rankLimits[$map] += self::numTrackedPlayerRanks;
        }

        return $rankLimits;
    }

    public static function getBoard($mode = "", $chamber = "")
    {
        $query = Database::query("SELECT ranks.profile_number, u.avatar, IFNULL(u.boardname, u.steamname) as boardname,
                chapters.id as chapterid, maps.steam_id as mapid, 
                ranks.changelog_id, ranks.score, ranks.player_rank, ranks.score_rank,  ranks.time_gained as date, has_demo, youtube_id,
                ranks.submission
            FROM usersnew as u
            JOIN (
                SELECT sc.changelog_id, sc.profile_number, sc.score, sc.map_id, sc.time_gained, sc.has_demo, sc.youtube_id, sc.submission,
                IF( @prevMap <> sc.map_id, @rownum := 1,  @rownum := @rownum + 1 ) as rowNum,
                IF( @prevMap <> sc.map_id, @displayRank := 1,  IF( @prevScore <> sc.score, @displayRank := @rownum,  @displayRank ) ) AS player_rank,
                IF( @prevMap <> sc.map_id, @rank := 1,  IF( @prevScore <> sc.score, @rank := @rank + 1,  @rank ) ) AS score_rank,
                @prevMap := sc.map_id, @prevScore := sc.score
                FROM (
                    SELECT changelog.submission, scores.changelog_id, scores.profile_number, scores.map_id, changelog.score, changelog.time_gained, changelog.youtube_id, changelog.has_demo
                    FROM scores
                    INNER JOIN changelog ON (scores.changelog_id = changelog.id)
                    WHERE scores.profile_number IN (SELECT profile_number FROM usersnew WHERE banned = 0)
                    AND scores.map_id IN (
                      SELECT steam_id 
                      FROM maps 
                      WHERE is_coop LIKE '%{$mode}%' AND steam_id LIKE '%{$chamber}%'
                    )  
                    AND changelog.banned = '0'  
                ) as sc
                JOIN (SELECT @rownum := NULL, @prevMap := 0, @prevScore := 0) AS r               
                ORDER BY sc.map_id, sc.score, sc.time_gained, sc.profile_number ASC               
            ) as ranks ON u.profile_number = ranks.profile_number
            JOIN maps ON ranks.map_id = maps.steam_id
            JOIN chapters ON maps.chapter_id = chapters.id
            AND player_rank <= ". self::numTrackedPlayerRanks);

        $board = array();
        while ($row = $query->fetch_assoc()) {
            $profileNumber = $row["profile_number"];
            $chapterId =$row["chapterid"];
            $mapId = $row["mapid"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["submission"] = $row["submission"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["changelogId"] = $row["changelog_id"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["playerRank"] = $row["player_rank"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["scoreRank"] = $row["score_rank"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["score"] = $row["score"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["date"] = $row["date"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["hasDemo"] = $row["has_demo"];
            $board[$chapterId][$mapId][$profileNumber]["scoreData"]["youtubeID"] = $row["youtube_id"];
            $board[$chapterId][$mapId][$profileNumber]["userData"]["boardname"] = $row["boardname"];
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

    //TODO: replace day amount with date range
    public static function getChangelog($parameters = array())
    {
        $param = array("chamber" => "" , "chapter" => ""
        , "boardName" => "" , "profileNumber" => ""
        , "type" => "" , "sp" => "1", "coop" => "1"
        , "wr" => ""
        , "banned" => 1
        , "demo" => "", "yt" => ""
        , "submission" => ""
        , "maxDaysAgo" => 0, "hasDate" => 0
        , "id" => "");

        foreach ($parameters as $key => $val) {
            if (array_key_exists($key, $param)) {
                $result = preg_replace("/[^a-zA-Z0-9]+\s/", "", $parameters[$key]);
                $param[$key] = Database::getMysqli()->real_escape_string($result);
            }
        }
        $whereClause = "";
        if ($param['maxDaysAgo'] != 0) {
            //$time = Util::daysAgo($param["maxDaysAgo"]);
            //$dateStr = date("Y-m-d H:i:s", strtotime('today', $time));
            //$whereClause = "time_gained > '".$dateStr."' AND ";
            $whereClause = "time_gained > DATE_SUB(NOW(), INTERVAL ".$param['maxDaysAgo']." DAY) AND ";
        }
        $whereClause1 = ($param['yt'] == "1") ?  "youtube_id IS NOT NULL AND " : "";
        $whereClause2 = ($param["hasDate"] == "1") ? "time_gained IS NOT NULL AND " : "";
        $whereClause3 = ($param["wr"] == "1") ? "post_rank = 1 AND " : "";
        $whereClause4 = ($param["banned"] == 0) ? "banned = 0 AND " : "";

        $changelog_data = Database::query("SELECT IFNULL(usersnew.boardname, usersnew.steamname) AS player_name, usersnew.avatar, ch.profile_number,
                                            ch.score, ch.id, ch.pre_rank, ch.post_rank, ch.wr_gain, ch.time_gained, ch.has_demo as hasDemo, ch.youtube_id as youtubeID,
                                            ch.banned, ch.submission,
                                            ch_previous.score as previous_score,
                                            maps.name as chamberName, chapters.id as chapterId, maps.steam_id AS mapid
												FROM (
                                                    SELECT *
                                                    FROM changelog
                                                    WHERE " . $whereClause . " " . $whereClause1 . " " . $whereClause2 . " " . $whereClause3 . " " . $whereClause4 . "
                                                    map_id LIKE '%{$param['chamber']}%'
                                                    AND profile_number LIKE '%{$param['profileNumber']}%'
                                                    AND submission LIKE '%{$param['submission']}%'
                                                    AND has_demo LIKE '%{$param['demo']}%'
                                                    AND id LIKE '%{$param['id']}%'
                                                    ORDER BY time_gained DESC, score ASC, profile_number ASC
                                                ) as ch
                                                LEFT JOIN changelog as ch_previous ON (ch_previous.id = ch.previous_id)
                                                INNER JOIN usersnew ON ch.profile_number = usersnew.profile_number
												INNER JOIN maps ON ch.map_id = maps.steam_id
												INNER JOIN chapters ON maps.chapter_id = chapters.id
												WHERE  usersnew.banned = 0
												AND maps.is_coop LIKE '%{$param['type']}%'
                                                AND chapters.id LIKE '%{$param['chapter']}%'
                                                AND IFNULL(usersnew.boardname, usersnew.steamname) LIKE '%{$param['boardName']}%'
												");
        $changelog = array();
        while ($row = $changelog_data->fetch_assoc()) {
            $row["improvement"] = null;
            $row["rank_improvement"] = null;
            if ($row["previous_score"] != NULL) {
                $row["improvement"] = ($row["previous_score"] - $row["score"]);
            }
            if ($row["pre_rank"] != NULL && $row["post_rank"] != NULL) {
                $row["rank_improvement"] = ($row["pre_rank"] - $row["post_rank"]);
            }
            $changelog[] = $row;
        }

        return $changelog;
    }

    public static function getChange($id) {
        $changelog = self::getChangelog(array("id" => $id));
        return $changelog[0];
    }

    public static function getYoutubeIDs($mode) {
        $data = Database::query(
            "SELECT changelog.profile_number as profileNumber, score, map_id as mapId, youtube_id as youtubeID, maps.chapter_id, IFNULL(usersnew.boardname, usersnew.steamname) AS player_name
             FROM changelog
             INNER JOIN usersnew ON changelog.profile_number = usersnew.profile_number
             INNER JOIN maps ON changelog.map_id = maps.steam_id
             WHERE changelog.banned = 0 AND usersnew.banned = 0 AND maps.is_coop = ". $mode ."
             AND youtube_id IS NOT NULL
             ORDER BY map_id, score, time_gained ASC");

        $youtubeIDs = array();
        while ($row = $data->fetch_assoc()) {
            $youtubeIDs[$row["chapter_id"]][$row["mapId"]][] = $row;
        }

        return $youtubeIDs;
    }

    public static function makeChamberPointBoard($board)
    {
        $points = Leaderboard::numTrackedPlayerRanks;
        while ($points >= 1) {
            $pointArray[] = max(1, round(pow($points, 2) / Leaderboard::numTrackedPlayerRanks));
            $points--;
        }

        foreach ($board as $chapter => $chapterData) {
            foreach ($chapterData as $map => $mapData) {
                foreach ($mapData as $user => $userScoreData) {
                    $pointBoard[$chapter][$map][$user]["userData"] = $userScoreData["userData"];
                    $videoPoints = ($userScoreData["scoreData"]["youtubeID"] != NULL) ? self::demoBonusPoints : 0;
                    $demoPoints = ($userScoreData["scoreData"]["hasDemo"] != 0) ? self::youTubeBonusPoints : 0;
                    $pointBoard[$chapter][$map][$user]["scoreData"]["score"] =
                        $pointArray[$userScoreData["scoreData"]["playerRank"] - 1]
                        + $videoPoints
                        + $demoPoints;
                }
            }
        }
        return $pointBoard;
    }

    //TODO: combine sorting functions
    public static function descScoreSort($a, $b) {
        $scoreA = $a["scoreData"]["score"];
        $scoreB = $b["scoreData"]["score"];
        if ($scoreA == $scoreB) {
            return 0;
        }
        else {
            return ($scoreA < $scoreB) ? 1 : -1;
        }
    }

    public static function ascScoreSort($a, $b) {
        $scoreA = $a["scoreData"]["score"];
        $scoreB = $b["scoreData"]["score"];
        if ($scoreA == $scoreB) {
            return 0;
        }
        else {
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
            $points["chapter"][$chapter] = self::calculateRanking($points["chapter"][$chapter]);
        }
        uasort($points["board"], array("Leaderboard", "descScoreSort"));
        $points["board"] = self::calculateRanking($points["board"]);

        return $points;
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
        if ($ascending) {
            uasort($scoreBoard, array("Leaderboard", "ascScoreSort"));
        }
        else {
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
                }
                else {
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
        $data = Database::query("SELECT IFNULL(boardname, steamname) AS nickname, profile_number FROM usersnew");
        $profileNumbers = [];
        $nicknames = [];

        while ($row = $data->fetch_assoc()) {
            $nickname = str_replace(" ", "", $row["nickname"]);
            $nicknames[$row["profile_number"]]["displayName"] = $nickname;
            $profileNumbers[strtolower($nickname)][] = $row["profile_number"];
        }

        foreach ($profileNumbers as $name => $numbers) {
            if (count($numbers) > 1) {
                foreach ($numbers as $number) {
                    $nicknames[$number]["useInURL"] = false;
                }
            }
            else {
                $nickname = $nicknames[$numbers[0]]["displayName"];

                //if (preg_match("/^[a-zA-Z0-9".preg_quote("'\"£$*()][:;@~!><>,=_+¬-~")."]+$/", $nickname)) {
                if (urlencode($nickname) == $nickname) {
                    $nicknames[$numbers[0]]["useInURL"] = true;
                }
                else {
                    $nicknames[$numbers[0]]["useInURL"] = false;
                }
            }
        }

        Cache::set("boardnames", $nicknames);
        Cache::set("profileNumbers", $profileNumbers);
    }

    public static function setDemo($changelogId, $hasDemo) {
        Database::query("UPDATE changelog
                        SET has_demo = '{$hasDemo}'
                        WHERE changelog.id = '{$changelogId}'");
    }

    public static function deleteYoutubeID($changelogId) {
        Database::query("UPDATE changelog
                        SET youtube_id = NULL
                        WHERE changelog.id = '{$changelogId}'");
    }

    public static function setYoutubeID($changelogId, $youtubeID)
    {
        if ($youtubeID != null && $youtubeID != "") {
            Database::query("UPDATE changelog
                        SET youtube_id = '{$youtubeID}'
                        WHERE changelog.id = '{$changelogId}'");
        }
    }

    public static function setBanned($changelogId, $banned)
    {
        Database::query("UPDATE changelog SET banned = '{$banned}'  WHERE id = '{$changelogId}'");

        $data = Database::query("SELECT profile_number, map_id FROM changelog WHERE id = '{$changelogId}'");
        $row = $data->fetch_assoc();

        self::resolveScore($row["profile_number"], $row["map_id"]);
    }

    //updating score with lowest non banned changelog entry
    //note that we sort the changelog by descending date such that we guarantee that in the scenario that there are
    //two changelog entries with the same score for whatever reason, the newest entry is picked
    public static function resolveScore($profileNumber, $mapId) {

        $minScoreRows = Database::query("
            SELECT changelog.score, changelog.id
            FROM changelog
            INNER JOIN (
                SELECT map_id, profile_number, min(score) as score 
                FROM changelog
                WHERE banned = 0 AND changelog.profile_number = '{$profileNumber}' AND changelog.map_id = '{$mapId}'
                GROUP BY map_id, profile_number
            ) as minScoreId ON (changelog.profile_number = minScoreId.profile_number AND changelog.map_id = minScoreId.map_id AND changelog.score = minScoreId.score)
            WHERE changelog.score = minScoreId.score
            ORDER BY time_gained DESC");

        if ($minScoreRows->num_rows > 0) {

            $row = $minScoreRows->fetch_assoc();
            $minScoreId = $row["id"];

            $dbData = Database::query("SELECT * FROM scores WHERE profile_number = {$profileNumber} AND map_id = {$mapId}");

            if ($dbData->num_rows > 0) {
                Database::query("UPDATE scores
                        SET scores.changelog_id = {$minScoreId}
                        WHERE profile_number = '{$profileNumber}' AND map_id = '{$mapId}'");

                if (Database::affectedRows() > 0)
                    self::log("Reconfigured score for id: {$profileNumber}, map: {$mapId}");
            }
            else {
                Database::query("INSERT INTO scores(profile_number, map_id, changelog_id) VALUES('{$profileNumber}', '{$mapId}', '{$minScoreId}')");

                if (Database::affectedRows() > 0)
                    self::log("Inserted score for id: {$profileNumber}, map: {$mapId}");
            }

        }
        else {
            Database::query("DELETE FROM scores WHERE profile_number = {$profileNumber} AND map_id = {$mapId}");

            if (Database::affectedRows() > 0)
                self::log("Deleted score for id: {$profileNumber}, map: {$mapId}");
        }
    }

    public static function submitChange($profileNumber, $chamber, $score, $youtubeID)
    {
        $maps = Cache::get("maps");
        $chapter = $maps["maps"][$chamber]["chapterId"];

        $oldBoards = self::getBoard("", $chamber);
        $oldChamberBoard = $oldBoards[$chapter][$chamber];

        $wr = 0;
        $keys = array_keys($oldChamberBoard);
        if ($score <= $oldChamberBoard[$keys[0]]["scoreData"]["score"]) {
            $wr = 1;
        }

        $preRank = isset($oldChamberBoard[$profileNumber])
            ? $oldChamberBoard[$profileNumber]["scoreData"]["playerRank"]
            : "NULL";
        $previousId = isset($oldChamberBoard[$profileNumber])
            ? $oldChamberBoard[$profileNumber]["scoreData"]["changelogId"]
            : "NULL";

        Database::query("INSERT INTO changelog(id, profile_number, score, map_id, wr_gain, previous_id, pre_rank, submission) 
              VALUES (NULL, '" . $profileNumber . "','" . $score . "','" . $chamber . "','" . $wr . "', ". $previousId .", ".$preRank.", 1)
            ");

        $id = Database::$instance->insert_id;

        Database::query("INSERT IGNORE INTO scores(profile_number, map_id, changelog_id)
              VALUES ('" . $profileNumber . "','" . $chamber . "', ".$id.")
            ");

        Database::query("UPDATE scores
              SET changelog_id = ".$id." 
              WHERE profile_number = ". $profileNumber . " AND map_id = " . $chamber);

        $newBoards = self::getBoard("", $chamber);
        $newChamberBoard = $newBoards[$chapter][$chamber];

        $postRank = isset($newChamberBoard[$profileNumber])
            ? $newChamberBoard[$profileNumber]["scoreData"]["playerRank"]
            : "NULL";

        Database::query("UPDATE changelog 
            SET post_rank = ".$postRank."
            WHERE id = ". $id);

        self::setYoutubeID($id, $youtubeID);
        return $id;
    }

    public static function deleteSubmission($id) {
        Database::query("UPDATE changelog as ch1
            INNER JOIN (
                SELECT *
                FROM changelog 
                WHERE changelog.id = '{$id}'
            ) as ch2 on ch1.previous_id = ch2.id
            SET ch1.previous_id = ch2.previous_id");

        $change = self::getChange($id);
        Database::query("DELETE FROM changelog where id = '{$id}'");
        self::resolveScore($change["profile_number"], $change["mapid"]);
    }

}

class LeastPortals extends Leaderboard
{
    protected function get_map_ids()
    {
        $data = Database::query("SELECT lp_id FROM maps ORDER BY id");
        while ($fuckingretardedmysqlifunctionthatdoesntreturnfuckingarrayinstantly = $data->fetch_assoc()) {
            $steamids[$fuckingretardedmysqlifunctionthatdoesntreturnfuckingarrayinstantly["lp_id"]] = 20;
        }
        return $steamids;
    }

    protected function get_leastportal_exceptions()
    {
        $data = Database::query("SELECT map_id, profile_number FROM leastportals_exceptions");
        while ($row = $data->fetch_assoc()) {
            $exceptions[] = array($row["map_id"], $row["profile_number"]);
        }
        return $exceptions;
    }

    protected function return_leastportals_data()
    {
        
        $data = Database::query("SELECT steam_id, portals FROM leastportals");
        while ($row = $data->fetch_assoc()) {
            $board[$row["steam_id"]] = $row["portals"];
        }
        return $board;
    }

    public function return_leastportals_board()
    {
        
        $data = Database::query("SELECT lp.steam_id, lp.portals, maps.name, chapters.chapter_name, maps.steam_id AS steam_id_image
								FROM leastportals AS lp
								INNER JOIN maps ON lp.steam_id = maps.lp_id
								INNER JOIN chapters ON maps.chapter_id = chapters.id
								ORDER BY chapters.is_multiplayer ASC, maps.id ASC
								");
        while ($row = $data->fetch_assoc()) {
            $board[$row["chapter_name"]][$row["name"]] = array($row["steam_id"], $row["steam_id_image"], $row["portals"]);
        }
        return $board;
    }
}
