FROM composer:2 AS composer

FROM wordpress:6.5-php8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates git unzip zip default-mysql-client \
    && update-ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# Default to development ini; production builds override via MERCATO_PHP_PROFILE=prod
# (see docker/wordpress/php.prod.ini)
ARG MERCATO_PHP_PROFILE=dev
COPY docker/wordpress/php.${MERCATO_PHP_PROFILE}.ini /usr/local/etc/php/conf.d/mercato.ini
