FROM php:8.3-fpm AS php83-base
ARG UID=1000 GID=1000

WORKDIR /var/www/html

# Example: FROM php:8.3-fpm-bookworm
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      git curl unzip zip \
      libicu-dev libzip-dev \
      libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
      libonig-dev libxml2-dev libpq-dev libsqlite3-dev \
      $PHPIZE_DEPS \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
      gd intl mbstring pdo_mysql bcmath pcntl zip opcache \
    ; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apt-get purge -y --auto-remove $PHPIZE_DEPS; \
    rm -rf /var/lib/apt/lists/* /tmp/pear

# PHP config
COPY ./docker/zulors/php.ini /usr/local/etc/php/conf.d/zulors-app.ini
COPY ./docker/zulors/opcache.ini /usr/local/etc/php/conf.d/zulors-opcache.ini

# Composer (copy: no need to curl-install again)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# App user (rename to sail if you prefer)
RUN groupadd -g "${GID}" sail && useradd -m -u "${UID}" -g sail sail
USER sail

# -------- Workers / CLI (Horizon, queue, Reverb) — SLIM
FROM php83-base AS php-runtime
# nothing extra

# -------- Web/PHP-FPM — with ffmpeg (+ optional Node for asset builds)
FROM php83-base AS php-web
USER root
COPY ./services/ffmpeg/bin/ffmpeg /usr/local/bin/ffmpeg
COPY ./services/ffmpeg/bin/ffprobe /usr/local/bin/ffprobe
RUN chmod +x /usr/local/bin/ffmpeg /usr/local/bin/ffprobe
RUN apt-get update && apt-get install -y --no-install-recommends default-mysql-client && rm -rf /var/lib/apt/lists/*


USER sail
