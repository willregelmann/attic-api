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
        'item_id',
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
     * Get the item
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}