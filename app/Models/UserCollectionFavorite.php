<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserCollectionFavorite extends Pivot
{
    use HasUuids;

    protected $table = 'user_collection_favorites';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'entity_id',
    ];

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Note: entity_id references DBoT collection UUID
     * No foreign key constraint - references external Database of Things API
     * Use DatabaseOfThingsService to fetch collection data from Supabase
     */
}
