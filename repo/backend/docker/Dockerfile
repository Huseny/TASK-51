FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y git unzip curl ca-certificates libzip-dev libonig-dev libxml2-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring zip xml pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views \
    && chmod -R 775 bootstrap/cache storage/framework

RUN composer install --no-interaction --prefer-dist
RUN chmod +x docker/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["sh", "docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
