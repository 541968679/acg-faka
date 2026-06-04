FROM composer:2 AS composer

FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl gd opcache pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/app/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/app/entrypoint.sh /usr/local/bin/acgfaka-entrypoint

RUN chmod +x /usr/local/bin/acgfaka-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["acgfaka-entrypoint"]
CMD ["apache2-foreground"]

