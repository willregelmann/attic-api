# Attic MCP Server

Model Context Protocol server for the Attic API, providing collection exploration tools to AI agents.

## Overview

This MCP server allows AI agents (like the curator) to explore the Attic database and understand the current state of collections before making suggestions. It uses the official MCP protocol, avoiding custom tool parameter bugs.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Attic API Container                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────┐         ┌──────────────────┐         │
│  │  Laravel API    │         │  MCP Server      │         │
│  │  (PHP/GraphQL)  │         │  (Node.js)       │         │
│  │                 │         │                  │         │
│  │  Port 8000      │         │  stdio protocol  │         │
│  └────────┬────────┘         └────────┬─────────┘         │
│           │                           │                    │
│           └───────────┬───────────────┘                    │
│                       │                                    │
│                   PostgreSQL                               │
│                   Connection                               │
└─────────────────────────────────────────────────────────────┘
                        ▲
                        │ MCP Protocol (stdio)
                        │
         ┌──────────────┴──────────────┐
         │   Agent SDK Hybrid Curator  │
         │   (curator-agent service)   │
         └─────────────────────────────┘
```

## Tools Provided

### 1. `get_collection`
Get collection details including name, metadata, and immediate children.

**Parameters:**
- `collection_id` (string, required): UUID of the collection

**Use case:** Quick overview of what's directly inside a collection

### 2. `get_collection_tree`
Get the full hierarchy of a collection (recursive).

**Parameters:**
- `collection_id` (string, required): UUID of the root collection
- `max_depth` (number, optional): Maximum depth to traverse (default: 3)

**Use case:** Understanding the complete structure before suggesting additions

### 3. `search_items`
Search for items or collections by name.

**Parameters:**
- `query` (string, required): Search query (case-insensitive)
- `type` (string, optional): Filter by type ("collection" or "collectible")

**Use case:** Check if something already exists before suggesting it

### 4. `get_subcollections`
Get direct subcollections with child counts.

**Parameters:**
- `collection_id` (string, required): UUID of the parent collection

**Use case:** Identify which areas need more work (empty subcollections, etc.)

## Usage

### Local Development

```bash
# Run the MCP server
cd /home/will/Projects/wills-attic/attic-api
node mcp-server/index.js
```

The server communicates via stdio (standard input/output), following the MCP protocol.

### From Agent SDK

```javascript
import { query } from '@anthropic-ai/claude-agent-sdk';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';

// Create MCP client
const mcpTransport = new StdioClientTransport({
  command: 'node',
  args: ['/path/to/attic-api/mcp-server/index.js']
});

const mcpClient = new Client({
  name: 'curator-client',
  version: '1.0.0'
}, {
  capabilities: {}
});

await mcpClient.connect(mcpTransport);

// Use in Agent SDK query
for await (const msg of query({
  prompt: 'Explore the collection...',
  options: {
    systemPrompt: '...',
    mcpServers: [mcpClient]
  }
})) {
  // Agent can now use MCP tools
}
```

## Database Schema

The MCP server connects directly to PostgreSQL using the same `.env` configuration as Laravel:

```env
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=attic
DB_USERNAME=sail
DB_PASSWORD=password
```

### Key Tables

- `items`: Collections and collectibles (UUID primary keys)
- `item_relationship`: Many-to-many relationships with `relationship_type`:
  - `contains`: Collection contains items
  - `variant_of`: Item is a variant
  - `component_of`: Item is part of another
  - `part_of`: Generic parent-child

## Next Steps

1. **Update Hybrid Curator** - Modify `curator-agent/src/agent-sdk-hybrid-curator.js` to:
   - Connect to the MCP server
   - Use collection exploration tools before researching
   - Make context-aware suggestions

2. **Test Integration** - Run the hybrid curator with MCP tools enabled

3. **Deploy** - Add MCP server startup to Railway deployment

## Files

- `index.js` - Main MCP server (stdio transport)
- `db.js` - PostgreSQL connection and query helpers
- `README.md` - This file

## Dependencies

- `@modelcontextprotocol/sdk` - Official MCP SDK
- `pg` - PostgreSQL client
- `dotenv` - Environment variable loading

## Benefits Over Custom Tools

✅ **Official protocol** - Better maintained
✅ **No parameter bugs** - MCP is well-tested
✅ **Direct database access** - Faster than HTTP
✅ **Context-aware** - Agent knows current state
✅ **Reusable** - Other agents can use same server
