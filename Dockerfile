FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www
