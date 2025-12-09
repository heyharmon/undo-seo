<?php

namespace App\Services;

use App\Exceptions\DataForSeoException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataForSeoService
{
    protected string $baseUrl;
    protected string $login;
    protected string $password;

    public function __construct()
    {
        $this->baseUrl = config('services.dataforseo.base_url');
        $this->login = config('services.dataforseo.login');
        $this->password = config('services.dataforseo.password');
    }

    /**
     * Make an authenticated request to the DataForSEO API.
     */
    protected function request(string $endpoint, array $data): array
    {
        Log::info('DataForSEO API request', [
            'endpoint' => $endpoint,
            'request_data' => $data,
        ]);

        try {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->timeout(60)
                ->post("{$this->baseUrl}{$endpoint}", $data);

            $json = $response->json();

            if ($response->failed()) {
                Log::error('DataForSEO API error', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $json,
                ]);
                throw new DataForSeoException('API request failed: ' . $response->status());
            }

            Log::info('DataForSEO API response', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'tasks_count' => count($json['tasks'] ?? []),
                'cost' => $json['cost'] ?? null,
            ]);

            return $json;

        } catch (DataForSeoException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('DataForSEO request exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);
            throw new DataForSeoException('Unable to fetch keywords. Please try again.');
        }
    }

    /**
     * Get keyword ideas - semantically related keywords based on category/relevance.
     * Used for initial topical map generation to get broad semantic coverage.
     */
    public function getKeywordIdeas(string $keyword, int $limit = 100): array
    {
        $response = $this->request('/dataforseo_labs/google/keyword_ideas/live', [[
            'keywords' => [$keyword],
            'location_code' => 2840, // US
            'language_code' => 'en',
            'limit' => $limit,
            'order_by' => ['keyword_info.search_volume,desc'],
        ]]);

        return $this->parseKeywordIdeasResponse($response);
    }

    /**
     * Get keyword suggestions - variations that contain the seed keyword.
     * Used for cluster expansion to get long-tail variations of a specific keyword.
     */
    public function getKeywordSuggestions(string $keyword, int $limit = 50): array
    {
        $response = $this->request('/dataforseo_labs/google/keyword_suggestions/live', [[
            'keyword' => $keyword,
            'location_code' => 2840, // US
            'language_code' => 'en',
            'limit' => $limit,
        ]]);

        return $this->parseKeywordSuggestionsResponse($response);
    }

    /**
     * Generate a complete topical map from a seed keyword.
     * Each keyword becomes its own cluster (flat structure).
     *
     * @param string $seedKeyword The seed keyword
     * @param string $source Which API to use: 'ideas' or 'suggestions'
     * @param int $limit Number of keywords to fetch
     */
    public function generateTopicalMap(string $seedKeyword, string $source = 'suggestions', int $limit = 100): array
    {
        Log::info('=== TOPICAL MAP GENERATION START ===', [
            'seed_keyword' => $seedKeyword,
            'source' => $source,
            'limit' => $limit,
        ]);

        // Fetch keywords from selected source
        $keywords = $source === 'ideas'
            ? $this->getKeywordIdeas($seedKeyword, $limit)
            : $this->getKeywordSuggestions($seedKeyword, $limit);

        Log::info('Keywords fetched', ['source' => $source, 'count' => count($keywords)]);

        // Each keyword becomes its own cluster (no children yet)
        $clusters = [];
        foreach ($keywords as $keyword) {
            $clusters[] = [
                'parent' => $keyword,
                'children' => [],
            ];
        }

        // Sort clusters by search volume (highest first)
        usort($clusters, fn($a, $b) => ($b['parent']['search_volume'] ?? 0) <=> ($a['parent']['search_volume'] ?? 0));

        Log::info('=== TOPICAL MAP GENERATION END ===', [
            'clusters' => count($clusters),
        ]);

        return [
            'clusters' => $clusters,
            'orphans' => [],
        ];
    }

    /**
     * Get difficulty label for a score.
     */
    public function getDifficultyLabel(int $difficulty): string
    {
        if ($difficulty < 30) return 'Easy';
        if ($difficulty < 60) return 'Doable';
        return 'Hard';
    }

    /**
     * Parse the Keyword Ideas API response.
     */
    protected function parseKeywordIdeasResponse(array $response): array
    {
        $keywords = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $results = $task['result'] ?? [];
            foreach ($results as $result) {
                $items = $result['items'] ?? [];

                Log::info('DataForSEO keyword ideas - raw items count', [
                    'total_items' => count($items),
                    'total_count' => $result['total_count'] ?? 0,
                ]);

                foreach ($items as $item) {
                    $keyword = $item['keyword'] ?? null;
                    $keywordInfo = $item['keyword_info'] ?? [];
                    $keywordProperties = $item['keyword_properties'] ?? [];

                    if (empty($keyword)) {
                        continue;
                    }

                    $keywords[] = [
                        'keyword' => $keyword,
                        'search_volume' => $keywordInfo['search_volume'] ?? 0,
                        'difficulty' => $keywordProperties['keyword_difficulty'] ?? 0,
                    ];
                }
            }
        }

        Log::info('DataForSEO keyword ideas - parsed', [
            'total_keywords' => count($keywords),
            'sample_keywords' => collect($keywords)->take(10)->pluck('keyword')->toArray(),
        ]);

        return $keywords;
    }

    /**
     * Parse the Keyword Suggestions API response.
     */
    protected function parseKeywordSuggestionsResponse(array $response): array
    {
        $keywords = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $results = $task['result'] ?? [];
            foreach ($results as $result) {
                $items = $result['items'] ?? [];

                Log::info('DataForSEO suggestions - raw items count', [
                    'total_items' => count($items),
                    'seed_keyword' => $result['seed_keyword'] ?? 'unknown',
                ]);

                foreach ($items as $item) {
                    $keyword = $item['keyword'] ?? null;
                    $keywordInfo = $item['keyword_info'] ?? [];
                    $keywordProperties = $item['keyword_properties'] ?? [];

                    if (empty($keyword)) {
                        continue;
                    }

                    $keywords[] = [
                        'keyword' => $keyword,
                        'search_volume' => $keywordInfo['search_volume'] ?? 0,
                        'difficulty' => $keywordProperties['keyword_difficulty'] ?? 0,
                    ];
                }
            }
        }

        Log::info('DataForSEO suggestions - parsed', [
            'total_keywords' => count($keywords),
            'sample_keywords' => collect($keywords)->take(10)->pluck('keyword')->toArray(),
        ]);

        return $keywords;
    }
}
