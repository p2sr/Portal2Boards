FROM php:7.4-apache
COPY --from=composer /usr/bin/composer /usr/bin/composer

# php:7.4 is based on Debian 11 (bullseye), which is EOL and moved to archive.debian.org
RUN printf '%s\n' \
        'deb http://archive.debian.org/debian bullseye main' \
        'deb http://archive.debian.org/debian-security bullseye-security main' \
        > /etc/apt/sources.list \
    && echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99archive
RUN apt-get update && apt-get upgrade -y
RUN apt-get install -y cron curl unzip libonig-dev libxml2-dev libcurl4-openssl-dev
RUN curl -fsSL https://deno.land/install.sh | DENO_INSTALL=/usr/local sh
RUN docker-php-ext-install mysqli mbstring xml curl

WORKDIR /var/www/html

# Set up apache
RUN a2enmod rewrite expires headers
RUN a2dissite 000-default.conf

# setup source files
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install
RUN deno cache --config util/demo_validator/deno.json util/demo_validator/parse_demo.ts
RUN mkdir -p cache demos sessions
RUN chown -R www-data:www-data .
RUN chmod +x /var/www/html/util/mdp/mdp

# enable site
RUN ln -s /etc/apache2/sites-available/boards.conf /etc/apache2/sites-enabled/boards.conf
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# enable cron jobs
RUN echo '*/1 * * * * www-data curl -Lk localhost/api/refreshCache.php > /dev/null 2>&1' > /etc/cron.d/board

EXPOSE 80

CMD service cron start && apachectl -D FOREGROUND
