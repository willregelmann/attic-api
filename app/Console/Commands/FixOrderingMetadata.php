<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixOrderingMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:ordering-metadata {--dry-run : Show what would be changed without making changes} {--type= : Fix specific type only (pokemon|figures|all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix ordering metadata issues in Pokemon cards and action figures';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $type = $this->option('type') ?? 'all';

        $this->info('Starting ordering metadata fix...');
        $this->info($dryRun ? 'DRY RUN MODE - No changes will be made' : 'LIVE MODE - Changes will be saved');
        $this->newLine();

        $totalFixed = 0;

        if ($type === 'all' || $type === 'pokemon') {
            $totalFixed += $this->fixPokemonCards($dryRun);
        }

        if ($type === 'all' || $type === 'figures') {
            $totalFixed += $this->fixActionFigures($dryRun);
        }

        $this->newLine();
        $this->info("Total items that would be fixed: {$totalFixed}");

        if (!$dryRun) {
            $this->info('All fixes have been applied successfully!');
        } else {
            $this->warn('Run without --dry-run to apply the changes');
        }
    }

    private function fixPokemonCards(bool $dryRun): int
    {
        $this->info('=== Fixing Pokemon Cards ===');

        // Find Pokemon cards missing card_number metadata
        $pokemonCards = Item::where('type', 'collectible')
            ->where('name', 'LIKE', '%#%')
            ->whereRaw("metadata->>'card_number' IS NULL")
            ->get();

        $this->info("Found {$pokemonCards->count()} Pokemon cards missing card_number metadata");

        $fixed = 0;

        foreach ($pokemonCards as $card) {
            // Extract card number from name (e.g., "Alakazam #1" -> "001")
            if (preg_match('/.*#(\d+)$/', $card->name, $matches)) {
                $cardNumber = str_pad($matches[1], 3, '0', STR_PAD_LEFT);

                // Try to determine the set size from similar cards or relationships
                $setSize = $this->determineSetSize($card);
                $fullCardNumber = "{$cardNumber}/{$setSize}";

                $this->line("  {$card->name} -> card_number: {$fullCardNumber}");

                if (!$dryRun) {
                    $metadata = $card->metadata ?? [];
                    $metadata['card_number'] = $fullCardNumber;
                    $card->update(['metadata' => $metadata]);
                }

                $fixed++;
            }
        }

        $this->info("Pokemon cards fixed: {$fixed}");
        return $fixed;
    }

    private function fixActionFigures(bool $dryRun): int
    {
        $this->info('=== Fixing Action Figures ===');

        // Find action figures with incorrect card_number fields
        $figuresWithCardNumbers = Item::where('type', 'collectible')
            ->where('name', 'LIKE', '%Action Figure')
            ->whereRaw("metadata->>'card_number' IS NOT NULL")
            ->get();

        $this->info("Found {$figuresWithCardNumbers->count()} action figures with incorrect card_number metadata");

        $fixed = 0;

        foreach ($figuresWithCardNumbers as $figure) {
            $this->line("  {$figure->name} -> removing card_number: {$figure->metadata['card_number']}");

            if (!$dryRun) {
                $metadata = $figure->metadata ?? [];
                unset($metadata['card_number']);
                $figure->update(['metadata' => $metadata]);
            }

            $fixed++;
        }

        // Fix figure_number and series_number format issues
        $figuresWithBadNumbers = Item::where('type', 'collectible')
            ->where('name', 'LIKE', '%Action Figure')
            ->get()
            ->filter(function ($figure) {
                $metadata = $figure->metadata ?? [];
                return (isset($metadata['figure_number']) && !is_numeric($metadata['figure_number'])) ||
                       (isset($metadata['series_number']) && !is_numeric($metadata['series_number']));
            });

        $this->info("Found {$figuresWithBadNumbers->count()} action figures with incorrectly formatted numbers");

        foreach ($figuresWithBadNumbers as $figure) {
            $metadata = $figure->metadata ?? [];
            $changes = [];

            // Fix figure_number
            if (isset($metadata['figure_number']) && !is_numeric($metadata['figure_number'])) {
                $oldValue = $metadata['figure_number'];
                if ($oldValue === 'six') {
                    $metadata['figure_number'] = '006';
                    $changes[] = "figure_number: '{$oldValue}' -> '006'";
                }
            }

            // Fix series_number
            if (isset($metadata['series_number']) && !is_numeric($metadata['series_number'])) {
                $oldValue = $metadata['series_number'];
                if ($oldValue === 'first') {
                    $metadata['series_number'] = 1;
                    $changes[] = "series_number: '{$oldValue}' -> 1";
                }
            }

            if (!empty($changes)) {
                $this->line("  {$figure->name} -> " . implode(', ', $changes));

                if (!$dryRun) {
                    $figure->update(['metadata' => $metadata]);
                }

                $fixed++;
            }
        }

        $this->info("Action figures fixed: {$fixed}");
        return $fixed;
    }

    private function determineSetSize(Item $card): string
    {
        // First try to find collection relationship with total_cards metadata
        $collections = $card->collections()->get();

        foreach ($collections as $collection) {
            if (isset($collection->metadata['total_cards'])) {
                return str_pad($collection->metadata['total_cards'], 3, '0', STR_PAD_LEFT);
            }
        }

        // Check relationship metadata for set_number format
        $relationship = DB::table('item_relationships')
            ->where('child_id', $card->id)
            ->where('relationship_type', 'contains')
            ->whereRaw("metadata->>'set_number' IS NOT NULL")
            ->first();

        if ($relationship) {
            $metadata = json_decode($relationship->metadata, true);
            if (isset($metadata['set_number']) && preg_match('/\/(\d+)$/', $metadata['set_number'], $matches)) {
                return str_pad($matches[1], 3, '0', STR_PAD_LEFT);
            }
        }

        // Default fallback
        return '102'; // Base Set has 102 cards
    }
}
