FROM php:8.1-cli

# Installa librerie e dipendenze necessarie
RUN apt-get update && apt-get install -y \
  git curl unzip zip libzip-dev libicu-dev libjpeg-dev libpng-dev libfreetype6-dev \
  pkg-config libonig-dev libxml2-dev gnupg ca-certificates

# Installa Node.js 20.x (compatibile con PrestaShop develop)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
  && apt-get install -y nodejs

# Installa estensioni PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install zip intl gd mbstring xml

# Installa Composer
RUN php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');" \
  && php /tmp/composer-setup.php --no-ansi --install-dir=/usr/local/bin --filename=composer \
  && rm -rf /tmp/composer-setup.php

# Imposta directory di lavoro
WORKDIR /app

# Copia i file del progetto
COPY . .
