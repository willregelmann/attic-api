<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserItem extends Pivot
{
    use HasUuids;

    protected $table = 'user_items';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'entity_id', // References Database of Things entity UUID
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
    public function parentCollection()
    {
        return $this->belongsTo(UserCollection::class, 'parent_collection_id');
    }

    /**
     * Note: entity_id references a Supabase entity UUID
     * No local Item relationship exists since canonical data is in Supabase
     */
}
