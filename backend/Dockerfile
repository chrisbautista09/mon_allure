FROM php:8.4-apache-bookworm

ENV APP_ENV=prod \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd opcache pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

COPY . .
RUN composer run-script post-install-cmd \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile \
    && chmod +x docker/entrypoint.sh \
    && chown -R www-data:www-data var public/assets

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]
