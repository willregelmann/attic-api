#!/bin/bash

echo "🗑️  Resetting database to completely clean state..."

# Fresh migrate (drops all tables and recreates them)
echo "📊 Running fresh migrations..."
docker-compose exec app php artisan migrate:fresh

# Clear all caches
echo "🧹 Clearing all caches..."
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear

echo "✅ Database completely reset!"
echo ""
echo "🗄️  Database is now completely empty - perfect for testing the UI experience"
echo "📊 GraphQL endpoint: http://localhost:8000/graphql"