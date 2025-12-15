# Milestone 7 — Completion & Next Actions

> **Objective:** Close the loop and tee up future workflows.

This milestone implements the completion phase where Claude summarizes what was accomplished, presents the final topical map, and suggests actionable next steps for the user.

---

## Flow Overview

```
AllPillarsProcessedEvent (from Milestone 6)
                │
                ▼
    ┌───────────────────────────┐
    │     CompletionNode        │
    └───────────────┬───────────┘
                    │
                    ▼
    ┌───────────────────────────┐
    │   Compile summary:        │
    │   • Total pillars         │
    │   • Total clusters        │
    │   • Keyword breakdown     │
    │   • Coverage assessment   │
    └───────────────┬───────────┘
                    │
                    ▼
    ┌───────────────────────────┐
    │   Generate next actions:  │
    │   • Competitor analysis   │
    │   • Search volume data    │
    │   • Expand Tier-2 clusters│
    │   • Content roadmap       │
    └───────────────┬───────────┘
                    │
                    ▼
    ┌───────────────────────────┐
    │   Present summary to user │
    │   Mark run as complete    │
    └───────────────┬───────────┘
                    │
                    ▼
              StopEvent
```

---

## Structured Output Classes

### Completion Summary DTO

```php
<?php

namespace App\Neuron\Dto;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;

class CompletionSummary
{
    #[SchemaProperty(description: 'Total number of pillar keywords created', required: true)]
    public int $pillarCount;

    #[SchemaProperty(description: 'Total number of cluster keywords created', required: true)]
    public int $clusterCount;

    #[SchemaProperty(description: 'Total keywords overall', required: true)]
    public int $totalKeywords;

    /**
     * @var \App\Neuron\Dto\PillarSummary[]
     */
    #[SchemaProperty(description: 'Summary for each pillar', required: true)]
    #[ArrayOf(PillarSummary::class)]
    public array $pillars;

    #[SchemaProperty(description: 'Breakdown by search intent', required: true)]
    public IntentBreakdown $intentBreakdown;

    #[SchemaProperty(description: 'Breakdown by content type', required: true)]
    public ContentTypeBreakdown $contentTypeBreakdown;

    #[SchemaProperty(description: 'Overall assessment of the topical map', required: true)]
    public string $overallAssessment;

    /**
     * @var \App\Neuron\Dto\SuggestedNextAction[]
     */
    #[SchemaProperty(description: 'Suggested next actions for the user', required: true)]
    #[ArrayOf(SuggestedNextAction::class)]
    public array $suggestedNextActions;
}

class PillarSummary
{
    #[SchemaProperty(description: 'Pillar name', required: true)]
    public string $name;

    #[SchemaProperty(description: 'Number of Tier-1 clusters', required: true)]
    public int $tier1Count;

    #[SchemaProperty(description: 'Number of Tier-2 clusters', required: true)]
    public int $tier2Count;

    #[SchemaProperty(description: 'Number of Tier-3 clusters', required: true)]
    public int $tier3Count;

    #[SchemaProperty(description: 'Total cluster count', required: true)]
    public int $totalClusters;

    #[SchemaProperty(description: 'Was this pillar skipped?', required: true)]
    public bool $wasSkipped;
}

class IntentBreakdown
{
    #[SchemaProperty(description: 'Count of informational keywords', required: true)]
    public int $informational;

    #[SchemaProperty(description: 'Count of commercial keywords', required: true)]
    public int $commercial;

    #[SchemaProperty(description: 'Count of transactional keywords', required: true)]
    public int $transactional;

    #[SchemaProperty(description: 'Count of navigational keywords', required: true)]
    public int $navigational;
}

class ContentTypeBreakdown
{
    #[SchemaProperty(description: 'Count of pillar pages', required: true)]
    public int $pillarPages;

    #[SchemaProperty(description: 'Count of articles', required: true)]
    public int $articles;

    #[SchemaProperty(description: 'Count of guides/tutorials', required: true)]
    public int $guides;

    #[SchemaProperty(description: 'Count of comparisons', required: true)]
    public int $comparisons;

    #[SchemaProperty(description: 'Count of landing pages', required: true)]
    public int $landingPages;

    #[SchemaProperty(description: 'Count of FAQ content', required: true)]
    public int $faq;
}

class SuggestedNextAction
{
    #[SchemaProperty(description: 'Action identifier', required: true)]
    public string $id;

    #[SchemaProperty(description: 'Short action title', required: true)]
    public string $title;

    #[SchemaProperty(description: 'Detailed description of the action', required: true)]
    public string $description;

    #[SchemaProperty(description: 'Priority: high, medium, low', required: true)]
    public string $priority;

    #[SchemaProperty(description: 'Estimated effort: quick, moderate, significant', required: true)]
    public string $effort;

    #[SchemaProperty(description: 'Expected impact on SEO', required: true)]
    public string $impact;

    #[SchemaProperty(description: 'Is this action available in the app?', required: true)]
    public bool $availableInApp;
}
```

---

## Completion Node

```php
<?php

namespace App\Neuron\Nodes;

use App\Neuron\Events\AllPillarsProcessedEvent;
use App\Neuron\Dto\CompletionSummary;
use App\Models\Keyword;
use App\Enums\AssistantMessageType;
use App\Enums\GenerationRunStatus;
use App\Enums\WorkflowStep;
use NeuronAI\Workflow\StopEvent;
use NeuronAI\Workflow\WorkflowState;

class CompletionNode extends BaseTopicalMapNode
{
    public function __invoke(
        AllPillarsProcessedEvent $event,
        WorkflowState $state
    ): StopEvent {

        $this->updateRunStatus(
            GenerationRunStatus::PROCESSING,
            WorkflowStep::COMPLETION
        );

        // Compile final summary
        $summary = $this->compileSummary($state, $event->summary);

        // Generate next actions
        $summary->suggestedNextActions = $this->generateNextActions($state, $summary);

        // Store summary in state
        $state->set('completion_summary', $this->serializeSummary($summary));

        // Create completion message
        $textContent = $this->buildCompletionMessage($summary);

        $structuredContent = [
            'type' => 'confirmation',
            'workflow_step' => 'completion',
            'summary' => $this->serializeSummary($summary),
            'next_actions' => array_map(fn($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'description' => $a->description,
                'priority' => $a->priority,
                'effort' => $a->effort,
                'impact' => $a->impact,
                'available' => $a->availableInApp,
            ], $summary->suggestedNextActions),
        ];

        $this->createAssistantMessage(
            AssistantMessageType::CONFIRMATION,
            $textContent,
            $structuredContent
        );

        // Mark run as complete
        $this->run->markAsCompleted();

        // Mark workflow step complete
        $this->markStepComplete($state, 'completion');

        // Store final summary on run
        $this->run->update([
            'workflow_state' => array_merge(
                $this->run->workflow_state ?? [],
                ['completion_summary' => $this->serializeSummary($summary)]
            ),
        ]);

        return new StopEvent();
    }

    /**
     * Compile summary from workflow state
     */
    private function compileSummary(WorkflowState $state, array $eventSummary): CompletionSummary
    {
        $validatedPillars = $state->get('validated_pillars', []);
        $completedIndexes = $state->get('completed_pillar_indexes', []);
        $skippedIndexes = $state->get('skipped_pillar_indexes', []);

        $summary = new CompletionSummary();
        $summary->pillarCount = count($completedIndexes);
        $summary->pillars = [];

        $totalClusters = 0;
        $intentBreakdown = ['informational' => 0, 'commercial' => 0, 'transactional' => 0, 'navigational' => 0];
        $contentBreakdown = ['pillar_pages' => 0, 'articles' => 0, 'guides' => 0, 'comparisons' => 0, 'landing_pages' => 0, 'faq' => 0];

        // Add pillar pages to content count
        $contentBreakdown['pillar_pages'] = $summary->pillarCount;

        foreach ($validatedPillars as $index => $pillar) {
            $wasSkipped = in_array($index, $skippedIndexes);
            $clusters = $state->get("clusters_pillar_{$index}", []);

            $tier1 = $clusters['tier1'] ?? [];
            $tier2 = $clusters['tier2'] ?? [];
            $tier3 = $clusters['tier3'] ?? [];

            $pillarSummary = new PillarSummary();
            $pillarSummary->name = $pillar['validated_name'] ?? $pillar['name'];
            $pillarSummary->tier1Count = count($tier1);
            $pillarSummary->tier2Count = count($tier2);
            $pillarSummary->tier3Count = count($tier3);
            $pillarSummary->totalClusters = $pillarSummary->tier1Count + $pillarSummary->tier2Count + $pillarSummary->tier3Count;
            $pillarSummary->wasSkipped = $wasSkipped;

            $summary->pillars[] = $pillarSummary;

            if (!$wasSkipped) {
                $totalClusters += $pillarSummary->totalClusters;

                // Count intents and content types
                foreach (array_merge($tier1, $tier2, $tier3) as $cluster) {
                    $intent = strtolower($cluster['intent'] ?? 'informational');
                    if (isset($intentBreakdown[$intent])) {
                        $intentBreakdown[$intent]++;
                    }

                    $contentType = strtolower($cluster['content_type'] ?? 'article');
                    $contentKey = $this->mapContentType($contentType);
                    if (isset($contentBreakdown[$contentKey])) {
                        $contentBreakdown[$contentKey]++;
                    }
                }
            }
        }

        $summary->clusterCount = $totalClusters;
        $summary->totalKeywords = $summary->pillarCount + $totalClusters;

        $summary->intentBreakdown = new IntentBreakdown();
        $summary->intentBreakdown->informational = $intentBreakdown['informational'];
        $summary->intentBreakdown->commercial = $intentBreakdown['commercial'];
        $summary->intentBreakdown->transactional = $intentBreakdown['transactional'];
        $summary->intentBreakdown->navigational = $intentBreakdown['navigational'];

        $summary->contentTypeBreakdown = new ContentTypeBreakdown();
        $summary->contentTypeBreakdown->pillarPages = $contentBreakdown['pillar_pages'];
        $summary->contentTypeBreakdown->articles = $contentBreakdown['articles'];
        $summary->contentTypeBreakdown->guides = $contentBreakdown['guides'];
        $summary->contentTypeBreakdown->comparisons = $contentBreakdown['comparisons'];
        $summary->contentTypeBreakdown->landingPages = $contentBreakdown['landing_pages'];
        $summary->contentTypeBreakdown->faq = $contentBreakdown['faq'];

        // Generate overall assessment
        $summary->overallAssessment = $this->generateAssessment($summary);

        return $summary;
    }

    /**
     * Map content type to breakdown key
     */
    private function mapContentType(string $contentType): string
    {
        return match(true) {
            str_contains($contentType, 'pillar') => 'pillar_pages',
            str_contains($contentType, 'guide') || str_contains($contentType, 'tutorial') => 'guides',
            str_contains($contentType, 'comparison') || str_contains($contentType, 'vs') => 'comparisons',
            str_contains($contentType, 'landing') => 'landing_pages',
            str_contains($contentType, 'faq') => 'faq',
            default => 'articles',
        };
    }

    /**
     * Generate overall assessment
     */
    private function generateAssessment(CompletionSummary $summary): string
    {
        $avgClustersPerPillar = $summary->pillarCount > 0
            ? round($summary->clusterCount / $summary->pillarCount, 1)
            : 0;

        $assessment = "Your topical map now contains {$summary->totalKeywords} keywords ";
        $assessment .= "across {$summary->pillarCount} pillars (average {$avgClustersPerPillar} clusters per pillar). ";

        // Assess intent balance
        $total = $summary->intentBreakdown->informational + $summary->intentBreakdown->commercial +
                 $summary->intentBreakdown->transactional + $summary->intentBreakdown->navigational;

        if ($total > 0) {
            $infoPercent = round(($summary->intentBreakdown->informational / $total) * 100);
            $commercialPercent = round((($summary->intentBreakdown->commercial + $summary->intentBreakdown->transactional) / $total) * 100);

            $assessment .= "Intent mix: {$infoPercent}% informational, {$commercialPercent}% commercial/transactional. ";

            if ($infoPercent > 80) {
                $assessment .= "Consider adding more commercial-intent keywords for conversion.";
            } elseif ($commercialPercent > 70) {
                $assessment .= "Good commercial focus. Consider adding informational content for authority.";
            } else {
                $assessment .= "Good balance of intent types for topical authority.";
            }
        }

        return $assessment;
    }

    /**
     * Generate suggested next actions
     */
    private function generateNextActions(WorkflowState $state, CompletionSummary $summary): array
    {
        $actions = [];

        // 1. Competitor deep analysis
        $actions[] = $this->createNextAction(
            'competitor_analysis',
            'Run Competitor Deep Analysis',
            'Analyze top-ranking competitors for each pillar to identify content gaps and keyword opportunities they\'re targeting that you might be missing.',
            'high',
            'moderate',
            'Discover competitor keywords and content strategies',
            false // Not yet implemented
        );

        // 2. Search volume data
        $actions[] = $this->createNextAction(
            'search_volume',
            'Pull Search Volume & Difficulty Data',
            'Enrich your keywords with actual search volume and keyword difficulty scores to prioritize content creation.',
            'high',
            'quick',
            'Prioritize keywords by traffic potential',
            false
        );

        // 3. Expand Tier-2 clusters if any pillars skipped
        $skippedCount = count($state->get('skipped_pillar_indexes', []));
        if ($skippedCount > 0) {
            $actions[] = $this->createNextAction(
                'complete_skipped',
                "Complete {$skippedCount} Skipped Pillar(s)",
                'Generate clusters for the pillars you skipped during this session.',
                'medium',
                'moderate',
                'Complete your topical coverage',
                true
            );
        }

        // 4. Expand Tier-2 clusters
        $actions[] = $this->createNextAction(
            'expand_tier2',
            'Expand Tier-2 Clusters',
            'Generate additional supporting clusters to deepen topical coverage for each pillar.',
            'medium',
            'moderate',
            'Increase topical depth and authority',
            true
        );

        // 5. Content roadmap
        $actions[] = $this->createNextAction(
            'content_roadmap',
            'Create Content Roadmap',
            'Turn your keyword map into a prioritized content calendar with recommended publishing order.',
            'medium',
            'significant',
            'Strategic content sequencing',
            false
        );

        // 6. Local SEO expansion (if applicable)
        $businessContext = $state->get('business_context', []);
        $geoScope = $businessContext['geography']['scope'] ?? 'national';

        if (in_array($geoScope, ['local', 'regional'])) {
            $actions[] = $this->createNextAction(
                'local_seo',
                'Expand Local SEO Keywords',
                'Generate location-specific variations of your keywords for local search targeting.',
                'high',
                'moderate',
                'Capture local search traffic',
                true
            );
        }

        return $actions;
    }

    /**
     * Create a next action object
     */
    private function createNextAction(
        string $id,
        string $title,
        string $description,
        string $priority,
        string $effort,
        string $impact,
        bool $availableInApp
    ): SuggestedNextAction {
        $action = new SuggestedNextAction();
        $action->id = $id;
        $action->title = $title;
        $action->description = $description;
        $action->priority = $priority;
        $action->effort = $effort;
        $action->impact = $impact;
        $action->availableInApp = $availableInApp;
        return $action;
    }

    /**
     * Build human-readable completion message
     */
    private function buildCompletionMessage(CompletionSummary $summary): string
    {
        $message = "## Topical Map Complete! \n\n";

        // Main stats
        $message .= "### Summary\n";
        $message .= "- **{$summary->pillarCount}** pillar keywords\n";
        $message .= "- **{$summary->clusterCount}** cluster keywords\n";
        $message .= "- **{$summary->totalKeywords}** total keywords\n\n";

        // Pillar breakdown
        $message .= "### Pillars Created\n";
        foreach ($summary->pillars as $pillar) {
            if (!$pillar->wasSkipped) {
                $message .= "- **{$pillar->name}**: {$pillar->totalClusters} clusters ";
                $message .= "({$pillar->tier1Count} T1, {$pillar->tier2Count} T2, {$pillar->tier3Count} T3)\n";
            } else {
                $message .= "- ~~{$pillar->name}~~ (skipped)\n";
            }
        }
        $message .= "\n";

        // Intent breakdown
        $message .= "### Intent Distribution\n";
        $message .= "- Informational: {$summary->intentBreakdown->informational}\n";
        $message .= "- Commercial: {$summary->intentBreakdown->commercial}\n";
        $message .= "- Transactional: {$summary->intentBreakdown->transactional}\n";
        if ($summary->intentBreakdown->navigational > 0) {
            $message .= "- Navigational: {$summary->intentBreakdown->navigational}\n";
        }
        $message .= "\n";

        // Assessment
        $message .= "### Assessment\n";
        $message .= "{$summary->overallAssessment}\n\n";

        // Next actions
        $message .= "---\n\n";
        $message .= "### Suggested Next Steps\n\n";

        foreach ($summary->suggestedNextActions as $action) {
            $priorityEmoji = match($action->priority) {
                'high' => '🔴',
                'medium' => '🟡',
                'low' => '🟢',
                default => '⚪',
            };

            $available = $action->availableInApp ? '' : ' _(coming soon)_';

            $message .= "**{$priorityEmoji} {$action->title}**{$available}\n";
            $message .= "{$action->description}\n";
            $message .= "_Effort: {$action->effort} | Impact: {$action->impact}_\n\n";
        }

        $message .= "---\n\n";
        $message .= "Your topical map is now saved and you can view it in the Keywords section. ";
        $message .= "You can always come back to refine or expand it.";

        return $message;
    }

    /**
     * Serialize summary for storage
     */
    private function serializeSummary(CompletionSummary $summary): array
    {
        return [
            'pillar_count' => $summary->pillarCount,
            'cluster_count' => $summary->clusterCount,
            'total_keywords' => $summary->totalKeywords,
            'pillars' => array_map(fn($p) => [
                'name' => $p->name,
                'tier1_count' => $p->tier1Count,
                'tier2_count' => $p->tier2Count,
                'tier3_count' => $p->tier3Count,
                'total_clusters' => $p->totalClusters,
                'was_skipped' => $p->wasSkipped,
            ], $summary->pillars),
            'intent_breakdown' => [
                'informational' => $summary->intentBreakdown->informational,
                'commercial' => $summary->intentBreakdown->commercial,
                'transactional' => $summary->intentBreakdown->transactional,
                'navigational' => $summary->intentBreakdown->navigational,
            ],
            'content_type_breakdown' => [
                'pillar_pages' => $summary->contentTypeBreakdown->pillarPages,
                'articles' => $summary->contentTypeBreakdown->articles,
                'guides' => $summary->contentTypeBreakdown->guides,
                'comparisons' => $summary->contentTypeBreakdown->comparisons,
                'landing_pages' => $summary->contentTypeBreakdown->landingPages,
                'faq' => $summary->contentTypeBreakdown->faq,
            ],
            'overall_assessment' => $summary->overallAssessment,
            'suggested_next_actions' => array_map(fn($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'description' => $a->description,
                'priority' => $a->priority,
                'effort' => $a->effort,
                'impact' => $a->impact,
                'available_in_app' => $a->availableInApp,
            ], $summary->suggestedNextActions),
        ];
    }
}
```

---

## Exit Criteria

- [ ] Completion node compiles summary statistics
- [ ] Summary includes pillar and cluster counts
- [ ] Intent breakdown calculated
- [ ] Content type breakdown calculated
- [ ] Overall assessment generated
- [ ] Next actions suggested based on context
- [ ] Run marked as completed
- [ ] Summary stored in database
- [ ] StopEvent returned to end workflow

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Neuron/Dto/CompletionSummary.php` | Completion summary DTO |
| `app/Neuron/Dto/PillarSummary.php` | Pillar summary DTO |
| `app/Neuron/Dto/IntentBreakdown.php` | Intent breakdown DTO |
| `app/Neuron/Dto/ContentTypeBreakdown.php` | Content type breakdown DTO |
| `app/Neuron/Dto/SuggestedNextAction.php` | Next action DTO |
| `app/Neuron/Nodes/CompletionNode.php` | Completion workflow node |

---

## Next Milestone

With the initial workflow complete, proceed to [Milestone 8: Refinement & Reruns](./MILESTONE_8.md) to implement iterative refinement capabilities.
