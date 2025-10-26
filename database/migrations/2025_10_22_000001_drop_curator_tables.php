<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drop all curator-related tables as canonical data is now managed in Supabase.
     * The curator system was designed to manage local collections/items, which no
     * longer exist as they've been migrated to Supabase's Database of Things.
     */
    public function up(): void
    {
        // Drop in reverse order of dependencies
        Schema::dropIfExists('curator_run_logs');
        Schema::dropIfExists('curator_suggestions');
        Schema::dropIfExists('collection_curators');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate curator tables in case of rollback
        // Note: This won't restore data, just the schema

        Schema::create('collection_curators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('collection_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('inactive');
            $table->json('curator_config');
            $table->string('schedule_type')->default('manual');
            $table->json('schedule_config')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('auto_approve')->default(false);
            $table->integer('confidence_threshold')->default(80);
            $table->integer('suggestions_made')->default(0);
            $table->integer('suggestions_approved')->default(0);
            $table->integer('suggestions_rejected')->default(0);
            $table->json('performance_metrics')->nullable();
            $table->string('api_token')->nullable();
            $table->uuid('user_id')->nullable();
            $table->timestamps();

            $table->foreign('collection_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['status', 'next_run_at']);
        });

        Schema::create('curator_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('curator_id');
            $table->uuid('collection_id');
            $table->string('action_type');
            $table->uuid('item_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->json('suggestion_data');
            $table->text('reasoning');
            $table->integer('confidence_score');
            $table->string('status')->default('pending');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->boolean('executed')->default(false);
            $table->timestamp('executed_at')->nullable();
            $table->json('execution_result')->nullable();
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();

            $table->foreign('curator_id')->references('id')->on('collection_curators')->onDelete('cascade');
            $table->foreign('collection_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'created_at']);
            $table->index(['curator_id', 'status']);
        });

        Schema::create('curator_run_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('curator_id');
            $table->string('status');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('items_analyzed')->default(0);
            $table->integer('suggestions_generated')->default(0);
            $table->json('api_usage')->nullable();
            $table->json('run_metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('curator_id')->references('id')->on('collection_curators')->onDelete('cascade');
            $table->index(['curator_id', 'started_at']);
        });
    }
};
