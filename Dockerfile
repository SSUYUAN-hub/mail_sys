FROM php:8.3-apache

# 安裝 IMAP 所需的新版依賴套件（將 libc-client2007e-dev 替換為 libssl-dev 與 libkrb5-dev 等）
RUN apt-get update && apt-get install -y \
    unzip git libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    libssl-dev libkrb5-dev \
    && rm -rf /var/lib/apt/lists/*

# 修正：PHP 8+ 在設定 IMAP 時，通常不需額外指定 --with-imap-ssl，
# 且新版環境若遇編譯問題，常需指定 Kerberos 路径或透過 PHP 核心內建機制。
# 這裡為你加上正確的配置參數：
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