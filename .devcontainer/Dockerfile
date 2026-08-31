FROM mcr.microsoft.com/devcontainers/php:1-8.2-bookworm

# System deps
RUN apt-get update && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y --no-install-recommends \
        git unzip curl libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
        libonig-dev libxml2-dev default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring zip bcmath gd exif pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Node 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# Composer already in base image; ensure available
RUN composer --version && php -v && node -v

WORKDIR /workspace
