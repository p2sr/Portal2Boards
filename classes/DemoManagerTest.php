<?php

$driveService;
$demoFolderId = "0B3FgAePoQ2khX3gzc2dlNDdVZm8";


$client = new Google_Client();
$client->setAuthConfigFile(ROOT_PATH."/secret/client_secret.json");
$client->addScope(Google_Service_Drive::DRIVE);
$client->setAccessToken(file_get_contents(ROOT_PATH."/secret/credentials.json"));
$this->driveService = new Google_Service_Drive($client);

$demoName = $this->getDemoName(8513);
$files_list = $this->driveService->files->listFiles(array(
    "q" => "title='".$demoName."' and mimeType='text/plain' and '0B3FgAePoQ2khX3gzc2dlNDdVZm8' in parents"
))->getItems();

print $files_list;
