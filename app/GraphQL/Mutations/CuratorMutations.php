<?php

namespace App\GraphQL\Mutations;

use App\Models\CollectionCurator;
use App\Models\CuratorSuggestion;
use App\Models\Item;
use App\Services\CuratorMessageBusService;
use Illuminate\Support\Facades\Auth;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CuratorMutations
{
    protected CuratorMessageBusService $messageBus;

    public function __construct(CuratorMessageBusService $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    /**
     * Create a new curator for a collection
     */
    public function createCurator($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $collection = Item::findOrFail($args['collection_id']);
        
        // Check if user is a maintainer
        $isMaintainer = $collection->maintainers()
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$isMaintainer) {
            throw new \Exception('You must be a collection maintainer to create a curator');
        }

        $curator = CollectionCurator::create([
            'collection_id' => $collection->id,
            'prompt' => $args['prompt'],
            'status' => 'inactive',
            'auto_approve' => $args['auto_approve'] ?? false,
            'confidence_threshold' => $args['confidence_threshold'] ?? 80,
            'next_run_at' => now()->addDay(), // First run will be tomorrow
        ]);

        return $curator;
    }

    /**
     * Update curator configuration
     */
    public function updateCurator($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $curator = CollectionCurator::findOrFail($args['id']);
        
        // Check permissions
        $isMaintainer = $curator->collection->maintainers()
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$isMaintainer) {
            throw new \Exception('You must be a collection maintainer to update the curator');
        }

        $updateData = [];
        
        if (isset($args['prompt'])) $updateData['prompt'] = $args['prompt'];
        if (isset($args['auto_approve'])) $updateData['auto_approve'] = $args['auto_approve'];
        if (isset($args['confidence_threshold'])) $updateData['confidence_threshold'] = $args['confidence_threshold'];

        $curator->update($updateData);

        // Notify curator service of the update
        $this->messageBus->updateCurator($curator);

        return $curator;
    }

    /**
     * Toggle curator active status
     */
    public function toggleCuratorStatus($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $curator = CollectionCurator::findOrFail($args['id']);
        
        // Check permissions
        $isMaintainer = $curator->collection->maintainers()
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$isMaintainer) {
            throw new \Exception('You must be a collection maintainer to toggle curator status');
        }

        $newStatus = $curator->status === 'active' ? 'inactive' : 'active';
        $curator->update(['status' => $newStatus]);

        // If activating, calculate next run time (always daily now)
        if ($newStatus === 'active') {
            $curator->update(['next_run_at' => $curator->calculateNextRunTime()]);
        }

        return $curator;
    }

    /**
     * Manually trigger a curator run
     */
    public function runCurator($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $curator = CollectionCurator::findOrFail($args['id']);
        
        // Check permissions
        $isMaintainer = $curator->collection->maintainers()
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$isMaintainer) {
            throw new \Exception('You must be a collection maintainer to run the curator');
        }

        if ($curator->status !== 'active') {
            $curator->update(['status' => 'active']);
        }

        // Send run command to curator service via message bus
        $this->messageBus->runCurator($curator);

        return [
            'success' => true,
            'message' => 'Curator run has been queued',
            'curator' => $curator,
        ];
    }

    /**
     * Review a curator suggestion
     */
    public function reviewSuggestion($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $suggestion = CuratorSuggestion::findOrFail($args['id']);
        
        // Check permissions
        $isMaintainer = $suggestion->collection->maintainers()
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$isMaintainer) {
            throw new \Exception('You must be a collection maintainer to review suggestions');
        }

        if (!$suggestion->isPending()) {
            throw new \Exception('This suggestion has already been reviewed');
        }

        if ($args['action'] === 'approve') {
            $suggestion->approve($user, $args['notes'] ?? null);
            
            // Execute immediately if requested
            if ($args['execute_now'] ?? false) {
                $suggestion->execute();
            }
        } else {
            $suggestion->reject($user, $args['notes'] ?? null);
        }

        return $suggestion;
    }

    /**
     * Bulk review suggestions
     */
    public function bulkReviewSuggestions($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $curator = CollectionCurator::findOrFail($args['curator_id']);
        
        // Check permissions
        $isMaintainer = $curator->collection->maintainers()
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$isMaintainer) {
            throw new \Exception('You must be a collection maintainer to review suggestions');
        }

        $suggestions = CuratorSuggestion::whereIn('id', $args['suggestion_ids'])
            ->where('curator_id', $curator->id)
            ->where('status', 'pending')
            ->get();

        $approved = 0;
        $rejected = 0;

        foreach ($suggestions as $suggestion) {
            if ($args['action'] === 'approve') {
                $suggestion->approve($user, $args['notes'] ?? null);
                $approved++;
                
                if ($args['execute_now'] ?? false) {
                    $suggestion->execute();
                }
            } else {
                $suggestion->reject($user, $args['notes'] ?? null);
                $rejected++;
            }
        }

        return [
            'success' => true,
            'approved' => $approved,
            'rejected' => $rejected,
        ];
    }

    /**
     * Delete a curator
     */
    public function deleteCurator($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();
        $curator = CollectionCurator::findOrFail($args['id']);
        
        // Check if user is collection owner
        $isOwner = $curator->collection->maintainers()
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();
            
        if (!$isOwner) {
            throw new \Exception('Only collection owners can delete curators');
        }

        $curator->delete();

        return 'Curator deleted successfully';
    }
}