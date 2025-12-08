# DataForSEO Domain

## Purpose

Provides keyword research data via the DataForSEO API. This service fetches related keywords, keyword suggestions, and generates clustered topical maps for SEO analysis.

## Backend

- **DataForSeoService**: `app/Services/DataForSeoService.php` — Core service for all API interactions
- **DataForSeoException**: `app/Exceptions/DataForSeoException.php` — Custom exception for API errors

### Configuration

Environment variables (add to `.env`):
```
DATAFORSEO_LOGIN=your_login
DATAFORSEO_PASSWORD=your_password
```

Config: `config/services.php` under `dataforseo` key.

### Service Methods

| Method | Purpose |
|--------|---------|
| `getRelatedKeywords($keyword, $limit)` | Fetch semantically related keywords |
| `getKeywordSuggestions($keyword, $limit)` | Fetch autocomplete-style suggestions |
| `generateTopicalMap($seed, $includeSuggestions)` | Generate clustered topical map |
| `getDifficultyLabel($score)` | Convert difficulty score to Easy/Doable/Hard |

### Clustering Logic

Keywords are grouped using DataForSEO's `connection_strength` score:
1. Keywords above threshold (0.3) are clustered together
2. Within each cluster, highest volume keyword becomes parent
3. Keywords below threshold become "orphans"

### Difficulty Thresholds

| Score | Label |
|-------|-------|
| 0-29 | Easy |
| 30-59 | Doable |
| 60-100 | Hard |

## Testing

Run the test command:
```bash
php artisan dataforseo:test "keto diet"
php artisan dataforseo:test "running shoes" --suggestions
```

## API Endpoints Used

- `POST /v3/dataforseo_labs/google/related_keywords/live`
- `POST /v3/dataforseo_labs/google/keyword_suggestions/live`

All requests filter out keywords with 0 search volume at the API level.
