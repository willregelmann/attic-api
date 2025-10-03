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
        'curator_user_id',
        'api_token_encrypted',
        'prompt',
        'status',
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
        'performance_metrics' => 'array',
        'auto_approve' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    protected $hidden = [
        'api_token_encrypted',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'collection_id');
    }

    public function curatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'curator_user_id');
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

        // Always runs daily, check if next run time has passed
        return $this->next_run_at && $this->next_run_at->isPast();
    }

    public function calculateNextRunTime(): ?string
    {
        // Always runs daily
        return now()->addDay();
    }

    public function getApprovalRate(): float
    {
        $total = $this->suggestions_approved + $this->suggestions_rejected;
        return $total > 0 ? ($this->suggestions_approved / $total) * 100 : 0;
    }

    /**
     * Store the API token encrypted
     */
    public function setApiToken(string $plainTextToken): void
    {
        $this->api_token_encrypted = encrypt($plainTextToken);
        $this->save();
    }

    /**
     * Retrieve the decrypted API token
     */
    public function getApiToken(): ?string
    {
        if (!$this->api_token_encrypted) {
            return null;
        }

        try {
            return decrypt($this->api_token_encrypted);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt curator API token', [
                'curator_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}