<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Collection extends Model
{
    use HasFactory, UsesUuid;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'type',
        'description',
        'metadata',
        'status',
        'image_url',
    ];

    protected $casts = [
        // metadata is handled as JSON string in GraphQL
    ];


    public function collectibles(): BelongsToMany
    {
        return $this->belongsToMany(Collectible::class);
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributed_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($collection) {
            if (empty($collection->slug)) {
                $collection->slug = Str::slug($collection->name);
                
                // Ensure slug is unique
                $count = static::where('slug', 'like', $collection->slug . '%')->count();
                if ($count > 0) {
                    $collection->slug = $collection->slug . '-' . ($count + 1);
                }
            }
        });
    }
}
