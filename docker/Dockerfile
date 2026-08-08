FROM php:8.5-apache

RUN apt-get update && apt-get install -y \
    libzip-dev unzip git libpng-dev libjpeg-dev libfreetype6-dev tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mysqli zip gd \
    && a2enmod rewrite \
    && ln -snf /usr/share/zoneinfo/America/Bogota /etc/localtime \
    && echo "America/Bogota" > /etc/timezone

# Zona horaria de PHP — no hay ningún php.ini cargado por defecto en esta imagen
RUN echo "date.timezone = America/Bogota" > /usr/local/etc/php/conf.d/timezone.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache escucha en 8080 (evita conflictos con otros servicios del host)
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:8080>/' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
