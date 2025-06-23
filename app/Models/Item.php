<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'collectible_id',
        'variant_id',
        'quantity',
        'condition',
        'personal_notes',
        'component_status',
        'completeness',
        'acquisition_info',
        'storage',
        'digital_ownership',
        'availability',
        'showcase_history',
        'user_images',
    ];

    protected $casts = [
        'component_status' => 'array',
        'acquisition_info' => 'array',
        'storage' => 'array',
        'digital_ownership' => 'array',
        'availability' => 'array',
        'showcase_history' => 'array',
        'user_images' => 'array',
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
