# Use official PHP Apache image
FROM php:8.2-apache

# Install required system dependencies, MariaDB server & PHP extensions
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    mariadb-server \
    mariadb-client \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libcurl4-openssl-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli curl pdo pdo_mysql \
    && a2enmod rewrite headers remoteip \
    && rm -rf /var/lib/apt/lists/*

# Optimize MariaDB memory for container (low memory footprint)
RUN echo "[mysqld]\ninnodb_buffer_pool_size = 32M\nperformance_schema = off\nskip-innodb-doublewrite\n" > /etc/mysql/mariadb.conf.d/99-render.cnf

# Allow .htaccess overrides in Apache
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory & copy code
WORKDIR /var/www/html
COPY . /var/www/html/

# Fix permissions and startup script
RUN chmod +x /var/www/html/start.sh \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Render sets PORT dynamically
ENV PORT=80
EXPOSE 80

CMD ["/bin/bash", "/var/www/html/start.sh"]
