__MILESTONE 3 OF 4__

Topical Map Generation

*Estimated effort: 2-3 days*

# Overview

Build the core topical map generation feature. Users enter a seed keyword and the system generates a structured topical map—clusters of related keywords grouped around parent keywords that represent distinct subtopics. By the end of this milestone, users can see the full landscape of content opportunities within a topic.

# Prerequisites

- Milestone 2 complete (DataForSEO service class exists with all methods)
- DataForSEO account with API credentials

# Deliverables

1. Keywords table migration with parent/child hierarchy
2. Keyword and Project model relationships
3. API endpoints for generating, viewing, and expanding topical maps
4. Frontend UI for seed input, cluster display, cluster expansion, and suggestion fetching

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

## 1. Data Model

### Keywords Table Migration

Create the keywords table with parent/child support:

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

```php
class Keyword extends Model
{
    protected $fillable = [
        'project_id',
        'parent_id',
        'keyword',
        'search_volume',
        'difficulty',
        'is_seed',
    ];

    protected $casts = [
        'is_seed' => 'boolean',
    ];

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

    // Difficulty label accessor (thresholds defined in Milestone 2)
    public function getDifficultyLabelAttribute(): ?string
    {
        if ($this->difficulty === null) return null;
        if ($this->difficulty < 30) return 'Easy';
        if ($this->difficulty < 60) return 'Doable';
        return 'Hard';
    }
}
```

### Project Model Updates

Add keywords relationships to the Project model:

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

## 2. API Endpoints

| Method | URI | Purpose |
|--------|-----|---------|
| POST | /api/projects/{id}/generate-map | Generate topical map from seed keyword |
| GET | /api/projects/{id}/clusters | Get all clusters for a project |
| GET | /api/projects/{id}/clusters/{clusterId} | Get cluster with children |
| POST | /api/projects/{id}/suggestions | Fetch suggestions for the entire topical map |
| POST | /api/projects/{id}/clusters/{clusterId}/suggestions | Fetch suggestions for a specific cluster |

### POST /api/projects/{id}/generate-map

Generate a new topical map for a project.

**Request body:**
```json
{
  "seed_keyword": "keto diet",
  "include_suggestions": false
}
```

- `seed_keyword` (required): The broad topic to build the topical map around
- `include_suggestions` (optional, default: false): Whether to also fetch keyword suggestions during generation

**Logic:**
1. Verify user owns the project
2. Clear any existing keywords for the project (or prompt to confirm overwrite)
3. Create the seed keyword record with `is_seed = true`
4. Call `DataForSeoService->generateTopicalMap()` with the seed and options
5. Create cluster parent keywords with `parent_id = null`
6. Create child keywords with `parent_id` referencing their cluster parent
7. Return the generated clusters

**Response:**
```json
{
  "seed": "keto diet",
  "cluster_count": 15,
  "total_keywords": 150,
  "included_suggestions": false,
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

### GET /api/projects/{id}/clusters

Get all clusters for a project.

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

Get a single cluster with all its children.

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

### POST /api/projects/{id}/suggestions

Fetch keyword suggestions for the seed keyword and merge into the existing topical map.

**Logic:**
1. Verify user owns the project and topical map exists
2. Get the seed keyword for the project
3. Call `DataForSeoService->getKeywordSuggestions()` with the seed
4. Deduplicate against existing keywords
5. Add new keywords to appropriate clusters (or create new clusters)
6. Return count of keywords added

**Response:**
```json
{
  "keywords_added": 23,
  "new_clusters": 2,
  "message": "Added 23 keyword suggestions to your topical map"
}
```

### POST /api/projects/{id}/clusters/{clusterId}/suggestions

Fetch keyword suggestions for a specific cluster parent and add as children.

**Logic:**
1. Verify user owns the project and cluster exists
2. Call `DataForSeoService->getKeywordSuggestions()` with the cluster's parent keyword
3. Deduplicate against existing children in this cluster
4. Add new keywords as children of this cluster
5. Return count of keywords added

**Response:**
```json
{
  "cluster_id": 1,
  "cluster_keyword": "keto diet types",
  "keywords_added": 8,
  "message": "Added 8 suggestions to the 'keto diet types' cluster"
}
```

## 3. Controller Implementation

Create `TopicalMapController` (or add to `ProjectController`):

```php
class TopicalMapController extends Controller
{
    public function __construct(
        protected DataForSeoService $dataForSeo
    ) {}

    public function generate(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'seed_keyword' => 'required|string|max:255',
            'include_suggestions' => 'boolean',
        ]);

        // Clear existing keywords
        $project->keywords()->delete();

        // Create seed keyword
        $project->keywords()->create([
            'keyword' => $validated['seed_keyword'],
            'is_seed' => true,
        ]);

        // Generate topical map via service
        $clusters = $this->dataForSeo->generateTopicalMap(
            $validated['seed_keyword'],
            $validated['include_suggestions'] ?? false
        );

        // Store clusters and children
        foreach ($clusters as $cluster) {
            $parent = $project->keywords()->create([
                'keyword' => $cluster['parent']['keyword'],
                'search_volume' => $cluster['parent']['search_volume'],
                'difficulty' => $cluster['parent']['difficulty'],
            ]);

            foreach ($cluster['children'] as $child) {
                $project->keywords()->create([
                    'parent_id' => $parent->id,
                    'keyword' => $child['keyword'],
                    'search_volume' => $child['search_volume'],
                    'difficulty' => $child['difficulty'],
                ]);
            }
        }

        return response()->json([
            'seed' => $validated['seed_keyword'],
            'cluster_count' => count($clusters),
            'total_keywords' => $project->keywords()->count(),
            // ...
        ]);
    }
}
```

## 4. Frontend Components

### Project Show Page (Projects/Show.vue)

The project detail page becomes the topical map workspace with two states:

**State 1: No topical map yet**
- Show seed keyword input
- Toggle option: "Include keyword suggestions" (off by default)
- "Generate Topical Map" button
- Brief explanation of what will happen

**State 2: Topical map exists**
- Display seed keyword at top
- Show cluster count and total keyword count
- "Get Suggestions" button (fetches suggestions for the entire map)
- List all clusters as expandable cards/rows
- Option to regenerate (with confirmation)

### Seed Input Component

- Text input: "Enter a seed keyword (e.g., keto diet, dog training)"
- Toggle/checkbox: "Include keyword suggestions" with helper text explaining this adds long-tail variations but takes longer
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
  - "Get Suggestions" button (fetches suggestions for this cluster only)

### Cluster Detail Component

When a cluster is expanded:

- Show all child keywords in a table or list
- Each child shows: keyword, search volume, difficulty badge
- Sorting by volume or difficulty (client-side)
- Filtering by difficulty level

### Suggestions Feedback

When fetching suggestions (top-level or cluster-level):
- Show loading state on the button
- After completion, show success message with count of keywords added
- Refresh the relevant cluster(s) to show new keywords

### Difficulty Badges

Color scheme (thresholds defined in Milestone 2):
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
2. User can toggle "Include keyword suggestions" option (off by default)
3. Clicking "Generate" creates a topical map with multiple clusters
4. When toggle is off, only Related Keywords endpoint is used
5. When toggle is on, both Related Keywords and Keyword Suggestions endpoints are used
6. Each cluster has a parent keyword and child keywords
7. Clusters display with keyword count, volume, and difficulty
8. User can expand a cluster to see all child keywords
9. Child keywords show volume and difficulty badges
10. User can sort/filter within an expanded cluster
11. User can click "Get Suggestions" at the top level to add suggestions to the entire map
12. User can click "Get Suggestions" on a cluster to add suggestions to that cluster only
13. Loading states show during generation and suggestion fetching
14. Existing keywords are cleared when regenerating (with confirmation)
15. Empty state displays before first generation

# Notes

- Generation may take 10-30 seconds due to API calls—set appropriate timeouts
- Consider queuing the generation job for better UX (return immediately, poll for completion)
- The quality of clusters depends on DataForSEO's data—AI enhancement comes in Milestone 4
- Start with a reasonable limit (e.g., 100-150 total keywords) to keep API costs manageable
- The suggestions toggle defaults to off to minimize API costs and generation time
- Fetching suggestions post-generation allows users to incrementally expand their map
- When adding suggestions to an existing map, deduplicate carefully to avoid duplicate keywords
