<?php

class DemoManager {
    const demoFolder = "demos";

    public function __construct() {
        mkdir(ROOT_PATH . DemoManager::demoFolder);
    }

    function getDemoName($id) {
        $data = Database::query("SELECT changelog.profile_number, score, map_id
              FROM changelog INNER JOIN usersnew ON (changelog.profile_number = usersnew.profile_number)
              WHERE changelog.id = '" . $id . "'");
        $row = $data->fetch_assoc();
        $map = str_replace(" ", "" , $GLOBALS["mapInfo"]["maps"][$row["map_id"]]["mapName"]);
        return $map."_".$row["score"]."_".$row["profile_number"].".dem";
    }

    function getDemoPath($id) {
        return ROOT_PATH . '/' . DemoManager::demoFolder . '/' . $this->getDemoName($id);
    }

    function getDemoURL($id) {
        $name = $this->getDemoName($id);
        $path = $this->getDemoPath($id);
        if (file_exists($path)) {
            return '/' . DemoManager::demoFolder . '/' . $name;
        } else {
            return NULL;
        }
    }

    function uploadDemo($data, $id) {
        Debug::log("Uploading demo for changelog $id");

        $path = $this->getDemoPath($id);

        $f = fopen($path, 'w');
        if (!$f) {
            Debug::log("Failed to open demo file $path for writing");
            throw new Exception("Failed to open demo file for writing");
        }

        fwrite($f, $data);
        fclose($f);
    }

    function deleteDemo($id) {
        Debug::log("Deleting demo for changelog $id");
        $path = $this->getDemoPath($id);
        if (!unlink($path)) {
            Debug::log("Could not delete demo file $path");
        }
    }
}
