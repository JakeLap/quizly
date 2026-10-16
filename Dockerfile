# syntax=docker/dockerfile:1

# Quizly's production image, e.g. for Coolify's Dockerfile build pack (see
# deploy/COOLIFY.md). By default one container runs all of it under
# supervisord:
#
#   the site        Octane (Swoole), port 8000
#   live updates    Reverb websockets, port 8080
#   the game clock  moves games along
#   the scheduler   housekeeping (expiring rooms, pruning old games)
#
# Each can also run in a container of its own: pass web, reverb, clock or
# scheduler as the command (see docker/entrypoint.sh).

ARG PHP_VERSION=8.5
ARG NODE_VERSION=24

# --- PHP with the extensions the app needs ------------------------------------
FROM php:${PHP_VERSION}-cli-bookworm AS php

COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions intl pcntl pdo_pgsql redis swoole zip \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-quizly.ini

WORKDIR /app

# --- Composer packages (without the development tools) ------------------------
FROM php AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

# --- npm packages -------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS node_modules

WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

# --- Front-end build ----------------------------------------------------------
# The build generates the typed routes (Wayfinder) with `php artisan`, so it
# needs PHP and the app as well as Node.
FROM vendor AS assets

COPY --from=node_modules /usr/local/bin/node /usr/local/bin/node
COPY --from=node_modules /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
COPY --from=node_modules /app/node_modules ./node_modules

RUN npm run build

# --- The app ------------------------------------------------------------------
FROM php AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends supervisor \
    && rm -rf /var/lib/apt/lists/*

# Everything that isn't a secret or a domain. The rest comes from the
# environment: APP_KEY, APP_URL, DB_URL, REDIS_URL, REVERB_APP_KEY,
# REVERB_APP_SECRET, REVERB_PUBLIC_HOST and REVERB_ALLOWED_ORIGINS.
ENV APP_NAME=Quizly \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=warning \
    DB_CONNECTION=pgsql \
    REDIS_CLIENT=phpredis \
    CACHE_STORE=redis \
    SESSION_DRIVER=redis \
    SESSION_SECURE_COOKIE=true \
    QUEUE_CONNECTION=sync \
    BROADCAST_CONNECTION=reverb \
    OCTANE_SERVER=swoole \
    REVERB_APP_ID=quizly \
    REVERB_HOST=127.0.0.1 \
    REVERB_PORT=8080 \
    REVERB_SCHEME=http \
    REVERB_PUBLIC_PORT=443 \
    REVERB_PUBLIC_SCHEME=https \
    REVERB_SCALING_ENABLED=true

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app ./
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# www-data's home holds tools' settings, e.g. tinker's history.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /var/www/.config \
    && chown -R www-data:www-data storage bootstrap/cache /var/www/.config \
    && chmod +x docker/entrypoint.sh

USER www-data

EXPOSE 8000 8080

# Healthy once the site answers and the websocket server accepts connections.
# The first start runs the migrations and imports the questions first.
HEALTHCHECK --interval=10s --timeout=5s --start-period=180s --retries=5 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8000/up") !== false && @fsockopen("127.0.0.1", 8080) ? 0 : 1);'

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["all"]
