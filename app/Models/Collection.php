<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'type',
        'description',
        'metadata',
        'status',
        'image_url',
        'contributed_by',
        'verified_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'verified_by' => 'array',
    ];

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributed_by');
    }

    public function collectibles(): BelongsToMany
    {
        return $this->belongsToMany(Collectible::class);
    }
}
