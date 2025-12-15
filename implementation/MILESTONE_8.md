# Milestone 8 — Refinement & Reruns (Chat-Native)

> **Objective:** Let users refine without restarting.

This milestone implements the refinement system that allows users to iteratively improve their topical map without starting over. Users can expand clusters, add forgotten services, focus on specific strategies, or generate more keywords—all through natural chat commands.

---

## Core Principle

```
Refinement, not regeneration.
- Claude never re-generates everything
- Claude never deletes keywords
- All changes are additive or modifications to existing keywords
- Previous work is always preserved
```

---

## Flow Overview

```
Completed Topical Map
         │
         ▼
    ┌────────────────────────────────────┐
    │  User sends refinement request     │
    │  via chat                          │
    └────────────────┬───────────────────┘
                     │
                     ▼
    ┌────────────────────────────────────┐
    │  Detect refinement type:           │
    │  • expand_pillar                   │
    │  • add_service                     │
    │  • focus_strategy                  │
    │  • generate_more                   │
    └────────────────┬───────────────────┘
                     │
    ┌────────────────┴────────────────────────────────────┐
    │                │                │                   │
    ▼                ▼                ▼                   ▼
Expand          Add New         Focus on          Generate More
Clusters        Service         Strategy          Keywords
    │                │                │                   │
    ▼                ▼                ▼                   ▼
Resume at       Restart at      Modify            Resume at
Cluster Gen     Context         Existing          Cluster Gen
Node            Ingestion       Keywords          Node
    │                │                │                   │
    └────────────────┴────────────────┴───────────────────┘
                                  │
                                  ▼
                     Add new keywords only
                     (never delete existing)
```

---

## Supported Refinement Commands

### 1. Expand Clusters for Pillar

**User says:** "Expand clusters for pillar X" or "Add more keywords for [pillar name]"

**Behavior:**
- Resume workflow at ClusterGenerationNode
- Target specific pillar
- Generate additional Tier-2/Tier-3 clusters
- Add to existing clusters (never replace)

### 2. Add a New Service/Offering

**User says:** "Add a new service I forgot" or "I also offer [service]"

**Behavior:**
- Add service to business context
- Generate new pillar for the service (if warranted)
- Generate clusters for new pillar
- Do NOT regenerate existing pillars/clusters

### 3. Focus on Strategy

**User says:** "Focus on local SEO" or "Add more transactional keywords"

**Behavior:**
- Modify generation parameters
- Generate additional keywords matching the focus
- Add to existing keywords (never replace)

### 4. Generate More Keywords

**User says:** "Generate more transactional keywords" or "I need more how-to content"

**Behavior:**
- Resume at ClusterGenerationNode
- Apply specific filters/focus
- Generate additional matching keywords
- Add to existing clusters

---

## Refinement Detector

```php
<?php

namespace App\Services;

use App\Enums\RefinementType;

class RefinementDetector
{
    /**
     * Detect the type of refinement request from user text
     */
    public function detect(string $text): RefinementType
    {
        $lower = strtolower($text);

        // Expand pillar patterns
        if (preg_match('/expand.*(clusters?|keywords?).*(for|on|under)/i', $text)) {
            return RefinementType::EXPAND_PILLAR;
        }
        if (str_contains($lower, 'more keywords for')) {
            return RefinementType::EXPAND_PILLAR;
        }
        if (str_contains($lower, 'add more') && str_contains($lower, 'pillar')) {
            return RefinementType::EXPAND_PILLAR;
        }

        // Add service patterns
        if (preg_match('/(add|forgot|also offer|new service|another product)/i', $text)) {
            return RefinementType::ADD_SERVICE;
        }
        if (str_contains($lower, 'forgot to mention')) {
            return RefinementType::ADD_SERVICE;
        }

        // Focus strategy patterns
        if (preg_match('/focus (on|more on)/i', $text)) {
            return RefinementType::FOCUS_STRATEGY;
        }
        if (str_contains($lower, 'local seo')) {
            return RefinementType::FOCUS_STRATEGY;
        }

        // Generate more patterns
        if (preg_match('/(more|generate|add).*(transactional|commercial|informational)/i', $text)) {
            return RefinementType::GENERATE_MORE;
        }
        if (str_contains($lower, 'more how-to') || str_contains($lower, 'more tutorials')) {
            return RefinementType::GENERATE_MORE;
        }

        // Default to generic expansion
        return RefinementType::GENERATE_MORE;
    }

    /**
     * Extract target pillar from refinement request
     */
    public function extractTargetPillar(string $text, array $existingPillars): ?string
    {
        foreach ($existingPillars as $pillar) {
            $pillarName = strtolower($pillar['name']);
            if (str_contains(strtolower($text), $pillarName)) {
                return $pillar['id'];
            }
        }

        // Try to match partial names
        foreach ($existingPillars as $pillar) {
            $words = explode(' ', strtolower($pillar['name']));
            foreach ($words as $word) {
                if (strlen($word) > 4 && str_contains(strtolower($text), $word)) {
                    return $pillar['id'];
                }
            }
        }

        return null;
    }

    /**
     * Extract focus parameters from strategy request
     */
    public function extractFocusParameters(string $text): array
    {
        $params = [];

        // Intent focus
        if (str_contains(strtolower($text), 'transactional')) {
            $params['intent_focus'] = 'transactional';
        } elseif (str_contains(strtolower($text), 'commercial')) {
            $params['intent_focus'] = 'commercial';
        } elseif (str_contains(strtolower($text), 'informational')) {
            $params['intent_focus'] = 'informational';
        }

        // Content type focus
        if (str_contains(strtolower($text), 'how-to') || str_contains(strtolower($text), 'tutorial')) {
            $params['content_focus'] = 'tutorial';
        } elseif (str_contains(strtolower($text), 'comparison')) {
            $params['content_focus'] = 'comparison';
        } elseif (str_contains(strtolower($text), 'faq')) {
            $params['content_focus'] = 'faq';
        }

        // Geo focus
        if (str_contains(strtolower($text), 'local')) {
            $params['geo_focus'] = 'local';
        }

        return $params;
    }
}
```

---

## Refinement Type Enum

```php
<?php

namespace App\Enums;

enum RefinementType: string
{
    case EXPAND_PILLAR = 'expand_pillar';
    case ADD_SERVICE = 'add_service';
    case FOCUS_STRATEGY = 'focus_strategy';
    case GENERATE_MORE = 'generate_more';
}
```

---

## Refinement Service

```php
<?php

namespace App\Services;

use App\Models\GenerationRun;
use App\Models\Project;
use App\Models\Keyword;
use App\Neuron\Workflows\TopicalMapChatWorkflow;
use App\Enums\RefinementType;
use App\Enums\GenerationRunStatus;
use App\Enums\WorkflowStep;
use App\Repositories\ChatMessageRepository;

class RefinementService
{
    public function __construct(
        private RefinementDetector $detector,
        private ChatMessageRepository $messageRepo,
        private GenerationRunService $runService
    ) {}

    /**
     * Process a refinement request
     */
    public function processRefinement(
        GenerationRun $completedRun,
        string $userRequest
    ): array {

        // Detect refinement type
        $refinementType = $this->detector->detect($userRequest);

        // Get existing workflow state
        $state = $completedRun->workflow_state ?? [];

        // Process based on refinement type
        return match($refinementType) {
            RefinementType::EXPAND_PILLAR => $this->expandPillar($completedRun, $userRequest, $state),
            RefinementType::ADD_SERVICE => $this->addService($completedRun, $userRequest, $state),
            RefinementType::FOCUS_STRATEGY => $this->focusStrategy($completedRun, $userRequest, $state),
            RefinementType::GENERATE_MORE => $this->generateMore($completedRun, $userRequest, $state),
        };
    }

    /**
     * Expand clusters for a specific pillar
     */
    private function expandPillar(GenerationRun $run, string $request, array $state): array
    {
        $validatedPillars = $state['validated_pillars'] ?? [];
        $targetPillarId = $this->detector->extractTargetPillar($request, $validatedPillars);

        if (!$targetPillarId) {
            // Ask user to specify pillar
            return [
                'status' => 'needs_clarification',
                'message' => 'Which pillar would you like to expand?',
                'options' => array_map(fn($p) => $p['name'], $validatedPillars),
            ];
        }

        // Find pillar index
        $pillarIndex = null;
        foreach ($validatedPillars as $index => $pillar) {
            if ($pillar['id'] === $targetPillarId) {
                $pillarIndex = $index;
                break;
            }
        }

        // Update run state to resume at specific pillar
        $run->update([
            'status' => GenerationRunStatus::PROCESSING,
            'current_step' => WorkflowStep::CLUSTER_GENERATION,
            'workflow_state' => array_merge($state, [
                'refinement_mode' => true,
                'refinement_type' => 'expand_pillar',
                'target_pillar_index' => $pillarIndex,
                'expand_tiers' => [2, 3], // Only expand T2/T3
            ]),
        ]);

        // Resume workflow
        $workflow = new TopicalMapChatWorkflow($run);
        return $workflow->process($request);
    }

    /**
     * Add a new service/offering
     */
    private function addService(GenerationRun $run, string $request, array $state): array
    {
        // Update business context with new service
        $businessContext = $state['business_context'] ?? [];

        // Extract new service from request
        $newService = $this->extractServiceFromRequest($request);

        // Add to business context
        $businessContext['additional_offerings'] = $businessContext['additional_offerings'] ?? [];
        $businessContext['additional_offerings'][] = $newService;

        // Create new pillar proposal for the service
        $run->update([
            'status' => GenerationRunStatus::PROCESSING,
            'current_step' => WorkflowStep::PILLAR_PROPOSAL,
            'workflow_state' => array_merge($state, [
                'refinement_mode' => true,
                'refinement_type' => 'add_service',
                'business_context' => $businessContext,
                'new_service' => $newService,
                'generate_single_pillar' => true,
            ]),
        ]);

        // Resume workflow
        $workflow = new TopicalMapChatWorkflow($run);
        return $workflow->process($request);
    }

    /**
     * Focus on a specific strategy
     */
    private function focusStrategy(GenerationRun $run, string $request, array $state): array
    {
        $focusParams = $this->detector->extractFocusParameters($request);

        // Update state with focus parameters
        $run->update([
            'status' => GenerationRunStatus::PROCESSING,
            'current_step' => WorkflowStep::CLUSTER_GENERATION,
            'workflow_state' => array_merge($state, [
                'refinement_mode' => true,
                'refinement_type' => 'focus_strategy',
                'focus_parameters' => $focusParams,
                'generate_focused_clusters' => true,
            ]),
        ]);

        // Resume workflow
        $workflow = new TopicalMapChatWorkflow($run);
        return $workflow->process($request);
    }

    /**
     * Generate more keywords with specific criteria
     */
    private function generateMore(GenerationRun $run, string $request, array $state): array
    {
        $focusParams = $this->detector->extractFocusParameters($request);

        $run->update([
            'status' => GenerationRunStatus::PROCESSING,
            'current_step' => WorkflowStep::CLUSTER_GENERATION,
            'workflow_state' => array_merge($state, [
                'refinement_mode' => true,
                'refinement_type' => 'generate_more',
                'focus_parameters' => $focusParams,
                'all_pillars' => true, // Generate for all pillars
            ]),
        ]);

        $workflow = new TopicalMapChatWorkflow($run);
        return $workflow->process($request);
    }

    /**
     * Extract service from natural language request
     */
    private function extractServiceFromRequest(string $request): array
    {
        // Simple extraction - could use Claude for more complex parsing
        $patterns = [
            '/(?:also offer|forgot to mention|new service|add)\s+(.+?)(?:\.|$)/i',
            '/(?:we also|I also)\s+(?:sell|offer|provide)\s+(.+?)(?:\.|$)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $request, $matches)) {
                return [
                    'name' => trim($matches[1]),
                    'type' => 'service',
                    'description' => "User-added service: " . trim($matches[1]),
                    'isPrimary' => false,
                ];
            }
        }

        // Fallback - use the whole request
        return [
            'name' => 'Additional Service',
            'type' => 'service',
            'description' => $request,
            'isPrimary' => false,
        ];
    }
}
```

---

## Modified Cluster Generation Node (Refinement-Aware)

```php
<?php

namespace App\Neuron\Nodes;

// ... existing imports ...

class ClusterGenerationNode extends BaseTopicalMapNode
{
    public function __invoke(
        PillarsValidatedEvent|GenerateClustersEvent|ClustersApprovedEvent $event,
        WorkflowState $state
    ): GenerateClustersEvent|AllPillarsProcessedEvent {

        // Check if in refinement mode
        if ($state->get('refinement_mode', false)) {
            return $this->handleRefinement($state);
        }

        // ... existing implementation ...
    }

    /**
     * Handle refinement-specific cluster generation
     */
    private function handleRefinement(WorkflowState $state): GenerateClustersEvent|AllPillarsProcessedEvent
    {
        $refinementType = $state->get('refinement_type');

        return match($refinementType) {
            'expand_pillar' => $this->handleExpandPillar($state),
            'focus_strategy' => $this->handleFocusStrategy($state),
            'generate_more' => $this->handleGenerateMore($state),
            default => $this->handleGenerateMore($state),
        };
    }

    /**
     * Handle expand pillar refinement
     */
    private function handleExpandPillar(WorkflowState $state): GenerateClustersEvent
    {
        $pillarIndex = $state->get('target_pillar_index');
        $validatedPillars = $state->get('validated_pillars', []);
        $pillar = $validatedPillars[$pillarIndex] ?? null;

        if (!$pillar) {
            throw new \RuntimeException('Target pillar not found');
        }

        // Get existing clusters for this pillar
        $existingClusters = $state->get("clusters_pillar_{$pillarIndex}", []);

        // Generate additional clusters (T2/T3 only)
        $additionalClusters = $this->generateAdditionalClusters(
            $pillar,
            $existingClusters,
            $state->get('expand_tiers', [2, 3]),
            $state
        );

        // Merge with existing (never replace)
        $mergedClusters = $this->mergeClusters($existingClusters, $additionalClusters);

        // Store updated clusters
        $state->set("clusters_pillar_{$pillarIndex}", $mergedClusters);

        // Present for approval
        return $this->presentClustersForApproval(
            $pillar,
            $mergedClusters,
            $pillarIndex,
            $state,
            true // isRefinement flag
        );
    }

    /**
     * Generate additional clusters without replacing existing ones
     */
    private function generateAdditionalClusters(
        array $pillar,
        array $existingClusters,
        array $tiers,
        WorkflowState $state
    ): array {

        $agent = ClusterGenerationAgent::make();

        // Get existing cluster names to avoid
        $existingNames = $this->getAllClusterNames($existingClusters);

        $prompt = "Generate ADDITIONAL cluster keywords for pillar '{$pillar['validated_name']}'.\n\n";
        $prompt .= "## IMPORTANT: These keywords already exist (DO NOT duplicate):\n";
        $prompt .= implode(', ', $existingNames) . "\n\n";
        $prompt .= "## Generate new keywords for tiers: " . implode(', ', array_map(fn($t) => "Tier {$t}", $tiers)) . "\n";
        $prompt .= "## Focus on adding depth and variety, not duplicating existing coverage.\n";

        // Add focus parameters if present
        $focusParams = $state->get('focus_parameters', []);
        if (!empty($focusParams)) {
            $prompt .= "\n## Focus on:\n";
            if (isset($focusParams['intent_focus'])) {
                $prompt .= "- Intent: {$focusParams['intent_focus']}\n";
            }
            if (isset($focusParams['content_focus'])) {
                $prompt .= "- Content type: {$focusParams['content_focus']}\n";
            }
            if (isset($focusParams['geo_focus'])) {
                $prompt .= "- Geographic focus: {$focusParams['geo_focus']}\n";
            }
        }

        $response = $agent->structured(
            new \NeuronAI\Chat\Messages\UserMessage($prompt),
            ClusterGenerationResponse::class
        );

        // Return only the new clusters
        return [
            'tier1' => in_array(1, $tiers) ? $this->serializeClusters($response->tier1Clusters, 1) : [],
            'tier2' => in_array(2, $tiers) ? $this->serializeClusters($response->tier2Clusters, 2) : [],
            'tier3' => in_array(3, $tiers) ? $this->serializeClusters($response->tier3Clusters, 3) : [],
        ];
    }

    /**
     * Merge new clusters with existing (never replace)
     */
    private function mergeClusters(array $existing, array $additional): array
    {
        // Preserve all existing clusters
        $merged = $existing;

        // Add new clusters to each tier
        foreach (['tier1', 'tier2', 'tier3'] as $tier) {
            if (!empty($additional[$tier])) {
                $merged[$tier] = array_merge(
                    $merged[$tier] ?? [],
                    $additional[$tier]
                );
            }
        }

        return $merged;
    }

    /**
     * Get all cluster names from a cluster structure
     */
    private function getAllClusterNames(array $clusters): array
    {
        $names = [];
        foreach (['tier1', 'tier2', 'tier3'] as $tier) {
            foreach ($clusters[$tier] ?? [] as $cluster) {
                $names[] = $cluster['name'];
            }
        }
        return $names;
    }

    /**
     * Handle focus strategy refinement
     */
    private function handleFocusStrategy(WorkflowState $state): GenerateClustersEvent|AllPillarsProcessedEvent
    {
        $focusParams = $state->get('focus_parameters', []);
        $validatedPillars = $state->get('validated_pillars', []);

        // Generate focused clusters for each pillar
        foreach ($validatedPillars as $index => $pillar) {
            $existingClusters = $state->get("clusters_pillar_{$index}", []);

            // Generate additional focused clusters
            $additionalClusters = $this->generateFocusedClusters(
                $pillar,
                $existingClusters,
                $focusParams,
                $state
            );

            // Merge
            $merged = $this->mergeClusters($existingClusters, $additionalClusters);
            $state->set("clusters_pillar_{$index}", $merged);
        }

        // Present summary
        return $this->presentRefinementSummary($state, $focusParams);
    }

    /**
     * Generate clusters with specific focus
     */
    private function generateFocusedClusters(
        array $pillar,
        array $existingClusters,
        array $focusParams,
        WorkflowState $state
    ): array {
        // Similar to generateAdditionalClusters but with strong focus constraints
        // Implementation similar to above with focus parameters enforced

        return [
            'tier1' => [],
            'tier2' => [], // Generate focused T2 clusters
            'tier3' => [],
        ];
    }
}
```

---

## Guardrails Enforcement

```php
<?php

namespace App\Neuron\Middleware;

use App\Models\Keyword;
use App\Models\GenerationRun;

class RefinementGuardrails
{
    /**
     * Ensure refinement never deletes existing keywords
     */
    public static function validateRefinement(
        GenerationRun $run,
        array $beforeKeywordIds,
        array $afterKeywordIds
    ): void {
        // Check no keywords were deleted
        $deleted = array_diff($beforeKeywordIds, $afterKeywordIds);

        if (!empty($deleted)) {
            throw new \InvalidArgumentException(
                'Refinement attempted to delete keywords. This is not allowed. ' .
                'Deleted IDs: ' . implode(', ', $deleted)
            );
        }
    }

    /**
     * Ensure we're adding, not replacing
     */
    public static function validateAddOnly(
        array $existingClusters,
        array $newClusters
    ): array {
        // All existing clusters must be preserved
        $existingIds = [];
        foreach (['tier1', 'tier2', 'tier3'] as $tier) {
            foreach ($existingClusters[$tier] ?? [] as $cluster) {
                $existingIds[] = $cluster['id'];
            }
        }

        $newIds = [];
        foreach (['tier1', 'tier2', 'tier3'] as $tier) {
            foreach ($newClusters[$tier] ?? [] as $cluster) {
                $newIds[] = $cluster['id'];
            }
        }

        // Check all existing IDs are present
        $missing = array_diff($existingIds, $newIds);
        if (!empty($missing)) {
            // Re-add missing clusters
            foreach ($missing as $missingId) {
                // Find and restore the missing cluster
                foreach (['tier1', 'tier2', 'tier3'] as $tier) {
                    foreach ($existingClusters[$tier] ?? [] as $cluster) {
                        if ($cluster['id'] === $missingId) {
                            $newClusters[$tier][] = $cluster;
                        }
                    }
                }
            }
        }

        return $newClusters;
    }

    /**
     * Prevent full regeneration
     */
    public static function preventFullRegeneration(
        string $action,
        WorkflowState $state
    ): void {
        $blockedActions = [
            'regenerate_all',
            'start_over',
            'delete_all',
            'clear_keywords',
        ];

        if (in_array($action, $blockedActions)) {
            throw new \InvalidArgumentException(
                "Action '{$action}' is not allowed in refinement mode. " .
                "Refinement should add or modify, never delete or restart."
            );
        }
    }
}
```

---

## Chat Controller Update

```php
<?php

// In ChatController.php, update the store method:

public function store(Request $request, Project $project): JsonResponse
{
    $this->authorize('update', $project);

    $validated = $request->validate([
        'content' => 'required|string',
        'structured_content' => 'nullable|array',
    ]);

    // Get or create generation run
    $activeRun = $this->runService->getActiveRun($project);

    if (!$activeRun) {
        // Check for completed runs that can be refined
        $completedRun = $this->runService->getLatestCompletedRun($project);

        if ($completedRun && $this->isRefinementRequest($validated['content'])) {
            // This is a refinement request on completed run
            return $this->handleRefinement($completedRun, $validated);
        }

        // This is an initial prompt - start new run
        $activeRun = $this->runService->startNewRun(
            $project,
            $validated['content']
        );
    }

    // Process through workflow
    $response = $this->workflowService->processMessage(
        $activeRun,
        $validated['content'],
        $validated['structured_content'] ?? null
    );

    return response()->json([
        'run' => [
            'id' => $activeRun->id,
            'status' => $activeRun->fresh()->status,
            'current_step' => $activeRun->fresh()->current_step,
        ],
        'response' => $response,
    ]);
}

private function isRefinementRequest(string $content): bool
{
    $refinementKeywords = [
        'expand', 'add more', 'forgot', 'also offer',
        'focus on', 'local seo', 'more keywords',
        'transactional', 'commercial', 'how-to',
    ];

    $lower = strtolower($content);
    foreach ($refinementKeywords as $keyword) {
        if (str_contains($lower, $keyword)) {
            return true;
        }
    }

    return false;
}

private function handleRefinement(GenerationRun $completedRun, array $validated): JsonResponse
{
    $refinementService = app(RefinementService::class);

    $response = $refinementService->processRefinement(
        $completedRun,
        $validated['content']
    );

    return response()->json([
        'run' => [
            'id' => $completedRun->id,
            'status' => $completedRun->fresh()->status,
            'current_step' => $completedRun->fresh()->current_step,
        ],
        'response' => $response,
        'is_refinement' => true,
    ]);
}
```

---

## Why This Works Better Than "Long-Running"

| Long-Running Approach | Chat-Driven Approach |
|----------------------|---------------------|
| User waits on long jobs | Immediate responses |
| All-or-nothing generation | Incremental refinement |
| Lost context on errors | State preserved always |
| No user control mid-process | User controls each step |
| Unpredictable outcomes | Deterministic workflow |
| Hard to modify results | Easy iterative refinement |

**The chat-driven approach:**
- Feels like a conversation, not a batch job
- No waiting on long background processes
- User stays in control throughout
- Claude never loses context
- System stays deterministic and predictable
- Refinement is natural, not a separate feature

---

## Exit Criteria

- [ ] Refinement detector correctly identifies request types
- [ ] "Expand clusters for pillar X" adds new clusters to specific pillar
- [ ] "Add a new service I forgot" creates new pillar and clusters
- [ ] "Focus on local SEO" generates location-focused keywords
- [ ] "Generate more transactional keywords" adds intent-specific keywords
- [ ] Existing keywords are NEVER deleted during refinement
- [ ] Refinement preserves all previous work
- [ ] Guardrails prevent regeneration of everything
- [ ] Each refinement command resumes workflow at appropriate step

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Enums/RefinementType.php` | Refinement type enum |
| `app/Services/RefinementDetector.php` | Detect refinement type from text |
| `app/Services/RefinementService.php` | Orchestrate refinement operations |
| `app/Neuron/Middleware/RefinementGuardrails.php` | Enforce refinement guardrails |

---

## Summary

This milestone completes the SEO Topical Map Engine by enabling true iterative refinement through natural chat commands. Users can continually expand and improve their keyword maps without ever starting over or losing previous work.

The system now provides:
1. **Initial generation** (Milestones 0-7)
2. **Iterative refinement** (Milestone 8)
3. **Preservation of all work** (guardrails)
4. **Natural chat interface** (throughout)

Together, these capabilities create a durable, expandable foundation for topical authority that evolves with the user's business.
