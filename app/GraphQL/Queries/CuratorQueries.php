<?php

namespace App\GraphQL\Queries;

use App\Models\CollectionCurator;
use App\Models\CuratorSuggestion;
use Illuminate\Support\Facades\Auth;

class CuratorQueries
{
    /**
     * Get curator for a collection
     */
    public function getCollectionCurator($rootValue, array $args)
    {
        $user = Auth::guard('sanctum')->user();

        // Check if user is a maintainer
        $isMaintainer = \App\Models\CollectionMaintainer::where('collection_id', $args['collection_id'])
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMaintainer) {
            return null; // Only maintainers can see curator config
        }

        return CollectionCurator::where('collection_id', $args['collection_id'])->first();
    }

    /**
     * Get curator with decrypted API token (for curator service)
     */
    public function getCuratorWithToken($rootValue, array $args)
    {
        $user = Auth::guard('sanctum')->user();
        $curator = CollectionCurator::findOrFail($args['curator_id']);

        // Only the curator's own user can retrieve its token
        if ($curator->curator_user_id !== $user->id) {
            throw new \Exception('Unauthorized: Only the curator user can retrieve its token');
        }

        return [
            'id' => $curator->id,
            'collection_id' => $curator->collection_id,
            'prompt' => $curator->prompt,
            'status' => $curator->status,
            'auto_approve' => $curator->auto_approve,
            'confidence_threshold' => $curator->confidence_threshold,
            'api_token' => $curator->getApiToken(),
            'collection' => $curator->collection,
        ];
    }
    
    /**
     * Get curator suggestions for a collection
     */
    public function getCuratorSuggestions($rootValue, array $args)
    {
        $user = Auth::guard('sanctum')->user();
        
        // Check if user is a maintainer
        $isMaintainer = \App\Models\CollectionMaintainer::where('collection_id', $args['collection_id'])
            ->where('user_id', $user->id)
            ->exists();
            
        if (!$isMaintainer) {
            return [];
        }
        
        $query = CuratorSuggestion::where('collection_id', $args['collection_id']);
        
        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }
}