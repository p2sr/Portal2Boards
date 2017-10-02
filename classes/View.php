<?php
class View {
    /* Used for forcing update for client side cached files */
    public $browserCacheVersion = "0.1";

    public $css = array();
    public $js = array();

    const morrisStyle = "https://cdnjs.cloudflare.com/ajax/libs/morris.js/0.5.0/morris.css";
    const morrisJs = "https://cdnjs.cloudflare.com/ajax/libs/morris.js/0.5.0/morris.min.js";
    const Raphael = "https://cdnjs.cloudflare.com/ajax/libs/raphael/2.2.1/raphael.min.js";
    const Vague = "https://cdnjs.cloudflare.com/ajax/libs/Vague.js/0.0.6/Vague.min.js";
    const moment = "https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.14.1/moment.min.js";
    const momentTimeZone = "https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.5/moment-timezone-with-data.min.js";
    const d3 = "https://cdnjs.cloudflare.com/ajax/libs/d3/4.2.2/d3.min.js";
    const jqueryColor = "https://cdnjs.cloudflare.com/ajax/libs/jquery-color/2.1.2/jquery.color.min.js";

    const pages = "/js/pages.js";
    const chart = "/js/chart.js";
    const date = "/js/date.js";
    const youtubeSearch = "/js/youtubeSearch.js";
    const youtubeEmbed = "/js/youtubeEmbed.js";
    const score = "/js/score.js";
    const rank = "/js/rank.js";

    static $page;
    static $pageData;
    static $sitePages = array(
        "chambers" => array(
            "contentTemplate" => "chambers.phtml",
            "js" => array(self::youtubeEmbed, self::d3, self::moment, self::momentTimeZone,self::date),
        ),
        "aggregated" => array(
            "contentTemplate" => "aggregated.phtml",
            "js" => array(self::d3, self::moment, self::morrisJs, self::Raphael, self::momentTimeZone, self::jqueryColor, self::date, self::pages),
        ),
        "changelog" => array(
            "contentTemplate" => "changelog.phtml",
            "pageTitle" => "Score updates",
            "js" => array(self::d3, self::morrisJs, self::Raphael, self::moment, self::momentTimeZone,self::date, self::pages, self::score, self::youtubeEmbed, self::rank, self::chart),
            "css" => array(self::morrisStyle)
        ),
        "profile" => array(
            "contentTemplate" => "profile.phtml",
            "pageTitle" => "Profile",
            "js" => array(self::d3, self::Vague,  self::morrisJs, self::Raphael, self::moment, self::momentTimeZone, self::date, self::score, self::youtubeEmbed, self::rank, self::score, self::chart),
            "css" => array(self::morrisStyle)
        ),
        "chamber" => array(
            "contentTemplate" => "chamber.phtml",
            "js" => array(self::d3, self::moment, self::morrisJs, self::Raphael, self::momentTimeZone, self::jqueryColor, self::date, self::pages, self::rank, self::score, self::youtubeEmbed),
            "css" => array(self::morrisStyle)
        ),
        "404" => array(
            "contentTemplate" => "404.phtml",
            "pageTitle" => "404 Not Found"
        ),
        "editprofile" => array(
            "contentTemplate" => "editprofile.phtml",
            "pageTitle" => "Edit profile"
        ),
        "lp" => array(
            "contentTemplate" => "leastportals.phtml",
            "pageTitle" => "Least Portals",
            "js" => array(self::youtubeEmbed)
        ),
        "about" => array(
            "contentTemplate" => "about.phtml",
            "pageTitle" => "About"
        )
    );

    public function __construct() {
        $this->siteTitle = "";

        $this->addCss("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css");

        $this->addJs("https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.0/jquery.min.js");

        $this->addCss("https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css");
        $this->addJs("https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js");

        $this->addJs("/js/popover.js");
        $this->addCss("/style/style.css");
    }
    public function addJs($path) {
        $this->js[] = $path;
        return $this;
    }

    public function addCss($path) {
        $this->css[] = $path;
        return $this;
    }
    public function addJsMultiple($arr) {
        foreach ($arr as $file) {
            $this->addJs($file);
        }
    }

    public function addCssMultiple($arr) {
        foreach ($arr as $file) {
            $this->addCss($file);
        }
    }
}
