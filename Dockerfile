# syntax=docker/dockerfile:1

# Node is only copied into the dev image (Vite, npm). Production gets its own stage later.
FROM node:24-bookworm-slim AS node

FROM dunglas/frankenphp:1.12-php8.4-bookworm AS base

ARG UID=1000
ARG GID=1000

RUN install-php-extensions \
        bcmath \
        intl \
        pcntl \
        pdo_pgsql \
        redis \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run as a normal user whose ids match the host, so files written to the
# bind mount (vendor, node_modules, logs) aren't owned by root on Linux.
RUN if ! getent group "${GID}" > /dev/null; then groupadd --gid "${GID}" app; fi \
    && useradd --uid "${UID}" --gid "${GID}" --create-home --shell /bin/bash app \
    && chown -R "${UID}:${GID}" /data/caddy /config/caddy

WORKDIR /app

FROM base AS dev

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && git config --system --add safe.directory /app

ENV NPM_CONFIG_UPDATE_NOTIFIER=false

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY docker/php/dev.ini "$PHP_INI_DIR/conf.d/zz-halfpast.ini"
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile

USER app

EXPOSE 8000
