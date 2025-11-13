<?php

namespace App\GraphQL\Mutations;

use App\Models\UserItem;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchUserItemMutations
{
    /**
     * Batch add items to user's collection
     *
     * @param mixed $rootValue The root value passed to the resolver
     * @param array $args The arguments passed to the mutation
     * @return array{success: bool, items_processed: int, items_skipped: int, message: string}
     * @throws \Exception If user is not authenticated
     */
    public function batchAddItemsToMyCollection($rootValue, array $args)
    {
        $user = auth()->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $entityIds = $args['entity_ids'];

        $processed = 0;
        $skipped = 0;

        DB::transaction(function () use ($user, $entityIds, &$processed, &$skipped) {
            foreach ($entityIds as $entityId) {
                // Check if already owned
                $exists = UserItem::where('user_id', $user->id)
                    ->where('entity_id', $entityId)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                UserItem::create([
                    'user_id' => $user->id,
                    'entity_id' => $entityId,
                ]);

                $processed++;
            }
        });

        Log::info('Batch add to collection completed', [
            'user_id' => $user->id,
            'processed' => $processed,
            'skipped' => $skipped,
        ]);

        return [
            'success' => true,
            'items_processed' => $processed,
            'items_skipped' => $skipped,
            'message' => "Added {$processed} items to your collection" . ($skipped > 0 ? " ({$skipped} already owned)" : "")
        ];
    }

    /**
     * Batch remove items from user's collection
     *
     * @param mixed $rootValue The root value passed to the resolver
     * @param array $args The arguments passed to the mutation
     * @return array{success: bool, items_processed: int, items_skipped: int, message: string}
     * @throws \Exception If user is not authenticated
     */
    public function batchRemoveItemsFromMyCollection($rootValue, array $args)
    {
        $user = auth()->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $entityIds = $args['entity_ids'];

        $deleted = UserItem::where('user_id', $user->id)
            ->whereIn('entity_id', $entityIds)
            ->delete();

        Log::info('Batch remove from collection completed', [
            'user_id' => $user->id,
            'deleted' => $deleted,
        ]);

        return [
            'success' => true,
            'items_processed' => $deleted,
            'items_skipped' => count($entityIds) - $deleted,
            'message' => "Removed {$deleted} items from your collection"
        ];
    }

    /**
     * Batch add items to wishlist
     *
     * @param mixed $rootValue The root value passed to the resolver
     * @param array $args The arguments passed to the mutation
     * @return array{success: bool, items_processed: int, items_skipped: int, message: string}
     * @throws \Exception If user is not authenticated
     */
    public function batchAddItemsToWishlist($rootValue, array $args)
    {
        $user = auth()->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $entityIds = $args['entity_ids'];
        $parentCollectionId = $args['parent_collection_id'] ?? null;

        $processed = 0;
        $skipped = 0;

        DB::transaction(function () use ($user, $entityIds, $parentCollectionId, &$processed, &$skipped) {
            foreach ($entityIds as $entityId) {
                // Skip if already owned
                $isOwned = UserItem::where('user_id', $user->id)
                    ->where('entity_id', $entityId)
                    ->exists();

                if ($isOwned) {
                    $skipped++;
                    continue;
                }

                // Skip if already wishlisted
                $exists = Wishlist::where('user_id', $user->id)
                    ->where('entity_id', $entityId)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Wishlist::create([
                    'user_id' => $user->id,
                    'entity_id' => $entityId,
                    'parent_collection_id' => $parentCollectionId,
                ]);

                $processed++;
            }
        });

        Log::info('Batch add to wishlist completed', [
            'user_id' => $user->id,
            'processed' => $processed,
            'skipped' => $skipped,
        ]);

        return [
            'success' => true,
            'items_processed' => $processed,
            'items_skipped' => $skipped,
            'message' => "Added {$processed} items to wishlist" . ($skipped > 0 ? " ({$skipped} skipped)" : "")
        ];
    }
}
