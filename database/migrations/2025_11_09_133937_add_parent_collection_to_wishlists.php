<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->uuid('parent_collection_id')->nullable()->after('user_id');

            $table->foreign('parent_collection_id')
                  ->references('id')
                  ->on('user_collections')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropForeign(['parent_collection_id']);
            $table->dropColumn('parent_collection_id');
        });
    }
};
