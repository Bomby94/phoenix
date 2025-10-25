# syntax=docker/dockerfile:1

FROM php:8.4-fpm AS base

# System deps
RUN apt-get update && apt-get install -y \
    git unzip zlib1g-dev libzip-dev libpng-dev libicu-dev \
    libonig-dev libxml2-dev curl openssh-client \
  && docker-php-ext-install pdo_mysql intl zip opcache bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create app dir
WORKDIR /srv/app

# 👤 Create a non-root user matching your host UID/GID
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} appuser && \
    useradd -u ${UID} -g ${GID} -m appuser && \
    chown -R appuser:appuser /srv/app

# 🧱 Production build
FROM base AS prod
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
COPY . .
RUN mkdir -p var/cache var/log && chown -R www-data:www-data var
USER www-data
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]

# 🧩 Development build
# 🧩 Development build
FROM base AS dev

# Install Symfony CLI
COPY --link \
    --from=ghcr.io/symfony-cli/symfony-cli:latest \
    /usr/local/bin/symfony /usr/local/bin/symfony

# Set working dir
WORKDIR /srv/app

# Copy app source code
COPY . .

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist

# Install PHPCS globally for appuser
USER appuser
RUN composer global require "squizlabs/php_codesniffer=*"

# Make sure Composer global binaries are in PATH
ENV PATH="/home/appuser/.composer/vendor/bin:${PATH}"


# Set default command
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]

