<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * List all projects for the authenticated user.
     */
    public function index(Request $request)
    {
        $projects = $request->user()
            ->projects()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($projects);
    }

    /**
     * Store a new project.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $project = $request->user()->projects()->create($validated);

        return response()->json($project, 201);
    }

    /**
     * Show a single project.
     */
    public function show(Request $request, Project $project)
    {
        // Ensure the user owns this project
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json($project);
    }

    /**
     * Update a project.
     */
    public function update(Request $request, Project $project)
    {
        // Ensure the user owns this project
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    /**
     * Delete a project.
     */
    public function destroy(Request $request, Project $project)
    {
        // Ensure the user owns this project
        if ($project->user_id !== $request->user()->id) {
            abort(403);
        }

        $project->delete();

        return response()->json(null, 204);
    }
}
