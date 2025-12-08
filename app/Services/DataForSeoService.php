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

    // Minimum connection strength to consider keywords related (0.0 to 1.0)
    protected float $connectionStrengthThreshold = 0.3;

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
        try {
            $response = Http::withBasicAuth($this->login, $this->password)
                ->timeout(30)
                ->post("{$this->baseUrl}{$endpoint}", $data);

            if ($response->failed()) {
                Log::error('DataForSEO API error', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                throw new DataForSeoException('API request failed: ' . $response->status());
            }

            return $response->json();

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
     * Get related keywords for clustering (primary method).
     * Returns semantically related keywords with volume, difficulty, and connection strength.
     */
    public function getRelatedKeywords(string $keyword, int $limit = 100): array
    {
        $response = $this->request('/dataforseo_labs/google/related_keywords/live', [[
            'keyword' => $keyword,
            'location_code' => 2840, // US
            'language_code' => 'en',
            'limit' => $limit,
            'filters' => [
                ['keyword_data.keyword_info.search_volume', '>', 0]
            ],
        ]]);

        return $this->parseRelatedKeywordsResponse($response);
    }

    /**
     * Get keyword suggestions for long-tail variations (optional).
     * Returns autocomplete-style suggestions with volume and difficulty.
     */
    public function getKeywordSuggestions(string $keyword, int $limit = 50): array
    {
        $response = $this->request('/dataforseo_labs/google/keyword_suggestions/live', [[
            'keyword' => $keyword,
            'location_code' => 2840, // US
            'language_code' => 'en',
            'limit' => $limit,
            'filters' => [
                ['keyword_data.keyword_info.search_volume', '>', 0]
            ],
        ]]);

        return $this->parseKeywordSuggestionsResponse($response);
    }

    /**
     * Generate a complete topical map from a seed keyword.
     * Returns structured data with clusters and their children.
     */
    public function generateTopicalMap(string $seedKeyword, bool $includeSuggestions = false): array
    {
        // Fetch related keywords (always)
        $relatedKeywords = $this->getRelatedKeywords($seedKeyword);

        // Optionally fetch suggestions for more long-tail variations
        $suggestions = [];
        if ($includeSuggestions) {
            $suggestions = $this->getKeywordSuggestions($seedKeyword);
        }

        // Combine and deduplicate all keywords
        $allKeywords = $this->mergeAndDeduplicate($relatedKeywords, $suggestions);

        // Group into clusters based on connection strength
        return $this->clusterKeywords($allKeywords);
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
     * Parse the related keywords API response into a clean array.
     */
    protected function parseRelatedKeywordsResponse(array $response): array
    {
        $keywords = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $results = $task['result'] ?? [];
            foreach ($results as $result) {
                $items = $result['items'] ?? [];
                foreach ($items as $item) {
                    $keywordData = $item['keyword_data'] ?? [];
                    $keywordInfo = $keywordData['keyword_info'] ?? [];

                    if (empty($keywordData['keyword'])) {
                        continue;
                    }

                    $keywords[] = [
                        'keyword' => $keywordData['keyword'],
                        'search_volume' => $keywordInfo['search_volume'] ?? 0,
                        'difficulty' => $keywordInfo['keyword_difficulty'] ?? 0,
                        'connection_strength' => $item['connection_strength'] ?? 0,
                    ];
                }
            }
        }

        return $keywords;
    }

    /**
     * Parse the keyword suggestions API response into a clean array.
     */
    protected function parseKeywordSuggestionsResponse(array $response): array
    {
        $keywords = [];
        $tasks = $response['tasks'] ?? [];

        foreach ($tasks as $task) {
            $results = $task['result'] ?? [];
            foreach ($results as $result) {
                $items = $result['items'] ?? [];
                foreach ($items as $item) {
                    $keywordData = $item['keyword_data'] ?? $item;
                    $keywordInfo = $keywordData['keyword_info'] ?? [];

                    $keyword = $keywordData['keyword'] ?? $item['keyword'] ?? null;
                    if (empty($keyword)) {
                        continue;
                    }

                    $keywords[] = [
                        'keyword' => $keyword,
                        'search_volume' => $keywordInfo['search_volume'] ?? 0,
                        'difficulty' => $keywordInfo['keyword_difficulty'] ?? 0,
                        'connection_strength' => $item['connection_strength'] ?? 0.5, // Default for suggestions
                    ];
                }
            }
        }

        return $keywords;
    }

    /**
     * Merge and deduplicate keywords from multiple sources.
     * When duplicates exist, keep the one with higher search volume.
     */
    protected function mergeAndDeduplicate(array $primary, array $secondary): array
    {
        $merged = [];

        // Index primary keywords by keyword text
        foreach ($primary as $kw) {
            $key = strtolower($kw['keyword']);
            $merged[$key] = $kw;
        }

        // Add secondary keywords, keeping higher volume on conflict
        foreach ($secondary as $kw) {
            $key = strtolower($kw['keyword']);
            if (!isset($merged[$key]) || $kw['search_volume'] > $merged[$key]['search_volume']) {
                $merged[$key] = $kw;
            }
        }

        return array_values($merged);
    }

    /**
     * Group keywords into clusters based on connection strength.
     * Returns clusters with parent/children structure plus orphan keywords.
     */
    protected function clusterKeywords(array $keywords): array
    {
        if (empty($keywords)) {
            return ['clusters' => [], 'orphans' => []];
        }

        // Sort by connection strength (highest first)
        usort($keywords, fn($a, $b) => $b['connection_strength'] <=> $a['connection_strength']);

        $clusters = [];
        $orphans = [];
        $assigned = [];

        // Group keywords with high connection strength together
        foreach ($keywords as $keyword) {
            $key = strtolower($keyword['keyword']);

            // Skip if already assigned to a cluster
            if (isset($assigned[$key])) {
                continue;
            }

            // Check if this keyword has strong enough connection to form/join a cluster
            if ($keyword['connection_strength'] >= $this->connectionStrengthThreshold) {
                // Find or create a cluster for this keyword
                $clusterFound = false;

                foreach ($clusters as &$cluster) {
                    // Check if this keyword relates to the cluster parent
                    if ($this->keywordsAreRelated($keyword, $cluster['parent'])) {
                        $cluster['children'][] = $keyword;
                        $assigned[$key] = true;
                        $clusterFound = true;
                        break;
                    }
                }
                unset($cluster);

                // If no matching cluster, create a new one with this keyword as parent
                if (!$clusterFound) {
                    $clusters[] = [
                        'parent' => $keyword,
                        'children' => [],
                    ];
                    $assigned[$key] = true;
                }
            } else {
                // Low connection strength = orphan
                $orphans[] = $keyword;
            }
        }

        // Sort clusters by parent search volume (highest first)
        usort($clusters, fn($a, $b) => $b['parent']['search_volume'] <=> $a['parent']['search_volume']);

        // Within each cluster, make the highest volume keyword the parent
        foreach ($clusters as &$cluster) {
            $allInCluster = array_merge([$cluster['parent']], $cluster['children']);
            usort($allInCluster, fn($a, $b) => $b['search_volume'] <=> $a['search_volume']);

            $cluster['parent'] = $allInCluster[0];
            $cluster['children'] = array_slice($allInCluster, 1);

            // Sort children by search volume
            usort($cluster['children'], fn($a, $b) => $b['search_volume'] <=> $a['search_volume']);
        }
        unset($cluster);

        // Re-sort clusters by new parent volume
        usort($clusters, fn($a, $b) => $b['parent']['search_volume'] <=> $a['parent']['search_volume']);

        return [
            'clusters' => $clusters,
            'orphans' => $orphans,
        ];
    }

    /**
     * Check if two keywords are related enough to be in the same cluster.
     * Uses simple word overlap as a secondary check alongside connection strength.
     */
    protected function keywordsAreRelated(array $keyword1, array $keyword2): bool
    {
        // Both must have decent connection strength
        if ($keyword1['connection_strength'] < $this->connectionStrengthThreshold) {
            return false;
        }

        // Check for word overlap (at least one shared word)
        $words1 = explode(' ', strtolower($keyword1['keyword']));
        $words2 = explode(' ', strtolower($keyword2['keyword']));

        $commonWords = array_intersect($words1, $words2);

        // Filter out common stop words
        $stopWords = ['a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can'];
        $meaningfulCommon = array_diff($commonWords, $stopWords);

        return count($meaningfulCommon) > 0;
    }
}
