FROM node:22-alpine AS web

WORKDIR /web
COPY web/package.json web/package-lock.json ./
RUN npm ci
COPY web/ ./
ENV VITE_API_URL=/api/v1
ENV VITE_DEMO=1
RUN npm run build


FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip libzip-dev libpng-dev libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo_sqlite zip gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

COPY api/ ./
RUN composer dump-autoload --optimize --no-dev

COPY --from=web /web/dist/ ./public/

COPY deploy/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache database

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf

ENV APP_ENV=demo \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/database.sqlite

CMD ["entrypoint"]
