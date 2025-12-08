__MILESTONE 3 OF 4__

Topical Map Generation

*Estimated effort: 2-3 days*

# Overview

Build the core topical map generation feature. Users enter a seed keyword and the system generates a structured topical map—clusters of related keywords grouped around parent keywords that represent distinct subtopics. By the end of this milestone, users can see the full landscape of content opportunities within a topic.

# Prerequisites

- Milestone 2 complete (DataForSEO service class exists)
- DataForSEO account with API credentials

# Deliverables

1. Updated data model with keyword hierarchy (parent/child relationships)
2. Topical map generation using DataForSEO endpoints
3. API endpoints for generating and viewing topical maps
4. Frontend UI for seed input, cluster display, and cluster expansion

# Core Concepts

## What is a Topical Map?

A topical map is a structured collection of keyword clusters that represent the content landscape for a topic. Each cluster groups related keywords around a parent keyword (the cluster's central theme).

## What is a Cluster?

A cluster is a group of related keywords organized around a parent keyword. The parent keyword serves as the primary keyword or central theme. Child keywords are variations, subtopics, or related queries.

Example for seed "keto diet":
- **Cluster: "keto diet types"** (parent)
  - "lazy keto diet" (child)
  - "strict keto diet" (child)
  - "cyclical keto diet" (child)
- **Cluster: "keto diet foods"** (parent)
  - "keto diet vegetables" (child)
  - "keto diet fruits" (child)
  - "keto diet snacks" (child)

# Technical Requirements

## 1. Data Model Updates

### Keywords Table Migration

Create or update the keywords table with parent/child support:

```
keywords
├── id
├── project_id (foreign key)
├── parent_id (nullable, self-referencing foreign key)
├── keyword (string)
├── search_volume (integer, nullable)
├── difficulty (integer, nullable)
├── is_seed (boolean, default false)
├── created_at
└── updated_at
```

**Key points:**
- Keywords belong directly to a project (no global cache)
- `parent_id = null` indicates a cluster parent (or the seed keyword)
- `parent_id = {keyword_id}` indicates a child keyword within a cluster
- `is_seed = true` marks the original seed keyword entered by the user

### Keyword Model

Update the Keyword model with relationships:

```php
// Parent relationship
public function parent()
{
    return $this->belongsTo(Keyword::class, 'parent_id');
}

// Children relationship (cluster members)
public function children()
{
    return $this->hasMany(Keyword::class, 'parent_id');
}

// Project relationship
public function project()
{
    return $this->belongsTo(Project::class);
}

// Check if this is a cluster parent
public function isClusterParent(): bool
{
    return $this->parent_id === null && !$this->is_seed;
}

// Difficulty label accessor
public function getDifficultyLabelAttribute(): ?string
{
    if ($this->difficulty === null) return null;
    if ($this->difficulty < 30) return 'Easy';
    if ($this->difficulty < 60) return 'Doable';
    return 'Hard';
}
```

### Project Model

Add keywords relationship:

```php
public function keywords()
{
    return $this->hasMany(Keyword::class);
}

// Get only cluster parents (top-level keywords, excluding seed)
public function clusters()
{
    return $this->hasMany(Keyword::class)
        ->whereNull('parent_id')
        ->where('is_seed', false);
}

// Get the seed keyword
public function seedKeyword()
{
    return $this->hasOne(Keyword::class)->where('is_seed', true);
}
```

## 2. DataForSEO Integration

### Endpoints to Use

**Primary: Related Keywords**
- Endpoint: `POST /v3/dataforseo_labs/google/related_keywords/live`
- Purpose: Get semantically related keywords with connection strength data
- Returns: Related keywords with search metrics and relationship data for grouping

**Secondary: Keyword Suggestions**
- Endpoint: `POST /v3/dataforseo_labs/google/keyword_suggestions/live`
- Purpose: Get autocomplete-style suggestions for long-tail variations
- Use: Supplement related keywords to catch variations the primary endpoint misses

### DataForSEO Service Methods

Add methods to the existing DataForSeoService:

```php
/**
 * Get related keywords for clustering
 */
public function getRelatedKeywords(string $keyword, int $limit = 100): array

/**
 * Get keyword suggestions for long-tail variations
 */
public function getKeywordSuggestions(string $keyword, int $limit = 50): array

/**
 * Generate a complete topical map from a seed keyword
 * Combines related keywords and suggestions, then groups into clusters
 */
public function generateTopicalMap(string $seedKeyword): array
```

### Clustering Logic

The `generateTopicalMap` method should:

1. Call the Related Keywords endpoint with the seed
2. Call the Keyword Suggestions endpoint with the seed
3. Combine and deduplicate results
4. Group keywords into clusters based on DataForSEO's connection data
5. Return structured data with clusters and their children

**Clustering approach:**
- Use DataForSEO's `connection_strength` or semantic grouping data from the related keywords response
- Keywords with high connection strength to each other form a cluster
- The keyword with the highest search volume in each group becomes the cluster parent
- Alternatively, use common word patterns to group (e.g., all keywords containing "keto diet types" group together)

## 3. API Endpoints

| Method | URI | Purpose |
|--------|-----|---------|
| POST | /api/projects/{id}/generate-map | Generate topical map from seed keyword |
| GET | /api/projects/{id}/clusters | Get all clusters for a project |
| GET | /api/projects/{id}/clusters/{clusterId} | Get cluster with children |

### POST /api/projects/{id}/generate-map

**Request body:**
```json
{
  "seed_keyword": "keto diet"
}
```

**Logic:**
1. Verify user owns the project
2. Clear any existing keywords for the project (or prompt to confirm overwrite)
3. Create the seed keyword record with `is_seed = true`
4. Call DataForSEO to generate topical map
5. Create cluster parent keywords with `parent_id = null`
6. Create child keywords with `parent_id` referencing their cluster parent
7. Return the generated clusters

**Response:**
```json
{
  "seed": "keto diet",
  "cluster_count": 15,
  "total_keywords": 150,
  "clusters": [
    {
      "id": 1,
      "keyword": "keto diet types",
      "search_volume": 2400,
      "difficulty": 35,
      "difficulty_label": "Doable",
      "children_count": 8
    }
  ]
}
```

### GET /api/projects/{id}/clusters/{clusterId}

**Response:**
```json
{
  "id": 1,
  "keyword": "keto diet types",
  "search_volume": 2400,
  "difficulty": 35,
  "difficulty_label": "Doable",
  "children": [
    {
      "id": 2,
      "keyword": "lazy keto diet",
      "search_volume": 1200,
      "difficulty": 28,
      "difficulty_label": "Easy"
    }
  ]
}
```

## 4. Frontend Components

### Project Show Page (Projects/Show.vue)

The project detail page becomes the topical map workspace with two states:

**State 1: No topical map yet**
- Show seed keyword input
- "Generate Topical Map" button
- Brief explanation of what will happen

**State 2: Topical map exists**
- Display seed keyword at top
- Show cluster count and total keyword count
- List all clusters as expandable cards/rows
- Option to regenerate (with confirmation)

### Seed Input Component

- Text input: "Enter a seed keyword (e.g., keto diet, dog training)"
- "Generate Topical Map" button
- Loading state while generating (this may take 10-30 seconds)
- Progress indicator or message: "Analyzing keywords..."

### Cluster List Component

Display clusters in a list or grid:

- Each cluster shows:
  - Parent keyword text (the cluster name)
  - Search volume (formatted with commas)
  - Difficulty badge (Easy/Doable/Hard with colors)
  - Child keyword count
  - Expand/collapse toggle

### Cluster Detail Component

When a cluster is expanded:

- Show all child keywords in a table or list
- Each child shows: keyword, search volume, difficulty badge
- Sorting by volume or difficulty (client-side)
- Filtering by difficulty level

### Difficulty Badges

Same color scheme as before:
- **Easy (0-29):** Green (bg-green-100 text-green-800)
- **Doable (30-59):** Yellow (bg-yellow-100 text-yellow-800)
- **Hard (60-100):** Red (bg-red-100 text-red-800)

## 5. Loading and Empty States

**Generating state:**
- Full-page or section loading state
- "Generating your topical map..."
- Consider showing progress if possible

**Empty state (no map yet):**
- Friendly prompt to enter a seed keyword
- Example seeds to inspire the user

**Error state:**
- Clear error message
- Retry button

# Acceptance Criteria

The milestone is complete when:

1. User can enter a seed keyword on the project page
2. Clicking "Generate" creates a topical map with multiple clusters
3. Each cluster has a parent keyword and child keywords
4. Clusters display with keyword count, volume, and difficulty
5. User can expand a cluster to see all child keywords
6. Child keywords show volume and difficulty badges
7. User can sort/filter within an expanded cluster
8. Loading state shows during generation
9. Existing keywords are cleared when regenerating (with confirmation)
10. Empty state displays before first generation

# Notes

- Generation may take 10-30 seconds due to API calls—set appropriate timeouts
- Consider queuing the generation job for better UX (return immediately, poll for completion)
- The quality of clusters depends on DataForSEO's data—AI enhancement comes in Milestone 4
- Start with a reasonable limit (e.g., 100-150 total keywords) to keep API costs manageable
- Store raw DataForSEO response data if needed for debugging or future AI processing
