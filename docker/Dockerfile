FROM php:8.5-apache

RUN apt-get update && apt-get install -y \
    libzip-dev unzip git \
    && docker-php-ext-install pdo_mysql mysqli zip \
    && a2enmod rewrite

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache escucha en 8080 (evita conflictos con otros servicios del host)
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:8080>/' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
