<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('parent_collection_id')->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('custom_image', 512)->nullable();
            $table->uuid('linked_dbot_collection_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'parent_collection_id'], 'idx_user_parent');

            // Foreign key to users
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        // Add self-referencing foreign key using raw SQL after table creation
        DB::statement('
            ALTER TABLE user_collections
            ADD CONSTRAINT user_collections_parent_collection_id_foreign
            FOREIGN KEY (parent_collection_id)
            REFERENCES user_collections(id)
            ON DELETE SET NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_collections');
    }
};
