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
        Schema::table('collection_curators', function (Blueprint $table) {
            $table->dropColumn(['schedule_type', 'schedule_config']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collection_curators', function (Blueprint $table) {
            $table->string('schedule_type')->default('daily')->after('status');
            $table->jsonb('schedule_config')->nullable()->after('schedule_type');
        });
    }
};
