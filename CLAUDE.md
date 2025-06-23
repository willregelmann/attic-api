# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Will's Attic is a collectibles management platform API built with Laravel 12.x, designed for Vercel serverless deployment. The system manages collections, collectibles, and user items with Google OAuth authentication and planned GraphQL API support via Lighthouse.

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
```bash
# Deploy to Vercel
vercel --prod

# Check deployment logs
vercel logs [deployment-url]
```

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

### Vercel Configuration
- PHP runtime: `vercel-php@0.5.0`
- Serverless functions in `/api/` directory
- Laravel routes accessible via `/api/index.php`
- Environment variables configured for production caching

## Current Implementation Status

### ✅ Completed
- Laravel 12 project structure with Vercel deployment
- Google OAuth authentication system
- Core database models and relationships
- API authentication endpoints
- User management with profile data
- Database seeders with sample data
- Comprehensive API documentation

### 🚧 In Progress / Planned
- GraphQL schema implementation (Lighthouse installed)
- Collection/Item CRUD operations
- Search functionality (Algolia integration planned)
- File upload system (Vercel Blob Storage)
- Trading system (wishlists, offers, matches)
- Real-time features (WebSockets)

## Environment Configuration

### Required Environment Variables
```bash
# Database (production)
DATABASE_URL=mysql://... # or postgres://...

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=your_callback_url

# Optional integrations
VERCEL_BLOB_READ_WRITE_TOKEN=...
ALGOLIA_APP_ID=...
ALGOLIA_SECRET=...
SENDGRID_API_KEY=...
```

### Sanctum Configuration
- Stateful domains configured for frontend integration
- Token expiration: 30 days
- CORS configured for `*.vercel.app` domains

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

The codebase follows Laravel conventions and is optimized for Vercel serverless deployment while maintaining compatibility with traditional hosting environments.