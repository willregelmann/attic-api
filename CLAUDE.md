# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel API backend for Attic - a collectibles management platform using GraphQL (Lighthouse) with PostgreSQL database.

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

The API uses Lighthouse GraphQL with schema-first development:
- **Schema**: `graphql/schema.graphql` - Defines all types, queries, and mutations
- **Resolvers**: `app/GraphQL/` - Custom resolvers for complex queries
  - `Queries/` - Query resolvers (SearchItems, CollectionItems, MyItems, etc.)
  - `Mutations/` - Mutation resolvers (AuthMutations, UserItemMutations, FavoriteMutations)
  - `Scalars/` - Custom scalar types (JSON)

### Database Models

Models use UUID primary keys and JSONB for flexible metadata storage:
- **Item**: Core model representing collectibles, collections, variants, and components
- **ItemRelationship**: Many-to-many relationships between items (contains, variant_of, component_of, part_of)
- **User**: Authentication and ownership
- **UserItem**: User's owned items with metadata
- **ItemImage**: Image attachments for items
- **CollectionMaintainer**: Collection management permissions

### Key Design Patterns

1. **Graph-based item structure**: Items can be collections containing other items, with relationship types defining the nature of connections
2. **UUID primary keys**: All models use UUIDs instead of auto-incrementing integers
3. **JSONB metadata**: Flexible storage for varying item attributes without schema changes
4. **Polymorphic items**: Single Item model serves multiple purposes based on `type` field

### Authentication

- Uses Laravel Sanctum for API token authentication
- Supports email/password and Google OAuth login
- Bearer tokens stored and sent with GraphQL requests
- Protected queries/mutations use `@guard` directive

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

### Working with Item Relationships

```php
// Get collection items
$items = $collection->children()
    ->wherePivot('relationship_type', 'contains')
    ->orderBy('item_relationship.canonical_order')
    ->get();

// Add item to collection
$collection->children()->attach($item->id, [
    'relationship_type' => 'contains',
    'canonical_order' => $order,
    'metadata' => ['custom' => 'data']
]);
```

## Custom Artisan Commands

Located in `app/Console/Commands/`:
- `ListPokemonSets`: List available Pokemon TCG sets
- `UpdatePokemonTCG`: Update Pokemon card data
- `LocalizeImages`: Download and store external images locally
- `FixOrderingMetadata`: Fix canonical ordering in collections

## Testing Approach

- Tests use actual PostgreSQL database in Docker (not SQLite)
- Database transactions ensure test isolation
- GraphQL queries tested via HTTP requests to actual endpoints
- Authentication tested with Sanctum tokens