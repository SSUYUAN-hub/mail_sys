FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    unzip git libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    libssl-dev libc-client2007e-dev libkrb5-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install mysqli pdo pdo_mysql gd zip imap

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

COPY . /var/www/html/
RUN composer dump-autoload --optimize
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80