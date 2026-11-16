FROM php:5.6-apache

# Ekstensi mysql_* (mysql_connect, dst.) masih tersedia di PHP 5.6
RUN docker-php-ext-install mysql

COPY php.ini /usr/local/etc/php/conf.d/app.ini
