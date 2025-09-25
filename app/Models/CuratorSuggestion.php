<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Item;
use App\Models\ItemImage;

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

        try {
            $result = match ($this->action_type) {
                'add_item' => $this->executeAddItem(),
                'add_subcollection' => $this->executeAddSubcollection(),
                'remove_item' => $this->executeRemoveItem(),
                'update_item' => $this->executeUpdateItem(),
                default => ['status' => 'error', 'message' => 'Unknown action type'],
            };

            $this->update([
                'executed' => true,
                'executed_at' => now(),
                'execution_result' => $result,
            ]);

            return $result['status'] === 'success';
        } catch (\Exception $e) {
            $this->update([
                'executed' => false,
                'execution_result' => [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ],
            ]);
            return false;
        }
    }

    private function executeAddItem(): array
    {
        $data = $this->suggestion_data;
        
        // Create the item
        $item = Item::create([
            'name' => $data['item_name'] ?? $data['name'] ?? 'Unnamed Item',
            'type' => $data['item_type'] ?? 'collectible',
            'metadata' => $data['metadata'] ?? [],
        ]);

        // Add to collection if specified
        if ($this->collection_id) {
            $this->collection->children()->attach($item->id, [
                'relationship_type' => 'contains',
                'metadata' => [],
            ]);
        }

        return [
            'status' => 'success',
            'item_id' => $item->id,
            'message' => "Created item: {$item->name}",
        ];
    }

    private function executeAddSubcollection(): array
    {
        $data = $this->suggestion_data;
        
        // Create the subcollection
        $subcollection = Item::create([
            'name' => $data['subcollection_name'] ?? 'Unnamed Subcollection',
            'type' => 'collection',
            'metadata' => $data['subcollection_metadata'] ?? [],
        ]);

        // Add to parent collection
        if ($this->collection_id) {
            $this->collection->children()->attach($subcollection->id, [
                'relationship_type' => 'contains',
                'metadata' => [],
            ]);
        }

        // Create images if logo_url or symbol_url is provided in metadata
        $metadata = $data['subcollection_metadata'] ?? [];
        if (!empty($metadata['logo_url'])) {
            ItemImage::create([
                'item_id' => $subcollection->id,
                'url' => $metadata['logo_url'],
                'alt_text' => $subcollection->name . ' logo',
                'is_primary' => true,
                'metadata' => ['type' => 'logo']
            ]);
        }
        
        if (!empty($metadata['symbol_url'])) {
            ItemImage::create([
                'item_id' => $subcollection->id,
                'url' => $metadata['symbol_url'],
                'alt_text' => $subcollection->name . ' symbol',
                'is_primary' => empty($metadata['logo_url']), // Only primary if no logo
                'metadata' => ['type' => 'symbol']
            ]);
        }

        // Create nested items if provided
        $createdItems = [];
        if (!empty($data['nested_items'])) {
            foreach ($data['nested_items'] as $nestedItem) {
                $item = Item::create([
                    'name' => $nestedItem['item_name'] ?? $nestedItem['name'] ?? 'Unnamed Item',
                    'type' => 'collectible',
                    'metadata' => $nestedItem['metadata'] ?? [],
                ]);
                
                // Add to subcollection
                $subcollection->children()->attach($item->id, [
                    'relationship_type' => 'contains',
                    'metadata' => [],
                ]);
                
                $createdItems[] = $item->id;
            }
        }

        return [
            'status' => 'success',
            'subcollection_id' => $subcollection->id,
            'created_items' => $createdItems,
            'message' => "Created subcollection: {$subcollection->name} with " . count($createdItems) . " items",
        ];
    }

    private function executeRemoveItem(): array
    {
        if (!$this->item_id) {
            return ['status' => 'error', 'message' => 'No item ID specified'];
        }

        $this->collection->children()->detach($this->item_id);

        return [
            'status' => 'success',
            'message' => "Removed item from collection",
        ];
    }

    private function executeUpdateItem(): array
    {
        if (!$this->item) {
            return ['status' => 'error', 'message' => 'Item not found'];
        }

        $data = $this->suggestion_data;
        $this->item->update([
            'name' => $data['name'] ?? $this->item->name,
            'metadata' => array_merge($this->item->metadata ?? [], $data['metadata'] ?? []),
        ]);

        return [
            'status' => 'success',
            'message' => "Updated item: {$this->item->name}",
        ];
    }
}