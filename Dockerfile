FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libzip-dev \
    && docker-php-ext-install mysqli mbstring zip \
    && a2dismod -f mpm_event mpm_worker \
    && a2enmod mpm_prefork \
    && a2enmod headers rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html
COPY docker/railway-entrypoint.sh /usr/local/bin/railway-entrypoint

RUN chmod +x /usr/local/bin/railway-entrypoint \
    && mkdir -p /opt/upload-protection \
    && cp /var/www/html/uploads/.htaccess /opt/upload-protection/.htaccess \
    && chown -R www-data:www-data /var/www/html/uploads

ENTRYPOINT ["railway-entrypoint"]
CMD ["apache2-foreground"]
