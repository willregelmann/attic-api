<?php

// Simple PHP server script for Railway deployment
$port = (int) ($_ENV['PORT'] ?? $_SERVER['PORT'] ?? 8080);
$host = '0.0.0.0';

echo "Starting Laravel server on {$host}:{$port}\n";

// Start the built-in PHP server with Laravel's public directory
$command = sprintf(
    'php -S %s:%d -t %s',
    $host,
    $port,
    __DIR__ . '/public'
);

echo "Command: {$command}\n";
passthru($command);