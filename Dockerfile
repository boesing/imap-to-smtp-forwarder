FROM ghcr.io/mlocati/php-extension-installer:latest@sha256:ac4b252c153a9f6de5578ff2c417790aba836bb49855543144606033eceda253 AS php-extension-installer
FROM composer/composer:2-bin AS composer

# Runtime base: PHP and the extensions required by the application, nothing else.
FROM php:8.5-cli-alpine@sha256:e899a75c775804e5466dbd9c96b31f8b6c6ae2ddb053a8466223a0c32992ff8e AS runtime
RUN --mount=type=bind,from=php-extension-installer,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
    install-php-extensions imap intl

# Used by `compose.yml` to run the test suites: adds composer, which installs the dev dependencies at runtime.
FROM runtime AS test
RUN apk add --no-cache unzip
COPY --from=composer /composer /usr/bin/composer
WORKDIR /usr/src/forwarder

FROM test AS vendor
COPY composer.json composer.lock ./
COPY src src/
RUN composer install --no-dev --no-plugins --classmap-authoritative

# Final image: no composer, no build tools, only the application and its production dependencies.
FROM runtime AS production
WORKDIR /usr/src/forwarder
RUN mkdir etc
# Prefix 00 so that additional ini files (sorted alphabetically, i.e. the one mounted by compose.yml) can override it.
COPY docker/php-production.ini /usr/local/etc/php/conf.d/00-production.ini
COPY --from=vendor /usr/src/forwarder/vendor vendor/
COPY bin/console bin/
COPY src src/
CMD ["php", "./bin/console", "start", "-d"]
