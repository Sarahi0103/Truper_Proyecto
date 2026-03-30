FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install mysqli \
    && a2enmod rewrite headers expires deflate \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["bash", "-lc", "PORT_VALUE=${PORT:-80}; sed -ri \"s/^Listen 80$/Listen ${PORT_VALUE}/\" /etc/apache2/ports.conf; sed -ri \"s/<VirtualHost \*:80>/<VirtualHost *:${PORT_VALUE}>/\" /etc/apache2/sites-available/000-default.conf; apache2-foreground"]
