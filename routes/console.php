<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule curator runs every 15 minutes
Schedule::command('curators:run-scheduled')->everyFifteenMinutes();

// Clean up expired curator suggestions daily
Schedule::call(function () {
    \App\Models\CuratorSuggestion::where('status', 'pending')
        ->where('expires_at', '<', now())
        ->update(['status' => 'expired']);
})->daily();
