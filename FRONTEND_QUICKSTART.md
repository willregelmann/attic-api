# Frontend Quick Start Guide

## 🚀 Get Started in 5 Minutes

### API Base URL
```
https://attic-pyhryhudn-will-regelmanns-projects.vercel.app
```

### Essential Endpoints

#### 1. Get Google OAuth URL
```javascript
const { url } = await fetch('/api/auth/google/url').then(r => r.json());
// Redirect user to this URL for Google sign-in
```

#### 2. Login with Google Token
```javascript
const loginData = await fetch('/api/auth/google/token', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ google_token: 'GOOGLE_ACCESS_TOKEN_HERE' })
}).then(r => r.json());

// Save the token
localStorage.setItem('attic_token', loginData.token);
```

#### 3. Get Current User
```javascript
const userData = await fetch('/api/auth/me', {
  headers: { 'Authorization': `Bearer ${localStorage.getItem('attic_token')}` }
}).then(r => r.json());
```

#### 4. Logout
```javascript
await fetch('/api/auth/logout', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${localStorage.getItem('attic_token')}` }
});
localStorage.removeItem('attic_token');
```

### React Hook Example

```typescript
// useAuth.ts
import { useState, useEffect } from 'react';

interface User {
  id: number;
  username: string;
  email: string;
  profile: {
    displayName: string;
    bio?: string;
    location?: string;
  };
  // ... other fields
}

export const useAuth = () => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState<string | null>(
    localStorage.getItem('attic_token')
  );

  const API_BASE = 'https://attic-pyhryhudn-will-regelmanns-projects.vercel.app';

  useEffect(() => {
    if (token) {
      fetchUser();
    } else {
      setLoading(false);
    }
  }, [token]);

  const fetchUser = async () => {
    try {
      const response = await fetch(`${API_BASE}/api/auth/me`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      
      if (response.ok) {
        const data = await response.json();
        setUser(data.user);
      } else {
        // Token invalid
        logout();
      }
    } catch (error) {
      console.error('Failed to fetch user:', error);
      logout();
    } finally {
      setLoading(false);
    }
  };

  const login = async (googleToken: string) => {
    try {
      const response = await fetch(`${API_BASE}/api/auth/google/token`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ google_token: googleToken })
      });

      const data = await response.json();
      
      if (data.success) {
        setToken(data.token);
        setUser(data.user);
        localStorage.setItem('attic_token', data.token);
        return data.user;
      } else {
        throw new Error(data.message || 'Login failed');
      }
    } catch (error) {
      console.error('Login error:', error);
      throw error;
    }
  };

  const logout = async () => {
    if (token) {
      try {
        await fetch(`${API_BASE}/api/auth/logout`, {
          method: 'POST',
          headers: { 'Authorization': `Bearer ${token}` }
        });
      } catch (error) {
        console.error('Logout error:', error);
      }
    }
    
    setToken(null);
    setUser(null);
    localStorage.removeItem('attic_token');
  };

  return {
    user,
    loading,
    login,
    logout,
    isAuthenticated: !!user
  };
};
```

### Google OAuth Setup

1. **Create Google OAuth App**:
   - Go to [Google Cloud Console](https://console.cloud.google.com/)
   - Create new project or select existing
   - Enable Google+ API
   - Create OAuth 2.0 credentials
   - Add your domain to authorized origins

2. **Frontend Integration** (React example):
```bash
npm install @google-cloud/oauth2
# or
npm install react-google-login
```

```jsx
import { GoogleLogin } from 'react-google-login';
import { useAuth } from './useAuth';

const LoginPage = () => {
  const { login } = useAuth();

  const handleGoogleSuccess = async (response) => {
    try {
      await login(response.accessToken);
      // Redirect to dashboard
    } catch (error) {
      console.error('Login failed:', error);
    }
  };

  return (
    <GoogleLogin
      clientId="YOUR_GOOGLE_CLIENT_ID"
      buttonText="Sign in with Google"
      onSuccess={handleGoogleSuccess}
      onFailure={(error) => console.error('Google login failed:', error)}
      cookiePolicy={'single_host_origin'}
      scope="profile email"
    />
  );
};
```

### Environment Variables Needed

Create `.env.local` file:
```env
NEXT_PUBLIC_API_BASE_URL=https://attic-pyhryhudn-will-regelmanns-projects.vercel.app
NEXT_PUBLIC_GOOGLE_CLIENT_ID=your_google_client_id_here
```

### Sample User Object

```json
{
  "id": 1,
  "username": "collector_one",
  "email": "user@example.com",
  "google_avatar": "https://lh3.googleusercontent.com/...",
  "profile": {
    "displayName": "John Collector",
    "bio": "Pokemon card enthusiast",
    "location": "San Francisco, CA"
  },
  "preferences": {
    "defaultVisibility": "private",
    "notifications": true
  },
  "trade_rating": {
    "score": 4.8,
    "totalTrades": 25,
    "completedTrades": 24
  },
  "subscription": {
    "tier": "premium",
    "expiresAt": "2024-12-31T23:59:59Z"
  },
  "collections": [],
  "items": [],
  "collectibles": [],
  "created_at": "2024-01-01T00:00:00Z",
  "last_active_at": "2024-01-01T12:30:00Z"
}
```

### Error Handling

```javascript
const handleApiError = (error, response) => {
  if (response?.status === 401) {
    // Token expired, redirect to login
    localStorage.removeItem('attic_token');
    window.location.href = '/login';
  } else if (response?.status === 422) {
    // Validation errors
    console.error('Validation errors:', error.errors);
  } else {
    // General error
    console.error('API error:', error.message);
  }
};
```

### Next Steps

1. **Test Authentication**: Implement the login flow
2. **Handle User State**: Store user data in your state management
3. **Protected Routes**: Check authentication before accessing user features
4. **Error Handling**: Implement proper error boundaries

### Need Help?

- Check the full [API Documentation](./API_DOCUMENTATION.md)
- API is currently behind Vercel auth - will need to be configured for public access
- All endpoints return JSON with consistent error format
- Token expires in 30 days, implement refresh logic as needed