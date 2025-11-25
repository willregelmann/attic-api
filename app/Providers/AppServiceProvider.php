<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\UserItem;
use App\Observers\UserItemObserver;
use App\Services\DbotDataCache;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register DbotDataCache as a singleton for request-scoped caching
        // This allows pre-fetched DBoT data to be shared across field resolvers
        $this->app->singleton(DbotDataCache::class, function () {
            return new DbotDataCache;
        });
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
