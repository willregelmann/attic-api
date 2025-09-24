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