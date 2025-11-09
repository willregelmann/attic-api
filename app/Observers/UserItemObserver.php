<?php

namespace App\Observers;

use App\Models\UserItem;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Log;

class UserItemObserver
{
    /**
     * Handle the UserItem "created" event.
     */
    public function created(UserItem $userItem): void
    {
        // Remove the item from the user's wishlist if it exists
        $deleted = Wishlist::where('user_id', $userItem->user_id)
            ->where('entity_id', $userItem->entity_id)
            ->delete();

        if ($deleted) {
            Log::info('Item removed from wishlist after being added to collection', [
                'user_id' => $userItem->user_id,
                'entity_id' => $userItem->entity_id
            ]);
        }
    }
}
