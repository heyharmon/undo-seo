<?php

namespace Database\Seeders;

use App\Models\Keyword;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class KeywordSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'heyharmon@gmail.com')->first() ?? User::first();

        $project = Project::create([
            'user_id' => $user->id,
            'name' => 'AI SEO Strategy',
        ]);

        // Pillar 1: AI Visibility
        $pillar1 = $this->createKeyword($project, null, 0, [
            'name' => 'AI Visibility Optimization',
            'volume' => 2400,
            'intent' => 'info',
            'status' => 'active',
        ]);

        $cluster1a = $this->createKeyword($project, $pillar1->id, 0, [
            'name' => 'AI Search Rankings',
            'volume' => 1200,
            'intent' => 'info',
            'status' => 'active',
        ]);

        $this->createKeyword($project, $cluster1a->id, 0, [
            'name' => 'how to rank in AI search',
            'volume' => 480,
            'intent' => 'info',
            'status' => 'active',
        ]);

        $this->createKeyword($project, $cluster1a->id, 1, [
            'name' => 'AI search optimization guide',
            'volume' => 320,
            'intent' => 'info',
            'status' => 'draft',
        ]);

        $this->createKeyword($project, $pillar1->id, 1, [
            'name' => 'AI Visibility Tools',
            'volume' => 880,
            'intent' => 'commercial',
            'status' => 'active',
        ]);

        // Pillar 2: Programmatic SEO
        $pillar2 = $this->createKeyword($project, null, 1, [
            'name' => 'Programmatic SEO',
            'volume' => 3200,
            'intent' => 'info',
            'status' => 'active',
        ]);

        $this->createKeyword($project, $pillar2->id, 0, [
            'name' => 'programmatic SEO templates',
            'volume' => 720,
            'intent' => 'commercial',
            'status' => 'draft',
        ]);

        // Pillar 3: Topical Authority
        $pillar3 = $this->createKeyword($project, null, 2, [
            'name' => 'Topical Authority',
            'volume' => 1800,
            'intent' => 'info',
            'status' => 'draft',
        ]);

        $cluster3a = $this->createKeyword($project, $pillar3->id, 0, [
            'name' => 'Content Clustering',
            'volume' => 920,
            'intent' => 'info',
            'status' => 'draft',
        ]);

        $this->createKeyword($project, $cluster3a->id, 0, [
            'name' => 'topic cluster strategy',
            'volume' => 480,
            'intent' => 'info',
            'status' => 'draft',
        ]);

        $this->createKeyword($project, $cluster3a->id, 1, [
            'name' => 'pillar page examples',
            'volume' => 390,
            'intent' => 'info',
            'status' => 'planned',
        ]);
    }

    private function createKeyword(Project $project, ?int $parentId, int $position, array $data): Keyword
    {
        return Keyword::create([
            'project_id' => $project->id,
            'parent_id' => $parentId,
            'position' => $position,
            'name' => $data['name'],
            'volume' => $data['volume'] ?? null,
            'intent' => $data['intent'] ?? 'info',
            'status' => $data['status'] ?? 'draft',
            'keyword_type' => $data['keyword_type'] ?? 'service',
            'content_type' => $data['content_type'] ?? 'article',
            'strategic_role' => $data['strategic_role'] ?? null,
            'strategic_opportunity' => $data['strategic_opportunity'] ?? null,
        ]);
    }
}

