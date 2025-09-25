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
     */
    public function registerCurator(User $curatorUser, CollectionCurator $curator): void
    {
        // Generate API token for the curator user
        $tokenResult = \App\Models\ApiToken::createTokenForUser($curatorUser, 'Curator Agent Token');
        $plainTextToken = $tokenResult['plainTextToken'];
        
        // Send registration command with token
        $this->sendCommand('register_curator', [
            'curator_id' => $curator->id,
            'api_token' => $plainTextToken,
            'curator_config' => [
                'name' => $curatorUser->name,
                'collection_id' => $curator->collection_id,
                'prompt' => $curator->prompt,
                'schedule' => $curator->schedule,
                'model' => config('services.anthropic.model', 'claude-3-haiku-20240307'),
                'auto_approve' => $curator->auto_approve,
                'confidence_threshold' => $curator->confidence_threshold
            ]
        ]);
        
        // Note: We do NOT store the token in our database
        // The curator service will store it encrypted
    }
    
    /**
     * Run a curator manually
     */
    public function runCurator(CollectionCurator $curator, ?string $task = null): void
    {
        $this->sendCommand('run_curator', [
            'curator_id' => $curator->id,
            'task' => $task,
            'strategy' => $task ? 'specific' : 'default'
        ]);
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
        
        $timestamp = now()->timestamp * 1000; // milliseconds
        $payload = json_encode($messageData) . $timestamp;
        
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