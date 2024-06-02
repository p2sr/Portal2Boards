<?php

class Discord {
    private static $username = 'board.portal2.sr';
    private static $avatar = 'https://raw.githubusercontent.com/p2sr/Portal2Boards/master/public/images/portal2boards_avatar.jpg';
    private static $embed_icon = 'https://raw.githubusercontent.com/p2sr/Portal2Boards/master/public/images/portal2boards_icon.png';

    public static function sendMdpWebhook($data, $demoName, $text, $err = null) {
        try {
            //Debug::log("Trying to sending Webhook for mdp");
            $payload = [
                'username' => 'Demo Parse Bot',
                'avatar_url' => self::$avatar,
                'content' => 'Link to change log: [Click Here](<https://board.portal2.sr/changelog?id='.$data['id'].'>)'
            ];
            $tempFile = self::CreateTempFile($text);
            $tempErrFile = self::CreateTempFile($err);
            $post = [
                'files[0]' => curl_file_create($tempFile, 'text/plain', $demoName.'.txt'),
                'payload_json' => json_encode($payload)
            ];
    
            if ($err != null) {
                $post['files[1]'] = curl_file_create($tempErrFile, 'text/plain', $demoName.'_err.txt');
            }
            //Debug::log(json_encode($payload));
            $ch = curl_init(Config::get()->discord_webhook_mdp);
            curl_setopt($ch, CURLOPT_USERAGENT, 'board.portal2.sr (https://github.com/p2sr/Portal2Boards)');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
            $response = curl_exec($ch);
            curl_close($ch);
            //Debug::log($response);

            self::DeleteTempFile($tempErrFile);
            self::DeleteTempFile($tempFile);
            //Debug::log("Finished sending");
            
        } catch (\Throwable $th) {
            Debug::log($th->__toString());
        }
        
    }

    public static function sendWebhook($data) {
        Debug::log("Sending Webhook - Building embed");
        $embed = self::buildEmbed($data);
        $payload = [
            'username' => self::$username,
            'avatar_url' => self::$avatar,
            'embeds' => [ $embed ]
        ];
        $post = [
            'payload_json' => json_encode($payload)
        ];
        Debug::log(json_encode($payload));
        $ch = curl_init(Config::get()->discord_webhook_wr);
        curl_setopt($ch, CURLOPT_USERAGENT, 'board.portal2.sr (https://github.com/p2sr/Portal2Boards)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_exec($ch);
        curl_close($ch);
        Debug::log("Sending Webhook - Finished");
    }

    public static function buildEmbed($data) {
        $embed = [
            'title' => $data['map'],
            'url' => 'https://board.portal2.sr/chamber/'.$data['map_id'],
            'color' => 295077,
            'thumbnail' => [
                'url' => 'https://raw.githubusercontent.com/p2sr/Portal2Boards/master/public/images/thumbnails/'.$data['map_id'].'.jpg',
            ],
            'fields' => [
                [
                    'name' => 'WR',
                    'value' => $data['score'].' (-'.$data['wr_diff'].')',
                    'inline' => true
                ],
                [
                    'name' => 'By',
                    'value' => '['.self::sanitiseText($data['player']).'](<https://board.portal2.sr/profile/'.$data['player_id'].'>)',
                    'inline' => true
                ],
            ]
        ];
        return (object)$embed;
    }

    public static function sanitiseText($text) {
        return preg_replace('/(\\*|_|`|~)/miu', '\\\\$1', $text);
    }

    private static function CreateTempFile($text) {
        $file = tempnam(sys_get_temp_dir(), 'POST');
        file_put_contents($file, $text);
        return $file;
    }

    private static function DeleteTempFile($file) {
        unlink($file);
    }
}
