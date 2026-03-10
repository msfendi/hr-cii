FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    gnupg2 \
    unixodbc-dev \
    libssl-dev \
    curl \
    libcurl4-openssl-dev \
    libxml2-dev \
    libgssapi-krb5-2 \
    zip \
    unzip \
    git \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Add Microsoft SQL Server repo and install msodbcsql17 and mssql-tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    apt-transport-https \
    curl \
    ca-certificates \
    gnupg2 \
    lsb-release

RUN curl -sSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor > /usr/share/keyrings/microsoft.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/11/prod bullseye main" > /etc/apt/sources.list.d/mssql-release.list

RUN apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql17 mssql-tools libgssapi-krb5-2 \
    && rm -rf /var/lib/apt/lists/*


# Install PHP extensions for SQL Server
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Install other PHP extensions needed by Laravel
RUN docker-php-ext-install pdo mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . /var/www

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]


# FROM php:8.1-fpm-alpine

# # Set working directory
# WORKDIR /var/www

# # Install system dependencies
# RUN apk update && apk add --no-cache \
#     git \
#     curl \
#     libpng-dev \
#     oniguruma-dev \
#     libxml2-dev \
#     zip \
#     unzip \
#     libzip-dev \
#     postgresql-dev \
#     mysql-client \
#     supervisor \
#     autoconf \
#     gcc \
#     g++ \
#     make \
#     bash

# # Install PHP extensions
# RUN docker-php-ext-install \
#     pdo_mysql \
#     mbstring \
#     exif \
#     pcntl \
#     bcmath \
#     gd \
#     zip

# # Install Composer
# COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# # Copy existing application directory
# COPY . /var/www

# # Set permissions
# RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# # Expose port
# EXPOSE 9000

# # Default command
# CMD ["php-fpm"]
