<?php

class DemoManager {

    private $driveService;
    const demoFolderId = "0B3FgAePoQ2khX3gzc2dlNDdVZm8";

    public function __construct() {
        $client = new Google_Client();
        $client->setAuthConfigFile(ROOT_PATH."/secret/client_secret.json");
        $client->addScope(Google_Service_Drive::DRIVE);
        $client->setAccessToken(file_get_contents(ROOT_PATH."/secret/credentials.json"));
        $this->driveService = new Google_Service_Drive($client);
    }

    function getDemoFile($id) {
        $demoName = $this->getDemoName($id);
        $files_list = $this->driveService->files->listFiles(array(
            "q" => "title='".$demoName."' and mimeType='text/plain' and '".DemoManager::demoFolderId."' in parents"
        ))->getItems();

        if (count($files_list) > 0) {
            return $files_list[0];
        }
        else {
            return NULL;
        }
    }

    function getDemoName($id) {
        return $id.".dem";
    }

    function uploadDemo($data, $id) {
        $demoName = $this->getDemoName($id);

        try {
            $this->deleteDemo($id);
        }
        catch (Exception $e) {
            print "Tried to delete demo .".$demoName." but no files found. This must be a new score.";
        }

        $parent = new Google_Service_Drive_ParentReference();
        $parent->setId(DemoManager::demoFolderId);
        $file = new Google_Service_Drive_DriveFile();
        $file->setTitle($demoName);
        $file->setParents(array($parent));
        $this->driveService->files->insert($file, array(
            'data' => $data,
            'mimeType' => 'text/plain',
            'uploadType' => 'media'
        ));
    }

    function getDemoURL($id) {
        $file = $this->getDemoFile($id);
        return "https://docs.google.com/uc?export=download&id=".$file->id;
    }

    function deleteDemo($id) {
        try {
            $file = $this->getDemoFile($id);
            if ($file != NULL) {
                $this->driveService->files->delete($file->getId());
            }
        }
        catch (Exception $e) {
            print "Can't delete demo ".$this->getDemoName($id). ". Error: " . $e->getMessage();
        }
    }

}