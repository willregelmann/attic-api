#!/bin/bash

# Install dependencies
composer install --no-dev --optimize-autoloader

# Create SQLite database file
touch database/database.sqlite

# Clear and cache config for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chmod -R 755 storage
chmod -R 755 bootstrap/cache