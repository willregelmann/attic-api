<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CuratorMessageBus
{
    private string $sharedSecret;
    
    public function __construct()
    {
        $this->sharedSecret = config('services.curator.shared_secret', 'change-this-secret');
    }
    
    /**
     * Send a command to the curator service
     */
    public function sendCommand(string $type, array $data): void
    {
        $message = $this->createMessage($type, $data);
        
        Redis::publish('curator:commands', json_encode($message));
        
        Log::info('Sent curator command', [
            'type' => $type,
            'curator_id' => $data['curator_id'] ?? null
        ]);
    }
    
    /**
     * Register a new curator with its token
     */
    public function registerCurator(string $curatorId, string $apiToken, array $config): void
    {
        $this->sendCommand('register_curator', [
            'curator_id' => $curatorId,
            'api_token' => $apiToken,
            'curator_config' => $config
        ]);
    }
    
    /**
     * Trigger a curator run
     */
    public function runCurator(string $curatorId, ?string $task = null, ?string $strategy = null): void
    {
        $this->sendCommand('run_curator', [
            'curator_id' => $curatorId,
            'task' => $task,
            'strategy' => $strategy
        ]);
    }
    
    /**
     * Update curator configuration
     */
    public function updateCurator(string $curatorId, array $config): void
    {
        $this->sendCommand('update_curator', [
            'curator_id' => $curatorId,
            'config' => $config
        ]);
    }
    
    /**
     * Delete a curator
     */
    public function deleteCurator(string $curatorId): void
    {
        $this->sendCommand('delete_curator', [
            'curator_id' => $curatorId
        ]);
    }
    
    /**
     * Create a signed message
     */
    private function createMessage(string $type, array $data): array
    {
        $messageData = array_merge($data, [
            'type' => $type,
            'id' => uniqid('msg_', true)
        ]);
        
        $timestamp = round(microtime(true) * 1000);
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
    public function listenForResponses(\Closure $handler): void
    {
        Redis::subscribe(['curator:responses'], function ($message) use ($handler) {
            $decoded = json_decode($message, true);
            
            if ($this->verifyMessage($decoded)) {
                $handler($decoded['data']);
            }
        });
    }
    
    /**
     * Verify message signature
     */
    private function verifyMessage(array $message): bool
    {
        if (!isset($message['data'], $message['timestamp'], $message['signature'])) {
            return false;
        }
        
        // Check timestamp freshness (1 minute tolerance)
        $now = round(microtime(true) * 1000);
        if (abs($now - $message['timestamp']) > 60000) {
            return false;
        }
        
        // Verify signature
        $payload = json_encode($message['data']) . $message['timestamp'];
        $expectedSignature = hash_hmac('sha256', $payload, $this->sharedSecret);
        
        return hash_equals($expectedSignature, $message['signature']);
    }
}