FROM php:8.4-fpm

# Install system dependencies and PHP extensions in single layer
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
    libsasl2-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Xdebug with build dependencies, configure, then clean up in single layer
# Note: xdebug.mode can be overridden by XDEBUG_MODE environment variable
RUN apt-get update \
    && apt-get install -y --no-install-recommends autoconf pkg-config gcc make libc-dev \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "; Xdebug configuration" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "; Default mode: off. Override with XDEBUG_MODE env var for debugging." >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.mode=\${XDEBUG_MODE}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.discover_client_host=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.max_nesting_level=512" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && apt-get remove -y gcc make autoconf pkg-config libc-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Default XDEBUG_MODE to off (can be overridden by docker-compose environment)
ENV XDEBUG_MODE=off

# Configure PHP upload limits
RUN echo "upload_max_filesize = 200M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 200M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 1024M" >> /usr/local/etc/php/conf.d/uploads.ini

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
