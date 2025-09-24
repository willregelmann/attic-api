<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ListPokemonSets extends Command
{
    protected $signature = 'pokemon:list {--recent : Show only recent sets}';
    protected $description = 'List available Pokemon TCG sets from the API';

    public function handle()
    {
        $this->info('Fetching Pokemon TCG sets...');

        $response = Http::timeout(60)->get('https://api.pokemontcg.io/v2/sets', [
            'pageSize' => 10,
            'orderBy' => '-releaseDate'
        ]);

        if (!$response->successful()) {
            $this->error('Failed to fetch sets: ' . $response->status());
            return;
        }

        $sets = $response->json()['data'] ?? [];

        $this->info("\nRecent Pokemon TCG Sets:");
        $this->info(str_repeat('-', 80));

        foreach ($sets as $set) {
            $this->line(sprintf(
                "ID: %-15s | Name: %-30s | Total: %d",
                $set['id'],
                substr($set['name'], 0, 30),
                $set['total'] ?? 0
            ));
        }

        $this->info(str_repeat('-', 80));
        $this->info("Use 'php artisan pokemon:update --set=<ID>' to import a specific set");
    }
}