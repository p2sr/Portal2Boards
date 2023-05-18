<?php
class User {

    public $profileNumber;
    public $isRegistered;
    public $hasRecords;
    public $userData = NULL;

    public function __construct($id) {
        if (is_numeric($id)) {
            $number = $id;
        }
        else {
            $profileNumbers = Cache::get("profileNumbers");
            $name = strtolower(urldecode($id));

            if (array_key_exists($name, $profileNumbers)){
                $number = $profileNumbers[$name][0];
            }
            else {
                $number = NULL;
            }
        }

        $this->profileNumber = $number;
        $this->setUserData();
    }

    public function getProfileData() {
        if ($this->userData != NULL) {
            if ($this->userData->banned == 0) {
                $this->getPoints();
                $this->getTimes();
            }
        }
    }

    //TODO: functional decomposition
    public function saveProfile($twitch = NULL, $youtube = NULL, $boardname = NULL) {
        if ($twitch != NULL) {
            Database::query("UPDATE usersnew SET twitch = '$twitch' WHERE profile_number = '$this->profileNumber'");
            $this->userData->twitch = $twitch;
        }
        else {
            Database::query("UPDATE usersnew SET twitch = NULL WHERE profile_number = '$this->profileNumber'");
            $this->userData->twitch = NULL;
        }

        if ($youtube != NULL) {
            Database::query("UPDATE usersnew SET youtube = '$youtube' WHERE profile_number = '$this->profileNumber'");
            $this->userData->youtube = $youtube;
        }
        else {
            Database::query("UPDATE usersnew SET youtube = NULL WHERE profile_number = '$this->profileNumber'");
            $this->userData->youtube = NULL;
        }

        if ($boardname != NULL) {
          Database::query("UPDATE usersnew SET boardname = '$boardname' WHERE profile_number = '$this->profileNumber'");
          $this->userData->boardname = $boardname;
        }
        else {
          Database::query("UPDATE usersnew SET boardname = NULL WHERE profile_number = '$this->profileNumber'");
            $this->userData->boardname = NULL;
        }
    }

    public static function updateProfileData($user) {

        $content = self::fetchCurrentProfileData($user);

        if ($content != NULL) {
            $userinfo = json_decode($content, true);

            if (isset($userinfo["response"]["players"][0]["personaname"]) && isset($userinfo["response"]["players"][0]["avatarfull"])) {
                $nickname = Database::getMysqli()->real_escape_string($userinfo["response"]["players"][0]["personaname"]);
                $avatar_url = $userinfo["response"]["players"][0]["avatarfull"];

                //making sure valid data was received
                if ($avatar_url != "") {
                    Database::query("UPDATE usersnew SET avatar = '{$avatar_url}', steamname = '{$nickname}' WHERE profile_number = '{$user}'");
                }
            }
        }
    }

    public static function fetchCurrentProfileData($user) {
        $steamAPIKey = Config::get()->steam_api_key;
        $ch = curl_init("https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=".$steamAPIKey->key."&steamids=" . $user);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $content = curl_exec($ch);
        curl_close($ch);

        return $content;
    }

    public function setUserData() {
        if (!$this->profileNumber) {
            return;
        }

        $data = Database::query("SELECT IFNULL(boardname, steamname) as displayName, usersnew.* FROM usersnew WHERE profile_number = '$this->profileNumber'");
        // Creates user if profile number does not exist
        if($data->num_rows == 0) {
            Database::query("INSERT INTO usersnew (profile_number) VALUES (" . $this->profileNumber. ")");
            User::updateProfileData($this->profileNumber);
            // Update again data
            $data = Database::query("SELECT IFNULL(boardname, steamname) as displayName, usersnew.* FROM usersnew WHERE profile_number = '$this->profileNumber'");
        }

        while($row = $data->fetch_object()) {

            $row->displayName = htmlspecialchars($row->displayName);
            $row->steamname = htmlspecialchars($row->steamname);
            $row->boardname = htmlspecialchars($row->boardname);
            $this->userData = $row;
            return;
        }
    }

    public static function getAllUserData() {
      $data = Database::query("SELECT avatar, profile_number, IFNULL(boardname, steamname) as displayName, boardname, steamname, banned, twitch, youtube, title FROM usersnew");
      while($row = $data->fetch_assoc()) {
          $userData[$row['profile_number']] = $row;
      }
      return $userData;
    }

    public function isRegistered() {
        if($data = Database::query("SELECT profile_number FROM usersnew WHERE profile_number = '$this->profileNumber'")) {
            if($data->num_rows > 0) {
                return true;
            }
        }
        return false;
    }

    public function hasRecords() {
        $boardPoints = Cache::get("globalPointBoard");
        return (isset($boardPoints[$this->profileNumber])) ? true : false;
    }

    public function getChangelog($dayAmount) {
        $leaderboard = new Leaderboard();
        return $leaderboard->getChangelog(array("profileNumber" => $this->profileNumber, "startDate" => (new DateTime("NOW - {$dayAmount} day"))->format('Y-m-d'), "hasDate" => 1));
    }

    public function getActivity($dayAmount) {
        $leaderboard = new Leaderboard();
        return $leaderboard->getActivity($this->getChangelog($dayAmount));
    }

    public function getPoints() {
        $points = new stdClass();

        $points->SP = $this->getScoreDataFromBoard(Cache::get("SPPointBoard"));
        $points->COOP = $this->getScoreDataFromBoard(Cache::get("COOPPointBoard"));
        $points->global = $this->getScoreDataFromBoard(Cache::get("globalPointBoard"));
        $points->SP["score"] = isset($points->SP["score"]) ? $points->SP["score"] : 0;
        $points->COOP["score"] = isset($points->COOP["score"]) ? $points->COOP["score"] : 0;
        $points->global["score"] = isset($points->global["score"]) ? $points->global["score"] : 0;

        $chapterPointBoards = Cache::get("chapterPointBoards");
        foreach ($chapterPointBoards as $chapter => $chapterData) {
            $points->chapters[$chapter] = $this->getScoreDataFromBoard($chapterData);
        }

        $this->points = $points;
    }

    function sortChambersByRank($score1, $score2) {
        if ($score1["playerRank"] == $score2["playerRank"]) {
            return $score1["index"] < $score2["index"] ? -1 : 1;
        }

        if ($score1["score"] == NULL) {
            return 1;
        }
        
        if ($score2["score"] == NULL) {
            return -1;
        }

        return $score1["playerRank"] < $score2["playerRank"] ? -1 : 1;
    }

    function sortChambersByDate($score1, $score2) {
        if ($score1["score"] == NULL && $score2["score"] == NULL
            || ($score1["score"] != NULL && $score2["score"] != NULL && $score1["date"] == NULL && $score2["date"] == NULL)
            || ($score1["date"] != NULL && $score2["date"] != NULL) && $score1["date"] == $score2["date"]) {
            return $score1["index"] < $score2["index"] ? -1 : 1;
        }

        if ($score1["score"] == NULL 
            || ($score1["score"] != NULL && $score2["score"] != NULL && $score1["date"] == NULL)) {
            return 1;
        }
        
        if ($score2["score"] == NULL
            || ($score1["score"] != NULL && $score2["score"] != NULL && $score2["date"] == NULL)) {
            return -1;
        }

        return strtotime($score1["date"]) < strtotime($score2["date"]) ? 1 : -1;
    }


    public function getTimes() {
        $times = new stdClass();

        $SPTimeBoard = Cache::get("SPTimeBoard");
        $COOPTimeBoard = Cache::get("COOPTimeBoard");
        $globalTimeBoard = Cache::get("globalTimeBoard");
        $times->SP = $this->getScoreDataFromBoard($SPTimeBoard);
        $times->COOP = $this->getScoreDataFromBoard($COOPTimeBoard);
        $times->global = $this->getScoreDataFromBoard($globalTimeBoard);

        $chapterTimeBoards = Cache::get("chapterTimeBoards");
        foreach ($chapterTimeBoards as $chapter => $chapterData) {
            $times->chapters[$chapter] = $this->getScoreDataFromBoard($chapterData);
        }


        $times->SP["chambers"] = $this->getChamberData(Cache::get("SPChamberBoard"));
        $times->COOP["chambers"] = $this->getChamberData(Cache::get("COOPChamberBoard"));
       
        $spOrderedByRank = array();
        $coopOrderedByRank = array();

        $spOrderedByDate = array();
        $coopOrderedByDate = array();

        $i = 0;
        foreach ($GLOBALS["mapInfo"]["modes"]["sp"] as $chapterId) {
            foreach ($GLOBALS["mapInfo"]["chapters"][$chapterId]["maps"] as $mapId) {
                $index = $i++;
                $spOrderedByDate[$mapId] = $times->SP["chambers"]["chamber"][$chapterId][$mapId];
                $spOrderedByDate[$mapId]["index"] = $index;

                $spOrderedByRank[$mapId] = $times->SP["chambers"]["chamber"][$chapterId][$mapId];
                $spOrderedByRank[$mapId]["index"] = $index;
            }
        }

        
        $i = 0;
        foreach ($GLOBALS["mapInfo"]["modes"]["coop"] as $chapterId) {
            foreach ($GLOBALS["mapInfo"]["chapters"][$chapterId]["maps"] as $mapId) {
                $index = $i++;
                $coopOrderedByDate[$mapId] = $times->COOP["chambers"]["chamber"][$chapterId][$mapId];
                $coopOrderedByDate[$mapId]["index"] = $index;

                $coopOrderedByRank[$mapId] = $times->COOP["chambers"]["chamber"][$chapterId][$mapId];
                $coopOrderedByRank[$mapId]["index"] = $index;
            }
        }

        uasort($spOrderedByRank, array("User", "sortChambersByRank"));
        $times->SP["chambers"]["chamberOrderedByRank"] = $spOrderedByRank;

        uasort($spOrderedByDate, array("User", "sortChambersByDate"));
        $times->SP["chambers"]["chamberOrderedByDate"] = $spOrderedByDate;

        uasort($coopOrderedByRank, array("User", "sortChambersByRank"));
        $times->COOP["chambers"]["chamberOrderedByRank"] = $coopOrderedByRank;

        uasort($coopOrderedByDate, array("User", "sortChambersByDate"));
        $times->COOP["chambers"]["chamberOrderedByDate"] = $coopOrderedByDate;

        $times->numDemos = $times->SP["chambers"]["numDemos"] + $times->COOP["chambers"]["numDemos"];
        $times->numYoutubeVideos = $times->SP["chambers"]["numYoutubeVideos"] + $times->COOP["chambers"]["numYoutubeVideos"];
        
        if ($times->SP["chambers"]["bestRank"] != NULL && $times->COOP["chambers"]["bestRank"] != NULL 
            && $times->SP["chambers"]["bestRank"]["scoreData"]["playerRank"] == $times->COOP["chambers"]["bestRank"]["scoreData"]["playerRank"]) {
            $times->bestRank = $times->SP["chambers"]["bestRank"];
            $times->bestRank["map"] = "several chambers";
        }
        else {
            $times->bestRank = Util::uMin($times->SP["chambers"]["bestRank"], $times->COOP["chambers"]["bestRank"], array('User', 'getPlayerRankFromScore'));
        }

        if ($times->SP["chambers"]["worstRank"] != NULL && $times->COOP["chambers"]["worstRank"] != NULL 
            && $times->SP["chambers"]["worstRank"]["scoreData"]["playerRank"] == $times->COOP["chambers"]["worstRank"]["scoreData"]["playerRank"]) {
            $times->worstRank = $times->SP["chambers"]["worstRank"];
            $times->worstRank["map"] = "several chambers";
        }
        else {
            $times->worstRank = Util::uMax($times->SP["chambers"]["worstRank"], $times->COOP["chambers"]["worstRank"], array('User', 'getPlayerRankFromScore'));
        }

        $times->oldestScore = Util::uMin($times->SP["chambers"]["oldestScore"], $times->COOP["chambers"]["oldestScore"], array('User', 'getTimeFromScore'));
        $times->newestScore = Util::uMax($times->SP["chambers"]["newestScore"], $times->COOP["chambers"]["newestScore"], array('User', 'getTimeFromScore'));

        $times->numWRs = $times->SP["chambers"]["numWRs"] + $times->COOP["chambers"]["numWRs"];
        $times->globalAveragePlace =
            (($times->SP["chambers"]["mapCount"] + $times->COOP["chambers"]["mapCount"]) >= 10)
                ? (round(($times->SP["chambers"]["rankSum"] + $times->COOP["chambers"]["rankSum"]) / ($times->SP["chambers"]["mapCount"] + $times->COOP["chambers"]["mapCount"]), 1, PHP_ROUND_HALF_UP))
                : NULL;

        $this->times = $times;
    }

    public function getScoreDataFromBoard($board) {
        if (isset($board[$this->profileNumber])) {
            $scoreData = $board[$this->profileNumber]["scoreData"];
            $scoreData["WRDiff"] = $this->getWRDiff($board);
            $scoreData["nextRankDiff"] = $this->getNextRankDiff($board);

            return $scoreData;
        }
        return NULL;
    }

    public function getWRDiff($scoreBoard) {
        $keys = array_keys($scoreBoard);
        if ($scoreBoard[$this->profileNumber]["scoreData"]["playerRank"] != 1) {
            return abs($scoreBoard[$this->profileNumber]["scoreData"]["score"] - $scoreBoard[$keys[0]]["scoreData"]["score"]);
        }
        return NULL;
    }

    public function getNextRankDiff($scoreBoard) {
        $keys = array_keys($scoreBoard);
        if ($scoreBoard[$this->profileNumber]["scoreData"]["playerRank"] > 1) {
            $index = array_search($this->profileNumber, $keys);
            $score = $scoreBoard[$this->profileNumber]["scoreData"]["score"];
            while (true) {
                if ($scoreBoard[$keys[$index]]["scoreData"]["score"] != $score) {
                    break;
                }
                $index--;
            }
            return abs($score - $scoreBoard[$keys[$index]]["scoreData"]["score"]);
        }
        return null;
    }

    public function getChamberData($board) {
        $times = NULL;
        $times["numWRs"] = 0;
        $times["rankSum"] = 0;
        $times["mapCount"] = 0;
        $times["bestRank"] = NULL;
        $times["worstRank"] = NULL;
        $times["oldestScore"] = NULL;
        $times["newestScore"] = NULL;
        $times["numDemos"] = 0;
        $times["numYoutubeVideos"] = 0;
        foreach ($board as $chapter => $chapterData) {
            foreach ($chapterData as $map => $mapData) {
                if (isset($mapData[$this->profileNumber])) {
                    $scoreData = $mapData[$this->profileNumber]["scoreData"];
                    $times["chamber"][$chapter][$map] = $scoreData;

                    if ($scoreData["hasDemo"] != 0) {
                        $times["numDemos"]++;
                    }
                    if ($scoreData["youtubeID"] != NULL) {
                        $times["numYoutubeVideos"]++;
                    }

                    $times["rankSum"] += $scoreData["playerRank"];
                    $times["mapCount"]++;

                    if ($times["worstRank"] == NULL) {
                        $times["bestRank"]["scoreData"] = $scoreData;
                        $times["worstRank"]["scoreData"] = $scoreData;
                        $times["bestRank"]["map"] = $map;
                        $times["worstRank"]["map"] = $map;
                    }
                    else {
                        if ($times["worstRank"]["scoreData"]["playerRank"] == $scoreData["playerRank"]) {
                            $times["worstRank"]["map"] = "several chambers";
                        }
                        if ($times["bestRank"]["scoreData"]["playerRank"] == $scoreData["playerRank"]) {
                            $times["bestRank"]["map"] = "several chambers";
                        }
                        if ($times["worstRank"]["scoreData"]["playerRank"] < $scoreData["playerRank"]) {
                            $times["worstRank"]["scoreData"] = $scoreData;
                            $times["worstRank"]["map"] = $map;
                        }
                        if ($times["bestRank"]["scoreData"]["playerRank"] > $scoreData["playerRank"]) {
                            $times["bestRank"]["scoreData"] = $scoreData;
                            $times["bestRank"]["map"] = $map;
                        }
                    }

                    if ($scoreData["date"] != NULL) {
                        if ($times["oldestScore"] == NULL) {
                            $times["oldestScore"]["scoreData"] = $scoreData;
                            $times["newestScore"]["scoreData"] = $scoreData;
                            $times["oldestScore"]["map"] = $map;
                            $times["newestScore"]["map"] = $map;
                        } else {
                            if (strtotime(date($scoreData["date"])) < strtotime(date($times["oldestScore"]["scoreData"]["date"]))) {
                                $times["oldestScore"]["scoreData"] = $scoreData;
                                $times["oldestScore"]["map"] = $map;
                            }
                            if (strtotime(date($scoreData["date"])) > strtotime(date($times["newestScore"]["scoreData"]["date"]))) {
                                $times["newestScore"]["scoreData"] = $scoreData;
                                $times["newestScore"]["map"] = $map;
                            }
                        }
                    }

                    $entryKeys = array_keys($mapData);
                    if ($mapData[$entryKeys[0]]["scoreData"]["score"] == $scoreData["score"]) {
                        $times["numWRs"]++;
                    }

                    $times["chamber"][$chapter][$map]["WRDiff"] = $this->getWRDiff($mapData);
                    $times["chamber"][$chapter][$map]["nextRankDiff"] = $this->getNextRankDiff($mapData);
                }
            }
        }
        $times["averagePlace"] = ($times["mapCount"] >= 10) ? (round(($times["rankSum"] / $times["mapCount"]), 1, PHP_ROUND_HALF_UP)) : NULL;
        return $times;
    }

    public static function getTimeFromScore($score) {
        return strtotime(date($score["scoreData"]["date"]));
    }

    public static function getPlayerRankFromScore($score) {
        return $score["scoreData"]["playerRank"];
    }

    public function isAdmin() {
        return $this->userData->admin == 1;
    }

}
