<?php

// This file is loaded BEFORE Laravel boots via Composer autoload
// It initializes OpenTelemetry SDK and registers Laravel instrumentation hooks

use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Instrumentation\Laravel\LaravelInstrumentation;

// Only run if tracing is enabled
if (getenv('OTEL_TRACES_EXPORTER') === 'otlp') {
    // Create HTTP client explicitly (discovery has issues)
    $httpClient = new \GuzzleHttp\Client(['timeout' => 10]);
    $requestFactory = \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
    $streamFactory = \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();

    // Create transport with explicit client
    $transport = new \OpenTelemetry\SDK\Common\Export\Http\PsrTransport(
        $httpClient,
        $requestFactory,
        $streamFactory,
        getenv('OTEL_EXPORTER_OTLP_ENDPOINT') . '/v1/traces',
        'application/x-protobuf',
        [],      // headers
        [],      // compression
        100,     // retry delay
        3        // max retries
    );
    $exporter = new SpanExporter($transport);

    // Create resource
    $resource = ResourceInfoFactory::defaultResource()->merge(
        ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => getenv('OTEL_SERVICE_NAME') ?: 'attic-api',
        ]))
    );

    // Create tracer provider
    $tracerProvider = TracerProvider::builder()
        ->addSpanProcessor(new SimpleSpanProcessor($exporter))
        ->setResource($resource)
        ->build();

    // Register SDK globally BEFORE Laravel boots
    Sdk::builder()
        ->setTracerProvider($tracerProvider)
        ->setAutoShutdown(true)
        ->buildAndRegisterGlobal();

    // Register Laravel instrumentation hooks NOW (before Laravel boots)
    // This enables nested spans for database queries, HTTP requests, etc.
    LaravelInstrumentation::register();
}
