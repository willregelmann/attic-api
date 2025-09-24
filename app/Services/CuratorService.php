<?php

namespace App\Services;

use App\Models\CollectionCurator;
use App\Models\CuratorSuggestion;
use App\Models\Item;
use App\Jobs\ProcessCuratorRun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CuratorService
{
    protected string $provider;
    protected string $apiKey;
    protected string $apiUrl;
    protected string $defaultModel;

    public function __construct()
    {
        $this->provider = config('services.ai.provider', 'anthropic');
        
        if ($this->provider === 'anthropic') {
            $this->apiKey = config('services.anthropic.key', '');
            $this->apiUrl = config('services.anthropic.url', 'https://api.anthropic.com');
            $this->defaultModel = config('services.anthropic.model', 'claude-3-opus-20240229');
        } else {
            $this->apiKey = config('services.openai.key', '');
            $this->apiUrl = config('services.openai.url', 'https://api.openai.com/v1');
            $this->defaultModel = config('services.openai.model', 'gpt-4');
        }
    }

    /**
     * Queue a curator run for processing
     */
    public function queueCuratorRun(CollectionCurator $curator): void
    {
        ProcessCuratorRun::dispatch($curator);
    }

    /**
     * Execute a curator run
     */
    public function runCurator(CollectionCurator $curator): array
    {
        if (!$curator->isActive()) {
            throw new \Exception('Curator is not active');
        }

        $runLog = $curator->runLogs()->create([
            'status' => 'started',
            'started_at' => now(),
        ]);

        try {
            // Get collection context
            $collection = $curator->collection;
            $existingItems = $this->getCollectionItems($collection);
            
            // Generate suggestions using AI
            $suggestions = $this->generateSuggestions($curator, $collection, $existingItems);
            
            // Store suggestions
            $suggestionsCreated = 0;
            foreach ($suggestions as $suggestion) {
                if ($this->shouldCreateSuggestion($curator, $suggestion)) {
                    $this->createSuggestion($curator, $suggestion);
                    $suggestionsCreated++;
                }
            }

            // Update curator statistics
            $curator->update([
                'last_run_at' => now(),
                'next_run_at' => $curator->calculateNextRunTime(),
                'suggestions_made' => $curator->suggestions_made + $suggestionsCreated,
            ]);

            // Complete run log
            $runLog->update([
                'status' => 'completed',
                'completed_at' => now(),
                'items_analyzed' => count($existingItems),
                'suggestions_generated' => $suggestionsCreated,
            ]);

            return [
                'success' => true,
                'suggestions_created' => $suggestionsCreated,
                'run_id' => $runLog->id,
            ];

        } catch (\Exception $e) {
            Log::error('Curator run failed', [
                'curator_id' => $curator->id,
                'error' => $e->getMessage(),
            ]);

            $runLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate AI suggestions for a collection
     */
    protected function generateSuggestions(CollectionCurator $curator, Item $collection, array $existingItems): array
    {
        $config = $curator->curator_config;
        
        // Build the prompt
        $prompt = $this->buildPrompt($curator, $collection, $existingItems);
        
        if ($this->provider === 'anthropic') {
            return $this->callAnthropicAPI($config, $prompt);
        } else {
            return $this->callOpenAI($config, $prompt);
        }
    }
    
    /**
     * Call Anthropic's Claude API
     */
    protected function callAnthropicAPI(array $config, string $prompt): array
    {
        $systemPrompt = $config['personality'] ?? 'You are a helpful collection curator.';
        $model = $config['ai_model'] ?? $this->defaultModel;
        
        // Anthropic requires a different header format
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post($this->apiUrl . '/v1/messages', [
            'model' => $model,
            'max_tokens' => 2000,
            'temperature' => $config['temperature'] ?? 0.7,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Anthropic API request failed: ' . $response->body());
        }

        $content = $response->json('content.0.text');
        
        // Claude returns text, so we need to extract JSON
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $suggestions = json_decode($matches[0], true);
            return $this->parseSuggestions($suggestions);
        }
        
        return [];
    }
    
    /**
     * Call OpenAI API
     */
    protected function callOpenAI(array $config, string $prompt): array
    {
        $systemPrompt = $config['personality'] ?? 'You are a helpful collection curator.';
        $model = $config['ai_model'] ?? $this->defaultModel;
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->post($this->apiUrl . '/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $config['temperature'] ?? 0.7,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object'],
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API request failed: ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        $suggestions = json_decode($content, true);

        return $this->parseSuggestions($suggestions);
    }

    /**
     * Build the AI prompt
     */
    protected function buildPrompt(CollectionCurator $curator, Item $collection, array $existingItems): string
    {
        $config = $curator->curator_config;
        
        $prompt = "You are curating the collection: {$collection->name}\n\n";
        
        if (!empty($collection->metadata['description'])) {
            $prompt .= "Collection Description: {$collection->metadata['description']}\n\n";
        }

        $prompt .= "Current items in collection:\n";
        foreach ($existingItems as $item) {
            $prompt .= "- {$item->name}";
            if (!empty($item->metadata['rarity'])) {
                $prompt .= " ({$item->metadata['rarity']})";
            }
            $prompt .= "\n";
        }

        $prompt .= "\nCuration Rules:\n";
        foreach ($config['rules'] ?? [] as $rule) {
            $prompt .= "- {$rule}\n";
        }

        $prompt .= "\nPlease suggest items to add or remove from this collection. ";
        $prompt .= "Return your response as a JSON object with an array of suggestions. ";
        $prompt .= "Each suggestion should have: action (add/remove), item_name, reason, confidence (0-100), and search_query (for finding the item).\n";
        $prompt .= "Example format: {\"suggestions\": [{\"action\": \"add\", \"item_name\": \"Item Name\", \"reason\": \"Why\", \"confidence\": 85, \"search_query\": \"search terms\"}]}";

        return $prompt;
    }

    /**
     * Parse AI suggestions into structured format
     */
    protected function parseSuggestions($aiResponse): array
    {
        if (!is_array($aiResponse) || !isset($aiResponse['suggestions'])) {
            return [];
        }

        $suggestions = [];
        foreach ($aiResponse['suggestions'] as $suggestion) {
            $suggestions[] = [
                'action_type' => $suggestion['action'] === 'add' ? 'add_item' : 'remove_item',
                'item_name' => $suggestion['item_name'] ?? '',
                'reasoning' => $suggestion['reason'] ?? '',
                'confidence_score' => min(100, max(0, $suggestion['confidence'] ?? 50)),
                'search_query' => $suggestion['search_query'] ?? $suggestion['item_name'] ?? '',
                'supporting_data' => $suggestion['metadata'] ?? [],
            ];
        }

        return $suggestions;
    }

    /**
     * Check if a suggestion should be created
     */
    protected function shouldCreateSuggestion(CollectionCurator $curator, array $suggestion): bool
    {
        // Check if similar suggestion already exists and is pending
        $existing = CuratorSuggestion::where('curator_id', $curator->id)
            ->where('status', 'pending')
            ->whereJsonContains('suggestion_data->item_name', $suggestion['item_name'])
            ->exists();

        return !$existing;
    }

    /**
     * Create a suggestion record
     */
    protected function createSuggestion(CollectionCurator $curator, array $suggestion): CuratorSuggestion
    {
        $suggestionModel = CuratorSuggestion::create([
            'curator_id' => $curator->id,
            'collection_id' => $curator->collection_id,
            'action_type' => $suggestion['action_type'],
            'suggestion_data' => [
                'item_name' => $suggestion['item_name'],
                'search_query' => $suggestion['search_query'],
                'supporting_data' => $suggestion['supporting_data'],
            ],
            'reasoning' => $suggestion['reasoning'],
            'confidence_score' => $suggestion['confidence_score'],
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        // Auto-approve if configured and confidence is high
        if ($suggestionModel->shouldAutoApprove()) {
            $suggestionModel->update(['status' => 'approved']);
            // Queue execution job
            $this->queueSuggestionExecution($suggestionModel);
        }

        return $suggestionModel;
    }

    /**
     * Get collection items
     */
    protected function getCollectionItems(Item $collection): array
    {
        return $collection->children()
            ->wherePivot('relationship_type', 'contains')
            ->orderBy('item_relationship.canonical_order')
            ->get()
            ->all();
    }

    /**
     * Queue suggestion execution
     */
    protected function queueSuggestionExecution(CuratorSuggestion $suggestion): void
    {
        // This would dispatch a job to execute the approved suggestion
        // For now, we'll leave this as a placeholder
        Log::info('Suggestion auto-approved and queued for execution', [
            'suggestion_id' => $suggestion->id,
        ]);
    }
}