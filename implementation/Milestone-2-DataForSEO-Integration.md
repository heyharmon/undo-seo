__MILESTONE 2 OF 4__

DataForSEO Integration

*Estimated effort: 2-3 days*

# Overview

Build the backend service layer that communicates with the DataForSEO API. This service handles fetching related keywords, keyword suggestions, search volume, and difficulty metrics. By the end of this milestone, you have a complete, reusable service class that Milestone 3 will use to generate topical maps.

# Prerequisites

- Milestone 1 complete (projects working)
- DataForSEO account with API credentials (login and password)

# Deliverables

1. DataForSEO service class with all keyword fetching methods
2. Related keywords method (primary, always used)
3. Keyword suggestions method (optional, user-enabled)
4. Topical map generation method with clustering logic
5. Difficulty labels (Easy/Doable/Hard)
6. Error handling and logging

# Technical Requirements

## 1. Environment Configuration

Add to .env:

```
DATAFORSEO_LOGIN=your_login
DATAFORSEO_PASSWORD=your_password
```

Add to config/services.php:

```php
'dataforseo' => [
    'login' => env('DATAFORSEO_LOGIN'),
    'password' => env('DATAFORSEO_PASSWORD'),
    'base_url' => 'https://api.dataforseo.com/v3',
],
```

## 2. DataForSEO Service Class

Create `App\Services\DataForSeoService` with the following responsibilities:

### API Authentication

- DataForSEO uses HTTP Basic Auth with login:password
- Base URL: https://api.dataforseo.com/v3
- Use Laravel HTTP client (Http facade)

```php
use Illuminate\Support\Facades\Http;

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

    protected function request(string $endpoint, array $data): array
    {
        $response = Http::withBasicAuth($this->login, $this->password)
            ->timeout(30)
            ->post("{$this->baseUrl}{$endpoint}", $data);

        if ($response->failed()) {
            // Log and handle error
            throw new \Exception('DataForSEO API request failed');
        }

        return $response->json();
    }
}
```

## 3. API Endpoints

### Primary: Related Keywords (always used)

Use the DataForSEO Labs → Google → Related Keywords endpoint.

- **Endpoint:** `POST /v3/dataforseo_labs/google/related_keywords/live`
- **Purpose:** Get semantically related keywords with connection strength data
- **Returns:** Related keywords with search volume, difficulty, and relationship data for clustering

**Request format:**
```json
[{
    "keyword": "keto diet",
    "location_code": 2840,
    "language_code": "en",
    "limit": 100
}]
```

**Key response fields:**
- `keyword` - The keyword text
- `search_volume` - Monthly search volume
- `keyword_difficulty` - Difficulty score 0-100
- `connection_strength` - How strongly related to seed (useful for clustering)

### Secondary: Keyword Suggestions (optional, user-enabled)

Use the DataForSEO Labs → Google → Keyword Suggestions endpoint.

- **Endpoint:** `POST /v3/dataforseo_labs/google/keyword_suggestions/live`
- **Purpose:** Get autocomplete-style suggestions for long-tail variations
- **Use:** Supplements related keywords to catch variations the primary endpoint misses
- **Not called by default** — user must explicitly enable this option

**Request format:**
```json
[{
    "keyword": "keto diet",
    "location_code": 2840,
    "language_code": "en",
    "limit": 50
}]
```

### Keyword Difficulty (if needed separately)

If the related keywords response doesn't include difficulty scores, use:

- **Endpoint:** `POST /v3/keywords_data/google/keyword_difficulty/live`
- **Input:** Array of keywords (batch up to 1000)
- **Returns:** Difficulty score 0-100 per keyword

**Note:** Explore the DataForSEO docs to find the most efficient approach. Ideally, use endpoints that return volume and difficulty together to reduce API costs.

## 4. Service Methods

### getRelatedKeywords

Fetch semantically related keywords for a seed keyword.

```php
/**
 * Get related keywords for clustering (primary method)
 *
 * @param string $keyword The seed keyword
 * @param int $limit Maximum keywords to return
 * @return array Array of keyword data with volume, difficulty, connection_strength
 */
public function getRelatedKeywords(string $keyword, int $limit = 100): array
{
    $response = $this->request('/v3/dataforseo_labs/google/related_keywords/live', [[
        'keyword' => $keyword,
        'location_code' => 2840, // US
        'language_code' => 'en',
        'limit' => $limit,
    ]]);

    // Parse and return keywords from response
    // Extract: keyword, search_volume, keyword_difficulty, connection_strength
}
```

### getKeywordSuggestions

Fetch autocomplete-style keyword suggestions.

```php
/**
 * Get keyword suggestions for long-tail variations (optional)
 *
 * @param string $keyword The seed keyword
 * @param int $limit Maximum suggestions to return
 * @return array Array of keyword suggestions with volume and difficulty
 */
public function getKeywordSuggestions(string $keyword, int $limit = 50): array
{
    $response = $this->request('/v3/dataforseo_labs/google/keyword_suggestions/live', [[
        'keyword' => $keyword,
        'location_code' => 2840,
        'language_code' => 'en',
        'limit' => $limit,
    ]]);

    // Parse and return suggestions from response
}
```

### generateTopicalMap

Generate a complete topical map with clustering.

```php
/**
 * Generate a topical map from a seed keyword
 *
 * @param string $seedKeyword The broad topic to analyze
 * @param bool $includeSuggestions Whether to also fetch keyword suggestions
 * @return array Structured data with clusters and their children
 */
public function generateTopicalMap(string $seedKeyword, bool $includeSuggestions = false): array
{
    // 1. Fetch related keywords (always)
    $relatedKeywords = $this->getRelatedKeywords($seedKeyword);

    // 2. Optionally fetch suggestions
    $suggestions = [];
    if ($includeSuggestions) {
        $suggestions = $this->getKeywordSuggestions($seedKeyword);
    }

    // 3. Combine and deduplicate
    $allKeywords = $this->mergeAndDeduplicate($relatedKeywords, $suggestions);

    // 4. Group into clusters
    $clusters = $this->clusterKeywords($allKeywords);

    return $clusters;
}
```

## 5. Clustering Logic

The clustering algorithm groups keywords into logical clusters around parent keywords.

### Clustering Approach

```php
/**
 * Group keywords into clusters based on connection strength and patterns
 *
 * @param array $keywords All keywords to cluster
 * @return array Structured clusters with parent and children
 */
protected function clusterKeywords(array $keywords): array
{
    // Strategy options (implement one or combine):
    
    // Option A: Use connection_strength from DataForSEO
    // - Keywords with high connection strength to each other form a cluster
    // - The keyword with highest search volume becomes cluster parent
    
    // Option B: Word pattern grouping
    // - Group keywords sharing common word patterns
    // - e.g., "keto diet types", "types of keto", "keto diet variations"
    
    // Option C: Semantic similarity
    // - Use word overlap or simple NLP to group related terms
}
```

### Cluster Structure

Each cluster should have:
- **Parent keyword:** The primary keyword representing the cluster theme (highest volume in group)
- **Children:** Related keywords that belong to this subtopic
- **Metrics:** Volume and difficulty for the parent

**Output format:**
```php
[
    [
        'parent' => [
            'keyword' => 'keto diet types',
            'search_volume' => 2400,
            'difficulty' => 35,
        ],
        'children' => [
            ['keyword' => 'lazy keto diet', 'search_volume' => 1200, 'difficulty' => 28],
            ['keyword' => 'strict keto diet', 'search_volume' => 800, 'difficulty' => 32],
        ]
    ],
    // ... more clusters
]
```

## 6. Difficulty Labels

Add difficulty label logic (can be in service or model):

| Score Range | Label | Color Suggestion |
|-------------|-------|------------------|
| 0 – 29 | Easy | Green |
| 30 – 59 | Doable | Yellow/Amber |
| 60 – 100 | Hard | Red |

```php
/**
 * Get difficulty label for a score
 */
public function getDifficultyLabel(int $difficulty): string
{
    if ($difficulty < 30) return 'Easy';
    if ($difficulty < 60) return 'Doable';
    return 'Hard';
}
```

## 7. Error Handling

- Log all API errors with context (endpoint, request payload, response)
- Return user-friendly error messages ("Unable to fetch keywords. Please try again.")
- Handle rate limits gracefully (DataForSEO has per-minute limits)
- Handle network timeouts (set reasonable timeout, e.g., 30 seconds)

```php
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

    } catch (\Exception $e) {
        Log::error('DataForSEO request exception', [
            'endpoint' => $endpoint,
            'message' => $e->getMessage(),
        ]);
        throw $e;
    }
}
```

# Acceptance Criteria

The milestone is complete when:

1. DataForSeoService can fetch related keywords for a seed keyword
2. DataForSeoService can fetch keyword suggestions for a seed keyword
3. Each returned keyword includes: keyword text, search_volume, difficulty score
4. `generateTopicalMap` returns clustered keywords with parent/children structure
5. Difficulty labels return "Easy", "Doable", or "Hard" based on thresholds
6. API errors are logged and handled gracefully
7. Service is testable via a simple artisan command

# Testing Tips

- Create a simple artisan command to test the service directly:
  ```
  php artisan dataforseo:test "keto diet"
  ```
- Test with a simple seed like "coffee" or "running shoes"
- Test with invalid API credentials to verify error handling
- Check logs to confirm API responses are being captured
- Verify clustering produces reasonable groupings

# Notes

- DataForSEO charges per task/request — be mindful during testing
- Consider adding a config value for max keywords returned per expansion
- Milestone 3 will use this service to generate and store topical maps
- If DataForSEO provides both volume and difficulty in one call, use that to reduce costs
- The clustering algorithm can be refined over time — start simple
- Consider caching API responses during development to reduce costs
