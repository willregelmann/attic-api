<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // OpenTelemetry initialization moved to bootstrap/otel.php
        // which loads before Laravel boots, enabling proper hook registration
        // for nested spans (database queries, cache operations, etc.)
    }

    public function boot(): void
    {
        // No-op: All telemetry setup happens in bootstrap/otel.php
    }
}
