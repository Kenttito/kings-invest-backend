FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files and artisan first for better caching
COPY Backend/composer.json Backend/composer.lock Backend/artisan ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy package files
COPY Backend/package.json Backend/package-lock.json ./

# Install Node.js dependencies
RUN npm install --legacy-peer-deps

# Copy the rest of the application
COPY Backend/ .

# Build assets
RUN npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"] 