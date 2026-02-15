FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    default-mysql-client

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Update Apache DocumentRoot to point to Laravel's public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Set ServerName to suppress warnings and match vhost
RUN echo "ServerName api.polar.localhost" >> /etc/apache2/apache2.conf

# Enable AllowOverride All for the DocumentRoot
RUN echo "<Directory ${APACHE_DOCUMENT_ROOT}>" > /etc/apache2/conf-available/laravel.conf && \
    echo "    Options Indexes FollowSymLinks" >> /etc/apache2/conf-available/laravel.conf && \
    echo "    AllowOverride All" >> /etc/apache2/conf-available/laravel.conf && \
    echo "    Require all granted" >> /etc/apache2/conf-available/laravel.conf && \
    echo "</Directory>" >> /etc/apache2/conf-available/laravel.conf && \
    a2enconf laravel

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory permissions
COPY --chown=www-data:www-data . /var/www/html

# Change current user to www
USER www-data

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
