<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add UUID columns to all tables
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->index('uuid');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->index('uuid');
        });

        Schema::table('collectibles', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->uuid('contributed_by_uuid')->nullable()->after('contributed_by');
            $table->index('uuid');
            $table->index('contributed_by_uuid');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->uuid('user_uuid')->nullable()->after('user_id');
            $table->uuid('collectible_uuid')->nullable()->after('collectible_id');
            $table->index('uuid');
            $table->index('user_uuid');
            $table->index('collectible_uuid');
        });

        Schema::table('collectible_collection', function (Blueprint $table) {
            $table->uuid('collection_uuid')->nullable()->after('collection_id');
            $table->uuid('collectible_uuid')->nullable()->after('collectible_id');
            $table->index('collection_uuid');
            $table->index('collectible_uuid');
        });

        // Step 2: Populate UUIDs for existing records
        $this->populateUuids();

        // Step 3: Make UUID columns non-nullable
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        Schema::table('collectibles', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        // Step 4: Update foreign key UUIDs based on relationships
        $this->updateForeignKeyUuids();

        // Step 5: Drop old foreign key constraints
        Schema::table('collectibles', function (Blueprint $table) {
            $table->dropForeign(['contributed_by']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['collectible_id']);
        });

        Schema::table('collectible_collection', function (Blueprint $table) {
            $table->dropForeign(['collection_id']);
            $table->dropForeign(['collectible_id']);
        });

        // Step 6: Drop old ID columns and rename UUID columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->renameColumn('uuid', 'id');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->renameColumn('uuid', 'id');
        });

        Schema::table('collectibles', function (Blueprint $table) {
            $table->dropColumn(['id', 'contributed_by']);
            $table->renameColumn('uuid', 'id');
            $table->renameColumn('contributed_by_uuid', 'contributed_by');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['id', 'user_id', 'collectible_id']);
            $table->renameColumn('uuid', 'id');
            $table->renameColumn('user_uuid', 'user_id');
            $table->renameColumn('collectible_uuid', 'collectible_id');
        });

        Schema::table('collectible_collection', function (Blueprint $table) {
            $table->dropColumn(['collection_id', 'collectible_id']);
            $table->renameColumn('collection_uuid', 'collection_id');
            $table->renameColumn('collectible_uuid', 'collectible_id');
        });

        // Step 7: Set UUID columns as primary keys
        Schema::table('users', function (Blueprint $table) {
            $table->primary('id');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->primary('id');
        });

        Schema::table('collectibles', function (Blueprint $table) {
            $table->primary('id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->primary('id');
        });

        // Step 8: Re-add foreign key constraints
        Schema::table('collectibles', function (Blueprint $table) {
            $table->foreign('contributed_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('collectible_id')->references('id')->on('collectibles')->onDelete('cascade');
        });

        Schema::table('collectible_collection', function (Blueprint $table) {
            $table->foreign('collection_id')->references('id')->on('collections')->onDelete('cascade');
            $table->foreign('collectible_id')->references('id')->on('collectibles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a complex migration to reverse, would require significant planning
        throw new \Exception('This migration cannot be automatically reversed due to data transformation complexity. Please restore from backup if needed.');
    }

    private function populateUuids(): void
    {
        // Generate UUIDs for all existing records
        $tables = ['users', 'collections', 'collectibles', 'items'];
        
        foreach ($tables as $table) {
            $records = DB::table($table)->whereNull('uuid')->get();
            foreach ($records as $record) {
                DB::table($table)
                    ->where('id', $record->id)
                    ->update(['uuid' => Str::uuid()->toString()]);
            }
        }
    }

    private function updateForeignKeyUuids(): void
    {
        // Update collectible foreign keys
        DB::statement('
            UPDATE collectibles
            SET contributed_by_uuid = u.uuid
            FROM users u
            WHERE collectibles.contributed_by = u.id
            AND collectibles.contributed_by IS NOT NULL
        ');

        // Update item foreign keys
        DB::statement('
            UPDATE items
            SET user_uuid = u.uuid
            FROM users u
            WHERE items.user_id = u.id
        ');

        DB::statement('
            UPDATE items
            SET collectible_uuid = c.uuid
            FROM collectibles c
            WHERE items.collectible_id = c.id
        ');

        // Update pivot table foreign keys
        DB::statement('
            UPDATE collectible_collection
            SET collection_uuid = c.uuid
            FROM collections c
            WHERE collectible_collection.collection_id = c.id
        ');

        DB::statement('
            UPDATE collectible_collection
            SET collectible_uuid = c.uuid
            FROM collectibles c
            WHERE collectible_collection.collectible_id = c.id
        ');
    }
};
