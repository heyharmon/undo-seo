# Milestone 6 — Cluster Generation (Iterative by Pillar)

> **Objective:** Generate clusters one pillar at a time to maintain control.

This milestone implements iterative cluster generation where Claude generates cluster keywords for one pillar at a time. This approach gives users control and prevents overwhelming them with hundreds of keywords at once. Tier-1 clusters (high priority) are validated via SERP search.

---

## Flow Overview

```
Validated Pillars (from Milestone 5)
                │
                ▼
    ┌───────────────────────────┐
    │   ClusterGenerationNode   │
    └───────────────┬───────────┘
                    │
                    │ For each pillar (iterative):
                    │
        ┌───────────┴───────────┐
        │                       │
        ▼                       │
┌─────────────────────┐         │
│ Generate clusters   │         │
│ for pillar N        │         │
│ - Tier 1 (priority) │         │
│ - Tier 2 (depth)    │         │
│ - Tier 3 (optional) │         │
└─────────┬───────────┘         │
          │                     │
          ▼                     │
┌─────────────────────┐         │
│ SERP validate       │         │
│ Tier-1 only         │         │
└─────────┬───────────┘         │
          │                     │
          ▼                     │
┌─────────────────────┐         │
│    INTERRUPT        │         │
│ Present clusters    │         │
│ Wait for approval   │         │
└─────────┬───────────┘         │
          │                     │
    ┌─────┴─────────────┐       │
    │                   │       │
    ▼                   ▼       │
 Approve          Request more  │
    │              depth        │
    │                   │       │
    │         ┌─────────┘       │
    │         │                 │
    │         ▼                 │
    │   Generate more           │
    │   clusters                │
    │         │                 │
    └─────────┴─────────────────┤
                                │
              ┌─────────────────┘
              │ More pillars?
              │
    ┌─────────┴─────────┐
    │                   │
    ▼                   ▼
 Yes: Loop          No: Done
 to next             │
 pillar              ▼
              AllPillarsProcessedEvent
```

---

## Structured Output Classes

### Cluster Generation DTO

```php
<?php

namespace App\Neuron\Dto;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;
use NeuronAI\StructuredOutput\Validation\Rules\Count;

class ClusterGenerationResponse
{
    #[SchemaProperty(description: 'The pillar ID these clusters belong to', required: true)]
    public string $pillarId;

    #[SchemaProperty(description: 'The pillar keyword', required: true)]
    public string $pillarName;

    /**
     * @var \App\Neuron\Dto\ClusterKeyword[]
     */
    #[SchemaProperty(
        description: 'Tier 1 clusters: High-priority keywords that directly support the pillar',
        required: true
    )]
    #[ArrayOf(ClusterKeyword::class)]
    #[Count(min: 3, max: 8)]
    public array $tier1Clusters;

    /**
     * @var \App\Neuron\Dto\ClusterKeyword[]
     */
    #[SchemaProperty(
        description: 'Tier 2 clusters: Supporting keywords that add depth',
        required: true
    )]
    #[ArrayOf(ClusterKeyword::class)]
    #[Count(min: 3, max: 12)]
    public array $tier2Clusters;

    /**
     * @var \App\Neuron\Dto\ClusterKeyword[]
     */
    #[SchemaProperty(
        description: 'Tier 3 clusters: Optional expansion keywords for comprehensive coverage',
        required: false
    )]
    #[ArrayOf(ClusterKeyword::class)]
    public array $tier3Clusters = [];

    #[SchemaProperty(
        description: 'How these clusters connect to create topical authority',
        required: true
    )]
    public string $clusterStrategy;

    #[SchemaProperty(
        description: 'Recommended internal linking structure between clusters',
        required: false
    )]
    public ?string $linkingStrategy = null;
}

class ClusterKeyword
{
    #[SchemaProperty(description: 'Unique identifier for this cluster', required: true)]
    public string $id;

    #[SchemaProperty(description: 'The cluster keyword phrase', required: true)]
    public string $name;

    #[SchemaProperty(
        description: 'Search intent: informational, commercial, transactional, navigational',
        required: true
    )]
    public string $intent;

    #[SchemaProperty(
        description: 'Content type: article, guide, tutorial, comparison, landing_page, faq',
        required: true
    )]
    public string $contentType;

    #[SchemaProperty(
        description: 'Keyword category: how_to, what_is, comparison, feature, benefit, pricing, alternative',
        required: true
    )]
    public string $keywordType;

    #[SchemaProperty(description: 'Brief rationale for including this cluster', required: true)]
    public string $rationale;

    #[SchemaProperty(
        description: 'Related clusters this should link to (by ID)',
        required: false
    )]
    public array $relatedClusters = [];

    #[SchemaProperty(
        description: 'Priority within tier: 1 = highest, 5 = lowest',
        required: true
    )]
    public int $priority;
}
```

---

## Cluster Generation Node

```php
<?php

namespace App\Neuron\Nodes;

use App\Neuron\Events\PillarsValidatedEvent;
use App\Neuron\Events\GenerateClustersEvent;
use App\Neuron\Events\ClustersApprovedEvent;
use App\Neuron\Events\AllPillarsProcessedEvent;
use App\Neuron\Dto\ClusterGenerationResponse;
use App\Neuron\Dto\ClusterValidationResult;
use App\Neuron\Agents\ClusterGenerationAgent;
use App\Neuron\Tools\SerpSearchTool;
use App\Services\KeywordCreationService;
use App\Enums\AssistantMessageType;
use App\Enums\GenerationRunStatus;
use App\Enums\WorkflowStep;
use NeuronAI\Workflow\WorkflowState;
use NeuronAI\Chat\Messages\UserMessage;

class ClusterGenerationNode extends BaseTopicalMapNode
{
    public function __invoke(
        PillarsValidatedEvent|GenerateClustersEvent|ClustersApprovedEvent $event,
        WorkflowState $state
    ): GenerateClustersEvent|AllPillarsProcessedEvent {

        // Check if resuming from interruption
        $feedback = $this->consumeInterruptFeedback();

        if ($feedback !== null) {
            return $this->processClusterFeedback($feedback, $state);
        }

        // Determine which pillar we're working on
        $validatedPillars = $state->get('validated_pillars', []);
        $currentIndex = $state->get('current_pillar_index', 0);

        // Check if all pillars are done
        if ($currentIndex >= count($validatedPillars)) {
            return $this->completeClusterGeneration($state);
        }

        $currentPillar = $validatedPillars[$currentIndex];

        $this->updateRunStatus(
            GenerationRunStatus::PROCESSING,
            WorkflowStep::CLUSTER_GENERATION
        );

        // Generate clusters for current pillar
        $clusters = $this->checkpoint(
            "clusters_pillar_{$currentIndex}",
            fn() => $this->generateClusters($currentPillar, $state)
        );

        // Validate Tier-1 clusters via SERP
        $validatedClusters = $this->checkpoint(
            "validate_tier1_{$currentIndex}",
            fn() => $this->validateTier1Clusters($clusters)
        );

        // Store in state
        $state->set("clusters_pillar_{$currentIndex}", $validatedClusters);

        // Present to user for approval
        return $this->presentClustersForApproval($currentPillar, $validatedClusters, $currentIndex, $state);
    }

    /**
     * Generate clusters for a pillar
     */
    private function generateClusters(array $pillar, WorkflowState $state): ClusterGenerationResponse
    {
        $agent = ClusterGenerationAgent::make();

        $businessContext = $state->get('business_context', []);

        $prompt = $this->buildClusterPrompt($pillar, $businessContext);

        return $agent->structured(
            new UserMessage($prompt),
            ClusterGenerationResponse::class,
            maxRetries: 2
        );
    }

    /**
     * Build prompt for cluster generation
     */
    private function buildClusterPrompt(array $pillar, array $businessContext): string
    {
        $prompt = "Generate cluster keywords for this pillar:\n\n";

        $prompt .= "## Pillar\n";
        $prompt .= "- Name: {$pillar['validated_name']}\n";
        $prompt .= "- ID: {$pillar['id']}\n";

        if (isset($pillar['serp_data'])) {
            $prompt .= "- Competition: {$pillar['serp_data']['competition_level']}\n";
            $prompt .= "- Top Competitors: " . implode(', ', $pillar['serp_data']['top_competitors'] ?? []) . "\n";
        }

        $prompt .= "\n## Business Context\n";
        $prompt .= json_encode($businessContext, JSON_PRETTY_PRINT) . "\n\n";

        $prompt .= "## Guidelines\n";
        $prompt .= "- **Tier 1**: 5-8 high-priority clusters that directly support the pillar\n";
        $prompt .= "- **Tier 2**: 5-10 supporting clusters that add topical depth\n";
        $prompt .= "- **Tier 3**: 3-5 optional expansion keywords (can be empty)\n\n";

        $prompt .= "## Cluster Types to Include\n";
        $prompt .= "- **How-to**: Tutorial/guide style content\n";
        $prompt .= "- **What-is**: Informational/definition content\n";
        $prompt .= "- **Comparison**: vs. competitors, alternatives\n";
        $prompt .= "- **Feature**: Specific feature/benefit content\n";
        $prompt .= "- **Pricing**: Cost/pricing related if relevant\n\n";

        $prompt .= "## Quality Rules\n";
        $prompt .= "- Each cluster should be specific enough to rank for\n";
        $prompt .= "- Include a mix of intents (informational, commercial, transactional)\n";
        $prompt .= "- Focus on 80/20 - the clusters that matter most\n";
        $prompt .= "- Avoid keyword stuffing or variations of the same keyword\n";

        return $prompt;
    }

    /**
     * Validate Tier-1 clusters via SERP (Tier-2/3 not validated to save API calls)
     */
    private function validateTier1Clusters(ClusterGenerationResponse $clusters): array
    {
        $agent = ClusterGenerationAgent::make()->addTool(
            new SerpSearchTool(config('services.serpapi.key'))
        );

        $validatedTier1 = [];

        foreach ($clusters->tier1Clusters as $cluster) {
            // SERP validate each Tier-1 cluster
            $prompt = "Validate this cluster keyword: \"{$cluster->name}\"\n";
            $prompt .= "Use serp_search to check if it has real search competition.\n";
            $prompt .= "Return: valid (true/false), competition level, and any suggested refinement.";

            try {
                $response = $agent->chat(new UserMessage($prompt));
                $content = $response->getContent();

                // Parse validation (simplified - could use structured output)
                $validated = [
                    'id' => $cluster->id,
                    'name' => $cluster->name,
                    'intent' => $cluster->intent,
                    'content_type' => $cluster->contentType,
                    'keyword_type' => $cluster->keywordType,
                    'rationale' => $cluster->rationale,
                    'priority' => $cluster->priority,
                    'tier' => 1,
                    'serp_validated' => true,
                    'validation_notes' => $content,
                ];

                $validatedTier1[] = $validated;
            } catch (\Exception $e) {
                // If validation fails, still include cluster but mark as unvalidated
                $validatedTier1[] = [
                    'id' => $cluster->id,
                    'name' => $cluster->name,
                    'intent' => $cluster->intent,
                    'content_type' => $cluster->contentType,
                    'keyword_type' => $cluster->keywordType,
                    'rationale' => $cluster->rationale,
                    'priority' => $cluster->priority,
                    'tier' => 1,
                    'serp_validated' => false,
                    'validation_notes' => 'Validation skipped: ' . $e->getMessage(),
                ];
            }
        }

        // Add Tier-2 and Tier-3 without SERP validation
        $tier2 = array_map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'intent' => $c->intent,
            'content_type' => $c->contentType,
            'keyword_type' => $c->keywordType,
            'rationale' => $c->rationale,
            'priority' => $c->priority,
            'tier' => 2,
            'serp_validated' => false,
        ], $clusters->tier2Clusters);

        $tier3 = array_map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'intent' => $c->intent,
            'content_type' => $c->contentType,
            'keyword_type' => $c->keywordType,
            'rationale' => $c->rationale,
            'priority' => $c->priority,
            'tier' => 3,
            'serp_validated' => false,
        ], $clusters->tier3Clusters);

        return [
            'pillar_id' => $clusters->pillarId,
            'pillar_name' => $clusters->pillarName,
            'tier1' => $validatedTier1,
            'tier2' => $tier2,
            'tier3' => $tier3,
            'cluster_strategy' => $clusters->clusterStrategy,
            'linking_strategy' => $clusters->linkingStrategy,
        ];
    }

    /**
     * Present clusters for user approval
     */
    private function presentClustersForApproval(
        array $pillar,
        array $clusters,
        int $pillarIndex,
        WorkflowState $state
    ): GenerateClustersEvent {

        $validatedPillars = $state->get('validated_pillars', []);
        $totalPillars = count($validatedPillars);

        $textContent = $this->buildClusterMessage($pillar, $clusters, $pillarIndex, $totalPillars);

        $structuredContent = [
            'type' => 'proposal',
            'workflow_step' => 'cluster_generation',
            'pillar_index' => $pillarIndex,
            'pillar_name' => $pillar['validated_name'],
            'total_pillars' => $totalPillars,
            'clusters' => $clusters,
            'actions_available' => [
                'approve_clusters',
                'request_more_depth',
                'upgrade_tier',
                'downgrade_tier',
                'remove_clusters',
                'skip_pillar',
            ],
        ];

        $this->interruptWithMessage(
            AssistantMessageType::PROPOSAL,
            $textContent,
            $structuredContent,
            GenerationRunStatus::AWAITING_APPROVAL,
            WorkflowStep::CLUSTER_GENERATION
        );

        return new GenerateClustersEvent(
            pillarIndex: $pillarIndex,
            pillar: $pillar
        );
    }

    /**
     * Build human-readable cluster message
     */
    private function buildClusterMessage(
        array $pillar,
        array $clusters,
        int $pillarIndex,
        int $totalPillars
    ): string {

        $current = $pillarIndex + 1;
        $message = "## Clusters for Pillar {$current}/{$totalPillars}: {$pillar['validated_name']}\n\n";

        $message .= "**Strategy:** {$clusters['cluster_strategy']}\n\n";

        // Tier 1
        $message .= "### Tier 1 — Priority Clusters (SERP Validated)\n";
        $message .= "_These are your highest-priority keywords for this pillar._\n\n";

        foreach ($clusters['tier1'] as $cluster) {
            $validated = $cluster['serp_validated'] ? '✓' : '○';
            $message .= "- **{$cluster['name']}** [{$validated}]\n";
            $message .= "  Intent: {$cluster['intent']} | Type: {$cluster['content_type']}\n";
            $message .= "  _{$cluster['rationale']}_\n\n";
        }

        // Tier 2
        $message .= "### Tier 2 — Supporting Clusters\n";
        $message .= "_These add depth to your topical coverage._\n\n";

        foreach ($clusters['tier2'] as $cluster) {
            $message .= "- **{$cluster['name']}**\n";
            $message .= "  Intent: {$cluster['intent']} | Type: {$cluster['content_type']}\n\n";
        }

        // Tier 3 (if exists)
        if (!empty($clusters['tier3'])) {
            $message .= "### Tier 3 — Expansion Clusters (Optional)\n";
            $message .= "_These provide comprehensive coverage but are lower priority._\n\n";

            foreach ($clusters['tier3'] as $cluster) {
                $message .= "- {$cluster['name']} ({$cluster['intent']})\n";
            }
            $message .= "\n";
        }

        // Summary
        $tier1Count = count($clusters['tier1']);
        $tier2Count = count($clusters['tier2']);
        $tier3Count = count($clusters['tier3']);
        $totalCount = $tier1Count + $tier2Count + $tier3Count;

        $message .= "---\n\n";
        $message .= "**Summary:** {$totalCount} total clusters ({$tier1Count} T1, {$tier2Count} T2, {$tier3Count} T3)\n\n";

        $message .= "**What would you like to do?**\n";
        $message .= "- Approve these clusters\n";
        $message .= "- Request more depth (generate more T2/T3 clusters)\n";
        $message .= "- Upgrade/downgrade specific clusters between tiers\n";
        $message .= "- Skip this pillar for now\n";

        return $message;
    }

    /**
     * Process user feedback on clusters
     */
    private function processClusterFeedback(array $feedback, WorkflowState $state): GenerateClustersEvent|AllPillarsProcessedEvent
    {
        $structuredInput = $feedback['structured_input'] ?? [];
        $userText = $feedback['user_input'] ?? '';

        $action = $structuredInput['action'] ?? $this->inferClusterAction($userText);
        $currentIndex = $state->get('current_pillar_index', 0);

        switch ($action) {
            case 'approve_clusters':
                return $this->approveClusters($currentIndex, $state);

            case 'request_more_depth':
                return $this->generateMoreClusters($currentIndex, $state);

            case 'skip_pillar':
                return $this->skipPillar($currentIndex, $state);

            case 'upgrade_tier':
            case 'downgrade_tier':
                return $this->adjustTiers($structuredInput['adjustments'] ?? [], $currentIndex, $state);

            default:
                return $this->approveClusters($currentIndex, $state);
        }
    }

    /**
     * Approve clusters and persist to database
     */
    private function approveClusters(int $pillarIndex, WorkflowState $state): GenerateClustersEvent|AllPillarsProcessedEvent
    {
        $clusters = $state->get("clusters_pillar_{$pillarIndex}", []);
        $validatedPillars = $state->get('validated_pillars', []);
        $pillar = $validatedPillars[$pillarIndex];

        // Create keywords in database using existing Keyword model
        $keywordService = app(KeywordCreationService::class);

        // First, ensure pillar keyword exists
        $pillarKeyword = $keywordService->createPillarKeyword(
            $this->run->project,
            \App\Models\ApprovedPillar::find($pillar['approved_pillar_id']),
            [
                'intent' => $pillar['serp_data']['intent_alignment'] ?? 'info',
                'keyword_type' => 'service',
                'strategic_role' => 'pillar',
            ]
        );

        // Create cluster keywords under pillar
        $allClusters = array_merge(
            $clusters['tier1'] ?? [],
            $clusters['tier2'] ?? [],
            $clusters['tier3'] ?? []
        );

        $keywordService->createClusterKeywords($pillarKeyword, $allClusters);

        // Store completion in state
        $completedPillars = $state->get('completed_pillar_indexes', []);
        $completedPillars[] = $pillarIndex;
        $state->set('completed_pillar_indexes', $completedPillars);

        // Move to next pillar
        $nextIndex = $pillarIndex + 1;
        $state->set('current_pillar_index', $nextIndex);

        // Confirmation message
        $clusterCount = count($allClusters);
        $this->createAssistantMessage(
            AssistantMessageType::CONFIRMATION,
            "Created {$clusterCount} cluster keywords for '{$pillar['validated_name']}'.",
            [
                'type' => 'confirmation',
                'action_taken' => 'clusters_created',
                'pillar' => $pillar['validated_name'],
                'cluster_count' => $clusterCount,
            ]
        );

        // Check if more pillars remain
        if ($nextIndex >= count($validatedPillars)) {
            return $this->completeClusterGeneration($state);
        }

        // Continue to next pillar
        $nextPillar = $validatedPillars[$nextIndex];

        return new GenerateClustersEvent(
            pillarIndex: $nextIndex,
            pillar: $nextPillar
        );
    }

    /**
     * Generate more clusters for depth
     */
    private function generateMoreClusters(int $pillarIndex, WorkflowState $state): GenerateClustersEvent
    {
        $validatedPillars = $state->get('validated_pillars', []);
        $pillar = $validatedPillars[$pillarIndex];
        $existingClusters = $state->get("clusters_pillar_{$pillarIndex}", []);

        // Generate additional clusters
        $agent = ClusterGenerationAgent::make();

        $existingNames = array_merge(
            array_map(fn($c) => $c['name'], $existingClusters['tier1'] ?? []),
            array_map(fn($c) => $c['name'], $existingClusters['tier2'] ?? []),
            array_map(fn($c) => $c['name'], $existingClusters['tier3'] ?? [])
        );

        $prompt = "Generate additional cluster keywords for pillar '{$pillar['validated_name']}'.\n";
        $prompt .= "Existing clusters (do not duplicate): " . implode(', ', $existingNames) . "\n";
        $prompt .= "Focus on Tier 2 and Tier 3 keywords that add depth.";

        // Generate and merge...
        // (Implementation similar to generateClusters but appends to existing)

        // Re-present with updated clusters
        return new GenerateClustersEvent(pillarIndex: $pillarIndex, pillar: $pillar);
    }

    /**
     * Skip pillar for now
     */
    private function skipPillar(int $pillarIndex, WorkflowState $state): GenerateClustersEvent|AllPillarsProcessedEvent
    {
        $validatedPillars = $state->get('validated_pillars', []);

        // Mark as skipped
        $skippedPillars = $state->get('skipped_pillar_indexes', []);
        $skippedPillars[] = $pillarIndex;
        $state->set('skipped_pillar_indexes', $skippedPillars);

        // Move to next
        $nextIndex = $pillarIndex + 1;
        $state->set('current_pillar_index', $nextIndex);

        if ($nextIndex >= count($validatedPillars)) {
            return $this->completeClusterGeneration($state);
        }

        return new GenerateClustersEvent(
            pillarIndex: $nextIndex,
            pillar: $validatedPillars[$nextIndex]
        );
    }

    /**
     * Complete cluster generation phase
     */
    private function completeClusterGeneration(WorkflowState $state): AllPillarsProcessedEvent
    {
        $this->markStepComplete($state, 'cluster_generation');

        // Gather summary statistics
        $validatedPillars = $state->get('validated_pillars', []);
        $completedIndexes = $state->get('completed_pillar_indexes', []);
        $skippedIndexes = $state->get('skipped_pillar_indexes', []);

        $totalClusters = 0;
        foreach ($completedIndexes as $idx) {
            $clusters = $state->get("clusters_pillar_{$idx}", []);
            $totalClusters += count($clusters['tier1'] ?? []);
            $totalClusters += count($clusters['tier2'] ?? []);
            $totalClusters += count($clusters['tier3'] ?? []);
        }

        $summary = [
            'total_pillars' => count($validatedPillars),
            'completed_pillars' => count($completedIndexes),
            'skipped_pillars' => count($skippedIndexes),
            'total_clusters' => $totalClusters,
        ];

        return new AllPillarsProcessedEvent(summary: $summary);
    }

    /**
     * Infer action from text
     */
    private function inferClusterAction(string $text): string
    {
        $lower = strtolower($text);

        if (str_contains($lower, 'approve') || str_contains($lower, 'looks good') || str_contains($lower, 'yes')) {
            return 'approve_clusters';
        }
        if (str_contains($lower, 'more') || str_contains($lower, 'depth') || str_contains($lower, 'expand')) {
            return 'request_more_depth';
        }
        if (str_contains($lower, 'skip')) {
            return 'skip_pillar';
        }

        return 'approve_clusters';
    }
}
```

---

## Cluster Generation Agent

```php
<?php

namespace App\Neuron\Agents;

use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;

class ClusterGenerationAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        return new Anthropic(
            key: config('neuron.anthropic.key'),
            model: config('neuron.anthropic.model'),
        );
    }

    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                "You are an SEO strategist generating cluster keywords that support pillar pages.",
                "Clusters should create a comprehensive topical map around the pillar keyword.",
                "Your goal is quality over quantity - focus on the 80/20 keywords that matter most.",
            ],
            steps: [
                "Analyze the pillar keyword and business context.",
                "Generate Tier 1 clusters: 5-8 high-priority keywords that directly support the pillar.",
                "Generate Tier 2 clusters: 5-10 supporting keywords that add topical depth.",
                "Optionally generate Tier 3 clusters for comprehensive coverage.",
                "Assign appropriate intent and content type to each cluster.",
                "Ensure variety in keyword types (how-to, what-is, comparison, etc.).",
            ],
            output: [
                "Each cluster should be specific and rankable.",
                "Include a mix of search intents.",
                "Avoid generating duplicate or near-duplicate keywords.",
                "Provide clear rationale for why each cluster supports the pillar.",
                "Fewer high-quality clusters is better than many mediocre ones.",
                "Consider the business's ability to create authoritative content.",
            ]
        );
    }
}
```

---

## Guardrails (Very Important)

These guardrails must be enforced throughout the implementation:

```php
<?php

namespace App\Neuron\Rules;

class ClusterGenerationGuardrails
{
    /**
     * Claude never re-generates everything
     */
    public static function preventFullRegeneration(WorkflowState $state, string $action): void
    {
        if ($action === 'regenerate_all') {
            throw new \InvalidArgumentException(
                'Full regeneration is not allowed. Use incremental changes instead.'
            );
        }
    }

    /**
     * Claude never deletes keywords (only user can)
     */
    public static function preventAutomaticDeletion(array $existingKeywords, array $newKeywords): array
    {
        // Ensure all existing keywords are preserved
        $existingIds = array_map(fn($k) => $k['id'], $existingKeywords);
        $newIds = array_map(fn($k) => $k['id'], $newKeywords);

        foreach ($existingIds as $id) {
            if (!in_array($id, $newIds)) {
                // Re-add removed keyword
                $keyword = collect($existingKeywords)->firstWhere('id', $id);
                $newKeywords[] = $keyword;
            }
        }

        return $newKeywords;
    }

    /**
     * All web access goes through SerpSearchTool
     */
    public static function ensureSerpToolOnly(Agent $agent): void
    {
        $tools = $agent->getTools();
        foreach ($tools as $tool) {
            if ($tool->getName() !== 'serp_search' && str_contains($tool->getName(), 'web')) {
                throw new \InvalidArgumentException(
                    'Only SerpSearchTool is allowed for web access.'
                );
            }
        }
    }

    /**
     * Pillars must be validated before cluster generation
     */
    public static function requireValidatedPillars(WorkflowState $state): void
    {
        $pillars = $state->get('validated_pillars', []);

        foreach ($pillars as $pillar) {
            if (!isset($pillar['serp_data']) || empty($pillar['serp_data'])) {
                throw new \InvalidArgumentException(
                    "Pillar '{$pillar['name']}' must be SERP validated before cluster generation."
                );
            }
        }
    }

    /**
     * Only Tier-1 clusters are validated via SERP
     */
    public static function limitSerpValidation(array $clusters): void
    {
        foreach ($clusters as $cluster) {
            if ($cluster['tier'] !== 1 && ($cluster['serp_validated'] ?? false)) {
                // This shouldn't happen but guard against it
                $cluster['serp_validated'] = false;
            }
        }
    }

    /**
     * Fewer keywords > more keywords (80/20 rule)
     */
    public static function enforceQualityOverQuantity(array $clusters): array
    {
        $tier1 = array_filter($clusters, fn($c) => $c['tier'] === 1);
        $tier2 = array_filter($clusters, fn($c) => $c['tier'] === 2);
        $tier3 = array_filter($clusters, fn($c) => $c['tier'] === 3);

        // Enforce limits
        if (count($tier1) > 8) {
            $tier1 = array_slice($tier1, 0, 8);
        }
        if (count($tier2) > 12) {
            $tier2 = array_slice($tier2, 0, 12);
        }
        if (count($tier3) > 5) {
            $tier3 = array_slice($tier3, 0, 5);
        }

        return array_merge($tier1, $tier2, $tier3);
    }
}
```

---

## Exit Criteria

- [ ] Clusters generated one pillar at a time
- [ ] Each cluster has intent, content type, and tier assignment
- [ ] Tier-1 clusters validated via SERP search
- [ ] Tier-2/Tier-3 clusters not validated (to save API calls)
- [ ] User can approve clusters per pillar
- [ ] User can request more depth (more T2/T3 clusters)
- [ ] User can upgrade/downgrade cluster tiers
- [ ] User can skip a pillar
- [ ] Clusters persisted incrementally to database
- [ ] Workflow advances pillar-by-pillar
- [ ] All guardrails enforced

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Neuron/Dto/ClusterGenerationResponse.php` | Cluster generation DTO |
| `app/Neuron/Dto/ClusterKeyword.php` | Individual cluster DTO |
| `app/Neuron/Agents/ClusterGenerationAgent.php` | Cluster generation agent |
| `app/Neuron/Nodes/ClusterGenerationNode.php` | Cluster generation node |
| `app/Neuron/Rules/ClusterGenerationGuardrails.php` | Guardrail enforcement |

---

## Next Milestone

With clusters generated, proceed to [Milestone 7: Completion & Next Actions](./MILESTONE_7.md) to finalize the workflow and present next steps.
