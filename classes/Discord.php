<?php
class Discord {
    const API = 'https://discordapp.com/api';
    private static $id;
    private static $token;
    private static $username;
    private static $avatar;
    private static $embed_icon;
    private static $mdp;

    public static function init() {
        $config = Config::get();
        self::$id = $config->discord_webhook_id;
        self::$token = $config->discord_webhook_token;
        self::$username = 'Portal2Boards';
        self::$avatar = 'https://raw.githubusercontent.com/p2sr/Portal2Boards/master/public/images/portal2boards_avatar.jpg';
        self::$embed_icon = 'https://raw.githubusercontent.com/p2sr/Portal2Boards/master/public/images/portal2boards_icon.png';
        self::$mdp = $config->discord_webhook_mdp;
    }

    public static function sendMdpWebhook($data, $demoName, $text, $err = null){
        try {
            //Debug::log("Trying to sending Webhook for mdp");
            $payload = [
                'username' => 'Demo Parse Bot',
                'avatar_url' => self::$avatar,
                'content' => 'Link to change log: [Click Here](https://board.portal2.sr/changelog?id='.$data['id'].')'
            ];
            $tempFile = self::CreateTempFile($text);
            $tempErrFile = self::CreateTempFile($err);
            $post = [
                'files[0]' => curl_file_create($tempFile, 'text/plain', $demoName.'.txt'),
                'payload_json' => json_encode($payload)
            ];
    
            if($err != null){
                $post['files[1]'] = curl_file_create($tempErrFile, 'text/plain', $demoName.'_err.txt');
            }
            //Debug::log(json_encode($payload));
            $ch = curl_init(self::$mdp);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // DEV TESTING
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
        $ch = curl_init(Discord::API.'/webhooks/'.self::$id.'/'.self::$token);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // DEV TESTING
        curl_setopt($ch, CURLOPT_USERAGENT, 'board.portal2.sr (https://github.com/p2sr/Portal2Boards)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_exec($ch);
        curl_close($ch);
        Debug::log("Sending Webhook - Finished");
    }

    public static function buildEmbed($data) {
        $embed = [
            'title' => 'New Portal 2 World Record',
            'url' => 'https://board.portal2.sr',
            'color' => 295077,
            'timestamp' => $data['timestamp']->format('Y-m-d\TH:i:s.u\Z'),
            'footer' => [
                'icon_url' => self::$embed_icon,
                'text' => 'board.portal2.sr'
            ],
            'image' => [
                'url' => 'https://board.portal2.sr/images/chambers_full/'.$data['map_id'].'.jpg'
            ],
            'author' => [
                'name' => $data['player'],
                'url' => 'https://board.portal2.sr/profile/'.$data['player_id'],
                'icon_url' => $data['player_avatar']
            ],
            'fields' => [
                [
                    'name' => 'Map',
                    'value' => '['.$data['map'].'](https://board.portal2.sr/chamber/'.$data['map_id'].')',
                    'inline' => true
                ],
                [
                    'name' => 'Time',
                    'value' => $data['score'].' (-'.$data['wr_diff'].')',
                    'inline' => true
                ],
                [
                    'name' => 'Player',
                    'value' => '['.self::sanitiseText($data['player']).'](https://board.portal2.sr/profile/'.$data['player_id'].')',
                    'inline' => true
                ],
                [
                    'name' => 'Date',
                    'value' => $data['timestamp']->format('Y-m-d H:i:s'),
                    'inline' => true
                ],
                [
                    'name' => 'Demo File',
                    'value' => '[Download](https://board.portal2.sr/getDemo?id='.$data['id'].')',
                    'inline' => true
                ]
            ]
        ];
        if ($data['yt'] != NULL && $data['yt'] != '') {
            array_push($embed['fields'], [
                'name' => 'Video Link',
                'value' => '[Watch](https://youtu.be/'.$data['yt'].')',
                'inline' => true
            ]);
        }
        if ($data['comment'] != NULL && $data['comment'] != '') {
            array_push($embed['fields'], [
                'name' => 'Comment',
                'value' => '*'.self::sanitiseText($data['comment']).'*',
                'inline' => false
            ]);
        }
        return (object)$embed;
    }

    public static function sanitiseText($text) {
        return preg_replace('/(\\*|_|`|~)/miu', '\\\\$1', $text);
    }

    private static function CreateTempFile($text){
        $file = tempnam(sys_get_temp_dir(), 'POST');
        file_put_contents($file, $text);
        return $file;
    }

    private static function DeleteTempFile($file){
        unlink($file);
    }
}
Discord::init();