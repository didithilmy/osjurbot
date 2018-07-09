FROM php:7.2-apache
RUN docker-php-ext-install pdo pdo_mysql bcmath
RUN a2enmod rewrite
COPY . /var/www/html

MAINTAINER aditya.hilmy@students.itb.ac.id