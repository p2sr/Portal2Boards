<?php

use obregonco\B2\Client;
use obregonco\B2\Bucket;

class DemoManager {
    const accountId = "accountId";
    const keyId = "keyId";
    const applicationKey = "applicationKey";

    const bucketId = "bucketId";

    private $client;
    private $bucket;

    public function __construct() {
        $this->client = new Client(DemoManager::accountId, [
            'keyId' => DemoManager::keyId,
            'applicationKey' => DemoManager::applicationKey,
        ]);

        $this->bucket = $this->client->getBucketFromId(DemoManager::bucketId);
    }

    function getDemoFile($id) {
        $demoName = $this->getDemoName($id);
        return $this->client->getFile($this->bucket, $demoName);
    }

    function getDemoName($id) {
        // return $id . ".dem"

        $data = Database::query("SELECT changelog.profile_number, score, map_id
              FROM changelog INNER JOIN usersnew ON (changelog.profile_number = usersnew.profile_number)
              WHERE changelog.id = '" . $id . "'");
        $row = $data->fetch_assoc();
        $map = str_replace(" ", "" , $GLOBALS["mapInfo"]["maps"][$row["map_id"]]["mapName"]);
        return $map."_".$row["score"]."_".$row["profile_number"].".dem";
    }

    function uploadDemo($data, $id) {
        $this->deleteDemo($id); // delete if exists - fails silently otherwise
        $demoName = $this->getDemoName($id);

        $this->client->upload([
            'BucketId' => $this->bucket->getId(),
            'FileName' => $demoName,
            'Body' => $data,
        ]);
    }

    function getDemoURL($id) {
        $file = $this->getDemoFile($id);
        if ($file != NULL) {
            return $this->client->getDownloadUrlForFile($file);
        }
        else {
            return NULL;
        }
    }

    function deleteDemo($id) {
        $file = $this->getDemoFile($id);
        if ($file != NULL) {
            $this->client->deleteFile($file);
        }
    }

}
