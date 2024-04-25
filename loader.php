<?php

define('ROOT_PATH', str_replace('\\', '/', __DIR__));

include(ROOT_PATH . "/vendor/autoload.php");

require_once(ROOT_PATH."/classes/Debug.php");
require_once(ROOT_PATH."/classes/Config.php");
require_once(ROOT_PATH."/classes/DemoManager.php");
require_once(ROOT_PATH."/classes/Discord.php");
require_once(ROOT_PATH."/classes/Cache.php");
require_once(ROOT_PATH."/classes/Leaderboard.php");
require_once(ROOT_PATH."/classes/SteamSignIn.php");
require_once(ROOT_PATH."/classes/User.php");
require_once(ROOT_PATH."/classes/View.php");
require_once(ROOT_PATH."/classes/Router.php");
require_once(ROOT_PATH."/classes/Database.php");
require_once(ROOT_PATH."/classes/Util.php");
require_once(ROOT_PATH."/classes/Auth.php");
require_once(ROOT_PATH."/classes/AutoRenderApiClient.php");
require_once(ROOT_PATH."/classes/MdpManager.php");
