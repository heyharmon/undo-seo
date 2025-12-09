<?php

namespace App\Console\Commands;

use App\Services\DataForSeoService;
use Illuminate\Console\Command;

class DataForSeoTest extends Command
{
    protected $signature = 'dataforseo:test {keyword}';

    protected $description = 'Test the DataForSEO service with a seed keyword';

    public function handle(DataForSeoService $service): int
    {
        $keyword = $this->argument('keyword');

        $this->info("Generating topical map for: {$keyword}");
        $this->info("Using Keyword Suggestions API");
        $this->newLine();

        try {
            $result = $service->generateTopicalMap($keyword);

            $clusters = $result['clusters'];

            $this->info('Clusters generated: ' . count($clusters));
            $this->newLine();

            // Display clusters (first 20)
            $displayCount = min(count($clusters), 20);
            foreach (array_slice($clusters, 0, $displayCount) as $index => $cluster) {
                $parent = $cluster['parent'];
                $label = $service->getDifficultyLabel($parent['difficulty'] ?? 0);

                $this->line(sprintf(
                    '<fg=white;options=bold>%d. %s</> <fg=gray>(vol: %s, diff: %d - %s)</>',
                    $index + 1,
                    $parent['keyword'],
                    number_format($parent['search_volume'] ?? 0),
                    $parent['difficulty'] ?? 0,
                    $label
                ));
            }

            if (count($clusters) > $displayCount) {
                $this->newLine();
                $this->line(sprintf('<fg=gray>... and %d more clusters</>', count($clusters) - $displayCount));
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
