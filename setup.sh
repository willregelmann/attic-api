#!/bin/bash

# Setup script for Attic API development environment
echo "Setting up Attic API development environment..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "Docker is not running. Please start Docker first."
    exit 1
fi

echo "Starting Docker containers..."
docker-compose up -d

echo "Waiting for PostgreSQL to be ready..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if docker-compose exec -T pgsql pg_isready -U sail -d attic > /dev/null 2>&1; then
        echo "PostgreSQL is ready!"
        break
    fi
    echo "Waiting for PostgreSQL... (attempt $((attempt+1))/$max_attempts)"
    sleep 2
    attempt=$((attempt+1))
done

if [ $attempt -eq $max_attempts ]; then
    echo "PostgreSQL failed to start. Check docker-compose logs."
    exit 1
fi

echo "Running migrations..."
./vendor/bin/sail artisan migrate:fresh

echo ""
echo "Setup complete!"
echo ""
echo "To view the containers:"
echo "  docker-compose ps"
echo ""
echo "To stop the containers:"
echo "  docker-compose down"