FROM composer:2.6.6 AS composer

FROM php:8.3-cli AS base
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN mkdir -p /usr/src/forwarder/etc/ /usr/src/forwarder/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions imap zip

# Add composer binary to the image
COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY composer.* /usr/src/forwarder
COPY bin/console /usr/src/forwarder/bin/
COPY src /usr/src/forwarder/src/

WORKDIR /usr/src/forwarder
RUN composer install --no-dev --no-plugins --classmap-authoritative
CMD ["php", "./bin/console", "start", "-d"]


