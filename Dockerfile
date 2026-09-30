FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

COPY docker/php/entrypoint.sh /usr/local/bin/workboard-php-entrypoint
RUN chmod +x /usr/local/bin/workboard-php-entrypoint

ENTRYPOINT ["workboard-php-entrypoint"]
CMD ["php-fpm"]
