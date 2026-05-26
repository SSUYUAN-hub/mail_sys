FROM php:8.3-apache

# 1. 透過官方提供的安全指令，下載擴充功能安裝器
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# 2. 基本的系統工具還是用 apt 安裝
RUN apt-get update && apt-get install -y \
    unzip git \
    && rm -rf /var/lib/apt/lists/*

# 3. 讓安裝器自動處理 gd, zip, mysqli, pdo_mysql 以及「極度難搞的 imap」
# 它會自動幫你補齊所有底層所需的 libpng, libjpeg, libkrb5-dev 以及 imap 補丁！
RUN install-php-extensions gd zip mysqli pdo pdo_mysql imap

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

COPY . /var/www/html/
RUN composer dump-autoload --optimize
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80