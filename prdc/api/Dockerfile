# syntax=docker/dockerfile:1.7
#
# Two-stage build.
#   builder ← composer install with dev deps (for migrations tooling)
#   runtime ← PHP-FPM + prod deps only, non-root user
#
# The same image is run by two services in docker-compose:
#   api     → php-fpm (HTTP)
#   worker  → bin/console messenger:consume (background)

# ─────────────────────────────────────────────────────────────────────
FROM composer:2 AS composer

# ─────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-bookworm AS builder

# System deps: libpq for pdo_pgsql, icu-dev for intl, git for composer.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libpq-dev libzip-dev unzip git \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_pgsql intl zip opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
    && rm -rf ~/.composer

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ─────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libpq-dev libzip-dev supervisor cron \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_pgsql intl zip opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /var/lib/apt/lists/*

# Opcache tuning for production. Preloading could be added later by
# pointing opcache.preload at config/preload.php.
RUN { \
      echo "opcache.enable=1"; \
      echo "opcache.memory_consumption=192"; \
      echo "opcache.interned_strings_buffer=16"; \
      echo "opcache.max_accelerated_files=20000"; \
      echo "opcache.validate_timestamps=0"; \
      echo "realpath_cache_size=4096K"; \
      echo "realpath_cache_ttl=600"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Run as non-root. Container still writes to /var/batches via a volume
# owned by this user.
RUN groupadd --system --gid 1000 app \
 && useradd --system --uid 1000 --gid app --home /app --shell /bin/false app \
 && mkdir -p /var/batches /var/run/fpm \
 && chown app:app /var/batches /var/run/fpm

# PHP-FPM listens on a Unix socket inside /var/run/fpm — Caddy mounts
# this directory as a shared volume. No TCP listener means no IP-level
# access path; only the Caddy container can reach FPM.
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-prdc.conf
# Remove the default www.conf that would otherwise compete on listen address.
RUN rm -f /usr/local/etc/php-fpm.d/www.conf.default /usr/local/etc/php-fpm.d/www.conf

# Worker process supervisor: runs the Messenger consumer + cron in
# parallel. The api service uses the default CMD (php-fpm); the worker
# service overrides command to invoke supervisord.
COPY docker/supervisord.conf /etc/supervisor/conf.d/worker.conf
COPY docker/crontab          /etc/cron.d/prdc
COPY docker/run-purge.sh     /app/docker/run-purge.sh
RUN chmod 0644 /etc/cron.d/prdc \
 && chmod 0755 /app/docker/run-purge.sh \
 && crontab -u app /etc/cron.d/prdc

WORKDIR /app
COPY --from=builder --chown=app:app /app /app

USER app
CMD ["php-fpm"]
