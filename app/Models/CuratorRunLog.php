<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CuratorRunLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'curator_id',
        'status',
        'started_at',
        'completed_at',
        'items_analyzed',
        'suggestions_generated',
        'api_usage',
        'run_metadata',
        'error_message',
    ];

    protected $casts = [
        'api_usage' => 'array',
        'run_metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function curator(): BelongsTo
    {
        return $this->belongsTo(CollectionCurator::class, 'curator_id');
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        
        return $this->completed_at->diffInSeconds($this->started_at);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRunning(): bool
    {
        return $this->status === 'started' && !$this->completed_at;
    }
}