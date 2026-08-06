FROM php:8.4-apache

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts

COPY backend/ ./
RUN composer run-script post-install-cmd \
    && mkdir -p var/cache var/log var/tailwind \
    && chown -R www-data:www-data var

COPY docker/php/entrypoint.sh /usr/local/bin/mon-allure-entrypoint
RUN chmod +x /usr/local/bin/mon-allure-entrypoint

ENTRYPOINT ["mon-allure-entrypoint"]
CMD ["apache2-foreground"]
