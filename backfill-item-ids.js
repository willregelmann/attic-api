#!/usr/bin/env node

/**
 * Backfill item_id column from suggestion_data for update_item suggestions
 */

import pg from 'pg';
const { Pool } = pg;

const pool = new Pool({
  host: 'localhost',
  port: 5432,
  user: 'postgres',
  password: 'localpassword123',
  database: 'railway'
});

async function backfillItemIds() {
  try {
    console.log('\n🔧 Backfilling item_id Column\n');
    console.log('='.repeat(80));

    // Count suggestions needing backfill
    const countResult = await pool.query(`
      SELECT COUNT(*) as count
      FROM curator_suggestions
      WHERE action_type = 'update_item'
        AND item_id IS NULL
        AND suggestion_data->'updates'->>'item_id' IS NOT NULL
    `);

    const count = parseInt(countResult.rows[0].count);
    console.log(`\n📊 Found ${count} suggestions needing item_id backfill\n`);

    if (count === 0) {
      // Try alternative path (item_id might be at root level)
      const altCountResult = await pool.query(`
        SELECT COUNT(*) as count
        FROM curator_suggestions
        WHERE action_type = 'update_item'
          AND item_id IS NULL
          AND suggestion_data->>'item_id' IS NOT NULL
      `);

      const altCount = parseInt(altCountResult.rows[0].count);

      if (altCount > 0) {
        console.log(`📊 Found ${altCount} suggestions with item_id at root level\n`);

        // Backfill from root level
        const result = await pool.query(`
          UPDATE curator_suggestions
          SET item_id = (suggestion_data->>'item_id')::uuid
          WHERE action_type = 'update_item'
            AND item_id IS NULL
            AND suggestion_data->>'item_id' IS NOT NULL
          RETURNING id, item_id
        `);

        console.log(`✅ Backfilled ${result.rowCount} suggestions with item_id from root level`);
        console.log(`\nSample updated suggestions:`);
        result.rows.slice(0, 5).forEach(row => {
          console.log(`  ${row.id.slice(0, 8)}... → item_id: ${row.item_id.slice(0, 8)}...`);
        });
      } else {
        console.log('✅ No suggestions need backfilling!');
      }
      return;
    }

    // Backfill from updates.item_id path
    const result = await pool.query(`
      UPDATE curator_suggestions
      SET item_id = (suggestion_data->'updates'->>'item_id')::uuid
      WHERE action_type = 'update_item'
        AND item_id IS NULL
        AND suggestion_data->'updates'->>'item_id' IS NOT NULL
      RETURNING id, item_id
    `);

    console.log(`✅ Backfilled ${result.rowCount} suggestions with item_id`);
    console.log(`\nSample updated suggestions:`);
    result.rows.slice(0, 5).forEach(row => {
      console.log(`  ${row.id.slice(0, 8)}... → item_id: ${row.item_id.slice(0, 8)}...`);
    });

    console.log('\n' + '='.repeat(80));
    console.log('✅ Backfill completed!\n');

  } catch (error) {
    console.error('\n❌ Backfill failed:', error.message);
    console.error(error.stack);
    process.exit(1);
  } finally {
    await pool.end();
  }
}

backfillItemIds();
