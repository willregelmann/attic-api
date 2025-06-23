# Will's Attic API Documentation

## Overview

Will's Attic API is a Laravel-based REST API for managing collectibles, collections, and user items. The API uses Google OAuth for authentication and Laravel Sanctum for API token management.

**Base URL**: `https://attic-pyhryhudn-will-regelmanns-projects.vercel.app`  
**API Version**: 1.0.0  
**Authentication**: Bearer Token (JWT)

## Authentication

### Google OAuth Flow

The API uses Google OAuth 2.0 for user authentication. The recommended flow for frontend applications:

1. Get Google OAuth URL from the API
2. Redirect user to Google for authentication
3. Exchange Google token for API token
4. Use API token for all subsequent requests

### Environment Variables Needed

```bash
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=your_redirect_uri
```

## API Endpoints

### Authentication Endpoints

#### Get Google OAuth URL
```http
GET /api/auth/google/url
```

**Response:**
```json
{
  "url": "https://accounts.google.com/oauth/authorize?client_id=..."
}
```

#### Authenticate with Google Token
```http
POST /api/auth/google/token
Content-Type: application/json

{
  "google_token": "ya29.a0AfH6SMC..."
}
```

**Response:**
```json
{
  "success": true,
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "username": "collector_one",
    "email": "user@example.com",
    "google_avatar": "https://lh3.googleusercontent.com/...",
    "profile": {
      "displayName": "John Collector",
      "bio": null,
      "location": null
    },
    "preferences": {
      "defaultVisibility": "private",
      "notifications": true
    },
    "trade_rating": {
      "score": 5.0,
      "totalTrades": 0,
      "completedTrades": 0
    },
    "subscription": {
      "tier": "free",
      "expiresAt": null
    },
    "collections": [],
    "items": [],
    "collectibles": [],
    "created_at": "2024-01-01T00:00:00.000000Z",
    "last_active_at": "2024-01-01T00:00:00.000000Z"
  },
  "expires_at": "2024-02-01T00:00:00.000000Z"
}
```

#### Get Current User
```http
GET /api/auth/me
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "username": "collector_one",
    "email": "user@example.com",
    // ... full user object with relationships
  }
}
```

#### Logout Current Session
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Successfully logged out"
}
```

#### Logout All Sessions
```http
POST /api/auth/logout-all
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Successfully logged out from all devices"
}
```

### Health Check

#### API Health
```http
GET /api/health
```

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2024-01-01T00:00:00.000000Z",
  "version": "1.0.0"
}
```

## Data Models

### User
```typescript
interface User {
  id: number;
  username: string;
  email: string;
  google_avatar?: string;
  profile: {
    displayName: string;
    bio?: string;
    location?: string;
  };
  preferences: {
    defaultVisibility: 'public' | 'private' | 'friends-only';
    notifications: boolean;
  };
  trade_rating: {
    score: number;
    totalTrades: number;
    completedTrades: number;
  };
  subscription: {
    tier: 'free' | 'premium';
    expiresAt?: string;
  };
  collections: Collection[];
  items: Item[];
  collectibles: Collectible[];
  created_at: string;
  last_active_at?: string;
}
```

### Collection
```typescript
interface Collection {
  id: number;
  name: string;
  slug: string;
  category: string; // 'trading-cards', 'action-figures', etc.
  type: 'official' | 'community';
  description?: string;
  metadata: {
    releaseDate?: string;
    publisher?: string;
    totalItems?: number;
    [key: string]: any;
  };
  status: 'active' | 'discontinued' | 'upcoming';
  image_url?: string;
  contributed_by?: number;
  verified_by?: number[];
  collectibles: Collectible[];
  created_at: string;
}
```

### Collectible
```typescript
interface Collectible {
  id: number;
  name: string;
  slug: string;
  category: string;
  base_attributes: {
    [key: string]: any; // Flexible per category
  };
  components?: string[]; // For complex items
  variants: Variant[];
  digital_metadata?: {
    blockchain?: string;
    contract?: string;
    [key: string]: any;
  };
  image_urls: {
    primary: string;
    variants?: { [variantId: string]: string };
  };
  contributed_by?: number;
  verified_by?: number[];
  collections: Collection[];
  items: Item[];
  created_at: string;
}

interface Variant {
  id: string;
  name: string;
  estimatedValue: number;
  rarity: 'common_variant' | 'rare_variant' | 'ultra_rare';
}
```

### Item (User's Owned Collectible)
```typescript
interface Item {
  id: number;
  user_id: number;
  collectible_id: number;
  variant_id?: string;
  quantity: number;
  condition: string; // 'Mint', 'Near Mint', 'Good', etc.
  personal_notes?: string;
  component_status?: {
    [component: string]: 'excellent' | 'good' | 'fair' | 'poor';
  };
  completeness: 'complete' | 'incomplete' | 'parts-only';
  acquisition_info: {
    date: string;
    method: 'purchase' | 'trade' | 'gift' | 'found';
    price?: number;
    source?: string;
  };
  storage?: {
    location?: string;
    protection?: string;
  };
  digital_ownership?: {
    wallet?: string;
    verified?: boolean;
  };
  availability: {
    forSale: {
      isListed: boolean;
      price?: number;
    };
    forTrade: {
      isAvailable: boolean;
      preferences?: string;
    };
  };
  user_images?: string[];
  user: User;
  collectible: Collectible;
  created_at: string;
}
```

## Error Handling

### Standard Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### Common HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized (invalid or missing token)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

## Frontend Integration Examples

### React/TypeScript Example

```typescript
// API client setup
class AtticAPI {
  private baseURL = 'https://attic-pyhryhudn-will-regelmanns-projects.vercel.app';
  private token: string | null = localStorage.getItem('attic_token');

  private async request(endpoint: string, options: RequestInit = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers,
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const response = await fetch(url, {
      ...options,
      headers,
    });

    const data = await response.json();
    
    if (!response.ok) {
      throw new Error(data.message || 'API request failed');
    }

    return data;
  }

  // Authentication methods
  async getGoogleAuthUrl() {
    return this.request('/api/auth/google/url');
  }

  async authenticateWithGoogle(googleToken: string) {
    const result = await this.request('/api/auth/google/token', {
      method: 'POST',
      body: JSON.stringify({ google_token: googleToken }),
    });
    
    if (result.success) {
      this.token = result.token;
      localStorage.setItem('attic_token', result.token);
    }
    
    return result;
  }

  async getCurrentUser() {
    return this.request('/api/auth/me');
  }

  async logout() {
    const result = await this.request('/api/auth/logout', {
      method: 'POST',
    });
    
    this.token = null;
    localStorage.removeItem('attic_token');
    
    return result;
  }

  // Future collection/item methods will go here
  // async getCollections() { ... }
  // async getUserItems() { ... }
}

// Usage example
const api = new AtticAPI();

// Google OAuth integration
const handleGoogleLogin = async (googleResponse: any) => {
  try {
    const result = await api.authenticateWithGoogle(googleResponse.access_token);
    if (result.success) {
      console.log('Logged in:', result.user);
      // Redirect to dashboard or update UI state
    }
  } catch (error) {
    console.error('Login failed:', error);
  }
};
```

### JavaScript/Fetch Example

```javascript
// Simple authentication flow
const authenticateUser = async (googleToken) => {
  try {
    const response = await fetch('/api/auth/google/token', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ google_token: googleToken }),
    });

    const data = await response.json();
    
    if (data.success) {
      // Store token for future requests
      localStorage.setItem('attic_token', data.token);
      return data.user;
    } else {
      throw new Error(data.message);
    }
  } catch (error) {
    console.error('Authentication failed:', error);
    throw error;
  }
};

// Make authenticated requests
const makeAuthenticatedRequest = async (endpoint, options = {}) => {
  const token = localStorage.getItem('attic_token');
  
  const response = await fetch(endpoint, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
      ...options.headers,
    },
  });

  if (response.status === 401) {
    // Token expired, redirect to login
    localStorage.removeItem('attic_token');
    window.location.href = '/login';
    return;
  }

  return response.json();
};
```

## Future Endpoints (Not Yet Implemented)

The following endpoints are planned for future implementation:

### Collections
- `GET /api/collections` - List all collections
- `GET /api/collections/{id}` - Get collection details
- `POST /api/collections` - Create new collection (admin)

### Collectibles
- `GET /api/collectibles` - List collectibles with filters
- `GET /api/collectibles/{id}` - Get collectible details
- `POST /api/collectibles` - Create new collectible (admin)

### User Items
- `GET /api/items` - Get user's items
- `POST /api/items` - Add item to collection
- `PUT /api/items/{id}` - Update item details
- `DELETE /api/items/{id}` - Remove item

### Search
- `GET /api/search` - Global search across collections/collectibles

## Rate Limiting

The API implements rate limiting to prevent abuse:
- **Authentication endpoints**: 10 requests per minute
- **General API endpoints**: 100 requests per minute
- **Search endpoints**: 30 requests per minute

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1640995200
```

## Support

For API questions or issues:
- Check the deployment logs in Vercel dashboard
- Review error responses for detailed information
- Ensure proper authentication token format
- Verify request content-type and structure

## Changelog

### v1.0.0 (Current)
- Initial API release
- Google OAuth authentication
- User management
- Core data models for collections, collectibles, and items
- API documentation