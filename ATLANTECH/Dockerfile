# PHP-FPM image for the ATLANTECH port API: the `app` service of the `server` Compose profile,
# used by CI, the dev server and deployment. Daily development runs PHP through Herd instead.
FROM php:8.4-fpm-bookworm

# Installs PHP extensions together with the system libraries they need.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql redis intl bcmath pcntl zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, so the layer is reused while composer.json and composer.lock are unchanged.
# Set INSTALL_DEV=false for a deployment image without test tools.
ARG INSTALL_DEV=true
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --no-autoloader \
        $( [ "$INSTALL_DEV" = "true" ] || echo "--no-dev" )

COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

# PHP-FPM listens on 9000; NGINX forwards PHP requests to it.
EXPOSE 9000
