FROM node:22-bookworm-slim AS frontend
WORKDIR /build
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --ignore-scripts
COPY resources ./resources
RUN npm run build

FROM php:8.3-apache-bookworm AS php-base

# Shared by application, release and local-preview; CPAN production checksum.
# Bookworm's 12.57 cannot write IPTC/PLUS DataMining (requires >=12.67).
ARG EXIFTOOL_VERSION=13.55
ARG EXIFTOOL_SHA256=5f4c81d34ad406538c2871ad72dbfceb5d9b412b2f16cbbeb4d712d270846667

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libapache2-mod-xsendfile \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libzip-dev \
        perl \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd intl opcache pdo_pgsql zip \
    && a2enmod rewrite headers xsendfile \
    && sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && rm -rf /var/lib/apt/lists/*

# Verify source digest, exact tool release and availability of the writable
# IPTC/PLUS DataMining property. This runs at image build, never on requests.
RUN set -eu; \
    curl --fail --silent --show-error --location \
      "https://www.cpan.org/authors/id/E/EX/EXIFTOOL/Image-ExifTool-${EXIFTOOL_VERSION}.tar.gz" \
      --output /tmp/image-exiftool.tar.gz; \
    printf '%s  %s\n' "$EXIFTOOL_SHA256" /tmp/image-exiftool.tar.gz | sha256sum --check --status; \
    mkdir -p /opt; \
    tar -xzf /tmp/image-exiftool.tar.gz -C /opt; \
    ln -s "/opt/Image-ExifTool-${EXIFTOOL_VERSION}/exiftool" /usr/local/bin/exiftool; \
    rm /tmp/image-exiftool.tar.gz; \
    test "$(exiftool -ver)" = "$EXIFTOOL_VERSION"; \
    exiftool -listw | grep -qw DataMining

COPY docker/php-runtime.ini /usr/local/etc/php/conf.d/zz-moeller-lars-runtime.ini
COPY docker/apache-mpm.conf /etc/apache2/mods-available/mpm_prefork.conf

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

# Composer scripts boot the Laravel application. Run them only after the complete
# candidate source exists so service providers may safely reference project resources.
FROM dependencies AS application
COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache \
    && composer dump-autoload --no-dev --no-interaction --optimize

FROM php-base AS runtime
ARG APP_GIT_SHA=unknown
ENV APP_RELEASE_SHA=${APP_GIT_SHA}
LABEL org.opencontainers.image.title="moeller-lars" \
      org.opencontainers.image.source="https://github.com/Wiiii90/moeller-lars" \
      org.opencontainers.image.revision="${APP_GIT_SHA}"

WORKDIR /var/www/html
COPY . .
COPY --from=application /var/www/html/vendor ./vendor
COPY --from=application /var/www/html/bootstrap/cache ./bootstrap/cache
# Ship the patched single-owner Filament support runtime in production too.
COPY --from=application /var/www/html/public/js/filament ./public/js/filament
COPY --from=frontend /build/public/build ./public/build
COPY --from=frontend /build/resources/views/filament/generated/analytics-world-map.blade.php ./resources/views/filament/generated/analytics-world-map.blade.php
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


# Local browser preview reuses the prepared application/dependency stages directly.
# Do not route it through the release runtime: that would copy the candidate source
# and vendor tree a second time and force a large runtime layer export on every edit.
FROM application AS local-preview
ARG APP_GIT_SHA=unknown
ENV APP_RELEASE_SHA=${APP_GIT_SHA} \
    APP_ENV=local \
    MOELLER_LARS_PERSISTENT_PREVIEW=1 \
    MEDIA_STORAGE_QUOTA_BYTES=5000000000

COPY --from=frontend /build/public/build ./public/build
COPY --from=frontend /build/resources/views/filament/generated/analytics-world-map.blade.php ./resources/views/filament/generated/analytics-world-map.blade.php
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 CMD curl --fail --silent --show-error http://127.0.0.1:8080/up >/dev/null || exit 1
ENTRYPOINT ["docker-php-entrypoint"]
CMD ["sh", "-lc", "php artisan migrate --force --no-interaction && php artisan optimize:clear && php artisan media:measure-capacity --no-interaction && exec apache2-foreground"]

# Keep the production runtime as the default image when no explicit target is requested.
FROM runtime AS release
