FROM php:8.4-apache
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get upgrade -y
RUN apt-get install -y cron curl unzip
RUN curl -fsSL https://deno.land/install.sh | DENO_INSTALL=/usr/local sh
# mbstring, xml and curl are built into the official PHP 8 images; only mysqli needs adding.
RUN docker-php-ext-install mysqli

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
# The official image makes /var/www/html world-writable with the sticky bit (1777). With the kernel's
# fs.protected_regular, www-data then can't append to files it doesn't own there, like a mounted
# debug.txt. It owns the folder, so it needs no more than 755.
RUN chmod 755 /var/www/html
RUN chmod +x /var/www/html/util/mdp/mdp

# enable site
RUN ln -s /etc/apache2/sites-available/boards.conf /etc/apache2/sites-enabled/boards.conf
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# enable cron jobs
RUN echo '*/1 * * * * www-data curl -Lk localhost/api/refreshCache.php > /dev/null 2>&1' > /etc/cron.d/board

EXPOSE 80

CMD service cron start && apachectl -D FOREGROUND
