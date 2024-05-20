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

# List available recipes.
help:
    just -lu

# Start all containers. Accepts arguments like `-d` to start in background.
up *args='':
    docker compose up $@

# Stop all containers.
down:
    docker compose down

# Build the server image.
build:
    docker compose build

# Start and recreate containers.
reload:
    docker compose up -d --force-recreate

# Refresh leaderboard cache.
cache:
    docker exec -u www-data -ti {{project}}-server curl -Lk localhost/api/refreshCache.php

# Fetch new scores from Steam.
update:
    docker exec -u www-data -ti {{project}}-server curl -Lk localhost/api/fetchNewScores.php

# Update Steam profiles.
update-profiles:
    docker exec -u www-data -ti {{project}}-server php -f /var/www/html/util/fetchImportantProfileData.php

# Open shell in server container.
server-debug:
    docker exec -u www-data -ti {{project}}-server bash

# Open shell in server container.
debug: server-debug

# Restart server container.
server-restart:
    docker container restart {{project}}-server

# Stop server container.
server-stop:
    docker container stop {{project}}-server

# Connect to database.
db:
    docker exec -ti {{project}}-db bash -c 'printf {{cnf}} > /etc/my.cnf' && docker exec -ti {{project}}-db mariadb

# Open shell in database container.
db-debug:
    docker exec -ti {{project}}-db bash

# Restart database container.
db-restart:
    docker container restart {{project}}-db

# Stop database container.
db-stop:
    docker container stop {{project}}-db

# Dump and compress a backup of the database.
db-dump:
    docker exec -ti {{project}}-db bash -c 'mariadb-dump {{dump_options}} | gzip -8 > /backups/${MARIADB_DATABASE}_dump_$(date +%Y-%m-%d-%H.%M.%S).sql.gz'

# Only dump a backup of the database.
db-dump-raw:
    docker exec -ti {{project}}-db bash -c 'mariadb-dump {{dump_options}} > /backups/${MARIADB_DATABASE}_dump_$(date +%Y-%m-%d-%H.%M.%S).sql'
