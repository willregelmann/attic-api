# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Will's Attic is a collectibles management platform API built with Laravel 12.x, designed for Railway deployment. The system manages collections, collectibles, and user items with Google OAuth authentication and planned GraphQL API support via Lighthouse.

## Architecture

### Core Domain Models
- **User**: Google OAuth authenticated users with profiles, preferences, and trade ratings
- **Collection**: Curated groups of collectibles (e.g., "Pokemon Base Set")
- **Collectible**: Individual collectible items with variants and metadata
- **Item**: User-owned instances of collectibles with condition, notes, and availability

### Authentication System
- Google OAuth 2.0 via Laravel Socialite for user authentication
- Laravel Sanctum for API token management (30-day expiration)
- Bearer token authentication for API endpoints
- User creation/linking with automatic username generation

### Database Design
- Uses JSON columns extensively for flexible metadata storage
- Proper indexing on foreign keys and frequently queried fields
- Supports both SQLite (development) and MySQL/PostgreSQL (production)
- Migration files follow Laravel conventions with descriptive names

### API Structure
- RESTful API endpoints under `/api/` prefix
- Authentication endpoints: `/api/auth/*`
- Health check: `/api/health`
- Future GraphQL endpoint planned for complex queries
- CORS configured for frontend domains

## Development Commands

### Daily Development
```bash
# Start development server with queue and logs
composer run dev

# Run tests
composer run test
# OR for specific test
php artisan test --filter=AuthTest

# Database operations
php artisan migrate
php artisan migrate:rollback
php artisan db:seed
php artisan db:seed --class=CoreDataSeeder

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Code Quality
```bash
# Format code
./vendor/bin/pint

# Run specific test suites
php artisan test tests/Unit
php artisan test tests/Feature

# Generate new components
php artisan make:controller ControllerName
php artisan make:model ModelName -m
php artisan make:seeder SeederName
```

### Deployment

### Production Deployment on Railway

#### Database Configuration
The API uses **Railway PostgreSQL** in production:
- **Database Type**: Managed PostgreSQL 
- **Connection**: Direct connection with full PostgreSQL extension support
- **SSL**: Automatically configured
- **Automatic Backups**: Included with Railway

#### Environment Variables
Railway automatically provides PostgreSQL environment variables:
- `DATABASE_URL` - Complete PostgreSQL connection string
- `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD` - Individual connection parameters

Additional Laravel environment variables (set in Railway dashboard):
```bash
APP_NAME=AtticAPI
APP_ENV=production
APP_KEY=base64:M9HZpBEwZvm/Bo1aFd2cqgvDA/pkTZgi3xyX5YUSfys=
APP_DEBUG=false
APP_URL=https://attic-api-production.up.railway.app
DB_CONNECTION=pgsql
LOG_CHANNEL=stderr
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# Google OAuth (required for authentication)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=https://attic-api-production.up.railway.app/api/auth/google/callback
```

#### Railway Deployment
```bash
# Login to Railway
railway login

# Link project (from attic-api directory)
railway link --project 12d4f6b0-bfc3-42ad-b823-7df2872588b4

# Railway is connected to GitHub for automatic deployments
# Simply push to main branch to deploy:
git push origin main

# Check deployment logs
railway logs --service attic-api

# Run commands with Railway environment
railway run --service attic-api php artisan migrate --force
```

**Important**: Railway deploys automatically from GitHub. Do not use `railway up` as it uploads local files instead of using the GitHub repository.

#### API URLs
- **Production URL**: `https://attic-api-production.up.railway.app`
- **API Base Path**: `/api`
- **Health Check**: `https://attic-api-production.up.railway.app/api/health`

#### Deployment Configuration
Railway uses the following configuration files:
- `railway.json` - Railway-specific deployment settings with automatic migrations
- `server.php` - Custom PHP server script to handle PORT environment variable
- `nixpacks.toml` - Build configuration with PHP 8.3 and PostgreSQL extensions
- `Procfile` - Process definition pointing to custom server.php

#### Database Migrations
Railway handles database migrations during deployment:
1. Migrations run automatically via `releaseCommand` in railway.json
2. Use `railway run --service attic-api php artisan migrate --force` for manual migrations
3. Railway PostgreSQL has full extension support (no compatibility issues)

#### Troubleshooting Production Issues
- **Build Errors**: Check Railway build logs in dashboard
- **Runtime Errors**: Use `railway logs` to view application logs
- **Database Errors**: Railway PostgreSQL has full PHP extension support
- **Environment Variables**: Verify all Laravel variables are set in Railway dashboard
- **Custom Domains**: Configure in Railway dashboard under service settings

## Key Implementation Details

### User Model Relationships
- `hasMany` for collections (contributed), items, collectibles (contributed)
- Uses `HasApiTokens` trait for Sanctum integration
- JSON casts for profile, preferences, trade_rating, subscription

### Authentication Flow
1. Frontend requests Google OAuth URL from `/api/auth/google/url`
2. User completes Google OAuth flow
3. Frontend sends Google token to `/api/auth/google/token`
4. API returns Laravel Sanctum token + user data
5. Subsequent requests use `Authorization: Bearer {token}` header

### Database Migrations
- Core models: users, collections, collectibles, items
- Pivot table: collectible_collection (many-to-many)
- Sanctum migrations: personal_access_tokens
- Future migrations planned for showcases, wishlists, trading system

### Railway Configuration
- **Runtime**: Nixpacks with PHP 8.3 and Node.js for asset building
- **Process Management**: Supervised PHP application server
- **Asset Building**: Vite builds assets during deployment
- **Caching**: Laravel config/route/view caching enabled in production
- **Health Checks**: Railway monitors `/api/health` endpoint

## Current Implementation Status

### ✅ Completed
- Laravel 12 project structure with Railway deployment
- Google OAuth authentication system
- Core database models and relationships
- API authentication endpoints
- User management with profile data
- Database seeders with sample data
- Railway deployment configuration (nixpacks, Procfile, deployment scripts)
- PostgreSQL database setup with full extension support
- Comprehensive API documentation

### 🚧 In Progress / Planned
- GraphQL schema implementation (Lighthouse installed)
- Collection/Item CRUD operations
- Search functionality (Algolia integration planned)
- File upload system (Railway Volumes or external storage)
- Trading system (wishlists, offers, matches)
- Real-time features (WebSockets)

## Environment Configuration

### Required Environment Variables
```bash
# Database (automatically provided by Railway PostgreSQL)
DATABASE_URL=postgres://...
PGHOST=...
PGPORT=5432
PGDATABASE=...
PGUSER=...
PGPASSWORD=...

# Laravel Configuration
APP_NAME=AtticAPI
APP_ENV=production
APP_KEY=base64:M9HZpBEwZvm/Bo1aFd2cqgvDA/pkTZgi3xyX5YUSfys=
APP_DEBUG=false
APP_URL=https://[your-app].up.railway.app

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=your_callback_url

# Optional integrations
ALGOLIA_APP_ID=...
ALGOLIA_SECRET=...
SENDGRID_API_KEY=...
```

### Sanctum Configuration
- Stateful domains configured for frontend integration
- Token expiration: 30 days
- CORS configured for `*.up.railway.app` domains and frontend domains

## Testing Strategy

### Test Structure
- Unit tests: Model relationships, business logic
- Feature tests: API endpoints, authentication flows
- Uses in-memory SQLite for test database
- PHPUnit configuration supports parallel testing

### Key Test Areas
- Google OAuth token validation
- API authentication middleware
- User creation and profile management
- Database relationships and cascading deletes

## Common Patterns

### JSON Column Usage
Models extensively use JSON columns for flexible data:
- User: `profile`, `preferences`, `trade_rating`, `subscription`
- Collectible: `base_attributes`, `variants`, `image_urls`, `digital_metadata`
- Item: `acquisition_info`, `availability`, `component_status`

### Model Factories and Seeders
- `CoreDataSeeder` provides realistic sample data
- Users created with complete profile structures
- Sample collections include Pokemon cards and Star Wars figures

### API Response Format
Consistent JSON response structure:
```php
// Success
['success' => true, 'data' => $data]

// Error
['success' => false, 'message' => 'Error', 'errors' => $validationErrors]
```

## Documentation

- `API_DOCUMENTATION.md`: Complete API reference with TypeScript interfaces
- `FRONTEND_QUICKSTART.md`: 5-minute integration guide for frontend developers
- `PROJECT_REQUIREMENTS.md`: Original project specifications and full data model requirements
- `attic-api.postman_collection.json`: Postman collection for API testing

The codebase follows Laravel conventions and is optimized for Railway deployment with full PostgreSQL support and traditional hosting compatibility.