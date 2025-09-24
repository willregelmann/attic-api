#!/bin/bash

echo "Starting application..."
echo "Environment: ${APP_ENV:-not set}"
echo "Database: ${DB_CONNECTION:-not set}"

# Test database connection first
echo "Testing database connection..."
php artisan db:show
if [ $? -ne 0 ]; then
    echo "ERROR: Database connection failed!"
    echo "Please check your database configuration."
    exit 1
fi

# Run migrations
echo "Running migrations..."
php artisan migrate --force
if [ $? -ne 0 ]; then
    echo "WARNING: Migrations failed, but continuing..."
    # Don't exit here as migrations might already be up to date
fi

# Clear and cache configs for production
echo "Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start the Laravel server
echo "Starting server on port ${PORT:-8000}..."
php artisan serve --host=0.0.0.0 --port=${PORT:-8000}