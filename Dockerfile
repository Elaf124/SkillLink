# Multi-stage Dockerfile for SkillLink (Laravel 12 Production)
FROM php:8.2-fpm-alpine as base

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    libzip-dev \
    unzip \
    oniguruma-dev \
    sqlite-dev \
    postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pdo_sqlite gd zip mbstring bcmath opcache

# Copy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Ensure sqlite database file exists and set storage, cache, & database permissions
RUN touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && mkdir -p /tmp/nginx_client_body /var/lib/nginx/body /var/lib/nginx/tmp /var/log/nginx \
    && chown -R www-data:www-data /tmp/nginx_client_body /var/lib/nginx /var/log/nginx \
    && chmod -R 777 /tmp/nginx_client_body \
    && php artisan storage:link || true

# Nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf

EXPOSE 80

# Entrypoint script for production caching & migration
CMD ["sh", "-c", "mkdir -p /tmp/nginx_client_body && chmod 777 /tmp/nginx_client_body && if [ -z \"$APP_KEY\" ]; then php artisan key:generate --force; fi && php artisan storage:link || true && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan migrate --force && php artisan db:seed --force && php-fpm -D && nginx -g 'daemon off;'"]
