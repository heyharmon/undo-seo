<?php

namespace App\Console\Commands;

use App\Services\DataForSeoService;
use Illuminate\Console\Command;

class DataForSeoTest extends Command
{
    protected $signature = 'dataforseo:test {keyword} {--suggestions : Include keyword suggestions}';

    protected $description = 'Test the DataForSEO service with a seed keyword';

    public function handle(DataForSeoService $service): int
    {
        $keyword = $this->argument('keyword');
        $includeSuggestions = $this->option('suggestions');

        $this->info("Generating topical map for: {$keyword}");
        $this->newLine();

        try {
            $result = $service->generateTopicalMap($keyword, $includeSuggestions);

            $clusters = $result['clusters'];
            $orphans = $result['orphans'];

            $this->info('Clusters: ' . count($clusters));
            $this->info('Orphan keywords: ' . count($orphans));
            $this->newLine();

            // Display clusters
            foreach ($clusters as $index => $cluster) {
                $parent = $cluster['parent'];
                $label = $service->getDifficultyLabel($parent['difficulty']);
                $childCount = count($cluster['children']);

                $this->line(sprintf(
                    '<fg=white;options=bold>%d. %s</> <fg=gray>(vol: %s, diff: %d - %s, children: %d)</>',
                    $index + 1,
                    $parent['keyword'],
                    number_format($parent['search_volume']),
                    $parent['difficulty'],
                    $label,
                    $childCount
                ));

                // Show first 3 children
                foreach (array_slice($cluster['children'], 0, 3) as $child) {
                    $childLabel = $service->getDifficultyLabel($child['difficulty']);
                    $this->line(sprintf(
                        '   └─ %s <fg=gray>(vol: %s, diff: %d - %s)</>',
                        $child['keyword'],
                        number_format($child['search_volume']),
                        $child['difficulty'],
                        $childLabel
                    ));
                }

                if ($childCount > 3) {
                    $this->line(sprintf('   └─ <fg=gray>... and %d more</>', $childCount - 3));
                }

                $this->newLine();
            }

            // Show orphan summary
            if (count($orphans) > 0) {
                $this->line('<fg=yellow>Orphan keywords (first 5):</>');
                foreach (array_slice($orphans, 0, 5) as $orphan) {
                    $this->line(sprintf(
                        '   • %s <fg=gray>(vol: %s)</>',
                        $orphan['keyword'],
                        number_format($orphan['search_volume'])
                    ));
                }
                if (count($orphans) > 5) {
                    $this->line(sprintf('   ... and %d more', count($orphans) - 5));
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
