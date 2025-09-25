# Will's Attic - Curator Agent Architecture

## Overview

The curator system uses autonomous AI agents that interact with the Attic API as special users. Curators have their own API tokens and operate independently from the main application.

## Architecture Components

### 1. Main API Service (Laravel)
- Creates curator user accounts
- Generates API tokens for curators
- Sends commands via Redis pub/sub
- Never stores curator tokens

### 2. Curator Service (Node.js/LangChain)
- Runs AI agents using Anthropic Claude
- Maintains its own SQLite database for tokens
- Receives commands via Redis pub/sub
- Executes curation tasks autonomously

### 3. Message Bus (Redis)
- Secure service-to-service communication
- HMAC-signed messages with timestamps
- Commands: register, run, update, delete
- Events: status updates, completion notifications

## Security Model

```
User → Creates Curator → Main API → Generates Token → Message Bus → Curator Service
                            ↓                                           ↓
                        No Token Stored                          Token Encrypted & Stored
```

### Key Security Features:
- **Token Isolation**: API tokens never stored in main database
- **Encrypted Storage**: Tokens encrypted in curator service database
- **Signed Messages**: All inter-service messages are HMAC-signed
- **Time-bound Messages**: 60-second validity window prevents replay attacks
- **Service Secret**: Shared secret for service authentication

## Data Flow

### 1. Curator Creation
```javascript
// User creates curator in UI
mutation CreateCurator {
  createCuratorUser(
    name: "Professor Oak"
    collection_id: "pokemon-tcg-id"
    prompt: "Manage Pokemon TCG collection"
  ) {
    curator { id, name }
    // No token returned to user!
  }
}

// Main API internally:
1. Creates curator user account
2. Generates API token
3. Sends to curator service via Redis:
   {
     type: 'register_curator',
     curator_id: '...',
     api_token: 'secret_token',
     config: { ... }
   }
4. Token is never stored in main DB

// Curator Service:
1. Receives message
2. Verifies HMAC signature
3. Encrypts and stores token
4. Activates AI agent
```

### 2. Manual Curator Run
```javascript
// User triggers run
mutation RunCurator {
  runCurator(id: "curator-id") {
    success
    message
  }
}

// Message flow:
Main API → Redis → Curator Service → Execute Agent → Redis → Main API
```

## Database Schema

### Main API Database (PostgreSQL)
```sql
-- Users table (extended)
users
  - id (UUID)
  - user_type ('human' | 'curator')
  - curator_owner_id (references users)
  - curator_config (JSON)

-- Collection curators
collection_curators
  - id
  - collection_id
  - curator_user_id
  - prompt
  - schedule
  - status
```

### Curator Service Database (SQLite)
```sql
-- Secure token storage
curator_tokens
  - curator_id (PRIMARY KEY)
  - api_token (ENCRYPTED)
  - created_at
  - last_used_at
  - is_active

-- Run history
curator_runs
  - id
  - curator_id
  - started_at
  - completed_at
  - status
  - output
  - suggestions_made

-- Configuration cache
curator_configs
  - curator_id
  - name
  - collection_id
  - prompt
  - schedule
  - model
```

## Message Bus Protocol

### Message Structure
```javascript
{
  data: {
    type: 'command_type',
    curator_id: '...',
    // command-specific data
  },
  timestamp: 1234567890,
  signature: 'hmac_sha256_signature'
}
```

### Commands
- `register_curator`: Create new curator with token
- `run_curator`: Execute curator task
- `update_curator`: Update configuration
- `delete_curator`: Remove curator

### Events
- `curator_registered`: Confirmation of registration
- `curator_run_complete`: Task completion with results
- `curator_error`: Error notifications

## Deployment

### Railway Services Setup

#### 1. Main API Service
```env
REDIS_URL=redis://...
CURATOR_SHARED_SECRET=secure-random-string
```

#### 2. Curator Service
```env
# Redis for messaging
REDIS_URL=redis://...
CURATOR_SHARED_SECRET=secure-random-string

# AI Configuration
ANTHROPIC_API_KEY=sk-ant-...
ATTIC_API_URL=https://api.railway.app/graphql

# Security
ENCRYPTION_KEY=32-char-key-for-token-encryption

# Database
DATABASE_PATH=/data/curator.db
```

#### 3. Redis Service
- Shared Redis instance for pub/sub
- No persistence needed (messages are transient)

## Monitoring

### Health Endpoints
```bash
# Curator Service Health
GET /health
{
  "status": "healthy",
  "curators": ["curator-1", "curator-2"],
  "database": "connected",
  "redis": "connected"
}

# Run History
GET /curators/:id/history
[
  {
    "run_id": "...",
    "started_at": "...",
    "status": "completed",
    "suggestions_made": 5
  }
]
```

### Logging
- All commands logged with timestamps
- Run outputs stored in curator service DB
- Errors tracked with full stack traces

## Benefits

1. **Security**: Tokens never exposed or stored in main DB
2. **Scalability**: Curator service can scale independently
3. **Reliability**: Service isolation prevents cascading failures
4. **Flexibility**: Easy to add new AI models or strategies
5. **Auditability**: Complete history in curator service
6. **Maintainability**: Clean separation of concerns

## Future Enhancements

- [ ] Webhook support for external triggers
- [ ] Multiple AI model support (GPT-4, Claude, etc.)
- [ ] Curator collaboration (multiple curators per collection)
- [ ] Advanced scheduling (cron expressions)
- [ ] Web UI for curator service monitoring
- [ ] Backup/restore for curator database