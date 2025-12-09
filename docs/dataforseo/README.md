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

**Example:** For "keto diet" you might get:
- low carb recipes
- intermittent fasting
- macro calculator
- carb cycling

**Usage in app:** Called via "Get Ideas" button to add semantically related clusters after the initial map is generated.

## Data Flow

```
1. User enters seed keyword → Keyword Suggestions API → Creates topical map
2. User clicks "Get Suggestions" → More long-tail variations added
3. User clicks "Get Ideas" → Semantically related topics added
4. User expands a cluster → Keyword Suggestions API → Adds children
5. (Future) AI reorganizes clusters semantically
```

## Service Class

`App\Services\DataForSeoService` provides:

| Method | Purpose |
|--------|---------|
| `generateTopicalMap($seedKeyword, $limit)` | Generate topical map using Suggestions API |
| `getKeywordSuggestions($keyword, $limit)` | Fetch long-tail variations |
| `getKeywordIdeas($keyword, $limit)` | Fetch semantically related keywords |
| `getDifficultyLabel($difficulty)` | Convert difficulty score to label |

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
