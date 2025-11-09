<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    use HasUuids;

    protected $table = 'wishlists';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'entity_id', // References Supabase entity UUID
        'parent_collection_id',
    ];

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent collection
     */
    public function parentCollection(): BelongsTo
    {
        return $this->belongsTo(UserCollection::class, 'parent_collection_id');
    }

    /**
     * Note: entity_id references a Supabase entity UUID
     * No local Item relationship exists since canonical data is in Supabase
     */
}
