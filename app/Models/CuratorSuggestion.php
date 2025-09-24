<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CuratorSuggestion extends Model
{
    use HasUuids;

    protected $fillable = [
        'curator_id',
        'collection_id',
        'action_type',
        'item_id',
        'suggestion_data',
        'reasoning',
        'confidence_score',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'executed',
        'executed_at',
        'execution_result',
        'expires_at',
    ];

    protected $casts = [
        'suggestion_data' => 'array',
        'execution_result' => 'array',
        'executed' => 'boolean',
        'reviewed_at' => 'datetime',
        'executed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function curator(): BelongsTo
    {
        return $this->belongsTo(CollectionCurator::class, 'curator_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'collection_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isHighConfidence(): bool
    {
        return $this->confidence_score >= ($this->curator->confidence_threshold ?? 80);
    }

    public function shouldAutoApprove(): bool
    {
        return $this->curator->auto_approve && $this->isHighConfidence();
    }

    public function approve(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $this->curator->increment('suggestions_approved');
    }

    public function reject(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        $this->curator->increment('suggestions_rejected');
    }

    public function execute(): bool
    {
        if ($this->status !== 'approved' || $this->executed) {
            return false;
        }

        // This would be implemented based on action_type
        // For now, we'll just mark as executed
        $this->update([
            'executed' => true,
            'executed_at' => now(),
            'execution_result' => ['status' => 'success'],
        ]);

        return true;
    }
}