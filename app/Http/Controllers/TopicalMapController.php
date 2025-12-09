<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Project;
use App\Services\DataForSeoService;
use Illuminate\Http\Request;

class TopicalMapController extends Controller
{
    public function __construct(
        protected DataForSeoService $dataForSeo
    ) {}

    /**
     * Generate a new topical map for a project.
     */
    public function generate(Request $request, Project $project)
    {
        // Ensure user owns the project
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'seed_keyword' => 'required|string|max:500',
            'source' => 'in:ideas,suggestions',
        ]);

        $source = $validated['source'] ?? 'suggestions';
        $seedInput = $validated['seed_keyword'];

        // For Ideas API, parse comma-separated keywords
        // For Suggestions API, use the single keyword
        if ($source === 'ideas') {
            $seedKeywords = array_map('trim', explode(',', $seedInput));
            $seedKeywords = array_filter($seedKeywords); // Remove empty values
            $primarySeed = $seedKeywords[0] ?? $seedInput;
        } else {
            $seedKeywords = $seedInput;
            $primarySeed = $seedInput;
        }

        // Clear existing keywords
        $project->keywords()->delete();

        // Create seed keyword record (store the primary/first keyword)
        $project->keywords()->create([
            'keyword' => $primarySeed,
            'is_seed' => true,
        ]);

        // Generate topical map via DataForSEO service
        $result = $this->dataForSeo->generateTopicalMap($seedKeywords, $source);

        $clusters = $result['clusters'];

        // Store clusters
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
            'source' => $source,
            'cluster_count' => $project->clusters()->count(),
            'total_keywords' => $project->keywords()->count(),
        ]);
    }

    /**
     * Get all clusters for a project.
     */
    public function clusters(Request $request, Project $project)
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $seed = $project->seedKeyword;
        $clusters = $project->clusters()
            ->withCount('children')
            ->orderByDesc('search_volume')
            ->get()
            ->map(fn ($cluster) => [
                'id' => $cluster->id,
                'keyword' => $cluster->keyword,
                'search_volume' => $cluster->search_volume,
                'difficulty' => $cluster->difficulty,
                'difficulty_label' => $cluster->difficulty_label,
                'children_count' => $cluster->children_count,
            ]);

        return response()->json([
            'seed' => $seed?->keyword,
            'cluster_count' => $clusters->count(),
            'total_keywords' => $project->keywords()->count(),
            'clusters' => $clusters,
        ]);
    }

    /**
     * Get a single cluster with all its children.
     */
    public function showCluster(Request $request, Project $project, Keyword $cluster)
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        // Verify cluster belongs to project
        if ($cluster->project_id !== $project->id) {
            abort(404);
        }

        $children = $cluster->children()
            ->orderByDesc('search_volume')
            ->get()
            ->map(fn ($child) => [
                'id' => $child->id,
                'keyword' => $child->keyword,
                'search_volume' => $child->search_volume,
                'difficulty' => $child->difficulty,
                'difficulty_label' => $child->difficulty_label,
            ]);

        return response()->json([
            'id' => $cluster->id,
            'keyword' => $cluster->keyword,
            'search_volume' => $cluster->search_volume,
            'difficulty' => $cluster->difficulty,
            'difficulty_label' => $cluster->difficulty_label,
            'children' => $children,
        ]);
    }

    /**
     * Fetch keyword suggestions for the entire topical map.
     */
    public function suggestions(Request $request, Project $project)
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $seed = $project->seedKeyword;
        if (!$seed) {
            return response()->json([
                'error' => 'No topical map exists. Generate one first.',
            ], 400);
        }

        // Get existing keywords to deduplicate
        $existingKeywords = $project->keywords()
            ->pluck('keyword')
            ->map(fn ($k) => strtolower($k))
            ->toArray();

        // Fetch suggestions from DataForSEO
        $suggestions = $this->dataForSeo->getKeywordSuggestions($seed->keyword);

        $keywordsAdded = 0;
        $newClusters = 0;

        foreach ($suggestions as $suggestion) {
            $keywordLower = strtolower($suggestion['keyword']);

            // Skip if already exists
            if (in_array($keywordLower, $existingKeywords)) {
                continue;
            }

            // Add as a new cluster (single keyword)
            $project->keywords()->create([
                'keyword' => $suggestion['keyword'],
                'search_volume' => $suggestion['search_volume'],
                'difficulty' => $suggestion['difficulty'],
            ]);

            $existingKeywords[] = $keywordLower;
            $keywordsAdded++;
            $newClusters++;
        }

        return response()->json([
            'keywords_added' => $keywordsAdded,
            'new_clusters' => $newClusters,
            'message' => "Added {$keywordsAdded} keyword suggestions to your topical map",
        ]);
    }

    /**
     * Fetch keyword suggestions for a specific cluster.
     */
    public function clusterSuggestions(Request $request, Project $project, Keyword $cluster)
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($cluster->project_id !== $project->id) {
            abort(404);
        }

        // Get existing keywords in this cluster to deduplicate
        $existingKeywords = $cluster->children()
            ->pluck('keyword')
            ->map(fn ($k) => strtolower($k))
            ->toArray();
        $existingKeywords[] = strtolower($cluster->keyword);

        // Fetch suggestions for this cluster's keyword
        $suggestions = $this->dataForSeo->getKeywordSuggestions($cluster->keyword);

        $keywordsAdded = 0;

        foreach ($suggestions as $suggestion) {
            $keywordLower = strtolower($suggestion['keyword']);

            // Skip if already exists in cluster
            if (in_array($keywordLower, $existingKeywords)) {
                continue;
            }

            // Add as child of this cluster
            $project->keywords()->create([
                'parent_id' => $cluster->id,
                'keyword' => $suggestion['keyword'],
                'search_volume' => $suggestion['search_volume'],
                'difficulty' => $suggestion['difficulty'],
            ]);

            $existingKeywords[] = $keywordLower;
            $keywordsAdded++;
        }

        return response()->json([
            'cluster_id' => $cluster->id,
            'cluster_keyword' => $cluster->keyword,
            'keywords_added' => $keywordsAdded,
            'message' => "Added {$keywordsAdded} suggestions to the '{$cluster->keyword}' cluster",
        ]);
    }

    /**
     * Fetch keyword ideas (semantically related) for the topical map.
     * Adds new clusters based on broader topic associations.
     */
    public function ideas(Request $request, Project $project)
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $seed = $project->seedKeyword;
        if (!$seed) {
            return response()->json([
                'error' => 'No topical map exists. Generate one first.',
            ], 400);
        }

        // Get existing keywords to deduplicate
        $existingKeywords = $project->keywords()
            ->pluck('keyword')
            ->map(fn ($k) => strtolower($k))
            ->toArray();

        // Fetch ideas from DataForSEO (semantically related keywords)
        $ideas = $this->dataForSeo->getKeywordIdeas([$seed->keyword]);

        $keywordsAdded = 0;
        $newClusters = 0;

        foreach ($ideas as $idea) {
            $keywordLower = strtolower($idea['keyword']);

            // Skip if already exists
            if (in_array($keywordLower, $existingKeywords)) {
                continue;
            }

            // Add as a new cluster (single keyword)
            $project->keywords()->create([
                'keyword' => $idea['keyword'],
                'search_volume' => $idea['search_volume'],
                'difficulty' => $idea['difficulty'],
            ]);

            $existingKeywords[] = $keywordLower;
            $keywordsAdded++;
            $newClusters++;
        }

        return response()->json([
            'keywords_added' => $keywordsAdded,
            'new_clusters' => $newClusters,
            'message' => "Added {$keywordsAdded} keyword ideas to your topical map",
        ]);
    }

    /**
     * Explore keywords without saving them.
     * Returns raw results from DataForSEO for preview.
     */
    public function explore(Request $request, Project $project)
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'query' => 'required|string|max:500',
            'source' => 'required|in:ideas,suggestions',
        ]);

        $query = $validated['query'];
        $source = $validated['source'];

        // Get existing keywords to mark which ones are already in the project
        $existingKeywords = $project->keywords()
            ->pluck('keyword')
            ->map(fn ($k) => strtolower($k))
            ->toArray();

        // Fetch keywords from selected source
        if ($source === 'ideas') {
            $seedKeywords = array_map('trim', explode(',', $query));
            $seedKeywords = array_filter($seedKeywords);
            $results = $this->dataForSeo->getKeywordIdeas($seedKeywords);
        } else {
            $results = $this->dataForSeo->getKeywordSuggestions($query);
        }

        // Mark keywords that already exist in the project
        $keywords = collect($results)->map(function ($kw) use ($existingKeywords) {
            return [
                'keyword' => $kw['keyword'],
                'search_volume' => $kw['search_volume'],
                'difficulty' => $kw['difficulty'],
                'in_project' => in_array(strtolower($kw['keyword']), $existingKeywords),
            ];
        });

        return response()->json([
            'query' => $query,
            'source' => $source,
            'count' => $keywords->count(),
            'keywords' => $keywords,
        ]);
    }

    /**
     * Add selected keywords to the project as clusters.
     */
    public function addKeywords(Request $request, Project $project)
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'keywords' => 'required|array|min:1',
            'keywords.*.keyword' => 'required|string',
            'keywords.*.search_volume' => 'nullable|integer',
            'keywords.*.difficulty' => 'nullable|integer',
        ]);

        // Get existing keywords to deduplicate
        $existingKeywords = $project->keywords()
            ->pluck('keyword')
            ->map(fn ($k) => strtolower($k))
            ->toArray();

        $keywordsAdded = 0;

        foreach ($validated['keywords'] as $kw) {
            $keywordLower = strtolower($kw['keyword']);

            // Skip if already exists
            if (in_array($keywordLower, $existingKeywords)) {
                continue;
            }

            // Add as a new cluster
            $project->keywords()->create([
                'keyword' => $kw['keyword'],
                'search_volume' => $kw['search_volume'] ?? null,
                'difficulty' => $kw['difficulty'] ?? null,
            ]);

            $existingKeywords[] = $keywordLower;
            $keywordsAdded++;
        }

        return response()->json([
            'keywords_added' => $keywordsAdded,
            'message' => "Added {$keywordsAdded} keywords to your topical map",
        ]);
    }
}
