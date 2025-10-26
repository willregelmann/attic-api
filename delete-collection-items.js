#!/usr/bin/env node

import axios from 'axios';

const COLLECTION_ID = '01998e63-08c4-73de-8276-3113dfd28e0e';
const API_URL = process.env.ATTIC_API_URL || 'http://localhost:8000';
const API_TOKEN = process.env.API_TOKEN || '32|mscw6QbgTZ8vyGn97vg8yRGnF6lQV7pJMCJFkEHt49662cfd';

async function graphql(queryStr, variables = {}) {
  const response = await axios.post(`${API_URL}/graphql`, {
    query: queryStr,
    variables
  }, {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${API_TOKEN}`
    }
  });

  if (response.data.errors) {
    throw new Error(`GraphQL Error: ${JSON.stringify(response.data.errors)}`);
  }

  return response.data.data;
}

async function deleteAllItems() {
  try {
    console.log('\n🗑️  Deleting All Items Under Collection\n');
    console.log('='.repeat(80));

    // Get all items in collection
    const data = await graphql(`
      query GetCollectionItems($id: ID!) {
        collectionItems(collection_id: $id) {
          id
          name
          type
        }
      }
    `, { id: COLLECTION_ID });

    const items = data.collectionItems || [];
    
    console.log(`\n📊 Found ${items.length} items to delete\n`);

    if (items.length === 0) {
      console.log('✅ Collection is already empty!');
      return;
    }

    console.log('⚠️  Proceeding with deletion...\n');

    // Delete items in batches
    const batchSize = 10;
    let deleted = 0;
    
    for (let i = 0; i < items.length; i += batchSize) {
      const batch = items.slice(i, i + batchSize);
      
      await Promise.all(batch.map(async (item) => {
        try {
          await graphql(`
            mutation DeleteItem($id: ID!) {
              deleteItem(id: $id)
            }
          `, { id: item.id });
          deleted++;
          process.stdout.write(`\rDeleted ${deleted}/${items.length} items...`);
        } catch (error) {
          console.error(`\n⚠️  Failed to delete ${item.name}: ${error.message}`);
        }
      }));
    }

    console.log('\n\n✅ Deletion completed!');
    console.log(`   Total deleted: ${deleted}/${items.length}\n`);
    console.log('='.repeat(80));

  } catch (error) {
    console.error('\n❌ Deletion failed:', error.message);
    process.exit(1);
  }
}

deleteAllItems();
