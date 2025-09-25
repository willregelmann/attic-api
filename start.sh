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
php artisan route:cache
php artisan view:cache
php artisan lighthouse:clear-cache

# List storage contents for debugging
echo "Storage contents:"
ls -la storage/app/public/images/collections/ || echo "No images found"

# Create public symlink for storage access
echo "Creating public storage symlink..."
# Remove any existing symlink
rm -rf public/storage
# Create symlink directly to the storage/app/public directory
ln -sfn /app/storage/app/public public/storage

# List storage structure for debugging
echo "Storage structure:"
ls -la storage/
ls -la storage/app/
ls -la storage/app/public/ || echo "No public dir"
ls -la public/ || echo "No public dir"
ls -la public/storage/ || echo "No storage link"

# Test if symlink works
echo "Testing symlink access:"
ls -la public/storage/images/collections/ 2>/dev/null || echo "Cannot access collections through symlink"

# Start the queue worker in the background
echo "Starting queue worker..."
php artisan queue:work --sleep=3 --tries=3 --timeout=90 --verbose 2>&1 | while read line; do echo "[QUEUE] $line"; done &
QUEUE_PID=$!
echo "Queue worker started with PID: $QUEUE_PID"

# Start the scheduler in the background
echo "Starting Laravel scheduler..."
while true; do
    echo "[SCHEDULER] Running scheduled tasks at $(date)..."
    php artisan schedule:run --verbose --no-interaction 2>&1 | while read line; do echo "[SCHEDULER] $line"; done
    sleep 60
done &
SCHEDULER_PID=$!
echo "Scheduler started with PID: $SCHEDULER_PID"

# Function to cleanup background processes
cleanup() {
    echo "Stopping background processes..."
    kill $SCHEDULER_PID 2>/dev/null
    kill $QUEUE_PID 2>/dev/null
    exit
}

# Set up trap to cleanup on exit
trap cleanup EXIT INT TERM

# Check if Railway is using FrankenPHP
if [ -f "/Caddyfile" ]; then
    echo "FrankenPHP/Caddy detected - storage will be served through Laravel routes"
    echo "Background services (queue worker and scheduler) are running"
    echo "Monitoring services..."
    
    # Monitor background processes and restart if they die
    while true; do
        # Check if queue worker is still running
        if ! kill -0 $QUEUE_PID 2>/dev/null; then
            echo "Queue worker died, restarting..."
            php artisan queue:work --sleep=3 --tries=3 --timeout=90 --verbose 2>&1 | while read line; do echo "[QUEUE] $line"; done &
            QUEUE_PID=$!
            echo "Queue worker restarted with PID: $QUEUE_PID"
        fi
        
        # Check if scheduler is still running
        if ! kill -0 $SCHEDULER_PID 2>/dev/null; then
            echo "Scheduler died, restarting..."
            while true; do
                echo "[SCHEDULER] Running scheduled tasks at $(date)..."
                php artisan schedule:run --verbose --no-interaction 2>&1 | while read line; do echo "[SCHEDULER] $line"; done
                sleep 60
            done &
            SCHEDULER_PID=$!
            echo "Scheduler restarted with PID: $SCHEDULER_PID"
        fi
        
        sleep 10
    done
else
    # Start the Laravel server
    echo "Starting Laravel server on port ${PORT:-8000}..."
    php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
fi