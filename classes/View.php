<?php
class View {
    /* Used for forcing update for client side cached files */
    public $browserCacheVersion = "0.1";

    public $css = array();
    public $js = array();

    const morrisStyle = [
        "https://cdnjs.cloudflare.com/ajax/libs/morris.js/0.5.0/morris.css",
        "sha512-fjy4e481VEA/OTVR4+WHMlZ4wcX/+ohNWKpVfb7q+YNnOCS++4ZDn3Vi6EaA2HJ89VXARJt7VvuAKaQ/gs1CbQ==",
    ];
    const morrisJs = [
        "https://cdnjs.cloudflare.com/ajax/libs/morris.js/0.5.0/morris.min.js",
        "sha512-9FtP5DAAufVz3oNWHfXGNYv5VP8Rzkq+uVK8TDWtDK8i7rqifXbecSFHPU5Xl0NqwTwSBO1tBh3GKeAXiAVNpg==",
    ];
    const Raphael = [
        "https://cdnjs.cloudflare.com/ajax/libs/raphael/2.2.1/raphael.min.js",
        "sha512-Sdc9Ehuo2k9ppAMuCnXBAKmHXvdq4aFcekw53ZYnU2ITBrPINlKbv0iT2RgSbnusGvUQrGyklKbl1nc6mPw7TQ==",
    ];
    const Vague = [
        "https://cdnjs.cloudflare.com/ajax/libs/Vague.js/0.0.6/Vague.min.js",
        "sha512-qVpl3V11365ajXqvMiAlzLC6SsHXElx436q5SA7wRqT+LHL7CcXlwbSNdkn2514XCyqaQ4L9gFpi1CCMScDGZg==",
    ];
    const moment = [
        "https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.14.1/moment.min.js",
        "sha512-Ov4tCf9Gt2Ej4H/Zesh6T11l33VZbD5Eo9OZatvJjD+W/Jhiv+eFfni6imWnthG8OXTvU4rggQ8br+YpUwJ8nw==",
    ];
    const momentTimeZone = [
        "https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.5/moment-timezone-with-data.min.js",
        "sha512-x+XnLMzWIKaaRHpfvC5PM9Auy9NPxzV4ZQQHyLpRkinUuDZsMdNhQ7KNk68zRlYCDyFnOJ0eGwfWpGvr51S99w==",
    ];
    const d3 = [
        "https://cdnjs.cloudflare.com/ajax/libs/d3/4.2.2/d3.min.js",
        "sha512-Fsayt8p+pwY5ebs4WM1KwVTQJHPKizdz4FQSuUKqR/EWcphyKiy3gGBc335410/YUHKhV1IydBMaFqwPkbT4LA==",
    ];
    const jqueryColor = [
        "https://cdnjs.cloudflare.com/ajax/libs/jquery-color/2.1.2/jquery.color.min.js",
        "sha512-VjRpiWhUqdNa9bwBV7LnlG8CwsCVPenFyOQTSRTOGHw/tjtME96zthh0Vv9Itf3i8w4CkUrdYaS6+dAt1m1YXQ==",
    ];

    const pages = ["/js/pages.js"];
    const chart = ["/js/chart.js"];
    const date = ["/js/date.js"];
    const youtubeSearch = ["/js/youtubeSearch.js"];
    const youtubeEmbed = ["/js/youtubeEmbed.js"];
    const score = ["/js/score.js"];
    const rank = ["/js/rank.js"];

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
        ),
        "wallofshame" => array(
            "contentTemplate" => "wallofshame.phtml",
            "pageTitle" => "Wall of Shame"
        ),
        "donators" => array(
            "contentTemplate" => "donators.phtml",
            "pageTitle" => "Donators"
        )
    );

    public function __construct() {
        $this->siteTitle = "";

        $this->addCss([
            "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.6.3/css/font-awesome.min.css",
            "sha512-4uGZHpbDliNxiAv/QzZNo/yb2FtAX+qiDb7ypBWiEdJQX8Pugp8M6il5SRkN8jQrDLWsh3rrPDSXRf3DwFYM6g==",
        ]);
        $this->addJs([
            "https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.0/jquery.min.js",
            "sha512-qzrZqY/kMVCEYeu/gCm8U2800Wz++LTGK4pitW/iswpCbjwxhsmUwleL1YXaHImptCHG0vJwU7Ly7ROw3ZQoww==",
        ]);
        $this->addCss([
            "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css",
            "sha512-6MXa8B6uaO18Hid6blRMetEIoPqHf7Ux1tnyIQdpt9qI5OACx7C+O3IVTr98vwGnlcg0LOLa02i9Y1HpVhlfiw==",
        ]);
        $this->addJs([
            "https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js",
            "sha512-iztkobsvnjKfAtTNdHkGVjAYTrrtlC7mGp/54c40wowO7LhURYl3gVzzcEqGl/qKXQltJ2HwMrdLcNUdo+N/RQ==",
        ]);

        $this->addJs(["/js/popover.js"]);
        $this->addCss(["/style/style.css"]);
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
