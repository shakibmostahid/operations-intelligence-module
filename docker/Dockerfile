FROM php:8.5-fpm-bookworm

ARG NODE_VERSION=22

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libicu-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-install \
        bcmath \
        intl \
        pcntl \
        pdo_mysql \
        zip \
    && curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/configs/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/entrypoints/app-entrypoint.sh /usr/local/bin/app-entrypoint
COPY --chown=www-data:www-data codes /var/www/html

RUN rm -f public/hot public/fonts-manifest.dev.json \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && npm ci \
    && npm run build \
    && rm -f public/hot public/fonts-manifest.dev.json \
    && rm -rf node_modules \
    && chmod +x /usr/local/bin/app-entrypoint

EXPOSE 9000

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
