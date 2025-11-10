<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserCollection extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'parent_collection_id',
        'name',
        'type',
        'description',
        'custom_image',
        'linked_dbot_collection_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns this collection
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent collection
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(UserCollection::class, 'parent_collection_id');
    }

    /**
     * Get child collections
     */
    public function subcollections(): HasMany
    {
        return $this->hasMany(UserCollection::class, 'parent_collection_id');
    }

    /**
     * Get items in this collection
     */
    public function items(): HasMany
    {
        return $this->hasMany(UserItem::class, 'parent_collection_id');
    }

    /**
     * Get wishlist items in this collection
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'parent_collection_id');
    }
}
