<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CollectionCurator extends Model
{
    use HasUuids;

    protected $fillable = [
        'collection_id',
        'name',
        'description',
        'status',
        'curator_config',
        'schedule_type',
        'schedule_config',
        'last_run_at',
        'next_run_at',
        'auto_approve',
        'confidence_threshold',
        'suggestions_made',
        'suggestions_approved',
        'suggestions_rejected',
        'performance_metrics',
    ];

    protected $casts = [
        'curator_config' => 'array',
        'schedule_config' => 'array',
        'performance_metrics' => 'array',
        'auto_approve' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'collection_id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(CuratorSuggestion::class, 'curator_id');
    }

    public function runLogs(): HasMany
    {
        return $this->hasMany(CuratorRunLog::class, 'curator_id');
    }

    public function pendingSuggestions(): HasMany
    {
        return $this->suggestions()->where('status', 'pending');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function shouldRunNow(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->schedule_type === 'manual') {
            return false;
        }

        return $this->next_run_at && $this->next_run_at->isPast();
    }

    public function calculateNextRunTime(): ?string
    {
        switch ($this->schedule_type) {
            case 'hourly':
                return now()->addHour();
            case 'daily':
                return now()->addDay();
            case 'weekly':
                return now()->addWeek();
            case 'manual':
            default:
                return null;
        }
    }

    public function getApprovalRate(): float
    {
        $total = $this->suggestions_approved + $this->suggestions_rejected;
        return $total > 0 ? ($this->suggestions_approved / $total) * 100 : 0;
    }
}