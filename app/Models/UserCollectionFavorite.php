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
        'collection_id',
    ];

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Note: collection_id references Supabase collection entity UUID
     * Use DatabaseOfThingsService to fetch collection data from Supabase
     */
}
