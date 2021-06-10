<?php
class Discord {
    const API = 'https://discordapp.com/api/v6';
    private static $id;
    private static $token;
    private static $username;
    private static $avatar;
    private static $embed_icon;

    public static function init() {
        $secret = json_decode(file_get_contents(ROOT_PATH.'/secret/discord.json'));
        self::$id = $secret->id;
        self::$token = $secret->token;
        self::$username = 'Portal2Boards';
        self::$avatar = 'https://raw.githubusercontent.com/iVerb1/Portal2Boards/master/public/images/portal2boards_avatar.jpg';
        self::$embed_icon = 'https://raw.githubusercontent.com/iVerb1/Portal2Boards/master/public/images/portal2boards_icon.png';
    }

    public static function sendWebhook($data) {
        $embed = self::buildEmbed($data);
        $payload = [
            'username' => self::$username,
            'avatar_url' => self::$avatar,
            'embeds' => [ $embed ]
        ];
        $post = [
            'payload_json' => json_encode($payload)
        ];
        $ch = curl_init(Discord::API.'/webhooks/'.self::$id.'/'.self::$token);
        curl_setopt($ch, CURLOPT_USERAGENT, 'board.iverb.me (https://github.com/iVerb1/Portal2Boards)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_exec($ch);
        curl_close($ch);
    }

    public static function buildEmbed($data) {
        $embed = [
            'title' => 'New Portal 2 World Record',
            'url' => 'https://board.portal2.sr',
            'color' => 295077,
            'timestamp' => $data['timestamp']->format('Y-m-d\TH:i:s.u\Z'),
            'footer' => [
                'icon_url' => self::$embed_icon,
                'text' => 'board.iverb.me'
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
                    'value' => $data['map'],
                    'inline' => true
                ],
                [
                    'name' => 'Time',
                    'value' => $data['score'].' (-'.$data['wr_diff'].')',
                    'inline' => true
                ],
                [
                    'name' => 'Player',
                    'value' => self::sanitiseText($data['player']),
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
}
Discord::init();