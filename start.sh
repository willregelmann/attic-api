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
    echo "Created directories in volume"
    
    # Debug: Show what's in the volume
    echo "Volume contents before linking:"
    ls -la "$RAILWAY_VOLUME_MOUNT_PATH/" || echo "Cannot list volume root"
    ls -la "$RAILWAY_VOLUME_MOUNT_PATH/app/" || echo "Cannot list volume app dir"
    ls -la "$RAILWAY_VOLUME_MOUNT_PATH/app/public/" || echo "Cannot list volume public dir"
    
    # Remove existing storage/app directory and symlink to volume
    echo "Creating symlink from storage/app to $RAILWAY_VOLUME_MOUNT_PATH/app"
    rm -rf storage/app
    ln -sfn "$RAILWAY_VOLUME_MOUNT_PATH/app" storage/app
    
    # Verify symlink was created
    echo "Verifying symlink:"
    ls -la storage/ | grep app
    
    # Test if we can write to the volume through the symlink
    echo "Testing write access..."
    touch storage/app/public/test-write.txt && echo "Write test successful" || echo "Write test failed"
    
    # Ensure permissions are correct
    chmod -R 775 "$RAILWAY_VOLUME_MOUNT_PATH/app" 2>/dev/null || echo "Could not change permissions"
    
    echo "Storage linked to Railway volume"
    
    # List final contents
    echo "Final storage contents:"
    ls -la storage/app/public/images/collections/ 2>/dev/null || echo "Collections directory not accessible"
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
# Don't cache routes in production as it can cause issues with closures
# php artisan route:cache
php artisan view:cache

# List storage contents for debugging
echo "Storage contents:"
ls -la storage/app/public/images/collections/ || echo "No images found"

# Start the Laravel server
echo "Starting server on port ${PORT:-8000}..."
php artisan serve --host=0.0.0.0 --port=${PORT:-8000}