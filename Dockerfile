FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libpq-dev unzip git \
    && docker-php-ext-install pdo_pgsql zip \
    && a2enmod rewrite headers expires deflate \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY . /var/www/html

WORKDIR /var/www/html

RUN composer install --no-dev --optimize-autoloader --no-interaction || true

RUN chown -R www-data:www-data /var/www/html

RUN chmod +x /var/www/html/docker/start.sh

EXPOSE 80

CMD ["/var/www/html/docker/start.sh"]
