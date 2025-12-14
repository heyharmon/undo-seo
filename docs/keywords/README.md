# Keywords Domain

## Purpose

Manages SEO keyword clusters organized into hierarchical topical maps. Users create projects to group keywords, then build nested keyword trees with pillar topics at the root and supporting subtopics beneath them.

## Data Model

-   **Project**: Container for keyword clusters. Belongs to a user.
-   **Keyword**: Hierarchical structure using `parent_id` for nesting. Stores name, volume, intent, status, keyword type, content type, and strategic notes.
-   **KeywordCompetitor**: Tracks competitors ranking for a keyword with name, URL, and rank.

## Backend

-   **ProjectController**: CRUD for projects plus a stats endpoint that returns pillar count, total keywords, combined volume, and status breakdown.
-   **KeywordController**: Full keyword management including tree retrieval with filters, create, update, delete, move (change parent), and bulk reorder.
-   **Policies**: `ProjectPolicy` and `KeywordPolicy` ensure users can only access their own data.

## Frontend

-   **Pages**: `resources/js/pages/projects/ProjectsIndex.vue` lists projects; `KeywordClusters.vue` displays the keyword tree table.
-   **Components**: `StatsBar`, `FilterBar`, `KeywordTree`, `KeywordRow`, `KeywordDetailPanel`, and `AddKeywordModal` in `resources/js/components/keywords/`.
-   **Store**: `resources/js/stores/keywords.js` (Pinia) manages project, keywords tree, filters, and selection state.
-   **Service**: `resources/js/services/projects.js` wraps all project and keyword API calls.

## Key Concepts

-   **Pillars**: Root-level keywords (`parent_id` is null). Represent main topics.
-   **Nesting**: Keywords can have children to any depth via `parent_id`.
-   **Position**: Integer field for ordering keywords within the same parent.
-   **Filtering**: Search, intent, and status filters return matching keywords plus their ancestors for tree context.
