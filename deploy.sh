#!/bin/bash

# Railway deployment script for Laravel
set -e

echo "🚀 Starting Laravel deployment on Railway..."

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# Generate optimized autoload files
echo "🔧 Generating optimized autoload files..."
composer dump-autoload --optimize

# Build Vite assets for production
echo "🎨 Building Vite assets..."
npm ci
npm run build

# Clear and cache Laravel configuration
echo "⚙️ Optimizing Laravel configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Seed database if needed (uncomment if you want to seed on every deploy)
# php artisan db:seed --force

echo "✅ Deployment completed successfully!"