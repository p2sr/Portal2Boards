FROM php:7.4-apache
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get upgrade -y
RUN apt-get install -y cron curl unzip
RUN docker-php-ext-install mysqli mbstring xml curl

WORKDIR /var/www/html

# Set up apache
RUN a2enmod rewrite expires headers
RUN a2dissite 000-default.conf

# setup source files
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install
RUN mkdir -p cache demos sessions
RUN chown -R www-data:www-data .

# enable site
RUN ln -s /etc/apache2/sites-available/boards.conf /etc/apache2/sites-enabled/boards.conf
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# enable cron jobs
RUN echo '*/1 * * * * www-data curl -Lk localhost/api/refreshCache.php > /dev/null 2>&1' > /etc/cron.d/board

EXPOSE 80

CMD service cron start && apachectl -D FOREGROUND