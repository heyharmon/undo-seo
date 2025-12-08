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
            'seed_keyword' => 'required|string|max:255',
            'include_suggestions' => 'boolean',
        ]);

        // Clear existing keywords
        $project->keywords()->delete();

        // Create seed keyword record
        $project->keywords()->create([
            'keyword' => $validated['seed_keyword'],
            'is_seed' => true,
        ]);

        // Generate topical map via DataForSEO service
        $result = $this->dataForSeo->generateTopicalMap(
            $validated['seed_keyword'],
            $validated['include_suggestions'] ?? false
        );

        $clusters = $result['clusters'];
        $orphans = $result['orphans'] ?? [];

        // Store clusters and their children
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

        // Store orphan keywords as their own clusters (single-keyword clusters)
        foreach ($orphans as $orphan) {
            $project->keywords()->create([
                'keyword' => $orphan['keyword'],
                'search_volume' => $orphan['search_volume'],
                'difficulty' => $orphan['difficulty'],
            ]);
        }

        return response()->json([
            'seed' => $validated['seed_keyword'],
            'cluster_count' => $project->clusters()->count(),
            'total_keywords' => $project->keywords()->count(),
            'included_suggestions' => $validated['include_suggestions'] ?? false,
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
}
