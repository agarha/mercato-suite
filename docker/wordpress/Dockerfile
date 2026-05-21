FROM wordpress:6.5-php8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip zip default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php

COPY docker/wordpress/php.ini /usr/local/etc/php/conf.d/mercato.ini
