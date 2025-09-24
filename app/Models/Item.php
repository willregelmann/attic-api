<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'type',
        'name',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get all parent relationships (collections this item belongs to)
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_relationships', 'child_id', 'parent_id')
            ->withPivot('relationship_type', 'canonical_order', 'metadata')
            ->withTimestamps()
            ->using(ItemRelationship::class);
    }

    /**
     * Get all child relationships (items contained in this collection)
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_relationships', 'parent_id', 'child_id')
            ->withPivot('relationship_type', 'canonical_order', 'metadata')
            ->withTimestamps()
            ->orderByPivot('canonical_order')
            ->using(ItemRelationship::class);
    }

    /**
     * Get variants of this item
     */
    public function variants(): BelongsToMany
    {
        return $this->children()->wherePivot('relationship_type', 'variant_of');
    }

    /**
     * Get components of this item
     */
    public function components(): BelongsToMany
    {
        return $this->children()->wherePivot('relationship_type', 'component_of');
    }

    /**
     * Get collections this item belongs to
     */
    public function collections(): BelongsToMany
    {
        return $this->parents()->wherePivot('relationship_type', 'contains');
    }

    /**
     * Get images for this item
     */
    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class);
    }

    /**
     * Get the primary image for this item (as a relationship)
     */
    public function primaryImage()
    {
        return $this->hasOne(ItemImage::class)->where('is_primary', true);
    }

    /**
     * Get users who own this item
     */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_items')
            ->withPivot('metadata')
            ->withTimestamps()
            ->using(UserItem::class);
    }

    /**
     * Get users who have favorited this collection
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_collection_favorites', 'collection_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Check if this is a collection type
     */
    public function isCollection(): bool
    {
        return $this->type === 'collection';
    }

    /**
     * Check if this is a collectible type
     */
    public function isCollectible(): bool
    {
        return $this->type === 'collectible';
    }

    /**
     * Get maintainers for this collection
     */
    public function maintainers(): HasMany
    {
        return $this->hasMany(CollectionMaintainer::class, 'collection_id');
    }

    /**
     * Scope for collections only
     */
    public function scopeCollections($query)
    {
        return $query->where('type', 'collection');
    }

    /**
     * Scope for collectibles only
     */
    public function scopeCollectibles($query)
    {
        return $query->where('type', 'collectible');
    }

    /**
     * Get children count attribute for GraphQL
     */
    public function getChildrenCountAttribute(): int
    {
        return $this->children()->count();
    }

    /**
     * Get owned children count attribute for GraphQL
     * Note: This is a placeholder - you'll need to pass the current user context
     */
    public function getOwnedChildrenCountAttribute(): int
    {
        // For now, return 0 - this would need to be calculated based on the authenticated user
        // In a real implementation, you'd need to check which children are owned by the current user
        return 0;
    }
}