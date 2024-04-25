set dotenv-load
set positional-arguments

project := env_var('PROJECT_NAME')

cnf := replace_regex('[client]
user=$MARIADB_USER
password=$MARIADB_PASSWORD
[clientroot]
user=root
password=$MARIADB_ROOT_PASSWORD
[MYSQL]
database=$MARIADB_DATABASE', '[\n]', "\\\\n")

dump_options := '--defaults-group-suffix=root --hex-blob --net-buffer-length 100K --routines --databases $MARIADB_DATABASE'

check:
    vendor/bin/phpstan analyse -l 9 api classes util views

server-debug:
    docker exec -ti {{project}}-server sh

server-restart:
    docker container restart {{project}}-server

server-stop:
    docker container stop {{project}}-server

build:
    docker compose build

up *args='':
    docker compose up "$@"

down:
    docker compose down

db:
    docker exec -ti {{project}}-db bash -c 'printf {{cnf}} > /etc/my.cnf' && docker exec -ti {{project}}-db mariadb

db-debug:
    docker exec -ti {{project}}-db bash

db-restart:
    docker container restart {{project}}-db

db-stop:
    docker container stop {{project}}-db

db-dump:
    docker exec -ti {{project}}-db bash -c 'mysqldump {{dump_options}} | gzip -8 > /backups/${MARIADB_DATABASE}_dump_$(date +%Y-%m-%d-%H.%M.%S).sql.gz'

db-dump-raw:
    docker exec -ti {{project}}-db bash -c 'mysqldump {{dump_options}} > /backups/${MARIADB_DATABASE}_dump_$(date +%Y-%m-%d-%H.%M.%S).sql'
