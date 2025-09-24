#!/bin/bash

# Run migrations (ignore errors if already run)
php artisan migrate --force 2>/dev/null || echo "Migrations already run or skipped"

# Start the Laravel server
php artisan serve --host=0.0.0.0 --port=${PORT:-8000}