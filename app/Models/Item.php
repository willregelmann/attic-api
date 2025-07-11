<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    use HasFactory, UsesUuid;

    protected $fillable = [
        'user_id',
        'collectible_id',
        'variant_id',
        'name',
        'personal_notes',
        'availability',
        'user_images',
        'is_favorite',
    ];

    protected $casts = [
        'availability' => 'array',
        'user_images' => 'array',
        'is_favorite' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collectible(): BelongsTo
    {
        return $this->belongsTo(Collectible::class);
    }

}
