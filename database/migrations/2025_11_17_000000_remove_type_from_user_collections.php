<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_collections', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_collections', function (Blueprint $table) {
            // Restore the type column with 'custom' as default
            $table->string('type')->default('custom')->after('name');
        });

        // Restore type values based on linked_dbot_collection_id
        \DB::table('user_collections')
            ->whereNotNull('linked_dbot_collection_id')
            ->update(['type' => 'linked']);
    }
};
