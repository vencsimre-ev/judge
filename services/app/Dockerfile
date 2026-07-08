FROM composer:2.7 AS php_builder
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-progress --no-interaction --ignore-platform-reqs --no-scripts

FROM php:8.3-apache

ARG UID=1000
ARG GID=1000

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip zip default-mysql-client libzip-dev libicu-dev \
    && docker-php-ext-install pdo_mysql intl zip \
    && a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

RUN groupmod -g ${GID} www-data && usermod -o -u ${UID} www-data

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
COPY .docker/php-apache/custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY .docker/php-apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=php_builder /app/vendor /var/www/html/vendor

RUN mkdir -p storage/app/public/score-sheets \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod +x docker-entrypoint.dev.sh artisan \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["./docker-entrypoint.dev.sh"]
