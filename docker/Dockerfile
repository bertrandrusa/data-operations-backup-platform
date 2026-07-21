FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY app/ /var/www/html/
COPY database/ /var/www/database/
COPY docker/web-entrypoint.sh /usr/local/bin/dataops-web-entrypoint

RUN chmod +x /usr/local/bin/dataops-web-entrypoint \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["dataops-web-entrypoint"]
CMD ["apache2-foreground"]

