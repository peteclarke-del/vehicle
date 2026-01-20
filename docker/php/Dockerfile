FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libicu-dev \
    libssl-dev \
    libsasl2-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Install Xdebug (PECL) and build deps, create config to enable coverage, then clean up
RUN apt-get update \
    && apt-get install -y --no-install-recommends autoconf pkg-config gcc make libc-dev \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && printf "; Xdebug settings added by Dockerfile\nxdebug.mode=coverage\nxdebug.start_with_request=trigger\nxdebug.discover_client_host=1\n" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && apt-get remove -y gcc make autoconf pkg-config libc-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* /tmp/pear
# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add entrypoint that fixes ownership for mounted volumes at container start
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory permissions

# Keep filesystem owned by www-data by default; entrypoint will fix host-mounted
# volumes when the container is started as root via docker-compose override.
RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000
CMD ["php-fpm"]

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
