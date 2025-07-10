#!/bin/bash

# Set working directory
cd /var/www/html

# Run Laravel setup commands
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Apache
apache2-foreground 