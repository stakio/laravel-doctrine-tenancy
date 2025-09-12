FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    zip \
    intl


# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy source code
COPY . .

# Install dev dependencies for testing
RUN composer install

# Set permissions
RUN chown -R www-data:www-data /app
RUN chmod -R 755 /app
RUN mkdir -p /app/.phpunit.cache
RUN chown -R www-data:www-data /app/.phpunit.cache
RUN chmod -R 755 /app/.phpunit.cache
USER www-data

# Default command
CMD ["composer", "test"]
