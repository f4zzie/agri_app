# syntax=docker/dockerfile:1
# AgriTrack – PHP 8.3 + Apache image. Build: docker compose up --build

# ---- 1. Composer dependencies (no dev tools in the runtime image) ----
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --optimize-autoloader --ignore-platform-reqs --no-scripts

# ---- 2. Runtime ----
FROM php:8.3-apache

# pdo_mysql for the app; gd improves Dompdf image support
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd \
 && a2enmod rewrite headers remoteip \
 && apt-get purge -y --auto-remove \
 && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'upload_max_filesize=10M\npost_max_size=12M\nexpose_php=Off\n' > "$PHP_INI_DIR/conf.d/agritrack.ini"

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor

RUN mkdir -p public/uploads/avatars public/uploads/disease_checks \
 && chown -R www-data:www-data public/uploads \
 && chmod +x docker/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["apache2-foreground"]
