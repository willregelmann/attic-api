<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store curator agent configurations
        Schema::create('collection_curators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('collection_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('inactive'); // inactive, active, paused, error
            
            // Curator personality and behavior
            $table->json('curator_config'); // Contains prompts, rules, preferences
            /*
             * Example curator_config:
             * {
             *   "personality": "A knowledgeable Pokemon card expert focusing on competitive play",
             *   "rules": [
             *     "Only include cards from Standard format",
             *     "Prioritize cards with high tournament usage",
             *     "Maximum 2 copies of any card except basic energy"
             *   ],
             *   "preferences": {
             *     "rarity_weight": 0.3,
             *     "meta_relevance_weight": 0.7,
             *     "price_ceiling": 50.00
             *   },
             *   "search_queries": [
             *     "Pokemon TCG tournament winners",
             *     "Pokemon TCG meta decks"
             *   ],
             *   "ai_model": "gpt-4",
             *   "temperature": 0.7
             * }
             */
            
            // Scheduling
            $table->string('schedule_type')->default('manual'); // manual, hourly, daily, weekly
            $table->json('schedule_config')->nullable(); // cron expression or interval config
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            
            // Approval settings
            $table->boolean('auto_approve')->default(false);
            $table->integer('confidence_threshold')->default(80); // 0-100
            
            // Statistics
            $table->integer('suggestions_made')->default(0);
            $table->integer('suggestions_approved')->default(0);
            $table->integer('suggestions_rejected')->default(0);
            $table->json('performance_metrics')->nullable();
            
            $table->timestamps();
            
            $table->foreign('collection_id')->references('id')->on('items')->onDelete('cascade');
            $table->index(['status', 'next_run_at']); // For job scheduling
        });
        
        // Store curator suggestions
        Schema::create('curator_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('curator_id');
            $table->uuid('collection_id');
            $table->string('action_type'); // add_item, remove_item, reorder, update_metadata
            $table->uuid('item_id')->nullable();
            
            // Suggestion details
            $table->json('suggestion_data');
            /*
             * Example for add_item:
             * {
             *   "item_name": "Charizard ex",
             *   "item_type": "card",
             *   "search_query": "Charizard ex Obsidian Flames",
             *   "reason": "High-value competitive card currently missing from collection",
             *   "confidence": 85,
             *   "supporting_data": {
             *     "tournament_usage": "15%",
             *     "price": "$35",
             *     "rarity": "Ultra Rare"
             *   }
             * }
             */
            
            $table->text('reasoning'); // AI's explanation
            $table->integer('confidence_score'); // 0-100
            
            // Workflow
            $table->string('status')->default('pending'); // pending, approved, rejected, expired
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            
            // If approved and executed
            $table->boolean('executed')->default(false);
            $table->timestamp('executed_at')->nullable();
            $table->json('execution_result')->nullable();
            
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();
            
            $table->foreign('curator_id')->references('id')->on('collection_curators')->onDelete('cascade');
            $table->foreign('collection_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['status', 'created_at']);
            $table->index(['curator_id', 'status']);
        });
        
        // Log curator runs
        Schema::create('curator_run_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('curator_id');
            $table->string('status'); // started, completed, failed
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            
            // Run details
            $table->integer('items_analyzed')->default(0);
            $table->integer('suggestions_generated')->default(0);
            $table->json('api_usage')->nullable(); // tokens used, costs, etc.
            $table->json('run_metadata')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            $table->foreign('curator_id')->references('id')->on('collection_curators')->onDelete('cascade');
            $table->index(['curator_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curator_run_logs');
        Schema::dropIfExists('curator_suggestions');
        Schema::dropIfExists('collection_curators');
    }
};