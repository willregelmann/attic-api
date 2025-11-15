<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Test the DBoT image_search RPC function
 */
class TestImageSearch extends Command
{
    protected $signature = 'dbot:test-image-search {--test-embedding : Test if embedding endpoint exists}';
    protected $description = 'Test the Database of Things image_search RPC endpoint';

    public function handle()
    {
        $baseUrl = config('services.database_of_things.url');
        $apiKey = config('services.database_of_things.api_key');

        if ($this->option('test-embedding')) {
            return $this->testEmbeddingEndpoint($baseUrl, $apiKey);
        }

        return $this->testImageSearch($baseUrl, $apiKey);
    }

    private function testImageSearch($baseUrl, $apiKey)
    {
        $url = $baseUrl . '/rest/v1/rpc/image_search';

        $this->info("Testing DBoT image_search RPC...");
        $this->info("URL: {$url}");

        // For now, create a dummy 512-dimensional embedding
        // (all zeros - just to test the endpoint structure)
        $dummyEmbedding = array_fill(0, 512, 0.0);

        $payload = [
            'query_embedding' => $dummyEmbedding,
            'result_limit' => 5,
        ];

        $this->info("Payload structure:");
        $this->line(json_encode([
            'query_embedding' => '[512 floats...]',
            'result_limit' => 5,
        ], JSON_PRETTY_PRINT));

        try {
            $client = new Client(['timeout' => 30.0]);

            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'apikey' => $apiKey,
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            $this->info("\nResponse Status: {$statusCode}");
            $this->info("\nResponse Body:");
            $this->line(json_encode($data, JSON_PRETTY_PRINT));

            if ($statusCode === 200 && is_array($data)) {
                $this->info("\n✓ Success! Found " . count($data) . " results");

                if (!empty($data)) {
                    $this->info("\nSample result structure:");
                    $this->line(json_encode($data[0], JSON_PRETTY_PRINT));
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("\n✗ Error: " . $e->getMessage());

            if (method_exists($e, 'getResponse')) {
                $response = $e->getResponse();
                if ($response) {
                    $this->error("Response body: " . $response->getBody()->getContents());
                }
            }

            return 1;
        }
    }

    private function testEmbeddingEndpoint($baseUrl, $apiKey)
    {
        $this->info("Testing potential DBoT embedding generation endpoints...\n");

        $client = new Client(['timeout' => 30.0, 'http_errors' => false]);

        $potentialEndpoints = [
            'generate_image_embedding',
            'image_embedding',
            'embed_image',
            'create_image_embedding',
        ];

        foreach ($potentialEndpoints as $endpoint) {
            $url = $baseUrl . '/rest/v1/rpc/' . $endpoint;
            $this->info("Testing: {$url}");

            try {
                $response = $client->post($url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'apikey' => $apiKey,
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Accept' => 'application/json',
                    ],
                    'json' => [
                        'test' => true,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();

                if ($statusCode === 200) {
                    $this->info("  ✓ Endpoint exists! Status: {$statusCode}");
                    $this->line("  Response: {$body}");
                } elseif ($statusCode === 404) {
                    $this->line("  ✗ Not found (404)");
                } else {
                    $this->warn("  ? Unexpected status: {$statusCode}");
                    $this->line("  Response: {$body}");
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Error: " . $e->getMessage());
            }

            $this->newLine();
        }

        $this->info("\nConclusion:");
        $this->warn("If no endpoints found, we'll need to either:");
        $this->line("1. Use an external CLIP service (HuggingFace, OpenAI CLIP API)");
        $this->line("2. Host our own CLIP model (Python microservice)");
        $this->line("3. Ask DBoT team about their embedding generation approach");

        return 0;
    }
}
