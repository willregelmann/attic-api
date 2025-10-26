#!/usr/bin/env node
/**
 * Attic MCP Server
 *
 * Provides Model Context Protocol tools for exploring the Attic collection database.
 * Used by AI agents (like the curator) to understand the current state of collections
 * before making suggestions.
 *
 * Tools provided:
 * - get_collection: Get collection details with immediate children
 * - get_collection_tree: Get full collection hierarchy
 * - search_items: Search for items by name
 * - get_subcollections: Get direct subcollections only
 * - create_suggestion: Create a curator suggestion (WRITE operation)
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import db from './db.js';

// Create MCP server
const server = new Server(
  {
    name: 'attic-mcp-server',
    version: '1.0.0',
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

// Tool definitions
const TOOLS = [
  {
    name: 'get_collection',
    description: 'Get collection details including name, metadata, and immediate children. ' +
                'Use this to see what\'s directly inside a collection.',
    inputSchema: {
      type: 'object',
      properties: {
        collection_id: {
          type: 'string',
          description: 'UUID of the collection to retrieve',
        },
      },
      required: ['collection_id'],
    },
  },
  {
    name: 'get_collection_tree',
    description: 'Get the full hierarchy of a collection (recursive). ' +
                'Use this to understand the complete structure before suggesting additions. ' +
                'Returns all subcollections and their children up to max_depth.',
    inputSchema: {
      type: 'object',
      properties: {
        collection_id: {
          type: 'string',
          description: 'UUID of the root collection',
        },
        max_depth: {
          type: 'number',
          description: 'Maximum depth to traverse (default: 3)',
          default: 3,
        },
      },
      required: ['collection_id'],
    },
  },
  {
    name: 'search_items',
    description: 'Search for items or collections by name. ' +
                'Use this to check if something already exists before suggesting it.',
    inputSchema: {
      type: 'object',
      properties: {
        query: {
          type: 'string',
          description: 'Search query (case-insensitive)',
        },
        type: {
          type: 'string',
          description: 'Filter by type: "collection" or "collectible"',
          enum: ['collection', 'collectible'],
        },
      },
      required: ['query'],
    },
  },
  {
    name: 'get_subcollections',
    description: 'Get direct subcollections of a collection with child counts. ' +
                'Use this to see which subcollections exist and how many items they contain. ' +
                'Useful for identifying which areas need more work.',
    inputSchema: {
      type: 'object',
      properties: {
        collection_id: {
          type: 'string',
          description: 'UUID of the parent collection',
        },
      },
      required: ['collection_id'],
    },
  },
  {
    name: 'create_suggestion',
    description: 'Create a curator suggestion to add a subcollection or item. ' +
                'Use this after researching the collection structure to suggest additions. ' +
                'You can specify a parent_subcollection to nest items hierarchically. ' +
                'IMPORTANT: Always check if an item exists first using search_items!',
    inputSchema: {
      type: 'object',
      properties: {
        collection_id: {
          type: 'string',
          description: 'UUID of the collection this suggestion is for',
        },
        action_type: {
          type: 'string',
          description: 'Type of action (default: "add_subcollection")',
          default: 'add_subcollection',
          enum: ['add_subcollection', 'add_item', 'add_items', 'update_item', 'delete_item'],
        },
        subcollection_name: {
          type: 'string',
          description: 'Name of the subcollection to create (required for add_subcollection)',
        },
        parent_subcollection: {
          type: 'string',
          description: 'Name of the parent subcollection (null for top-level)',
        },
        item_name: {
          type: 'string',
          description: 'Name of the item to add (for add_item action)',
        },
        item_id: {
          type: 'string',
          description: 'UUID of the item to update or delete (for update_item/delete_item actions)',
        },
        updates: {
          type: 'object',
          description: 'Properties to update (for update_item action). Can include: name, parent_id, metadata, type, etc.',
        },
        metadata: {
          type: 'object',
          description: 'Additional metadata for the item (description, year, etc.)',
        },
        reasoning: {
          type: 'string',
          description: 'Explanation of why this suggestion is being made',
        },
        confidence_score: {
          type: 'number',
          description: 'Confidence score 0-100 (default: 85)',
          default: 85,
        },
      },
      required: ['collection_id', 'reasoning'],
    },
  },
];

// List tools handler
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: TOOLS,
  };
});

// Call tool handler
server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    switch (name) {
      case 'get_collection': {
        const collection = await db.getCollection(args.collection_id);
        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(collection, null, 2),
            },
          ],
        };
      }

      case 'get_collection_tree': {
        const tree = await db.getCollectionTree(
          args.collection_id,
          args.max_depth || 3
        );
        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(tree, null, 2),
            },
          ],
        };
      }

      case 'search_items': {
        const results = await db.searchItems(args.query, args.type);
        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(results, null, 2),
            },
          ],
        };
      }

      case 'get_subcollections': {
        const subcollections = await db.getSubcollections(args.collection_id);
        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(subcollections, null, 2),
            },
          ],
        };
      }

      case 'create_suggestion': {
        const suggestion = await db.createSuggestion({
          collection_id: args.collection_id,
          action_type: args.action_type || 'add_subcollection',
          suggestion_data: {
            subcollection_name: args.subcollection_name,
            parent_subcollection: args.parent_subcollection,
            item_name: args.item_name,
            item_id: args.item_id,
            updates: args.updates,
            metadata: args.metadata || {}
          },
          reasoning: args.reasoning,
          confidence_score: args.confidence_score || 85
        });
        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify({
                success: true,
                suggestion: suggestion
              }, null, 2),
            },
          ],
        };
      }

      default:
        throw new Error(`Unknown tool: ${name}`);
    }
  } catch (error) {
    return {
      content: [
        {
          type: 'text',
          text: `Error: ${error.message}\n${error.stack}`,
        },
      ],
      isError: true,
    };
  }
});

// Start server
async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error('Attic MCP Server running on stdio');
}

main().catch((error) => {
  console.error('Server error:', error);
  process.exit(1);
});
