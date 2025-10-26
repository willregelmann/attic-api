<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserItem extends Pivot
{
    use HasUuids;

    protected $table = 'user_items';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'entity_id', // References Supabase entity UUID
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Note: entity_id references a Supabase entity UUID
     * No local Item relationship exists since canonical data is in Supabase
     */
}