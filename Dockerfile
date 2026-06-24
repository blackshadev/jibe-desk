FROM existenz/webstack:8.5 AS base

RUN apk -U --no-cache add  \
    php85-phar  \
    php85-bcmath \
    php85-curl \
    php85-dom \
    php85-exif \
    php85-gd \
    php85-iconv \
    php85-intl \
    php85-mbstring \
    php85-openssl \
    php85-pcntl \
    php85-pdo_pgsql \
    php85-phar \
    php85-posix \
    php85-session \
    php85-xml \
    php85-zip \
    php85-tokenizer \
    php85-fileinfo \
    php85-xmlwriter \
    php85-xmlreader \
    php85-simplexml

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# === dev ===
FROM base AS dev

ARG UID=255
ARG GID=255

RUN apk --no-cache add bash curl git shadow php85-cli php85-sqlite3 php85-pdo_sqlite php85-pecl-xdebug watchexec
RUN groupmod --gid 1020 dialout || true
RUN groupmod --gid ${GID} php && \
    usermod --uid ${UID}  php
RUN ln -s /usr/bin/php85 /usr/bin/php
RUN export BUN_INSTALL=/usr/local/bun && \
    curl -fsSL https://bun.com/install | bash && \
    ln -s /usr/local/bun/bin/bun /usr/bin/bun && \
    chmod +rx /usr/local/bun/

# === production-builder ===
FROM dev AS production-builder

COPY --chown=php:nginx ./composer.* /www
RUN composer install --prefer-dist --no-progress --no-interaction --no-scripts --no-dev
COPY --chown=php:nginx ./ /www
RUN composer dump-autoload --optimize --strict-psr

# === production ===
FROM base AS production

COPY --from=production-builder --chown=php:nginx www/ /www
RUN chmod -R 775 /www/storage
RUN php /www/artisan storage:link
RUN php /www/artisan filament:assets
RUN php /www/artisan autowire:cache
