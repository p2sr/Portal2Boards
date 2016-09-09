<?php
class Router {

    static $location;

    public function __construct()
    {
        //Start timer for determining page load time
        $this->startupTimestamp = microtime(true);

        //setting timezone to UTC
        date_default_timezone_set('Etc/UTC');

        //set cookie and session
        ini_set('session.cookie_lifetime','86400');
        session_set_cookie_params(86400);
        session_start();

        //Disable error reporting
        error_reporting(0);

        //TODO: remove
        //disable memory limit.
        ini_set('memory_limit', '-1');
        //setting max execution time to 10 minutes for big data
        ini_set('max_execution_time', 600);

        //set user if logged in
        if (isset($_SESSION["user"])) {
            SteamSignIn::$loggedInUser = new User($_SESSION["user"]);
        }

        //prepare request URI for processing
        $request = explode('/', $_SERVER['REQUEST_URI']);
        $withoutGet = explode('?', $request[count($request) - 1]);
        $request[count($request) - 1] = $withoutGet[0];
        self::$location = $request;
        $this->processRequest(self::$location);
    }

    public function routeToDefault() {
        header("Location: /changelog");
        exit;
    }
    
    public function routeTo404() {
        View::$page = "404";
        View::$pageData = View::$sitePages[View::$page];
    }

    public function processRequest($location)
    {
        $view = new View();
        $GLOBALS["mapInfo"] = Cache::get("maps");

        //non-page request handling
        if ($location[1] == "login") {
            if ($user = SteamSignIn::validate()) {
                $_SESSION["user"] = $user;
                SteamSignIn::$loggedInUser = new User($user);
            }
            header("Location: /profile/".$user);
            exit;
        }

        if ($location[1] == "logout") {
            unset($_SESSION["user"]);
            $this->routeToDefault();
            exit;
        }

        if ($location[1] == "uploadDemo") {
            header('Connection: close');
            header('Content-Length: 0');
            flush();
            if (isset($_POST)) {
                $change = Leaderboard::getChange($_POST["id"]);
                if (SteamSignIn::hasProfilePrivileges($change["profile_number"])) {
                    if (array_key_exists("demoFile", $_FILES)) {
                        $file = $_FILES["demoFile"];
                        if ($file["name"] != "") {
                            $this->uploadDemo($file, $_POST["id"]);
                        }
                    }
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "setYoutubeID") {
            if (isset($_POST)) {
                $change = Leaderboard::getChange($_POST["id"]);
                if (SteamSignIn::hasProfilePrivileges($change["profile_number"])) {
                    Leaderboard::setYoutubeID($_POST["id"], $_POST["youtubeID"]);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "deleteYoutubeID") {
            if (isset($_POST)) {
                $change = Leaderboard::getChange($_POST["id"]);
                if (SteamSignIn::hasProfilePrivileges($change["profile_number"])) {
                    Leaderboard::deleteYoutubeID($_POST["id"]);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "deleteDemo") {
            if (isset($_POST)) {
                $change = Leaderboard::getChange($_POST["id"]);
                if (SteamSignIn::hasProfilePrivileges($change["profile_number"])) {
                    $demoManager = new DemoManager();
                    $demoManager->deleteDemo($_POST["id"]);
                    Leaderboard::setDemo($_POST["id"], false);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "getDemo") {
            $data = Database::query("SELECT score, map_id, IFNULL(boardname, steamname) as displayName
              FROM changelog INNER JOIN usersnew ON (changelog.profile_number = usersnew.profile_number)
              WHERE changelog.id = ". $_GET["id"]);
            $row = $data->fetch_assoc();

            $map = str_replace(" ", "" , $GLOBALS["mapInfo"]["maps"][$row["map_id"]]["mapName"]);
            $score = str_replace(":", "", Leaderboard::convertToTime($row["score"]));
            $score = str_replace(".", "", $score);
            $displayName = str_replace(" ", "", $row["displayName"]);

            $demoManager = new DemoManager();
            $demoURL = $demoManager->getDemoURL($_GET["id"]);
            $data = file_get_contents($demoURL);
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename='.$displayName."_".$map."_".$score.".dem");
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header("Content-length: " . strlen($data));
            echo $data;
            exit;
        }

        if ($location[1] == "getChangelogJSON") {
            if ($_GET) {
                $param = $this->prepareChangelogParams($_GET);
                $changelog = Leaderboard::getChangelog($param);
                echo json_encode($changelog);
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "setScoreBanStatus") {
            if (isset($_POST)) {
                if (SteamSignIn::loggedInUserIsAdmin()) {
                    Leaderboard::setBanned($_POST["id"], $_POST["banStatus"]);
                    print_r($_POST);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "submitChange") {
            if (isset($_POST)) {
                if (SteamSignIn::hasProfilePrivileges($_POST["profileNumber"])) {
                    $id = Leaderboard::submitChange($_POST["profileNumber"], $_POST["chamber"], $_POST["score"], $_POST["youtubeID"]);

                    if (array_key_exists("demoFile", $_FILES)) {
                        $file = $_FILES["demoFile"];
                        if ($file["name"] != "") {
                            $this->uploadDemo($file, $id);
                        }
                    }

                    $change = Leaderboard::getChange($id);
                    echo json_encode($change);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "deleteSubmission") {
            if (isset($_POST)) {
                $change = Leaderboard::getChange($_POST["id"]);
                if (SteamSignIn::hasProfilePrivileges($change["profile_number"])) {
                    Leaderboard::deleteSubmission($_POST["id"]);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        //page request handling
        if ($location[1] == "") {
            $this->routeToDefault();
            exit;
        }
        if (!array_key_exists($location[1], View::$sitePages)) {
            $this->routeTo404();
        }
        else {
            View::$page = $location[1];
            View::$pageData = View::$sitePages[View::$page];
        }

        if (isset(View::$pageData["js"])) {
            $view->addJsMultiple(View::$pageData["js"]);
        }
        if (isset(View::$pageData["css"])) {
            $view->addCssMultiple(View::$pageData["css"]);
        }

        if ($location[1] == "chambers" && isset($location[2])) {
            if ($location[2] == "sp") {
                $view->board = Cache::get("SPChamberBoard");
                View::$pageData["pageTitle"] = "Chambers - Single Player";
            }
            else if ($location[2] == "coop") {
                $view->board = Cache::get("COOPChamberBoard");
                View::$pageData["pageTitle"] = "Chambers - Cooperative";
            }
            else
                $this->routeTo404();
        }

        if ($location[1] == "aggregated" && isset($location[2])) {
            if ($location[2] == "sp") {
                $view->points = Cache::get("SPPointBoard");
                $view->times = Cache::get("SPTimeBoard");
                View::$pageData["pageTitle"] = "Aggregated - Single Player";
                $view->mode = "Single Player";
            }
            else if ($location[2] == "coop") {
                $view->points = Cache::get("COOPPointBoard");
                $view->times = Cache::get("COOPTimeBoard");
                View::$pageData["pageTitle"] = "Aggregated - Cooperative";
                $view->mode = "Cooperative";
            }
            else if ($location[2] == "overall") {
                View::$pageData["pageTitle"] = "Aggregated - Overall";
                $view->points = Cache::get("globalPointBoard");
                $view->times = Cache::get("globalTimeBoard");
                $view->mode = "Overall";
            }
            else if ($location[2] == "chapter") {
                View::$pageData["pageTitle"] = "Aggregated -".$GLOBALS["mapInfo"]["chapters"][$location[3]]["chapterName"];
                $view->mode = $GLOBALS["mapInfo"]["chapters"][$location[3]]["chapterName"];
                $view->points = Cache::get("chapterPointBoard".$location[3]);
                $view->times = Cache::get("chapterTimeBoard".$location[3]);
            }
        else
            $this->routeTo404();
        }

        if ($location[1] == "changelog") {
            $param = $this->prepareChangelogParams($_GET);
            if ($_GET) {
                $view->changelog = Leaderboard::getChangelog($param);
            }
            else {
                $view->changelog = Cache::get("changelog");
            }
        }

        if ($location[1] == "profile" && isset($location[2])) {
            $displayNames = Cache::get("boardnames");
            $id = $location[2];
            if (is_numeric($id) && strlen($id) == 17) {
                if ($displayNames[$location[2]]["useInURL"]) {
                    header("Location: /profile/" . $displayNames[$location[2]]["displayName"]);
                    exit;
                }
            }

            $view->profile = new User($location[2]);
            $view->profile->getProfileData();
            View::$pageData["pageTitle"] = (isset($view->profile->userData->displayName)) ? $view->profile->userData->displayName : "No profile";
        }

        if ($location[1] == "chamber" && isset($location[2])) {
            $GLOBALS["chamberID"] = $location[2];
            View::$pageData["pageTitle"] = $GLOBALS["mapInfo"]["maps"][$location[2]]["mapName"];
            $view->chamber = Cache::get("chamberBoard" . $location[2]);
        }

//        if ($location[1] == "leastportals") {
//            $leastPortals = new LeastPortals();
//            $view->board = $leastPortals->return_leastportals_board();
//        }

        if ($location[1] == "editprofile") {
            if (isset(SteamSignIn::$loggedInUser)) {
                if ($_POST) {
                    if (strlen($_POST["twitch"]) != 0) {
                        if (!preg_match("/^[A-Za-z0-9_]+$/", $_POST["twitch"])) {
                            $view->msg = "Twitch username must contain only letters, numbers, and underscores.";
                        }
                    }

                    //TODO: more robust/safe?
                    if (strpos($_POST["youtube"], '/user/') !== false) {
                        $youtubePrefix = "/user/";
                        $strComponents = explode("/user/", $_POST["youtube"]);
                        $youtubeChannelID = $strComponents[count($strComponents) - 1];
                    }
                    else if (strpos($_POST["youtube"], '/channel/') !== false) {
                        $youtubePrefix = "/channel/";
                        $strComponents = explode("/channel/", $_POST["youtube"]);
                        $youtubeChannelID = $strComponents[count($strComponents) - 1];
                    }
                    else {
                        $youtubePrefix = "/user/";
                        $youtubeChannelID = $_POST["youtube"];
                    }

                    if (strlen($youtubeChannelID) != 0) {
                        if (!preg_match("/^[A-Za-z0-9_]+$/", $youtubeChannelID)) {
                            $view->msg = "Youtube channel id or username must contain only letters, numbers, and underscores.";
                        }
                    }

                    if (!isset($view->msg)) {
                        $mysqli = Database::getMysqli();
                        $youtube = ($youtubeChannelID != "") ? $youtubePrefix . $mysqli->real_escape_string($youtubeChannelID) : NULL;
                        $twitch = ($_POST["twitch"] != "") ? $mysqli->real_escape_string($_POST["twitch"]) : NULL;
                        $boardname = ($_POST["boardname"] != "") ? $mysqli->real_escape_string($_POST["boardname"]) : NULL;
                        SteamSignIn::$loggedInUser->saveProfile($twitch, $youtube, $boardname);
                        $view->msg = "Profile updated. Wait a minute for the changes to take effect.";
                    }
                }
            } else {
                $this->routeToDefault();
            }
        }

        include(ROOT_PATH . '/views/parts/main.phtml');

    }

    private function uploadDemo($file, $id) {
        $demoManager = new DemoManager();

        if ($file["size"] < 1024 * 1024 * 5) {
            $data = file_get_contents($file["tmp_name"]);
            $demoManager->uploadDemo($data, $id);
            Leaderboard::setDemo($id, true);
        }
    }

    private function prepareChangelogParams($params)
    {
        $result = array("chamber" => "" , "chapter" => ""
        , "boardName" => "" , "profileNumber" => ""
        , "type" => "" , "sp" => "1", "coop" => "1"
        , "wr" => ""
        , "demo" => "", "yt" => ""
        , "maxDaysAgo" => "0"
        , "submission" => "");
        $changelog_post = array();
        foreach ($params as $key => $val) {
            $changelog_post[$key] = $val;
        }
        foreach ($changelog_post as $key => $val) {
            if (array_key_exists($key, $result)) {
                $result[$key] = $changelog_post[$key];
            }
        }
        if ($result["sp"] == "1" && $result["coop"] != "1") {
            $result["type"] = "0";
        }
        elseif ($result["sp"] != "1" && $result["coop"] == "1") {
            $result["type"] = "1";
        }

        if ($result["wr"] == "0") {
            $result["wr"] = "";
        }
        if ($result["yt"] == "0") {
            $result["yt"] = "";
        }
        if ($result["demo"] == "0") {
            $result["demo"] = "";
        }
        if ($result["submission"] == "0") {
            $result["submission"] = "";
        }

        return $result;
    }

}
