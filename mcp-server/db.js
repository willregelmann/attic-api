/**
 * Database connection for MCP server
 * Connects to PostgreSQL using environment variables inherited from parent process
 *
 * IMPORTANT: No dotenv here! MCP server is spawned as a child process and inherits
 * environment variables from the parent. Loading dotenv would pollute stdout and
 * break the JSON-RPC stdio protocol.
 */
import pg from 'pg';

const { Pool } = pg;

// Create PostgreSQL connection pool
// Defaults match Docker Compose setup in wills-attic/docker-compose.yml
const pool = new Pool({
  host: process.env.DB_HOST || 'localhost',
  port: parseInt(process.env.DB_PORT || '5432'),
  database: process.env.DB_DATABASE || 'railway',
  user: process.env.DB_USERNAME || 'postgres',
  password: process.env.DB_PASSWORD || 'localpassword123',
});

// Test connection
pool.on('error', (err) => {
  console.error('Unexpected error on idle client', err);
  process.exit(-1);
});

/**
 * Query helper
 */
export async function query(text, params) {
  const start = Date.now();
  const res = await pool.query(text, params);
  const duration = Date.now() - start;
  // Use stderr for logging to avoid breaking stdio JSON-RPC protocol
  console.error('Executed query', { text: text.substring(0, 50) + '...', duration, rows: res.rowCount });
  return res;
}

/**
 * Get collection with immediate children
 */
export async function getCollection(collectionId) {
  const collectionQuery = `
    SELECT id, name, type, metadata, created_at, updated_at
    FROM items
    WHERE id = $1
  `;

  const childrenQuery = `
    SELECT
      i.id, i.name, i.type, i.metadata,
      ir.relationship_type, ir.canonical_order
    FROM items i
    INNER JOIN item_relationships ir ON i.id = ir.child_id
    WHERE ir.parent_id = $1 AND ir.relationship_type = 'contains'
    ORDER BY ir.canonical_order ASC, i.name ASC
  `;

  const collection = await query(collectionQuery, [collectionId]);
  const children = await query(childrenQuery, [collectionId]);

  return {
    ...collection.rows[0],
    children: children.rows
  };
}

/**
 * Get collection tree (recursive)
 */
export async function getCollectionTree(collectionId, maxDepth = 3) {
  const treeQuery = `
    WITH RECURSIVE collection_tree AS (
      -- Base case: the root collection
      SELECT
        i.id, i.name, i.type, i.metadata,
        NULL::uuid as parent_id,
        0 as depth,
        ARRAY[i.id] as path
      FROM items i
      WHERE i.id = $1

      UNION ALL

      -- Recursive case: children of collections
      SELECT
        i.id, i.name, i.type, i.metadata,
        ir.parent_id,
        ct.depth + 1,
        ct.path || i.id
      FROM items i
      INNER JOIN item_relationships ir ON i.id = ir.child_id
      INNER JOIN collection_tree ct ON ir.parent_id = ct.id
      WHERE ir.relationship_type = 'contains'
        AND ct.depth < $2
        AND NOT (i.id = ANY(ct.path)) -- Prevent cycles
    )
    SELECT * FROM collection_tree
    ORDER BY depth, name
  `;

  const result = await query(treeQuery, [collectionId, maxDepth]);
  return result.rows;
}

/**
 * Search items by name
 */
export async function searchItems(searchQuery, itemType = null) {
  let sql = `
    SELECT id, name, type, metadata
    FROM items
    WHERE name ILIKE $1
  `;

  const params = [`%${searchQuery}%`];

  if (itemType) {
    sql += ` AND type = $2`;
    params.push(itemType);
  }

  sql += ` ORDER BY name LIMIT 50`;

  const result = await query(sql, params);
  return result.rows;
}

/**
 * Get direct subcollections of a collection
 */
export async function getSubcollections(collectionId) {
  const sql = `
    SELECT
      i.id, i.name, i.type, i.metadata,
      ir.canonical_order,
      COUNT(children.child_id) as child_count
    FROM items i
    INNER JOIN item_relationships ir ON i.id = ir.child_id
    LEFT JOIN item_relationships children ON i.id = children.parent_id AND children.relationship_type = 'contains'
    WHERE ir.parent_id = $1
      AND ir.relationship_type = 'contains'
      AND i.type = 'collection'
    GROUP BY i.id, i.name, i.type, i.metadata, ir.canonical_order
    ORDER BY ir.canonical_order ASC, i.name ASC
  `;

  const result = await query(sql, [collectionId]);
  return result.rows;
}

/**
 * Create a curator suggestion
 */
export async function createSuggestion(suggestionData) {
  const sql = `
    INSERT INTO curator_suggestions (
      id,
      collection_id,
      action_type,
      suggestion_data,
      reasoning,
      confidence_score,
      status
    ) VALUES (gen_random_uuid(), $1, $2, $3, $4, $5, 'pending')
    RETURNING id, collection_id, action_type, suggestion_data, reasoning, confidence_score, status, created_at
  `;

  const params = [
    suggestionData.collection_id,
    suggestionData.action_type || 'add_subcollection',
    JSON.stringify(suggestionData.suggestion_data),
    suggestionData.reasoning,
    suggestionData.confidence_score || 85
  ];

  const result = await query(sql, params);
  return result.rows[0];
}

export default {
  query,
  getCollection,
  getCollectionTree,
  searchItems,
  getSubcollections,
  createSuggestion
};
