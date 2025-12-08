__MILESTONE 4 OF 4__

AI-Enhanced Clustering

*Estimated effort: 2-3 days*

# Overview

Enhance the topical map with AI-powered clustering using OpenAI. While DataForSEO provides raw keyword data and basic grouping, AI can recognize conceptual relationships that pattern matching misses. This milestone adds intelligent cluster refinement, better cluster naming, and gap analysis to identify missing content opportunities.

# Prerequisites

- Milestone 3 complete (topical map generation working)
- OpenAI API key

# Deliverables

1. OpenAI service integration
2. AI-powered cluster refinement (reorganize keywords)
3. AI-generated cluster names
4. Gap analysis (suggest missing keywords)
5. UI for triggering and reviewing AI enhancements

# The Problem AI Solves

DataForSEO's pattern-based clustering has limitations:

**What DataForSEO does well:**
- Groups keywords that share common words ("keto diet types", "types of keto diet")
- Identifies high-volume head terms
- Provides accurate search metrics

**What DataForSEO misses:**
- Conceptual relationships without shared words ("macros" and "tracking carbs" belong together)
- Semantic connections ("keto flu" and "electrolyte imbalance" are related)
- Intent-based grouping (informational vs. transactional queries)
- Optimal cluster boundaries (when to split or merge)

**What AI adds:**
- Understands meaning, not just word patterns
- Recognizes that "carb cycling" relates to "keto breaks" conceptually
- Can name clusters with descriptive titles beyond the parent keyword
- Identifies gaps in topic coverage

# Technical Requirements

## 1. Environment Configuration

Add to .env:

```
OPENAI_API_KEY=your_api_key
OPENAI_MODEL=gpt-4o
```

## 2. OpenAI Service Class

Create `App\Services\OpenAiService`:

```php
class OpenAiService
{
    /**
     * Analyze keywords and suggest optimal clustering
     */
    public function analyzeClusters(array $keywords): array

    /**
     * Generate descriptive names for clusters
     */
    public function generateClusterNames(array $clusters): array

    /**
     * Identify gaps in topic coverage
     */
    public function analyzeGaps(string $seedKeyword, array $clusters): array
}
```

## 3. AI Cluster Refinement

### Purpose

Take the existing DataForSEO-generated clusters and reorganize them based on semantic understanding.

### Implementation

**Method: `analyzeClusters(array $keywords)`**

Input: Flat array of all keywords from the project (ignore existing parent/child relationships)

Prompt strategy:
```
You are an SEO expert analyzing keywords for topical clustering.

Given these keywords related to "{seed_keyword}":
{keyword_list}

Group these keywords into logical clusters based on:
1. Semantic similarity (concepts that belong together)
2. Search intent (informational, transactional, navigational)
3. Content topic (what article would cover these keywords?)

For each cluster:
- Choose the best parent keyword (highest volume that represents the cluster)
- List all keywords that belong in this cluster
- A keyword should only appear in one cluster

Return JSON:
{
  "clusters": [
    {
      "parent_keyword": "keto diet meal plan",
      "keywords": ["keto meal prep", "weekly keto menu", "keto diet schedule"]
    }
  ]
}
```

**Output:** New cluster assignments that may differ from DataForSEO's grouping

### Database Updates

When AI refinement is applied:
1. Store the AI-suggested groupings
2. Update `parent_id` relationships based on AI recommendations
3. Optionally keep original DataForSEO grouping for comparison

Consider adding to keywords table:
```
ai_cluster_id (nullable) - For tracking AI-suggested groupings before applying
```

Or add a flag to track refinement status:
```
is_ai_refined (boolean, default false) - On the project
```

## 4. AI Cluster Naming

### Purpose

Generate descriptive, human-friendly names for clusters that go beyond the parent keyword.

### Implementation

**Method: `generateClusterNames(array $clusters)`**

Input: Array of clusters with their keywords

Prompt strategy:
```
You are an SEO content strategist.

For each keyword cluster below, generate a clear, descriptive name that:
1. Describes the content theme (not just the parent keyword)
2. Would make sense as a content category or pillar page title
3. Is concise (2-5 words)

Clusters:
{cluster_data}

Return JSON:
{
  "cluster_names": [
    {
      "parent_keyword": "keto diet types",
      "suggested_name": "Keto Diet Variations & Approaches"
    }
  ]
}
```

### Database Updates

Add to keywords table (for cluster parents only):
```
cluster_name (string, nullable) - AI-generated descriptive name
```

Or create a separate display name field that defaults to the keyword but can be overridden.

## 5. Gap Analysis

### Purpose

Identify missing keywords or subtopics that would complete the topical map.

### Implementation

**Method: `analyzeGaps(string $seedKeyword, array $clusters)`**

Prompt strategy:
```
You are an SEO expert reviewing a topical map for completeness.

Seed topic: "{seed_keyword}"

Current clusters and keywords:
{cluster_summary}

Analyze this topical map and identify:
1. Missing subtopics that should be covered
2. Gaps within existing clusters (missing variations)
3. Related topics that could expand the map

For each gap:
- Explain what's missing
- Suggest 3-5 keywords to add
- Indicate which existing cluster they belong to (or suggest a new cluster)

Return JSON:
{
  "gaps": [
    {
      "type": "missing_subtopic",
      "description": "No coverage of keto diet for specific health conditions",
      "suggested_keywords": ["keto diet for diabetics", "keto diet for pcos", "keto diet for epilepsy"],
      "cluster": "new",
      "suggested_cluster_name": "Keto for Health Conditions"
    }
  ]
}
```

### User Experience

Gap analysis results should be:
1. Displayed to the user as suggestions
2. Allow user to accept/reject suggestions
3. Accepted keywords are added to the topical map

## 6. API Endpoints

| Method | URI | Purpose |
|--------|-----|---------|
| POST | /api/projects/{id}/ai/refine-clusters | Trigger AI cluster refinement |
| POST | /api/projects/{id}/ai/generate-names | Generate AI cluster names |
| POST | /api/projects/{id}/ai/analyze-gaps | Run gap analysis |
| POST | /api/projects/{id}/ai/apply-suggestions | Apply selected AI suggestions |

### POST /api/projects/{id}/ai/refine-clusters

**Logic:**
1. Fetch all keywords for the project
2. Send to OpenAI for cluster analysis
3. Return suggested new groupings (don't apply yet)
4. User reviews and confirms before applying

**Response:**
```json
{
  "current_cluster_count": 15,
  "suggested_cluster_count": 12,
  "changes": [
    {
      "type": "merge",
      "description": "Merged 'keto macros' and 'tracking carbs' into single cluster",
      "affected_keywords": ["macro counting", "carb tracking", "net carbs"]
    },
    {
      "type": "move",
      "description": "Moved 'keto flu remedies' from 'keto side effects' to 'keto supplements'",
      "keyword": "keto flu remedies"
    }
  ],
  "suggested_clusters": [...]
}
```

### POST /api/projects/{id}/ai/analyze-gaps

**Response:**
```json
{
  "gaps": [
    {
      "type": "missing_subtopic",
      "description": "No coverage of keto diet for specific demographics",
      "suggested_keywords": [
        {"keyword": "keto diet for women over 40", "estimated_volume": "medium"},
        {"keyword": "keto diet for athletes", "estimated_volume": "high"}
      ],
      "suggested_cluster": "Keto for Specific Groups"
    }
  ],
  "completeness_score": 78
}
```

## 7. Frontend Components

### AI Enhancement Panel

Add an "AI Enhance" section or button to the project page:

**Options:**
- "Refine Clusters" - Reorganize based on AI analysis
- "Generate Names" - Create descriptive cluster names
- "Find Gaps" - Identify missing topics

Each action shows a preview of changes before applying.

### Cluster Refinement Review

When user triggers refinement:
1. Show loading state: "AI is analyzing your keywords..."
2. Display proposed changes in a diff-style view
3. Show what will be merged, split, or moved
4. "Apply Changes" and "Cancel" buttons
5. After applying, refresh the topical map view

### Gap Analysis Results

Display as a suggestions panel:
- List of identified gaps with descriptions
- Suggested keywords for each gap
- Checkbox to select which suggestions to add
- "Add Selected Keywords" button
- Visual indicator of completeness score

### Cluster Names Display

- Show AI-generated name alongside or instead of parent keyword
- Small "AI" badge to indicate generated name
- Allow user to edit/override the name

## 8. Cost Considerations

OpenAI API calls cost money. Implement safeguards:

1. **Rate limiting:** Limit AI actions per project per day
2. **Caching:** Cache AI results for the same keyword set
3. **Batch processing:** Send keywords in batches, not one at a time
4. **Token optimization:** Use concise prompts, limit response size

Track usage:
```
ai_usage
├── id
├── project_id
├── action (refine|name|gaps)
├── tokens_used
├── cost_estimate
├── created_at
```

# Acceptance Criteria

The milestone is complete when:

1. User can trigger AI cluster refinement from the project page
2. AI suggests cluster reorganizations with explanations
3. User can preview changes before applying
4. Applied changes update the keyword relationships in the database
5. User can generate AI-powered cluster names
6. AI-generated names display on clusters
7. User can run gap analysis
8. Gap suggestions show missing topics with suggested keywords
9. User can accept gap suggestions to add keywords
10. AI actions show appropriate loading states
11. Errors from OpenAI are handled gracefully

# UX Notes

- **AI is assistive, not automatic.** Always show suggestions for user review before applying.
- **Make AI optional.** The topical map from Milestone 3 should be fully usable without AI.
- **Explain the value.** Brief tooltips or descriptions help users understand what each AI action does.
- **Show confidence.** If possible, indicate how confident the AI is in its suggestions.

# Prompt Engineering Notes

- Keep prompts focused on one task at a time
- Provide clear output format (JSON) for reliable parsing
- Include examples in prompts for better results
- Test prompts with various seed keywords before finalizing
- Consider using system prompts to set consistent behavior

# Notes

- Start with GPT-4o for best quality; can test GPT-4o-mini for cost savings
- AI refinement works best with 50-200 keywords; very large sets may need chunking
- Gap analysis is most valuable—it directly helps users create more content
- Consider adding a "feedback" mechanism where users can rate AI suggestions
- Store prompts in config/database so they can be iterated without code changes
