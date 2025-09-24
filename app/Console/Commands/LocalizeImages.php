<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ImageStorageService;
use App\Models\Item;
use App\Models\ItemImage;

class LocalizeImages extends Command
{
    protected $signature = 'images:localize {--type=} {--limit=}';
    protected $description = 'Download and store all external images locally';

    private $imageService;

    public function __construct()
    {
        parent::__construct();
        $this->imageService = new ImageStorageService();
    }

    public function handle()
    {
        $type = $this->option('type');
        $limit = $this->option('limit');

        $this->info('Starting image localization process...');

        // Get items with images
        $query = Item::whereHas('images', function($q) {
            // Skip already localized images
            $q->whereNotLike('url', '%localhost:8888%')
              ->whereNotLike('url', '%/storage/%');
        });

        if ($type) {
            $query->where('type', $type);
        }

        if ($limit) {
            $query->limit($limit);
        }

        $items = $query->get();
        $this->info('Found ' . $items->count() . ' items with external images');

        $totalImages = 0;
        $successCount = 0;
        $failCount = 0;

        $progressBar = $this->output->createProgressBar($items->count());
        $progressBar->start();

        foreach ($items as $item) {
            $images = $item->images()->where('url', 'NOT LIKE', '%localhost:8888%')->get();

            foreach ($images as $image) {
                $totalImages++;
                $originalUrl = $image->url;

                $localUrl = $this->imageService->storeImageFromUrl(
                    $originalUrl,
                    $item->type,
                    $item->name
                );

                if ($localUrl) {
                    $image->url = $localUrl;
                    $image->metadata = array_merge($image->metadata ?? [], [
                        'original_url' => $originalUrl,
                        'localized_at' => now()->toIso8601String()
                    ]);
                    $image->save();
                    $successCount++;
                } else {
                    $failCount++;
                    $this->warn("\nFailed to download: " . $originalUrl);
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n\nImage localization complete!");
        $this->info("Total images processed: $totalImages");
        $this->info("Successfully localized: $successCount");
        if ($failCount > 0) {
            $this->warn("Failed: $failCount");
        }

        return 0;
    }
}