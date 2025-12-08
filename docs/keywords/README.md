# Keywords Domain

## Purpose

Keywords form the core of the topical map. Each project contains keywords organized in a parent/child hierarchy representing clusters of related terms around subtopics.

## Backend

- **Keyword model**: `app/Models/Keyword.php` — Self-referencing hierarchy with project ownership
- **TopicalMapController**: `app/Http/Controllers/TopicalMapController.php` — Generates and manages topical maps

### Database Schema

```sql
keywords
├── id
├── project_id (foreign key → projects)
├── parent_id (nullable, self-referencing → keywords)
├── keyword (string)
├── search_volume (integer, nullable)
├── difficulty (0-100, nullable)
├── is_seed (boolean)
├── created_at
└── updated_at
```

### Keyword Hierarchy

| Type | parent_id | is_seed | Description |
|------|-----------|---------|-------------|
| Seed | null | true | Original keyword entered by user |
| Cluster Parent | null | false | Top-level cluster keyword |
| Child | {parent_id} | false | Belongs to a cluster |

### API Endpoints

| Method | URI | Description |
|--------|-----|-------------|
| POST | `/api/projects/{id}/generate-map` | Generate topical map from seed |
| GET | `/api/projects/{id}/clusters` | List all clusters |
| GET | `/api/projects/{id}/clusters/{id}` | Get cluster with children |
| POST | `/api/projects/{id}/suggestions` | Add suggestions to map |
| POST | `/api/projects/{id}/clusters/{id}/suggestions` | Add suggestions to cluster |

### Model Relationships

```php
// Keyword
$keyword->project    // BelongsTo Project
$keyword->parent     // BelongsTo Keyword (nullable)
$keyword->children   // HasMany Keyword

// Project
$project->keywords     // All keywords
$project->clusters     // Cluster parents only
$project->seedKeyword  // The seed keyword
```

## Frontend

- **Service**: `resources/js/services/keywords.js`
- **Components**:
  - `ClusterSidebar.vue` — Searchable cluster list with selection
  - `KeywordsTable.vue` — Sortable table of keywords in selected cluster
  - `DifficultyBadge.vue` — Easy/Doable/Hard badge or dot indicator

### Difficulty Labels

| Score | Label | Dot Color |
|-------|-------|-----------|
| 0-29 | Easy | Green |
| 30-59 | Doable | Amber |
| 60-100 | Hard | Red |
