<?php
class Router {

    static $location;
    
    //10MB in bytes
    const maxUploadBytes = 16777216; //1024 * 1024 * 16
    
    //a week in seconds
    const sessionLifetime = 604800; //60 * 60 * 24 * 7

    public function __construct()
    {
        //Start timer for determining page load time
        $this->startupTimestamp = microtime(true);

        //session save path
        ini_set('session.save_path', ROOT_PATH . '/sessions');
        
        //configure cookies and sessions
        ini_set('session.cookie_lifetime', self::sessionLifetime);
        session_set_cookie_params(self::sessionLifetime);

        ini_set('session.gc_maxlifetime', self::sessionLifetime);
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 1);

        //start session if logged in
        if (isset($_COOKIE["PHPSESSID"])) {
            session_start();
            setcookie(session_name(), session_id(), time() + self::sessionLifetime, '/');
            
            if (isset($_SESSION["user"])) {
                $_SESSION['user'] = $_SESSION['user']; //keep session variable alive
                SteamSignIn::$loggedInUser = new User($_SESSION["user"]);
            }
            else {
                //edge case: cookie still exists while session does not exist on the server
                $this->destroySession();
            }
        }

        //TODO: remove
        //disable memory limit.
        ini_set('memory_limit', '-1');
        
        //setting max execution time to 5 minutes for big data
        ini_set('max_execution_time', 300);

        //setting upload limits
        ini_set('post_max_size', (self::maxUploadBytes / (1024 * 1024))."M");
        ini_set('upload_max_filesize', (self::maxUploadBytes / (1024 * 1024))."M");

        //disable debugging
        //Debug::disableAllLogging();

        //Disable error reporting
        error_reporting(1);

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

        //start session and set session cookie when logging in
        if ($location[1] == "login") {

            if ($user = SteamSignIn::validate()) {
                session_start();                
                $_SESSION["user"] = $user;
                if($auth_hash = Auth::get_auth_hash($user)){
                    if($auth_hash == null){
                        $auth_hash = Auth::gen_auth_hash($user);
                    }
                    $_SESSION["user_auth_hash"] = $auth_hash;
                }
                header("Location: /profile/".$user);
            } else {
                header("Location: /");
            }

            exit;
        }

        //destroy session and session cookie when logging out
        if ($location[1] == "logout") {
            $this->destroySession();
            $this->routeToDefault();
            exit;
        }

        // TODO - NEW API SHIT

        if($location[1] == "api-v2"){
            // unauthenticated endpoints first
            if ($location[2] == "active-profiles") {
                if (!$_POST || !isset($_POST["months"]) || !is_numeric($_POST["months"])) {
                    echo "Missing or invalid paramters";
                    http_response_code(400);
                    exit;
                }
                $runners = Leaderboard::getActiveRunners($_POST["months"]);
                echo json_encode(array(
                    "profiles" => $runners,
                ));
                exit;
            }

            if (!$_POST || !isset($_POST["auth_hash"])) {
                echo "Missing paramters";
                http_response_code(400);
                exit;
            }

            $userId = Auth::test_auth_hash($_POST["auth_hash"]);
            if ($userId == null) {
                echo "User validation failed";
                http_response_code(400);
                exit;
            }

            if ($location[2] == "validate-user") {
                // If we got here, the hash is definitely valid
                echo "{\"userId\": \"{$userId}\"}";
                exit;
            }

            if ($location[2] == "auto-submit") {
                if (!isset($_POST["mapId"]) or !is_numeric($_POST["mapId"])) {
                    echo "No valid Map Id Provided";
                    http_response_code(400);
                    exit;
                }

                if (!isset($_POST["score"]) or !is_numeric($_POST["score"])) {
                    echo "No valid score provided";
                    http_response_code(400);
                    exit;
                }

                if (!isset($_FILES["demoFile"])) {
                    echo "No demo provided";
                    http_response_code(400);
                    exit;
                }

                $comment = isset($_POST["comment"]) ? $_POST["comment"] : null;

                $id = Leaderboard::submitChange($userId, $_POST["mapId"], $_POST["score"], null, $comment, true);

                if (array_key_exists("demoFile", $_FILES)) {
                    $file = $_FILES["demoFile"];
                    if ($file["name"] != "") {
                        $this->uploadDemo($file, $id);
                    }
                }

                $change = Leaderboard::getChange($id);
                echo json_encode($change);
                exit;
            }

            if ($location[2] == "current-pb") {
                // Get current valid PB
                $pb_row = Leaderboard::getLatestPb($userId, $_POST["mapId"]);
                if (isset($pb_row)) {
                    echo json_encode($pb_row);
                } else {
                    echo "{}"; // No PB
                }
                exit;
            }

        }

        //TODO: don't flush connection but rather give a more refined status update to client which can then follow up by polling the back end for successful upload
        //TODO: You could also just don't flush and hope the backend finishes in an acceptable time
        if ($location[1] == "uploadDemo") {
            echo "Uploading Demo";

            header('Connection: close');
            header('Content-Length: 0');
            flush();

            if (isset($_POST["id"]) && isset($_FILES["demoFile"])) {

                if (!is_numeric($_POST["id"])) {
                    exit;
                }

                $change = Leaderboard::getChange($_POST["id"]);
                echo "Uploading Demo to id: ".$_POST["id"];
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
            if (isset($_POST["id"]) && isset($_POST["youtubeID"])) {

                if (!is_numeric($_POST["id"])) {
                    exit;
                }

                if (!preg_match("/^[A-Za-z0-9_\\-?=]*$/", $_POST["youtubeID"])) {
                    exit;
                }

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
            if (isset($_POST["id"])) {

                if (!is_numeric($_POST["id"])) {
                    exit;
                }

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

        if ($location[1] == "setComment") {
            if (isset($_POST["id"]) && isset($_POST["comment"])) {

                if (!is_numeric($_POST["id"])) {
                    exit;
                }

                $change = Leaderboard::getChange($_POST["id"]);
                if (SteamSignIn::hasProfilePrivileges($change["profile_number"])) {
                    Leaderboard::setComment($_POST["id"], $_POST["comment"]);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "deleteComment") {
            if (isset($_POST["id"])) {

                if (!is_numeric($_POST["id"])) {
                    exit;
                }

                $change = Leaderboard::getChange($_POST["id"]);
                if (SteamSignIn::hasProfilePrivileges($change["profile_number"])) {
                    Leaderboard::deleteComment($_POST["id"]);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "deleteDemo") {
            if (isset($_POST["id"])) {

                if (!is_numeric($_POST["id"])) {
                    exit;
                }

                $change = Leaderboard::getChange($_POST["id"]);
                if (SteamSignIn::hasProfilePrivileges($change["profile_number"])) {
                    $demoManager = new DemoManager();
                    $demoManager->deleteDemo($_POST["id"]);
                    Leaderboard::setDemo($_POST["id"], 0);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "getDemo") {

            if (isset($_GET["id"])) {
                if (!is_numeric($_GET["id"])) {
                    exit;
                }
            }

            // $data = Database::query("SELECT changelog.profile_number, score, map_id, IFNULL(boardname, steamname) as displayName
            //   FROM changelog INNER JOIN usersnew ON (changelog.profile_number = usersnew.profile_number)
            //   WHERE changelog.id = '" . $_GET["id"] . "'");
            // $row = $data->fetch_assoc();
            
            // $map = str_replace(" ", "" , $GLOBALS["mapInfo"]["maps"][$row["map_id"]]["mapName"]);
            // $score = str_replace(":", "", Leaderboard::convertToTime($row["score"]));
            // $score = str_replace(".", "", $score);
            // $displayName = preg_replace("/[^A-Za-z0-9]/", '', $row["displayName"]);
            // if (!$displayName) $displayName = $row["profile_number"];

            $demoManager = new DemoManager();
            $demoURL = $demoManager->getDemoURL($_GET["id"]);

            if ($demoURL != NULL) {
                // $data = file_get_contents($demoURL);
                // header('Content-Description: File Transfer');
                // header('Content-Type: application/octet-stream');
                // header('Content-Disposition: attachment; filename='.$map."_".$score."_".$displayName.".dem");
                // header('Content-Transfer-Encoding: binary');
                // header('Expires: 0');
                // header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                // header('Pragma: public');
                // header("Content-length: " . strlen($data));
                // echo $data;
                
                header('Location: ' . $demoURL, true, 303);
                die();
            } 
            else {
                echo "Demo URL cannot be resolved";
            }

            exit;
        }

        if ($location[1] == "setScoreBanStatus") {
            if (isset($_POST["id"]) && isset($_POST["banStatus"])) {

                if (!is_numeric($_POST["id"]) || !is_numeric($_POST["banStatus"])) {
                    exit;
                }

                if (SteamSignIn::loggedInUserIsAdmin()) {
                    Leaderboard::setScoreBanStatus($_POST["id"], $_POST["banStatus"]);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "submitChange") {
            if (isset($_POST["profileNumber"]) && isset($_POST["chamber"]) && isset($_POST["score"]) && isset($_POST["youtubeID"]) && isset($_POST["comment"])) {

                if (!is_numeric($_POST["profileNumber"])) {
                    exit;
                }

                if (!is_numeric($_POST["chamber"])) {
                    exit;
                }

                if (!is_numeric($_POST["score"])) {
                    exit;
                }

                if (!preg_match("/^[A-Za-z0-9_\\-?=]*$/", $_POST["youtubeID"])) {
                    exit;
                }

                if (SteamSignIn::hasProfilePrivileges($_POST["profileNumber"])) {
                    $id = Leaderboard::submitChange($_POST["profileNumber"], $_POST["chamber"], $_POST["score"], $_POST["youtubeID"], $_POST["comment"], false);

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
            if (isset($_POST["id"])) {

                if (!is_numeric($_POST["id"])) {
                    exit;
                }

                $change = Leaderboard::getChange($_POST["id"]);
                if (SteamSignIn::hasProfilePrivileges($change["profile_number"])) {
                    Leaderboard::deleteSubmission($_POST["id"]);
                    $demoManager = new DemoManager();
                    $demoManager->deleteDemo($_POST["id"]);
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "verifyScore") {
            if (isset($_POST["id"])) {
                if (!is_numeric($_POST["id"])) {
                    exit;
                }

                if (SteamSignIn::loggedInUserIsAdmin()) {
                    Database::query("UPDATE changelog SET pending=0 WHERE changelog.id='{$_POST['id']}'");
                }
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "fetchNewChamberScores") {
            if (isset($_POST["chamber"])) {

                if (!is_numeric($_POST["chamber"])) {
                    exit;
                }

                Leaderboard::fetchNewData($_POST["chamber"]);
                $chamberBoard = Leaderboard::getBoard(array("chamber" => $_POST["chamber"]));
                Leaderboard::cacheChamberBoards($chamberBoard);
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "fetchNewUserData") {
            if (isset($_POST["profileNumber"])) {

                $profileNumber = $_POST["profileNumber"];

                if (!SteamSignIn::hasProfilePrivileges($profileNumber)) {
                    exit;
                }

                if (!is_numeric($profileNumber)) {
                    exit;
                }

                $data = Database::query("SELECT IFNULL(boardname, steamname) as displayName FROM usersnew WHERE profile_number = '{$profileNumber}'");
                $row = $data->fetch_assoc();
                $oldNickname = str_replace(" ", "", $row["displayName"]);

                User::updateProfileData($profileNumber);

                $data2 = Database::query("SELECT IFNULL(boardname, steamname) as displayName FROM usersnew WHERE profile_number = '{$profileNumber}'");
                $row2 = $data2->fetch_assoc();

                $newNickname = str_replace(" ", "", $row2["displayName"]);

                print_r("old nickname " . $oldNickname . "\n");
                print_r("new nickname " . $newNickname . "\n");
                
                if (strtolower($oldNickname) != strtolower($newNickname)) {

                    print_r("nickname updated\n");

                    $nicknames = Cache::get("boardnames");
                    $profileNumbers = Cache::get("profileNumbers");

                    //remove old nickname
                    $cleanedNumbers = array();

                    foreach ($profileNumbers[strtolower($oldNickname)] as $index => $number) {
                        if ($number != $profileNumber) {
                            $cleanedNumbers[] = $number;
                        }
                    }

                    print_r("cleaned profile numbers: ");
                    print_r($cleanedNumbers);
                    print_r("\n");

                    $profileNumbers[strtolower($oldNickname)] = $cleanedNumbers;

                    if (count($profileNumbers[strtolower($oldNickname)]) == 0) {
                        print_r("no profiles with old nick\n");
                        unset($profileNumbers[strtolower($oldNickname)]);
                    }
                    else if (count($profileNumbers[strtolower($oldNickname)]) == 1) {
                        print_r("one profile with old nick. Removing conflict\n");
                        $number = $profileNumbers[strtolower($oldNickname)][0];
                        $nicknames[$number]["useInURL"] = true;
                    }

                    //add new nickname
                    $nicknames[$profileNumber]["displayName"] = $newNickname;
                    $profileNumbers[strtolower($newNickname)][] = $profileNumber;

                    if (count($profileNumbers[strtolower($newNickname)]) > 1) {
                        print_r("conflict with new nick\n");
                        foreach ($profileNumbers[strtolower($newNickname)] as $number) {
                            $nicknames[$number]["useInURL"] = false;
                        }
                    }
                    else {
                        print_r("no conflict with new nick");
                        //if (preg_match("/^[a-zA-Z0-9".preg_quote("'\"£$*()][:;@~!><>,=_+¬-~")."]+$/", $newNickname)) {
                        if (urlencode($newNickname) == $newNickname && !is_numeric($newNickname)) {
                            $nicknames[$profileNumber]["useInURL"] = true;
                        }
                        else {
                            $nicknames[$profileNumber]["useInURL"] = false;
                        }
                    }

                    Cache::set("boardnames", $nicknames);
                    Cache::set("profileNumbers", $profileNumbers);

                }

                //Leaderboard::cacheProfileURLData();
            }
            else {
                echo "Missing post data!";
            }
            exit;
        }

        if ($location[1] == "setProfileBanStatus") {
            if (isset($_POST["profileNumber"]) && isset($_POST["banStatus"])) {

                if (!SteamSignIn::loggedInUserIsAdmin()) {
                    exit;
                }

                if (!is_numeric($_POST["profileNumber"]) || !is_numeric($_POST["banStatus"])) {
                    exit;
                }

                if (SteamSignIn::isLoggedIn($_POST["profileNumber"])) {
                    exit;
                }

                Leaderboard::setProfileBanStatus($_POST["profileNumber"], $_POST["banStatus"]);
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
            else {
                $this->routeTo404();
            }

            if ((isset($location[3]) && $location[3] == "json") || (isset($location[4]) && $location[4] == "json")) {
                echo "{\"Points\":" . json_encode($view->points) . ", \"Times\":" . json_encode($view->times) . "}";
                exit;
            }
        }

        if ($location[1] == "changelog") {

            if (!$_GET) {
                $changelogParams = array("startDate" => (new DateTime('NOW - 7 day'))->format('Y-m-d'), "pending" => "2");
            }
            else {
                $changelogParams = $_GET;
            }

            $param = $this->prepareChangelogParams($changelogParams);

            $view->changelog = Leaderboard::getChangelog($param);

            if (isset($location[2]) && $location[2] == "json") {
                echo json_encode($view->changelog);
                exit;                
            }
        }

        if ($location[1] == "profile" && isset($location[2])) {
            $displayNames = Cache::get("boardnames");
            $id = $location[2];
            if (is_numeric($id) && strlen($id) == 17 && !(isset($location[3]) && $location[3] == "json")) {
                if ($displayNames[$location[2]]["useInURL"]) {
                    header("Location: /profile/" . $displayNames[$location[2]]["displayName"]);
                    exit;
                }
            }

            $view->profile = new User($location[2]);
            $view->profile->getProfileData();
            View::$pageData["pageTitle"] = (isset($view->profile->userData->displayName)) ? $view->profile->userData->displayName : "No profile";

            if (isset($location[3]) && $location[3] == "json") {
                // REALLY IMPORTANT: don't show the user's auth hash!
                unset($view->profile->userData->auth_hash);
                echo json_encode($view->profile);
                exit;                
            }
        }

        if ($location[1] == "chamber" && isset($location[2])) {
            $view->chamber = Cache::get("chamberBoard" . $location[2]);

            if ($view->chamber) {
                $GLOBALS["chamberID"] = $location[2];
                View::$pageData["pageTitle"] = $GLOBALS["mapInfo"]["maps"][$location[2]]["mapName"];
    
                if (isset($location[3]) && $location[3] == "json") {
                    echo json_encode($view->chamber);
                    exit;
                }
            } else {
                if (isset($location[3]) && $location[3] == "json") {
                    echo "{}";
                    exit;
                } else {
                    $this->routeTo404();
                }
            }
        }

        if ($location[1] == "lp") {
            if ($location[2] == "sp") {
                $view->board = Leaderboard::getLeastPortalsBoard(0);
                View::$pageData["pageTitle"] = "Least Portals - Single Player";
            }
            if ($location[2] == "coop") {
                $view->board = Leaderboard::getLeastPortalsBoard(1);
                View::$pageData["pageTitle"] = "Least Portals - Cooperative";
            }
        }

        if ($location[1] == "donators") {
            $data = Database::query("SELECT profile_number, avatar, IFNULL(boardname, steamname) as playername, donation_amount FROM usersnew WHERE title LIKE 'Donator' ORDER BY CAST(donation_amount AS DECIMAL(9, 2)) DESC");
            $view->donators = array();

            while ($row = $data->fetch_assoc()) {
                $row["playername"] = htmlspecialchars($row["playername"]);
                $view->donators[] = $row;
            }

            if (isset($location[2]) && $location[2] == "json") {
                echo json_encode($view->donators);
                exit;
            }
        }

        if ($location[1] == "wallofshame") {
            $data = Database::query("SELECT profile_number, avatar, IFNULL(boardname, steamname) as playername FROM usersnew WHERE banned = 1 ORDER BY playername");
            $view->wallofshame = array();

            while ($row = $data->fetch_assoc()) {
                $row["playername"] = htmlspecialchars($row["playername"]);
                $view->wallofshame[] = $row;
            }

            if (isset($location[2]) && $location[2] == "json") {
                echo json_encode($view->wallofshame);
                exit;
            }
        }

        if ($location[1] == "editprofile") {
            if (isset(SteamSignIn::$loggedInUser)) {
                if ($_POST) {

                    $mysqli = Database::getMysqli();
                    $youtube = NULL;
                    $twitch = NULL;
                    $boardname = NULL;

                    if (strlen($_POST["twitch"]) != 0) {
                        if (!preg_match("/^[A-Za-z0-9_]+$/", $_POST["twitch"])) {
                            $view->msg = "Twitch username must contain only letters, numbers, and underscores.";
                        }
                        else {
                            $twitch = $mysqli->real_escape_string($_POST["twitch"]);
                        }
                    }

                    $boardname = trim($_POST["boardname"]);
                    $boardname = preg_replace('/\s+/', ' ', $boardname);
                    if (strlen($boardname) != 0) {
                        if (!preg_match("/^[A-Za-z 0-9_]+$/", $boardname) || strlen($boardname) > 30) {
                            $view->msg = "Board name must be at most 30 characters, and contain only letters, numbers, and underscores.";
                        }
                        else {
                             $mysqli->real_escape_string($boardname);
                        }
                    }

                    if (strlen($_POST["youtube"]) != 0) {
                        if (!preg_match("/^[A-Za-z0-9_\\-\\/:.@]+$/", $_POST["youtube"])) {
                            $view->msg = "Invalid YouTube channel id or username.";
                        }
                        else {
                            if (strpos($_POST["youtube"], '@') !== false) {
                                $youtubePrefix = "/@";
                                $strComponents = explode("@", $_POST["youtube"]);
                                $youtubeChannelID = $strComponents[count($strComponents) - 1];
                            }
                            else if (strpos($_POST["youtube"], '/user/') !== false) {
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
                                $youtubePrefix = "/@";
                                $youtubeChannelID = $_POST["youtube"];
                            }
                            $youtube = $youtubePrefix . $mysqli->real_escape_string($youtubeChannelID);
                        }
                    }

                    if (!isset($view->msg)) {                       
                        SteamSignIn::$loggedInUser->saveProfile($twitch, $youtube, $boardname);
                        $view->msg = "Profile updated. Wait a minute for the changes to take effect.";
                    }
                }
            }
            else {
                $this->routeToDefault();
            }
        }

        if ($location[1] == "regenerateAuthHash"){
            if (isset(SteamSignIn::$loggedInUser)){
                if ($_POST) {
                    Auth::gen_auth_hash(SteamSignIn::$loggedInUser->profileNumber);
                }
                exit;
            }
            else {
                $this->routeToDefault();
            }
        }

        include(ROOT_PATH . '/views/parts/main.phtml');

    }

    private function uploadDemo($file, $id) {
        $demoManager = new DemoManager();
        if ($file["size"] < self::maxUploadBytes) {
            $data = file_get_contents($file["tmp_name"]);
            $demoPath = $demoManager->uploadDemo($data, $id);
            Leaderboard::setDemo($id, 1);
            try {
                //Debug::log("Attempting to run execute mdp for $demoPath");
                $demoDetails = $demoManager->getDemoDetails($id);
                MdpManager::Execute($demoPath, $demoDetails);
            } catch (\Throwable $th) {
                //throw $th;
                Debug::log("FAILED to Execute mdp");
                Debug::log($th->__toString());
            }
            return true;
        }
        else {
            return false;
        }
    }

    private function prepareChangelogParams($params)
    {
        $result = array(
            "id" => ""
            , "chamber" => ""
            , "chapter" => ""
            , "boardName" => ""
            , "profileNumber" => ""
            , "type" => ""
            , "sp" => "1"
            , "coop" => "1"
            , "wr" => ""
            , "demo" => ""
            , "yt" => ""
            , "maxDaysAgo" => ""
            , "startDate" => ""
            , "endDate" => ""
            , "startRank" => ""
            , "endRank" => ""
            , "submission" => ""
            , "banned" => ""
            , "pending" => "");

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

        return $result;
    }

    private function destroySession() {
        setcookie(session_name(), null, -1, '/');
        session_destroy();
        unset($_SESSION);
    }

}
