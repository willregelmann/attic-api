<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemImage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'item_id',
        'user_id',
        'url',
        'alt_text',
        'is_primary',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_primary' => 'boolean',
    ];

    /**
     * Get the item that owns this image
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the user who uploaded this image
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for primary images only
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}