FROM php:8.4-fpm

# System deps for pgsql, intl, zip, gd + redis via pecl
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev libicu-dev libzip-dev zlib1g-dev libpng-dev libjpeg-dev libfreetype6-dev \
    unzip git \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip gd \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && rm -rf /var/lib/apt/lists/*

# Upload limits aligned with spec §9.2 (CV 5MB, nginx 10m body)
RUN printf 'upload_max_filesize=6M\npost_max_size=12M\n' > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
