<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\PersonalAccessToken;
use App\Models\UserItem;
use App\Observers\UserItemObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use our custom PersonalAccessToken model that supports UUIDs
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Register observers
        UserItem::observe(UserItemObserver::class);
    }
}
