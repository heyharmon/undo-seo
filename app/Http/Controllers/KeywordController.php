<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KeywordController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $query = $project->allKeywords();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('intent')) {
            $query->where('intent', $request->intent);
        }

        if ($request->has('keyword_type')) {
            $query->where('keyword_type', $request->keyword_type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $filteredKeywords = $query->orderBy('position')->get();

        // If filters are applied, include ancestors for context
        if ($request->has('status') || $request->has('intent') || $request->has('keyword_type') || $request->has('search')) {
            $ancestorIds = $this->getAncestorIds($filteredKeywords);
            $allKeywordIds = $filteredKeywords->pluck('id')->merge($ancestorIds)->unique();
            $keywords = $project->allKeywords()->whereIn('id', $allKeywordIds)->orderBy('position')->get();
        } else {
            $keywords = $filteredKeywords;
        }

        // Build nested tree structure
        $tree = $this->buildTree($keywords);

        return response()->json($tree);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:keywords,id',
            'volume' => 'nullable|integer|min:0',
            'intent' => 'required|in:info,commercial,transactional,navigational',
            'status' => 'required|in:active,draft,planned',
            'keyword_type' => 'required|in:product,service,benefit,price,competitor',
            'content_type' => 'required|in:pillar_page,article,tutorial,comparison,landing_page',
            'strategic_role' => 'nullable|string',
            'strategic_opportunity' => 'nullable|string',
        ]);

        // Validate parent belongs to same project
        if ($request->parent_id) {
            $parent = Keyword::findOrFail($request->parent_id);
            if ($parent->project_id !== $project->id) {
                return response()->json(['error' => 'Parent keyword must belong to the same project'], 422);
            }
        }

        // Get next position
        $maxPosition = $project->allKeywords()
            ->where('parent_id', $request->parent_id)
            ->max('position') ?? -1;

        $keyword = $project->allKeywords()->create([
            'parent_id' => $request->parent_id,
            'name' => $request->name,
            'volume' => $request->volume,
            'intent' => $request->intent,
            'status' => $request->status,
            'keyword_type' => $request->keyword_type,
            'content_type' => $request->content_type,
            'strategic_role' => $request->strategic_role,
            'strategic_opportunity' => $request->strategic_opportunity,
            'position' => $maxPosition + 1,
        ]);

        $keyword->load('competitors');

        return response()->json($keyword, 201);
    }

    public function show(Keyword $keyword)
    {
        $this->authorize('view', $keyword);

        $keyword->load('competitors');

        return response()->json($keyword);
    }

    public function update(Request $request, Keyword $keyword)
    {
        $this->authorize('update', $keyword);

        $request->validate([
            'name' => 'required|string|max:255',
            'volume' => 'nullable|integer|min:0',
            'intent' => 'required|in:info,commercial,transactional,navigational',
            'status' => 'required|in:active,draft,planned',
            'keyword_type' => 'required|in:product,service,benefit,price,competitor',
            'content_type' => 'required|in:pillar_page,article,tutorial,comparison,landing_page',
            'strategic_role' => 'nullable|string',
            'strategic_opportunity' => 'nullable|string',
            'competitors' => 'nullable|array',
            'competitors.*.id' => 'nullable|exists:keyword_competitors,id',
            'competitors.*.name' => 'required_without:competitors.*.id|string|max:255',
            'competitors.*.url' => 'required_without:competitors.*.id|string|url|max:255',
            'competitors.*.rank' => 'required_without:competitors.*.id|integer|min:1',
        ]);

        $keyword->update($request->only([
            'name',
            'volume',
            'intent',
            'status',
            'keyword_type',
            'content_type',
            'strategic_role',
            'strategic_opportunity',
        ]));

        // Sync competitors if provided
        if ($request->has('competitors')) {
            $competitorIds = [];
            foreach ($request->competitors as $competitorData) {
                if (isset($competitorData['id'])) {
                    // Update existing competitor
                    $competitor = $keyword->competitors()->findOrFail($competitorData['id']);
                    $competitor->update([
                        'name' => $competitorData['name'],
                        'url' => $competitorData['url'],
                        'rank' => $competitorData['rank'],
                    ]);
                    $competitorIds[] = $competitor->id;
                } else {
                    // Create new competitor
                    $competitor = $keyword->competitors()->create([
                        'name' => $competitorData['name'],
                        'url' => $competitorData['url'],
                        'rank' => $competitorData['rank'],
                    ]);
                    $competitorIds[] = $competitor->id;
                }
            }
            // Delete competitors not in the list
            $keyword->competitors()->whereNotIn('id', $competitorIds)->delete();
        }

        $keyword->load('competitors');

        return response()->json($keyword);
    }

    public function destroy(Keyword $keyword)
    {
        $this->authorize('delete', $keyword);

        // Cascade delete children (handled by database foreign key)
        $keyword->delete();

        return response()->json(['message' => 'Keyword deleted successfully']);
    }

    public function move(Request $request, Keyword $keyword)
    {
        $this->authorize('update', $keyword);

        $request->validate([
            'parent_id' => 'nullable|exists:keywords,id',
            'position' => 'required|integer|min:0',
        ]);

        // Validate parent belongs to same project
        if ($request->parent_id) {
            $parent = Keyword::findOrFail($request->parent_id);
            if ($parent->project_id !== $keyword->project_id) {
                return response()->json(['error' => 'Parent keyword must belong to the same project'], 422);
            }
            // Prevent moving keyword to be its own descendant
            if ($this->isDescendant($keyword, $request->parent_id)) {
                return response()->json(['error' => 'Cannot move keyword to be a descendant of itself'], 422);
            }
        }

        DB::transaction(function () use ($keyword, $request) {
            $oldParentId = $keyword->parent_id;
            $newParentId = $request->parent_id;
            $newPosition = $request->position;

            // If parent changed, shift positions in old parent's children
            if ($oldParentId !== $newParentId) {
                $keyword->project->allKeywords()
                    ->where('parent_id', $oldParentId)
                    ->where('position', '>', $keyword->position)
                    ->decrement('position');
            }

            // Shift positions in new parent's children to make room
            $keyword->project->allKeywords()
                ->where('parent_id', $newParentId)
                ->where('position', '>=', $newPosition)
                ->increment('position');

            // Update keyword
            $keyword->update([
                'parent_id' => $newParentId,
                'position' => $newPosition,
            ]);
        });

        $keyword->refresh();

        return response()->json($keyword);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'keywords' => 'required|array',
            'keywords.*.id' => 'required|exists:keywords,id',
            'keywords.*.position' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->keywords as $item) {
                $keyword = Keyword::findOrFail($item['id']);
                $this->authorize('update', $keyword);

                $keyword->update(['position' => $item['position']]);
            }
        });

        return response()->json(['message' => 'Keywords reordered successfully']);
    }

    private function buildTree($keywords, $parentId = null)
    {
        $branch = [];

        foreach ($keywords as $keyword) {
            if ($keyword->parent_id === $parentId) {
                $children = $this->buildTree($keywords, $keyword->id);
                $keywordArray = $keyword->toArray();
                if (!empty($children)) {
                    $keywordArray['children'] = $children;
                }
                $branch[] = $keywordArray;
            }
        }

        return $branch;
    }

    private function isDescendant(Keyword $keyword, $potentialAncestorId): bool
    {
        $current = Keyword::find($potentialAncestorId);
        
        if (!$current) {
            return false;
        }

        while ($current->parent_id !== null) {
            if ($current->parent_id === $keyword->id) {
                return true;
            }
            $current = Keyword::find($current->parent_id);
            if (!$current) {
                break;
            }
        }

        return false;
    }

    private function getAncestorIds($keywords): \Illuminate\Support\Collection
    {
        $ancestorIds = collect();

        foreach ($keywords as $keyword) {
            $current = $keyword;
            while ($current->parent_id !== null) {
                $ancestorIds->push($current->parent_id);
                $current = Keyword::find($current->parent_id);
                if (!$current) {
                    break;
                }
            }
        }

        return $ancestorIds->unique();
    }
}
