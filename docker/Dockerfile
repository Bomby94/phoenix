# syntax=docker/dockerfile:1

FROM php:8.4-fpm AS base

# System deps
RUN apt-get update && apt-get install -y \
    git unzip zlib1g-dev libzip-dev libpng-dev libicu-dev \
    libonig-dev libxml2-dev curl openssh-client gcc make autoconf\
  && docker-php-ext-install pdo_mysql intl zip opcache bcmath

RUN pecl install opentelemetry && docker-php-ext-enable opentelemetry


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
COPY apps/infinity .
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
RUN mkdir -p var/cache var/log && chown -R www-data:www-data var
USER www-data
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]


# 🧩 Development build
FROM base AS dev

# Install Symfony CLI
COPY --link \
    --from=ghcr.io/symfony-cli/symfony-cli:latest \
    /usr/local/bin/symfony /usr/local/bin/symfony

# Set working dir
WORKDIR /srv/app

RUN pecl install xdebug && docker-php-ext-enable xdebug
USER appuser

# Copy app source code
COPY . .

RUN git config --global --add safe.directory /srv/app
# Install PHPCS globally for appuser
RUN composer global require "squizlabs/php_codesniffer=*"

# Make sure Composer global binaries are in PATH
ENV PATH="/home/appuser/.composer/vendor/bin:${PATH}"

# Set default command
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]

