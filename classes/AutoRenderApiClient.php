<?php

class AutoRenderApiClient
{
    public static function getUserVideos($user) {
        $ids = array();
        foreach ($user->times->SP["chambers"]["chamber"] as $chapter) {
            foreach ($chapter as $map){
                if($map["hasDemo"] == 1){
                    array_push($ids, (int)$map["changelogId"]);
                }
            }
        }
        foreach ($user->times->COOP["chambers"]["chamber"] as $chapter) {
            foreach ($chapter as $map){
                if($map["hasDemo"] == 1){
                    array_push($ids, (int)$map["changelogId"]);
                }
            }
        }
        //Debug::log(json_encode($ids));
        $result = AutoRenderApiClient::getIfVideosExists($ids);
        //Debug::log(json_encode($result->ids));
        return $result->ids;
    }

    public static function getBoardVideos($board){
        //Debug::log(json_encode($board));
        $ids = array();
        foreach ($board as $entry){
            if(isset($entry["scoreData"])){
                if($entry["scoreData"]["hasDemo"] == 1){
                    array_push($ids, (int)$entry["scoreData"]["changelogId"]);
                }
            }
            else{
                if ($entry["hasDemo"] == 1){
                    array_push($ids, (int)$entry["id"]);
                }
            }
        }
        //Debug::log(json_encode($ids));
        $result = AutoRenderApiClient::getIfVideosExists($ids);
        //Debug::log(json_encode($result->ids));
        return $result->ids;
    }

    public static function getChambersVideos($board){
        //Debug::log(json_encode($board));
        $ids = array();
        foreach ($board as $chapter){
            foreach ($chapter as $chamber){
                foreach($chamber as $entry){
                    if($entry["scoreData"]["hasDemo"] == 1){
                        array_push($ids, (int)$entry["scoreData"]["changelogId"]);
                    }
                }
            }
        }
        //Debug::log(json_encode($ids));
        $result = AutoRenderApiClient::getIfVideosExists($ids);
        //Debug::log(json_encode($result->ids));
        return $result->ids;
    }

    public static function getIfVideosExists($ids = array()) {
        $post = [
            'ids' => $ids
        ];
        //Debug::log(json_encode($post));

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://autorender.portal2.sr/api/v1/check-videos-exist',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($post),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        if($http_code == 200) {
            return json_decode($response);
        }
        else{
            Debug::log("Failed to get auto render details");
            Debug::log($response);
            Debug::log($http_code);
        }

        return [];
    }
}
