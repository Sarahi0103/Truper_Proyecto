FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install mysqli \
    && a2enmod rewrite headers expires deflate \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

RUN chmod +x /var/www/html/docker/start.sh

EXPOSE 80

CMD ["/var/www/html/docker/start.sh"]
