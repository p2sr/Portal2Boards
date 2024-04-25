FROM ubuntu:22.04

WORKDIR /var/www/html

ARG SERVER_NAME
ARG APT_PACKAGES
ARG DEBIAN_FRONTEND=noninteractive

# install dependencies
RUN apt-get update
RUN apt-get install -y software-properties-common
RUN add-apt-repository ppa:ondrej/php
RUN apt-get update && apt-get upgrade -y
RUN apt-get install -y ${APT_PACKAGES} cron curl php7.4 php7.4-cli php7.4-curl php7.4-mysql apache2 libapache2-mod-php7.4

# install composer
RUN apt-get install -y unzip
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN mv composer.phar /usr/local/bin/composer

# setup apache
RUN a2enmod rewrite expires headers ssl
RUN a2dissite 000-default.conf
RUN rm /var/www/html/index.html

# setup source files
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install
RUN mkdir -p cache demos sessions /etc/apache2/ssl
RUN chown -R www-data:www-data .

# enable site
RUN ln -s /etc/apache2/sites-available/${SERVER_NAME}.conf /etc/apache2/sites-enabled/${SERVER_NAME}.conf
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# enable cron job
RUN echo '*/1 * * * * www-data php /var/www/html/api/refreshCache.php > /dev/null 2>&1' > /etc/cron.d/board

EXPOSE 80 443

CMD service cron start && apachectl -D FOREGROUND
