Will's Attic - API Backend Requirements
Project Overview

Will's Attic is a comprehensive collection management platform that supports multiple collectible categories, social features, trading, and premium functionality. The backend will be built using Laravel with Lighthouse GraphQL.
Technology Stack

    Framework: Laravel 10.x
    Hosting: Vercel (using Vercel's Laravel support)
    GraphQL: Lighthouse 6.x
    Database: PlanetScale (MySQL) or Vercel Postgres
    Authentication: Google OAuth 2.0 + Laravel Socialite
    File Storage: Vercel Blob Storage or AWS S3
    Queue System: Vercel Functions or external Redis
    Search: Algolia (Vercel partner)
    Cache: Vercel Edge Cache + Redis (if needed)
    Image Processing: Vercel's Image Optimization API

Core Data Models & Relationships
1. Users

php

// Migration: create_users_table
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('username')->unique();
    $table->string('email')->unique();
    $table->string('google_id')->unique(); // Google OAuth ID
    $table->string('google_avatar')->nullable(); // Google profile picture
    $table->timestamp('email_verified_at')->nullable();
    $table->json('profile'); // displayName, bio, location (avatar from Google)
    $table->json('preferences'); // defaultVisibility, notifications, etc.
    $table->json('trade_rating'); // score, totalTrades, completedTrades
    $table->json('subscription'); // tier, expiresAt
    $table->timestamp('last_active_at')->nullable();
    $table->timestamps();
});

2. Collections

php

// Migration: create_collections_table
Schema::create('collections', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('category'); // trading-cards, action-figures, etc.
    $table->enum('type', ['official', 'community'])->default('official');
    $table->text('description')->nullable();
    $table->json('metadata'); // releaseDate, publisher, totalItems, etc.
    $table->enum('status', ['active', 'discontinued', 'upcoming'])->default('active');
    $table->string('image_url')->nullable();
    $table->foreignId('contributed_by')->nullable()->constrained('users');
    $table->json('verified_by')->nullable(); // Array of user IDs
    $table->timestamps();
    
    $table->index(['category', 'status']);
    $table->index('slug');
});

3. Collectables

php

// Migration: create_collectables_table
Schema::create('collectables', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('category');
    $table->json('base_attributes'); // Flexible attributes per category
    $table->json('components')->nullable(); // For complex items like toys
    $table->json('variants'); // Array of variant objects with pricing
    $table->json('digital_metadata')->nullable(); // blockchain, contract, etc.
    $table->json('image_urls'); // primary and variant images
    $table->foreignId('contributed_by')->nullable()->constrained('users');
    $table->json('verified_by')->nullable();
    $table->timestamps();
    
    $table->index('category');
    $table->index('slug');
    $table->fullText(['name', 'slug']);
});

// Pivot table for collections <-> collectables relationship
Schema::create('collectable_collection', function (Blueprint $table) {
    $table->id();
    $table->foreignId('collectable_id')->constrained()->onDelete('cascade');
    $table->foreignId('collection_id')->constrained()->onDelete('cascade');
    $table->timestamps();
    
    $table->unique(['collectable_id', 'collection_id']);
});

4. Items (User's Owned Items)

php

// Migration: create_items_table
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('collectable_id')->constrained();
    $table->string('variant_id')->nullable(); // References variant within collectable
    $table->integer('quantity')->default(1);
    $table->string('condition');
    $table->text('personal_notes')->nullable();
    $table->json('component_status')->nullable(); // For tracking parts
    $table->enum('completeness', ['complete', 'incomplete', 'parts-only'])->default('complete');
    $table->json('acquisition_info'); // date, method, price, source, etc.
    $table->json('storage')->nullable(); // location, protection
    $table->json('digital_ownership')->nullable(); // wallet, blockchain verification
    $table->json('availability'); // forSale, forTrade settings
    $table->json('showcase_history')->nullable(); // Track showcase membership
    $table->json('user_images')->nullable(); // User-uploaded photos
    $table->timestamps();
    
    $table->index(['user_id', 'collectable_id']);
    $table->index(['availability->forSale->isListed', 'availability->forSale->price']);
    $table->index('availability->forTrade->isAvailable');
});

5. Showcases

php

// Migration: create_showcases_table
Schema::create('showcases', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->string('slug');
    $table->text('description')->nullable();
    $table->enum('category', ['personal', 'public', 'shared'])->default('personal');
    $table->enum('visibility', ['public', 'private', 'friends-only'])->default('private');
    $table->enum('display_type', ['grid', 'timeline', 'story', 'comparison'])->default('grid');
    $table->json('theme'); // coverImage, backgroundColor, layout
    $table->json('items'); // Array of {itemId, position, displayOptions}
    $table->json('stats'); // views, likes, shares
    $table->json('tags')->nullable();
    $table->boolean('allow_comments')->default(true);
    $table->timestamps();
    
    $table->index(['user_id', 'visibility']);
    $table->index(['category', 'visibility']);
    $table->unique(['user_id', 'slug']);
});

6. Wishlists

php

// Migration: create_wishlists_table
Schema::create('wishlists', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('collectable_id')->constrained();
    $table->string('variant_id')->nullable();
    $table->json('preferences'); // maxPrice, minCondition, etc.
    $table->json('trade_preferences'); // willingToTrade, cashPlusItems, etc.
    $table->json('notifications'); // newListings, priceDrops, etc.
    $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index(['user_id', 'is_active']);
    $table->index(['collectable_id', 'is_active']);
    $table->unique(['user_id', 'collectable_id', 'variant_id']);
});

7. Trade Matches (Computed)

php

// Migration: create_trade_matches_table
Schema::create('trade_matches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('wishlist_id')->constrained()->onDelete('cascade');
    $table->foreignId('wishlist_user_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('available_item_id')->constrained('items')->onDelete('cascade');
    $table->foreignId('available_item_user_id')->constrained('users')->onDelete('cascade');
    $table->decimal('match_score', 3, 2); // 0.00 to 1.00
    $table->json('match_factors'); // priceMatch, conditionMatch, etc.
    $table->boolean('mutual_match')->default(false);
    $table->foreignId('mutual_match_item_id')->nullable()->constrained('items');
    $table->json('estimated_trade_value');
    $table->enum('status', ['active', 'user_contacted', 'trade_initiated', 'expired'])->default('active');
    $table->json('notifications_sent')->nullable();
    $table->timestamp('expires_at');
    $table->timestamps();
    
    $table->index(['wishlist_user_id', 'status']);
    $table->index(['available_item_user_id', 'status']);
    $table->index(['mutual_match', 'status']);
});

8. Trade Offers

php

// Migration: create_trade_offers_table
Schema::create('trade_offers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('from_user_id')->constrained('users');
    $table->foreignId('to_user_id')->constrained('users');
    $table->json('offered_items'); // Array of {itemId, quantity}
    $table->json('requested_items'); // Array of {itemId, quantity}
    $table->decimal('cash_adjustment', 10, 2)->default(0); // + means from_user adds cash
    $table->enum('status', ['pending', 'accepted', 'declined', 'expired', 'completed'])->default('pending');
    $table->json('messages')->nullable(); // Trade conversation
    $table->timestamp('expires_at');
    $table->timestamp('accepted_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    
    $table->index(['from_user_id', 'status']);
    $table->index(['to_user_id', 'status']);
});

9. Item Watchers

php

// Migration: create_item_watchers_table
Schema::create('item_watchers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('item_id')->constrained()->onDelete('cascade');
    $table->enum('watch_type', ['sale', 'trade', 'both'])->default('both');
    $table->boolean('notified')->default(false); // For price changes
    $table->timestamps();
    
    $table->unique(['user_id', 'item_id']);
    $table->index(['item_id', 'watch_type']);
});

GraphQL Schema Structure
Core Types

graphql

type User {
  id: ID!
  username: String!
  email: String!
  googleId: String!
  googleAvatar: String
  profile: UserProfile!
  preferences: UserPreferences!
  tradeRating: TradeRating!
  subscription: Subscription!
  collections: [Collection!]! @hasMany
  items: [Item!]! @hasMany
  showcases: [Showcase!]! @hasMany
  wishlists: [Wishlist!]! @hasMany
  tradeOffers: [TradeOffer!]! @hasMany
  createdAt: DateTime!
  lastActiveAt: DateTime
}

type Collection {
  id: ID!
  name: String!
  slug: String!
  category: String!
  type: CollectionType!
  description: String
  metadata: JSON
  status: CollectionStatus!
  imageUrl: String
  collectables: [Collectable!]! @belongsToMany
  contributedBy: User @belongsTo
  verifiedBy: [User!]
  userProgress(userId: ID): CollectionProgress
  createdAt: DateTime!
}

type Collectable {
  id: ID!
  name: String!
  slug: String!
  category: String!
  baseAttributes: JSON!
  components: [Component!]
  variants: [Variant!]!
  digitalMetadata: DigitalMetadata
  imageUrls: ImageUrls!
  collections: [Collection!]! @belongsToMany
  userItems(userId: ID): [Item!]
  marketData: MarketData
  contributedBy: User @belongsTo
  verifiedBy: [User!]
  createdAt: DateTime!
}

type Item {
  id: ID!
  user: User! @belongsTo
  collectable: Collectable! @belongsTo
  variantId: String
  quantity: Int!
  condition: String!
  personalNotes: String
  componentStatus: [ComponentStatus!]
  completeness: Completeness!
  acquisitionInfo: AcquisitionInfo!
  storage: Storage
  digitalOwnership: DigitalOwnership
  availability: ItemAvailability!
  watchers: [ItemWatcher!]! @hasMany
  showcaseHistory: [ShowcaseHistory!]
  userImages: [String!]
  estimatedValue: Money
  createdAt: DateTime!
}

type Showcase {
  id: ID!
  user: User! @belongsTo
  name: String!
  slug: String!
  description: String
  category: ShowcaseCategory!
  visibility: Visibility!
  displayType: DisplayType!
  theme: ShowcaseTheme!
  items: [ShowcaseItem!]!
  stats: ShowcaseStats!
  tags: [String!]
  allowComments: Boolean!
  createdAt: DateTime!
}

type Wishlist {
  id: ID!
  user: User! @belongsTo
  collectable: Collectable! @belongsTo
  variantId: String
  preferences: WishlistPreferences!
  tradePreferences: TradePreferences!
  notifications: NotificationSettings!
  priority: Priority!
  isActive: Boolean!
  availableMatches: [TradeMatch!]
  createdAt: DateTime!
}

type TradeMatch {
  id: ID!
  wishlist: Wishlist! @belongsTo
  availableItem: Item! @belongsTo
  matchScore: Float!
  matchFactors: MatchFactors!
  mutualMatch: Boolean!
  estimatedTradeValue: TradeValue!
  status: TradeMatchStatus!
  createdAt: DateTime!
  expiresAt: DateTime!
}

type TradeOffer {
  id: ID!
  fromUser: User! @belongsTo
  toUser: User! @belongsTo
  offeredItems: [TradeItem!]!
  requestedItems: [TradeItem!]!
  cashAdjustment: Money!
  status: TradeOfferStatus!
  messages: [TradeMessage!]!
  expiresAt: DateTime!
  createdAt: DateTime!
}

Key Mutations

graphql

type Mutation {
  # Authentication (Google OAuth)
  loginWithGoogle(googleToken: String!): AuthPayload!
  completeRegistration(input: CompleteRegistrationInput!): AuthPayload!
  logout: Boolean!
  
  # Collections & Collectables
  createCollection(input: CreateCollectionInput!): Collection!
  updateCollection(id: ID!, input: UpdateCollectionInput!): Collection!
  createCollectable(input: CreateCollectableInput!): Collectable!
  updateCollectable(id: ID!, input: UpdateCollectableInput!): Collectable!
  
  # Items
  addItem(input: AddItemInput!): Item!
  updateItem(id: ID!, input: UpdateItemInput!): Item!
  deleteItem(id: ID!): Boolean!
  updateItemAvailability(id: ID!, availability: ItemAvailabilityInput!): Item!
  
  # Wishlists
  addToWishlist(input: WishlistInput!): Wishlist!
  updateWishlist(id: ID!, input: UpdateWishlistInput!): Wishlist!
  removeFromWishlist(id: ID!): Boolean!
  
  # Showcases
  createShowcase(input: CreateShowcaseInput!): Showcase!
  updateShowcase(id: ID!, input: UpdateShowcaseInput!): Showcase!
  addItemToShowcase(showcaseId: ID!, itemId: ID!, displayOptions: ShowcaseItemDisplayInput): ShowcaseItem!
  removeItemFromShowcase(showcaseId: ID!, itemId: ID!): Boolean!
  
  # Trading
  createTradeOffer(input: CreateTradeOfferInput!): TradeOffer!
  respondToTradeOffer(id: ID!, response: TradeOfferResponse!, message: String): TradeOffer!
  addTradeMessage(tradeOfferId: ID!, message: String!): TradeMessage!
  
  # Watching
  watchItem(itemId: ID!, watchType: WatchType!): ItemWatcher!
  unwatchItem(itemId: ID!): Boolean!
  
  # File uploads
  uploadImage(file: Upload!, type: ImageType!): String!
}

Key Queries

graphql

type Query {
  # User data
  me: User @auth
  user(username: String!): User
  
  # Collections & Collectables
  collections(category: String, type: CollectionType, status: CollectionStatus): [Collection!]!
  collection(slug: String!): Collection
  collectables(
    category: String,
    collections: [ID!],
    search: String,
    owned: Boolean,
    wishlist: Boolean
  ): [Collectable!]!
  collectable(slug: String!): Collectable
  
  # Items
  myItems(
    category: String,
    collections: [ID!],
    forSale: Boolean,
    forTrade: Boolean,
    condition: String
  ): [Item!]! @auth
  itemsForSale(
    category: String,
    maxPrice: Float,
    condition: String,
    location: String
  ): [Item!]!
  itemsForTrade(category: String, wantedItems: [ID!]): [Item!]!
  
  # Wishlists & Matches
  myWishlist: [Wishlist!]! @auth
  tradeMatches: [TradeMatch!]! @auth
  mutualMatches: [TradeMatch!]! @auth
  
  # Showcases
  myShowcases: [Showcase!]! @auth
  publicShowcases(category: String, tags: [String!]): [Showcase!]!
  showcase(userId: ID!, slug: String!): Showcase
  
  # Trading
  myTradeOffers: [TradeOffer!]! @auth
  tradeOffer(id: ID!): TradeOffer! @auth
  
  # Analytics (Premium)
  collectionAnalytics: CollectionAnalytics! @auth @premium
  marketTrends(category: String!): MarketTrends! @premium
  priceHistory(collectableId: ID!, variantId: String): [PricePoint!]! @premium
}

Key Features Implementation
1. Authentication & Authorization

    Google OAuth 2.0 integration with Laravel Socialite
    JWT tokens for API authentication after Google login
    GraphQL middleware for auth checking
    Role-based permissions (admin, premium user, standard user)
    Rate limiting on mutations
    First-time user onboarding flow (username selection, preferences)

2. Search & Filtering

    Algolia integration for full-text search (Vercel partner)
    Advanced filtering with Lighthouse arguments
    Category-specific attribute filtering
    Geographic search for local trading
    Real-time search suggestions

3. Real-time Features

    Vercel Functions for real-time notifications
    WebSocket connections via Pusher or Ably for trade offers
    Live price updates for market data
    Real-time showcase view counts
    Push notifications via Firebase (mobile) or web push

4. Background Jobs

    Vercel Cron Jobs for scheduled tasks
    Trade match computation (hourly via cron)
    Price data scraping from external APIs (daily cron)
    Email notifications (daily digest + instant via queues)
    Image processing with Vercel's Image Optimization
    Cache warming for popular queries

5. Premium Features

    Advanced analytics queries
    Enhanced notification settings
    Priority trade matching
    Unlimited showcases
    Price history and trend data

6. File Management

    Vercel Blob Storage for user uploads
    Image upload validation and processing
    Multiple image sizes via Vercel Image Optimization
    CDN integration for fast global delivery
    User-generated content moderation

7. External Integrations

    Google OAuth for authentication
    TCGPlayer API for Pokemon card prices
    eBay API for market data
    Stripe for premium subscriptions (Vercel integration)
    SendGrid for emails (Vercel partner)
    Blockchain APIs for NFT verification
    Algolia for search (Vercel partner)

Performance Considerations
Database Optimization

    Proper indexing on frequent query fields
    JSON column indexing for flexible attributes
    Query optimization with database views
    PlanetScale's connection pooling and branching
    Database insights and performance monitoring

Caching Strategy

    Vercel Edge Cache for API responses
    Redis (Upstash) for session storage if needed
    Query result caching with tags
    Fragment caching for expensive computations
    CDN caching for static assets and images

API Performance

    DataLoader pattern for N+1 query prevention
    Query complexity analysis and limiting
    Response pagination with cursor-based pagination
    GraphQL query caching

Security Requirements
Data Protection

    Input validation and sanitization
    SQL injection prevention
    XSS protection
    File upload security
    Rate limiting and DDoS protection

Privacy

    GDPR compliance for EU users
    User data export functionality
    Account deletion with data cleanup
    Privacy controls for user profiles

API Security

    Google OAuth 2.0 security standards
    CORS configuration for Vercel deployment
    API versioning strategy
    Request signing for sensitive operations
    Audit logging for admin actions
    Vercel's built-in DDoS protection

Testing Strategy
Unit Tests

    Model relationships and validations
    Business logic in services
    GraphQL resolvers
    Authentication and authorization

Integration Tests

    API endpoint testing
    Database transaction integrity
    File upload workflows
    External API integrations

Performance Tests

    Load testing for concurrent users
    Database query performance
    Memory usage optimization
    Cache effectiveness

Deployment & DevOps
Vercel Deployment

    Automatic deployments from Git
    Environment variables configuration
    Preview deployments for testing
    Production deployment with custom domain
    Serverless function deployment
    Database connection via PlanetScale

Monitoring

    Vercel Analytics for performance insights
    Error tracking with Sentry (Vercel integration)
    Uptime monitoring
    Database performance monitoring (PlanetScale insights)
    User analytics and behavior tracking

Scaling Considerations

    Vercel's automatic scaling for serverless functions
    Edge caching and global distribution
    Database scaling with PlanetScale
    CDN optimization for global users
    Function timeout and memory optimization

This API backend will provide a robust, scalable foundation for Will's Attic with comprehensive GraphQL endpoints, real-time features, and premium functionality that can grow with the user base.

