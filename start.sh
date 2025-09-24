#!/bin/bash

echo "Starting application..."
echo "Environment: ${APP_ENV:-not set}"
echo "Database: ${DB_CONNECTION:-not set}"

# Setup storage for Railway volumes
echo "Setting up storage..."
if [ -n "$RAILWAY_VOLUME_MOUNT_PATH" ]; then
    echo "Railway volume detected at: $RAILWAY_VOLUME_MOUNT_PATH"
    
    # Create storage directories in the volume
    mkdir -p "$RAILWAY_VOLUME_MOUNT_PATH/app/public/images/collections"
    
    # Remove existing storage/app directory and symlink to volume
    rm -rf storage/app
    ln -sf "$RAILWAY_VOLUME_MOUNT_PATH/app" storage/app
    
    # Ensure permissions are correct
    chmod -R 775 "$RAILWAY_VOLUME_MOUNT_PATH/app"
    
    echo "Storage linked to Railway volume"
else
    echo "No Railway volume detected, using local storage"
    # Ensure local storage directories exist
    mkdir -p storage/app/public/images/collections
fi

# Note: We're not creating a public symlink anymore since we serve images through Laravel
echo "Storage setup complete. Images will be served through Laravel routes."

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