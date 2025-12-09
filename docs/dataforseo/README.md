# DataForSEO Integration

Handles keyword research via the DataForSEO Labs API.

## API Endpoints Used

### Keyword Suggestions (`/dataforseo_labs/google/keyword_suggestions/live`)
**Purpose:** Topical map generation and expansion

Returns search queries that *include* the seed keyword. These are long-tail variations and questions that contain the exact phrase.

**Example:** For "keto diet" you get:
- keto diet for beginners
- keto diet recipes
- is keto diet safe
- keto diet meal plan

**Usage in app:**
- Called when generating a new topical map (each keyword becomes a cluster)
- Called via "Get Suggestions" to add more long-tail keywords
- Called when expanding a specific cluster

### Keyword Ideas (`/dataforseo_labs/google/keyword_ideas/live`)
**Purpose:** Discover new topic areas

Returns semantically related keywords based on product/service categories. These are *conceptually* related keywords that may not contain the exact seed phrase.

**Accepts:** One or more keywords (comma-separated)

**Example:** For "keto diet, weight loss" you might get:
- low carb recipes
- intermittent fasting
- macro calculator
- carb cycling
- calorie deficit

**Usage in app:**
- Can be selected when generating a new topical map
- Called via "Get Ideas" button to add semantically related clusters

## Data Flow

```
1. User selects source (Suggestions or Ideas) and enters seed keyword(s)
2. Selected API → Creates topical map (each keyword = cluster)
3. User clicks "Get Suggestions" → More long-tail variations added
4. User clicks "Get Ideas" → Semantically related topics added
5. User expands a cluster → Keyword Suggestions API → Adds children
6. (Future) AI reorganizes clusters semantically
```

## Service Class

`App\Services\DataForSeoService` provides:

| Method | Purpose |
|--------|---------|
| `generateTopicalMap($seeds, $source, $limit)` | Generate topical map using selected API |
| `getKeywordSuggestions($keyword, $limit)` | Fetch long-tail variations (single keyword) |
| `getKeywordIdeas($keywords, $limit)` | Fetch semantically related keywords (array) |
| `getDifficultyLabel($difficulty)` | Convert difficulty score to label |

The `$source` parameter accepts `'suggestions'` (default) or `'ideas'`.

## Response Data

Both endpoints return normalized keyword data:

```php
[
    'keyword' => 'the keyword phrase',
    'search_volume' => 1000,       // Monthly searches
    'difficulty' => 45,            // 0-100 score
]
```

## Configuration

Set credentials in `.env`:

```
DATAFORSEO_LOGIN=your_login
DATAFORSEO_PASSWORD=your_password
```
