<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserItem extends Pivot
{
    use HasUuids, SoftDeletes;

    protected $table = 'user_items';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'entity_id', // References Database of Things entity UUID (nullable for custom items)
        'name', // Name for custom items (null for DBoT items)
        'variant_id',
        'parent_collection_id',
        'metadata',
        'notes',
        'images',
    ];

    protected $casts = [
        'metadata' => 'array',
        'images' => 'array',
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

    /**
     * Check if this is a custom item (not linked to DBoT)
     */
    public function isCustomItem(): bool
    {
        return is_null($this->entity_id);
    }
}
