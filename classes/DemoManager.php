<?php

class DemoManager {
    const demoFolder = "demos";

    public function __construct() {
        mkdir(ROOT_PATH . DemoManager::demoFolder);
    }

    function getDemoName(int $id) {
        $row = Database::findOne(
            "SELECT changelog.profile_number
                  , score
                  , map_id
             FROM changelog
             INNER JOIN usersnew ON changelog.profile_number = usersnew.profile_number
             WHERE changelog.id = ?",
            "i",
            [
                $id,
            ]
        );

        $map = str_replace(" ", "" , $GLOBALS["mapInfo"]["maps"][$row["map_id"]]["mapName"]);
        return $map."_".$row["score"]."_".$row["profile_number"]."_".$id.".dem";
    }

    function getDemoDetails(int $id) {
        $row = Database::findOne(
            "SELECT changelog.id
                  , changelog.profile_number
                  , map_id
             FROM changelog
             INNER JOIN usersnew ON changelog.profile_number = usersnew.profile_number
             WHERE changelog.id = ?",
            "i",
            [
                $id,
            ]
        );

        return $row;
    }

    function getDemoPath(int $id) {
        return ROOT_PATH . '/' . DemoManager::demoFolder . '/' . $this->getDemoName($id);
    }

    function getDemoURL(int $id) {
        $name = $this->getDemoName($id);
        $path = $this->getDemoPath($id);
        if (file_exists($path)) {
            return '/' . DemoManager::demoFolder . '/' . $name;
        } else {
            return NULL;
        }
    }

    function uploadDemo($data, int $id) {
        Debug::log("Uploading demo for changelog $id");

        $path = $this->getDemoPath($id);

        $f = fopen($path, 'w');
        if (!$f) {
            Debug::log("Failed to open demo file $path for writing");
            throw new Exception("Failed to open demo file for writing");
        }

        fwrite($f, $data);
        fclose($f);
        return $path;
    }

    function deleteDemo(int $id) {
        Debug::log("Deleting demo for changelog $id");
        $path = $this->getDemoPath($id);
        if (!unlink($path)) {
            Debug::log("Could not delete demo file $path");
        }
    }
}
