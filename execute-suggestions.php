<?php
// Direct PHP script to execute curator suggestions
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$collectionId = '01998e63-08c4-73de-8276-3113dfd28e0e';

// Get approved unexecuted suggestions
$suggestions = DB::table('curator_suggestions')
    ->where('collection_id', $collectionId)
    ->where('status', 'approved')
    ->where('executed', false)
    ->orderBy('created_at')
    ->limit(42)
    ->get();

echo "Found " . count($suggestions) . " suggestions to execute\n\n";

$executed = 0;
$failed = 0;

foreach ($suggestions as $suggestion) {
    try {
        $data = json_decode($suggestion->suggestion_data, true);

        if ($suggestion->action_type === 'add_item' || $suggestion->action_type === 'add_subcollection') {
            // Create the item with UUID
            $newId = \Illuminate\Support\Str::uuid()->toString();

            DB::table('items')->insert([
                'id' => $newId,
                'name' => $data['name'],
                'item_type' => $data['item_type'] ?? 'collectible',
                'metadata' => json_encode($data['metadata'] ?? []),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Add relationship to collection
            DB::table('item_relationships')->insert([
                'parent_id' => $collectionId,
                'child_id' => $newId,
                'relationship_type' => 'contains',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Mark as executed
            DB::table('curator_suggestions')
                ->where('id', $suggestion->id)
                ->update([
                    'executed' => true,
                    'executed_at' => now()
                ]);

            $executed++;
            echo ".";
            if ($executed % 10 === 0) echo " $executed\n";
        }
    } catch (Exception $e) {
        $failed++;
        echo "F";
        echo "\nError executing suggestion {$suggestion->id}: " . $e->getMessage() . "\n";
    }
}

echo "\n\nExecution complete: $executed executed, $failed failed\n";
