<?php

namespace App\Console\Commands;

use App\Models\CollectionCurator;
use App\Services\CuratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunScheduledCurators extends Command
{
    protected $signature = 'curators:run-scheduled';
    protected $description = 'Run all scheduled curators that are due';

    protected CuratorService $curatorService;

    public function __construct(CuratorService $curatorService)
    {
        parent::__construct();
        $this->curatorService = $curatorService;
    }

    public function handle(): int
    {
        $this->info('Checking for scheduled curator runs...');

        $curators = CollectionCurator::where('status', 'active')
            ->where('schedule_type', '!=', 'manual')
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        if ($curators->isEmpty()) {
            $this->info('No curators are due to run.');
            return Command::SUCCESS;
        }

        $this->info("Found {$curators->count()} curator(s) to run.");

        foreach ($curators as $curator) {
            try {
                $this->info("Running curator: {$curator->name} (ID: {$curator->id})");
                
                // Queue the curator run
                $this->curatorService->queueCuratorRun($curator);
                
                // Update next run time
                $curator->update([
                    'next_run_at' => $curator->calculateNextRunTime(),
                ]);
                
                $this->info("✓ Curator {$curator->name} queued successfully");
                
            } catch (\Exception $e) {
                $this->error("✗ Failed to queue curator {$curator->name}: {$e->getMessage()}");
                Log::error('Failed to queue scheduled curator', [
                    'curator_id' => $curator->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Scheduled curator run complete.');
        return Command::SUCCESS;
    }
}