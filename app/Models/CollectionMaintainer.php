<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionMaintainer extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_id',
        'user_id',
        'role',
        'permissions'
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Get the collection
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'collection_id');
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user is owner
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Check if user is maintainer
     */
    public function isMaintainer(): bool
    {
        return in_array($this->role, ['owner', 'maintainer']);
    }

    /**
     * Check if user is contributor
     */
    public function isContributor(): bool
    {
        return in_array($this->role, ['owner', 'maintainer', 'contributor']);
    }
}