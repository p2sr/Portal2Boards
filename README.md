# board.portal2.sr

Challenge Mode leaderboard for Portal 2 speedrunners.

## Development

### Requirements

- [Docker Engine] | [Reference](https://docs.docker.com/compose/reference/)
- [mkcert]
- [Steam Web API Key]

[Docker Engine]: https://docs.docker.com/engine/install
[mkcert]: https://github.com/FiloSottile/mkcert
[Steam Web API Key]: https://steamcommunity.com/dev

### Setup

- Project setup with `chmod +x setup && ./setup dev`
- Start the containers with `docker compose up`
- Add the host entry `127.0.0.1 board.portal2.local` to `/etc/hosts`

The server should now be available at: `https://board.portal2.local`

### Overview of .env

This is used by Dockerfile and docker-compose.yml.

|Variable|Description|
|---|---|
|PROJECT_NAME|This name is used as a prefix for the containers.|
|SERVER_NAME|The domain name which should be set before building the image. Docker will use it to mount the correct apache config file which links to the SSL certificates.|
|HTTP_PORT|The unsafe HTTP port of the local host. Change it if a different port is needed e.g. reverse proxy|
|HTTPS_PORT|The safe HTTPS port of the local host. Change it if a different port is needed e.g. reverse proxy|
|DATABASE_PORT|The MariaDB database port of the local host. NOTE: Make sure that the docker compose file does not expose the server to an unwanted address. By default it's mapped to `127.0.0.1`.|
|MARIADB_ROOT_PASSWORD|The root's password of the MariaDB database.|
|APT_PACKAGES|Optional apt-packages to build the server image. The image should be kept as small as possible but sometimes it is useful to install some packages (e.g. `vim`, `htop` etc.) in order to debug problems more quickly.|

### Overview of .config.json

This is used by the server.

|Key|Description|
|---|---|
|database_host|Address of the database. Docker creates a link to the container under the `database` alias.|
|database_port|Port of the database.|
|database_user|User login name for database.|
|database_pass|User password for database access.|
|database_name|The database name.|
|discord_webhook_id|The webhook ID for sending wr updates to a Discord channel.|
|discord_webhook_token|The webhook token for sending wr updates to a Discord channel.|
|discord_webhook_mdp|Discord webhook URL for sending [mdp] data to a Discord channel.|
|steam_api_key|The Steam Web API Key for fetching profile data.|

[mdp]: https://github.com/p2sr/mdp

## Credits

* Originally developed and designed by [ncla] (2014-2015)
* Further development by [iVerb] (2016-2020)

[ncla]: https://github.com/ncla/Portal-2-Leaderboard
[iVerb]: https://github.com/iVerb1/Portal2Boards

## License

Software licensed under CC Attribution - Non-commercial license.
https://creativecommons.org/licenses/by-nc/4.0/legalcode
