<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\CollectionCurator;
use App\Models\User;

class CuratorMessageBusService
{
    private string $sharedSecret;
    
    public function __construct()
    {
        $this->sharedSecret = config('services.curator.shared_secret', 'change-in-production');
    }
    
    /**
     * Send a command to the curator service via Redis
     */
    public function sendCommand(string $type, array $data): void
    {
        $message = $this->createSignedMessage($type, $data);
        
        Redis::publish('curator:commands', json_encode($message));
        
        Log::info('Sent curator command', [
            'type' => $type,
            'curator_id' => $data['curator_id'] ?? null
        ]);
    }
    
    /**
     * Register a new curator with the service
     *
     * @param CollectionCurator $curator The curator to register
     * @param string $apiToken The API token for the curator's user account
     */
    public function registerCurator(CollectionCurator $curator, string $apiToken): void
    {
        // Get the curator user
        $curatorUser = $curator->curatorUser;

        // Send registration command with token via Redis
        $this->sendCommand('register_curator', [
            'curator_id' => $curator->id,
            'collection_id' => $curator->collection_id,
            'api_token' => $apiToken,
            'curator_config' => [
                'name' => $curatorUser->name ?? "Curator {$curator->id}",
                'collection_id' => $curator->collection_id,
                'prompt' => $curator->prompt,
                'model' => config('services.curator.model', 'claude-3-5-sonnet-20241022'),
                'auto_approve' => $curator->auto_approve,
                'confidence_threshold' => $curator->confidence_threshold
            ]
        ]);

        Log::info('Sent curator registration to message bus', [
            'curator_id' => $curator->id,
            'collection_id' => $curator->collection_id
        ]);
    }
    
    /**
     * Run a curator manually
     */
    public function runCurator(CollectionCurator $curator, ?string $task = null): void
    {
        $data = [
            'curator_id' => $curator->id,
            'task' => $task,
            'strategy' => 'default'
        ];
        
        // If a custom task message is provided, append it to the prompt
        if ($task) {
            $data['additional_instructions'] = $task;
            $data['strategy'] = 'custom';
        }
        
        $this->sendCommand('run_curator', $data);
    }
    
    /**
     * Update curator configuration
     */
    public function updateCurator(CollectionCurator $curator): void
    {
        $this->sendCommand('update_curator', [
            'curator_id' => $curator->id,
            'config' => [
                'prompt' => $curator->prompt,
                'schedule' => $curator->schedule,
                'auto_approve' => $curator->auto_approve,
                'confidence_threshold' => $curator->confidence_threshold
            ]
        ]);
    }
    
    /**
     * Delete a curator
     */
    public function deleteCurator(CollectionCurator $curator): void
    {
        $this->sendCommand('delete_curator', [
            'curator_id' => $curator->id
        ]);
    }
    
    /**
     * Create a signed message for secure communication
     */
    private function createSignedMessage(string $type, array $data): array
    {
        $messageData = array_merge($data, [
            'type' => $type,
            'id' => \Illuminate\Support\Str::uuid()->toString()
        ]);
        
        // Sort keys to ensure consistent ordering
        ksort($messageData);
        
        $timestamp = now()->timestamp * 1000; // milliseconds
        $payload = json_encode($messageData, JSON_UNESCAPED_SLASHES) . $timestamp;
        
        $signature = hash_hmac('sha256', $payload, $this->sharedSecret);
        
        return [
            'data' => $messageData,
            'timestamp' => $timestamp,
            'signature' => $signature
        ];
    }
    
    /**
     * Listen for responses from the curator service
     */
    public function listenForResponses(): void
    {
        Redis::subscribe(['curator:responses'], function ($message) {
            try {
                $decoded = json_decode($message, true);
                
                if (!$this->verifySignature($decoded)) {
                    Log::warning('Invalid signature on curator response');
                    return;
                }
                
                $this->handleResponse($decoded['data']);
            } catch (\Exception $e) {
                Log::error('Error handling curator response', [
                    'error' => $e->getMessage()
                ]);
            }
        });
    }
    
    /**
     * Verify message signature
     */
    private function verifySignature(array $message): bool
    {
        if (!isset($message['data'], $message['timestamp'], $message['signature'])) {
            return false;
        }
        
        // Check timestamp (prevent replay attacks)
        $now = now()->timestamp * 1000;
        if (abs($now - $message['timestamp']) > 60000) { // 1 minute tolerance
            return false;
        }
        
        $payload = json_encode($message['data']) . $message['timestamp'];
        $expectedSignature = hash_hmac('sha256', $payload, $this->sharedSecret);
        
        return hash_equals($expectedSignature, $message['signature']);
    }
    
    /**
     * Handle responses from the curator service
     */
    private function handleResponse(array $data): void
    {
        Log::info('Received curator response', $data);
        
        switch ($data['type'] ?? null) {
            case 'event':
                $this->handleEvent($data);
                break;
                
            case 'ack':
                Log::info('Command acknowledged', [
                    'original_id' => $data['original_id'] ?? null
                ]);
                break;
                
            default:
                Log::warning('Unknown response type', $data);
        }
    }
    
    /**
     * Handle events from the curator service
     */
    private function handleEvent(array $data): void
    {
        $eventType = $data['event_type'] ?? null;
        $eventData = $data['data'] ?? [];
        
        switch ($eventType) {
            case 'curator_registered':
                Log::info('Curator registered successfully', $eventData);
                break;
                
            case 'curator_run_complete':
                $this->handleRunComplete($eventData);
                break;
                
            case 'curator_run_failed':
                Log::error('Curator run failed', $eventData);
                break;
                
            case 'curator_deleted':
                Log::info('Curator deleted', $eventData);
                break;
                
            default:
                Log::info('Curator event', [
                    'type' => $eventType,
                    'data' => $eventData
                ]);
        }
    }
    
    /**
     * Handle run completion
     */
    private function handleRunComplete(array $data): void
    {
        $curatorId = $data['curator_id'] ?? null;
        
        if (!$curatorId) {
            return;
        }
        
        $curator = CollectionCurator::find($curatorId);
        if ($curator) {
            $curator->update([
                'last_run_at' => now(),
                'next_run_at' => $curator->calculateNextRunTime()
            ]);
        }
        
        Log::info('Curator run completed', $data);
    }
}