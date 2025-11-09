# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 11 GraphQL API backend for Attic collectibles management platform.

**Technology Stack:**
- Laravel 11 with Lighthouse GraphQL
- PostgreSQL with UUID primary keys
- Laravel Sanctum authentication
- Docker via Laravel Sail

**Data Architecture:**
- **Canonical collectibles data**: Read-only from external Supabase "Database of Things" API
- **User-specific data**: Stored locally (owned items, wishlists, favorites, API tokens)

## Development Commands

### Docker Setup with Laravel Sail

```bash
# Start Docker containers (PostgreSQL + Laravel)
./vendor/bin/sail up -d

# Stop containers
./vendor/bin/sail down

# Shell into the application container
./vendor/bin/sail shell
```

### Database Management

```bash
# Run migrations
./vendor/bin/sail artisan migrate

# Fresh migration (drops all tables)
./vendor/bin/sail artisan migrate:fresh

# Run seeders
./vendor/bin/sail artisan db:seed

# Access PostgreSQL shell
./vendor/bin/sail exec pgsql psql -U sail -d attic
```

### Testing

```bash
# Run all tests
./vendor/bin/sail test

# Run specific test file
./vendor/bin/sail test tests/Feature/ExampleTest.php

# Run with coverage
./vendor/bin/sail test --coverage
```

### Code Quality

```bash
# Run Laravel Pint (code formatter)
./vendor/bin/sail artisan pint

# Check code style without fixing
./vendor/bin/sail artisan pint --test
```

### Development Server

```bash
# Start development server with all services
composer dev
# This runs concurrently: server, queue worker, logs, and vite

# Or run individually:
./vendor/bin/sail artisan serve
./vendor/bin/sail artisan queue:listen --tries=1
./vendor/bin/sail artisan pail
npm run dev
```

### Artisan Commands

```bash
# Clear all caches
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan route:clear

# Generate IDE helpers for models
./vendor/bin/sail artisan ide-helper:models

# Access Tinker (Laravel REPL)
./vendor/bin/sail artisan tinker

# List custom commands
./vendor/bin/sail artisan list
```

### Railway Deployment

```bash
# Deploy to Railway
railway up

# Check deployment logs
railway logs

# The app uses start.sh script for production:
# - Runs migrations
# - Starts PHP server on configured PORT
```

## Architecture

### GraphQL API Structure

Schema-first development with Lighthouse GraphQL:

**Schema**: `graphql/schema.graphql`
- Defines all types, queries, and mutations
- 283 lines of clean GraphQL schema
- Two main categories: Database of Things queries and user data management

**Resolvers**: `app/GraphQL/`
- `Queries/DatabaseOfThings/` - External Supabase integration (7 resolvers)
  - `CollectionsList.php` - Browse collections
  - `SearchEntities.php` - Search collectibles
  - `SemanticSearch.php` - Vector-based semantic search
  - `CollectionItems.php` - Items in a collection
  - `GetEntity.php` - Single entity details
  - `GetItemParents.php` - Parent collections
  - `GetCollectionFilterFields.php` - Dynamic filtering
- `Queries/` - User-specific data queries
  - `MyItems.php` - User's owned items
  - `MyFavoriteCollections.php` - Favorited collections
  - `MyWishlist.php` - Wishlist items
  - `MyApiTokens.php` - User's API tokens
- `Mutations/` - User data mutations
  - `AuthMutations.php` - Login, register, logout
  - `UserItemMutations.php` - Add/update/remove owned items
  - `FavoriteMutations.php` - Favorite/unfavorite collections
  - `WishlistMutations.php` - Add/remove wishlist items
  - `ApiTokenMutations.php` - Create/revoke API tokens
- `Scalars/` - Custom scalar types (JSON)

**Services**: `app/Services/`
- `DatabaseOfThingsService.php` - Supabase GraphQL client with query methods

### Database Models

All models use UUID primary keys (`HasUuids` trait) and store flexible metadata in JSONB columns:

**User Data Models:**
- **User** (`app/Models/User.php`)
  - Authentication with Laravel Sanctum
  - Relationships: userItems(), favoriteCollections(), apiTokens(), wishlists()

- **UserItem** (`app/Models/UserItem.php`)
  - User's owned collectible items
  - `entity_id` field references Supabase entity UUID
  - JSONB `metadata` field for custom attributes
  - JSONB `notes` field for user notes

- **Wishlist** (`app/Models/Wishlist.php`)
  - User's wishlist items
  - `entity_id` field references Supabase entity UUID

- **UserCollectionFavorite** (`app/Models/UserCollectionFavorite.php`)
  - User's favorited collections
  - `collection_id` field references Supabase collection UUID

- **ApiToken** (`app/Models/ApiToken.php`)
  - User API tokens for external access
  - Token abilities, expiration, last used tracking

### Key Design Patterns

1. **Read-only canonical data**: Collections and collectibles fetched from Supabase "Database of Things" API
   - No local CRUD operations for canonical items
   - Supabase entities queried via DatabaseOfThingsService

2. **Entity ID references**: User data references external entities by UUID
   - `UserItem.entity_id` → Supabase entity UUID
   - `UserCollectionFavorite.collection_id` → Supabase collection UUID
   - `Wishlist.entity_id` → Supabase entity UUID
   - No foreign key constraints (external data)

3. **UUID primary keys**: All models use Laravel's `HasUuids` trait
   - PostgreSQL UUID type, not strings
   - No auto-incrementing integers

4. **JSONB for flexibility**: Metadata stored in JSONB columns
   - `UserItem.metadata` for custom attributes
   - Cast to array in Eloquent models
   - Query with PostgreSQL JSONB operators

5. **Service-based integration**: DatabaseOfThingsService encapsulates all Supabase API calls
   - Centralized error handling
   - Consistent GraphQL query formatting
   - Easy to mock in tests

### Database Schema

**User-specific tables** (PostgreSQL with UUIDs):
```sql
users (id, name, email, password, email_verified_at, created_at, updated_at)
user_items (id, user_id, entity_id, metadata, notes, created_at, updated_at)
wishlists (id, user_id, entity_id, created_at, updated_at)
user_collection_favorites (id, user_id, collection_id, created_at, updated_at)
api_tokens (id, user_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at)
```

**Key Points:**
- All IDs are UUIDs (PostgreSQL uuid type)
- `entity_id` and `collection_id` reference external Supabase entities (no FK constraints)
- `metadata` columns are JSONB
- Migrations in `database/migrations/`

### Authentication

- **Laravel Sanctum** for API token authentication
- Supports email/password and Google OAuth login
- Bearer tokens included in GraphQL request headers
- Protected queries/mutations use `@guard(with: ["sanctum"])` directive
- Token stored in `api_tokens` table with abilities and expiration

## Environment Configuration

Key environment variables for local development:

```env
# Database (PostgreSQL via Docker)
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=attic
DB_USERNAME=sail
DB_PASSWORD=password

# Application
APP_PORT=80
APP_URL=http://localhost

# GraphQL
LIGHTHOUSE_CACHE_ENABLE=false
LIGHTHOUSE_PLAYGROUND_ENABLED=true

# Authentication
SANCTUM_STATEFUL_DOMAINS=localhost

# Supabase Database of Things Integration
DATABASE_OF_THINGS_API_URL=http://host.docker.internal:54321
DATABASE_OF_THINGS_API_KEY=sb_publishable_ACJWlzQHlZjBrEguHvfOxg_3BJgxAaH
DATABASE_OF_THINGS_SERVICE_KEY=sb_secret_N7UND0UgjKTVK-Uodkm0Hg_xSvEMPvz
DATABASE_OF_THINGS_PUBLIC_HOST=localhost
```

## Common Tasks

### Adding a New GraphQL Query

1. Define the query in `graphql/schema.graphql`
2. Create resolver class: `./vendor/bin/sail artisan lighthouse:query QueryName`
3. Implement resolver logic in `app/GraphQL/Queries/QueryName.php`
4. Test in GraphQL playground: http://localhost/graphql-playground

### Adding a New GraphQL Mutation

1. Define the mutation in `graphql/schema.graphql`
2. Create resolver class: `./vendor/bin/sail artisan lighthouse:mutation MutationName`
3. Implement resolver logic in `app/GraphQL/Mutations/MutationName.php`
4. Test in GraphQL playground

### Creating a New Migration

```bash
# Create migration file
./vendor/bin/sail artisan make:migration create_table_name

# Edit the migration to use UUIDs:
Schema::create('table_name', function (Blueprint $table) {
    $table->uuid('id')->primary();
    // other columns...
    $table->timestamps();
});

# Run the migration
./vendor/bin/sail artisan migrate
```

### Working with Database of Things (Supabase)

```bash
# Test Supabase connection
./vendor/bin/sail artisan supabase:test --search=Pikachu
```

```php
// Use DatabaseOfThingsService in resolvers
use App\Services\DatabaseOfThingsService;

class MyResolver
{
    public function __invoke($_, array $args)
    {
        $service = app(DatabaseOfThingsService::class);

        // Search entities
        $results = $service->searchEntities($args['query'], $args['first']);

        // Get collection items
        $items = $service->getCollectionItems($collectionId, $first, $after);

        // Semantic search
        $results = $service->semanticSearch($query, $type, $first);

        return $results;
    }
}
```

## GraphQL API Reference

### Available Queries

**Database of Things (Supabase) - Read-Only:**
- `databaseOfThingsCollections` - Browse collections
- `databaseOfThingsSearch` - Text search for entities
- `databaseOfThingsSemanticSearch` - Vector-based semantic search
- `databaseOfThingsCollectionItems` - Get items in a collection
- `databaseOfThingsEntity` - Get single entity by ID
- `databaseOfThingsItemParents` - Get parent collections for an item
- `databaseOfThingsCollectionFilterFields` - Get filterable fields for a collection

**User Data:**
- `me` - Current authenticated user
- `myItems` - User's owned items (UserItem records with entity_id references)
- `myFavoriteCollections` - User's favorited collections with stats
- `myWishlist` - User's wishlist items
- `myApiTokens` - User's API tokens

### Available Mutations

**Authentication:**
- `login(email, password)` - Email/password login
- `register(name, email, password)` - User registration
- `googleLogin(google_token)` - Google OAuth login
- `logout` - Logout current user

**User Item Management:**
- `addItemToMyCollection(entity_id, metadata, notes)` - Add item to collection
- `updateMyItem(entity_id, metadata, notes)` - Update owned item
- `removeItemFromMyCollection(entity_id)` - Remove item from collection

**Collection Favorites:**
- `favoriteCollection(collection_id)` - Add collection to favorites
- `unfavoriteCollection(collection_id)` - Remove from favorites

**Wishlist:**
- `addItemToWishlist(entity_id)` - Add item to wishlist
- `removeItemFromWishlist(entity_id)` - Remove from wishlist

**API Tokens:**
- `createApiToken(name, abilities, expires_at)` - Create new API token
- `revokeApiToken(id)` - Revoke API token

## Custom Artisan Commands

Located in `app/Console/Commands/`:

**TestSupabaseConnection** - Test Database of Things API connectivity
```bash
./vendor/bin/sail artisan supabase:test --search=Pikachu
```

Tests connection to Supabase GraphQL endpoint and performs a sample search query.

## Testing Approach

- Tests use actual PostgreSQL database in Docker (not SQLite)
- Database transactions ensure test isolation
- GraphQL queries tested via HTTP requests to actual endpoints
- Authentication tested with Sanctum tokens