<?php

namespace App\Jobs;

use App\Models\CollectionCurator;
use App\Services\CuratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCuratorRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes

    protected CollectionCurator $curator;

    public function __construct(CollectionCurator $curator)
    {
        $this->curator = $curator;
        $this->queue = 'curators'; // Use dedicated queue for curator jobs
    }

    public function handle(CuratorService $curatorService): void
    {
        Log::info('Starting curator run', [
            'curator_id' => $this->curator->id,
            'collection_id' => $this->curator->collection_id,
        ]);

        try {
            $result = $curatorService->runCurator($this->curator);
            
            Log::info('Curator run completed', [
                'curator_id' => $this->curator->id,
                'suggestions_created' => $result['suggestions_created'],
            ]);
        } catch (\Exception $e) {
            Log::error('Curator run failed', [
                'curator_id' => $this->curator->id,
                'error' => $e->getMessage(),
            ]);
            
            // Update curator status to error
            $this->curator->update(['status' => 'error']);
            
            throw $e; // Re-throw to mark job as failed
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Curator job failed after retries', [
            'curator_id' => $this->curator->id,
            'error' => $exception->getMessage(),
        ]);

        $this->curator->update([
            'status' => 'error',
            'performance_metrics' => array_merge(
                $this->curator->performance_metrics ?? [],
                ['last_error' => $exception->getMessage()]
            ),
        ]);
    }
}