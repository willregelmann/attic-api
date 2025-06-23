<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collectible extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'base_attributes',
        'components',
        'variants',
        'digital_metadata',
        'image_urls',
        'contributed_by',
        'verified_by',
    ];

    protected $casts = [
        'base_attributes' => 'array',
        'components' => 'array',
        'variants' => 'array',
        'digital_metadata' => 'array',
        'image_urls' => 'array',
        'verified_by' => 'array',
    ];

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributed_by');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
