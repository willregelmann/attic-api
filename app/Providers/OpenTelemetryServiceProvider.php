<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\SDK\Trace\TracerProviderFactory;

class OpenTelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SDK auto-configures from OTEL_* environment variables
    }

    public function boot(): void
    {
        if (env('OTEL_TRACES_EXPORTER', 'none') !== 'none') {
            $factory = new TracerProviderFactory();
            $tracerProvider = $factory->create();
            \OpenTelemetry\API\Globals::registerInitialTracerProvider($tracerProvider);
        }
    }
}
