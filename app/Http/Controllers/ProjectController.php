<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = $request->user()->projects()->orderBy('created_at', 'desc')->get();

        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $project = $request->user()->projects()->create([
            'name' => $request->name,
        ]);

        return response()->json($project, 201);
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);

        return response()->json($project);
    }

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $project->update([
            'name' => $request->name,
        ]);

        return response()->json($project);
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    public function stats(Project $project)
    {
        $this->authorize('view', $project);

        $keywords = $project->allKeywords()->get();

        $pillarCount = $keywords->whereNull('parent_id')->count();
        $totalKeywords = $keywords->count();
        $combinedVolume = $keywords->sum('volume') ?? 0;
        $statusBreakdown = [
            'active' => $keywords->where('status', 'active')->count(),
            'draft' => $keywords->where('status', 'draft')->count(),
            'planned' => $keywords->where('status', 'planned')->count(),
        ];

        return response()->json([
            'pillar_count' => $pillarCount,
            'total_keywords' => $totalKeywords,
            'combined_volume' => $combinedVolume,
            'status_breakdown' => $statusBreakdown,
        ]);
    }
}
