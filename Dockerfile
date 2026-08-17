FROM node:22-bookworm-slim AS frontend
WORKDIR /build
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --ignore-scripts
COPY resources ./resources
RUN npm run build

FROM php:8.3-apache-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd intl opcache pdo_pgsql zip \
    && a2enmod rewrite headers \
    && sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php-runtime.ini /usr/local/etc/php/conf.d/zz-moeller-lars-runtime.ini
COPY docker/apache-mpm.conf /etc/apache2/mods-available/mpm_prefork.conf

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
COPY artisan ./artisan
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY resources/views ./resources/views
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
    && composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM php-base AS runtime
ARG APP_GIT_SHA=unknown
ENV APP_RELEASE_SHA=${APP_GIT_SHA}
LABEL org.opencontainers.image.title="moeller-lars" \
      org.opencontainers.image.source="https://github.com/Wiiii90/moeller-lars" \
      org.opencontainers.image.revision="${APP_GIT_SHA}"

WORKDIR /var/www/html
COPY . .
COPY --from=dependencies /var/www/html/vendor ./vendor
COPY --from=frontend /build/public/build ./public/build
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/production-entrypoint.sh /usr/local/bin/moeller-lars-entrypoint

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private/originals storage/app/private/variants bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/moeller-lars-entrypoint \
    && printf '{"git_sha":"%s"}\n' "$APP_GIT_SHA" > /app-release.json

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 CMD curl --fail --silent --show-error http://127.0.0.1:8080/up >/dev/null || exit 1
ENTRYPOINT ["/usr/local/bin/moeller-lars-entrypoint"]
CMD ["apache2-foreground"]
