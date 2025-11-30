<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCollection extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_collection_id',
        'name',
        'description',
        'custom_image',  // Keep for backward compatibility (deprecated)
        'images',  // NEW: array of image objects
        'linked_dbot_collection_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'images' => 'array',  // NEW: cast to array
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

    /**
     * Computed accessor for type field
     * Always returns 'collection' - UserCollections are always collections
     * (matches DBoT EntityType: 'item' or 'collection')
     */
    public function getTypeAttribute(): string
    {
        return 'collection';
    }

    /**
     * Computed accessor for category field
     * Returns 'linked' if collection is linked to a DBoT collection, otherwise 'custom'
     * Category describes what KIND of collection (custom, linked, trading_cards, etc.)
     */
    public function getCategoryAttribute(): string
    {
        return $this->linked_dbot_collection_id ? 'linked' : 'custom';
    }
}
