<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ItemRelationship extends Pivot
{
    use HasUuid;

    protected $table = 'item_relationships';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'parent_id',
        'child_id',
        'relationship_type',
        'canonical_order',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'canonical_order' => 'integer',
    ];

    /**
     * Get the parent item
     */
    public function parent()
    {
        return $this->belongsTo(Item::class, 'parent_id');
    }

    /**
     * Get the child item
     */
    public function child()
    {
        return $this->belongsTo(Item::class, 'child_id');
    }
}