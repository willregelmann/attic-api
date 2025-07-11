#!/bin/bash

echo "🐳 Setting up Docker environment for Will's Attic API..."

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    echo "📄 Copying .env.example to .env..."
    cp .env.example .env
fi

# Build and start containers
echo "🚀 Building and starting Docker containers..."
docker-compose up -d --build

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 10

# Generate application key
echo "🔑 Generating application key..."
docker-compose exec app php artisan key:generate

# Run migrations
echo "📊 Running database migrations..."
docker-compose exec app php artisan migrate

# Skip seeders - empty database for fresh start
echo "🗄️  Database ready (no seed data - clean start for UI testing)"

# Clear caches
echo "🧹 Clearing caches..."
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:clear

echo "✅ Docker setup complete!"
echo ""
echo "🌐 API is now running at: http://localhost:8000"
echo "🗃️  Database is running on: localhost:3306"
echo "📊 GraphQL endpoint: http://localhost:8000/graphql"
echo "🔍 GraphQL Playground: http://localhost:8000/graphql-playground"
echo ""
echo "📝 To view logs: docker-compose logs -f"
echo "🛑 To stop: docker-compose down"
echo "🔄 To restart: docker-compose restart"