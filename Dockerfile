FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    ;

RUN curl -sSLf \
        -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
    @composer \
    zip \
    http \
    ;

COPY . /app/.

RUN cd /app && \
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --no-progress --no-suggest --optimize-autoloader --no-scripts && \
    COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --optimize --no-dev --classmap-authoritative &&  \
    APP_ENV=prod ./bin/console secrets:decrypt-to-local --force && \
    COMPOSER_ALLOW_SUPERUSER=1 composer dump-env prod && \
    rm -rf /root/.composer/cache

WORKDIR /app
