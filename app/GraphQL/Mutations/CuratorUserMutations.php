<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use App\Models\ApiToken;
use App\Models\CollectionCurator;
use App\Services\CuratorMessageBusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CuratorUserMutations
{
    protected CuratorMessageBusService $messageBus;
    
    public function __construct(CuratorMessageBusService $messageBus)
    {
        $this->messageBus = $messageBus;
    }
    /**
     * Create a curator user for a collection
     */
    public function createCuratorUser($_, array $args)
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        // Check if user has permission to manage this collection
        // For now, we'll allow any authenticated user
        
        // Create the curator user account
        $curatorUser = User::create([
            'name' => $args['name'],
            'email' => $args['email'] ?? Str::slug($args['name']) . '@curator.attic.local',
            'password' => Hash::make(Str::random(32)), // Random password since curators use API tokens
            'user_type' => 'curator',
            'curator_owner_id' => $user->id,
            'curator_config' => [
                'collection_id' => $args['collection_id'],
                'prompt' => $args['prompt'],
                'model' => $args['model'] ?? 'claude-3-haiku-20240307',
                'schedule' => $args['schedule'] ?? 'daily',
                'auto_approve' => $args['auto_approve'] ?? false,
                'confidence_threshold' => $args['confidence_threshold'] ?? 80,
            ],
        ]);

        // Create or update the collection curator record
        $collectionCurator = CollectionCurator::updateOrCreate(
            ['collection_id' => $args['collection_id']],
            [
                'curator_user_id' => $curatorUser->id,
                'prompt' => $args['prompt'],
                'status' => 'active',
                'auto_approve' => $args['auto_approve'] ?? false,
                'confidence_threshold' => $args['confidence_threshold'] ?? 80,
            ]
        );

        // Register curator with the curator service via message bus
        // This will create the API token and send it directly to the curator service
        $this->messageBus->registerCurator($curatorUser, $collectionCurator);

        return [
            'curator' => $curatorUser,
            'collectionCurator' => $collectionCurator,
            // Note: API token is not returned - it's sent directly to the curator service
        ];
    }

    /**
     * Update a curator user's configuration
     */
    public function updateCuratorUser($_, array $args)
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $curatorUser = User::find($args['id']);
        
        if (!$curatorUser || $curatorUser->user_type !== 'curator') {
            throw new \Exception('Curator user not found');
        }
        
        // Check ownership
        if ($curatorUser->curator_owner_id !== $user->id && !$user->is_admin) {
            throw new \Exception('You do not have permission to update this curator');
        }

        // Update curator configuration
        $config = $curatorUser->curator_config ?? [];
        
        if (isset($args['name'])) {
            $curatorUser->name = $args['name'];
        }
        
        if (isset($args['prompt'])) {
            $config['prompt'] = $args['prompt'];
        }
        
        if (isset($args['model'])) {
            $config['model'] = $args['model'];
        }
        
        if (isset($args['schedule'])) {
            $config['schedule'] = $args['schedule'];
        }
        
        if (isset($args['auto_approve'])) {
            $config['auto_approve'] = $args['auto_approve'];
        }
        
        if (isset($args['confidence_threshold'])) {
            $config['confidence_threshold'] = $args['confidence_threshold'];
        }
        
        $curatorUser->curator_config = $config;
        $curatorUser->save();

        // Update collection curator record if exists
        if ($collectionCurator = CollectionCurator::where('curator_user_id', $curatorUser->id)->first()) {
            if (isset($args['prompt'])) {
                $collectionCurator->prompt = $args['prompt'];
            }
            if (isset($args['auto_approve'])) {
                $collectionCurator->auto_approve = $args['auto_approve'];
            }
            if (isset($args['confidence_threshold'])) {
                $collectionCurator->confidence_threshold = $args['confidence_threshold'];
            }
            $collectionCurator->save();
        }

        return $curatorUser;
    }

    /**
     * List curator users owned by the authenticated user
     */
    public function myCuratorUsers($_, array $args)
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        return User::where('curator_owner_id', $user->id)
            ->where('user_type', 'curator')
            ->get();
    }

    /**
     * Delete a curator user
     */
    public function deleteCuratorUser($_, array $args)
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $curatorUser = User::find($args['id']);
        
        if (!$curatorUser || $curatorUser->user_type !== 'curator') {
            throw new \Exception('Curator user not found');
        }
        
        // Check ownership
        if ($curatorUser->curator_owner_id !== $user->id && !$user->is_admin) {
            throw new \Exception('You do not have permission to delete this curator');
        }

        // Delete associated API tokens
        $curatorUser->apiTokens()->delete();
        
        // Delete the curator user
        $curatorUser->delete();

        return 'Curator user deleted successfully';
    }
}