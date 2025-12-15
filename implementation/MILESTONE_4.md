# Milestone 4 — Pillar Proposal & Approval Loop

> **Objective:** Make pillars explicitly user-approved before moving on.

This milestone implements the pillar proposal step where Claude generates 4-8 pillar keywords based on the business context, presents them to the user for approval, and allows editing before validation.

---

## Flow Overview

```
Business Context (from Milestone 3)
                │
                ▼
    ┌───────────────────────┐
    │  PillarProposalNode   │
    └───────────┬───────────┘
                │
                ▼
    ┌───────────────────────┐
    │  Claude generates     │
    │  4-8 pillar keywords  │
    │  with rationale       │
    └───────────┬───────────┘
                │
                ▼
    ┌───────────────────────┐
    │     INTERRUPT         │
    │  Present proposals    │
    │  Wait for approval    │
    └───────────┬───────────┘
                │
    ┌───────────┴────────────────────────┐
    │                                    │
    ▼                                    ▼
Approve All                         Partial/Edit
    │                                    │
    │                    ┌───────────────┴───────────────┐
    │                    │                               │
    │                    ▼                               ▼
    │            Edit/Remove pillars              Add suggestions
    │                    │                               │
    │                    └───────────┬───────────────────┘
    │                                │
    │                    ┌───────────▼───────────┐
    │                    │  Re-propose or        │
    │                    │  Accept edits         │
    │                    └───────────┬───────────┘
    │                                │
    └────────────────────────────────┘
                │
                ▼
    ┌───────────────────────┐
    │  Store approved       │
    │  pillars              │
    └───────────┬───────────┘
                │
                ▼
         PillarsApprovedEvent
         (triggers validation)
```

---

## Structured Output Classes

### PillarProposal DTO

```php
<?php

namespace App\Neuron\Dto;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;
use NeuronAI\StructuredOutput\Validation\Rules\Count;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

class PillarProposalResponse
{
    /**
     * @var \App\Neuron\Dto\ProposedPillar[]
     */
    #[SchemaProperty(
        description: 'List of proposed pillar keywords (4-8 pillars)',
        required: true
    )]
    #[ArrayOf(ProposedPillar::class)]
    #[Count(min: 4, max: 8)]
    public array $pillars;

    #[SchemaProperty(
        description: 'Overall strategy explanation for why these pillars were chosen',
        required: true
    )]
    #[NotBlank]
    public string $strategyRationale;

    #[SchemaProperty(
        description: 'How these pillars work together to create topical authority',
        required: true
    )]
    public string $topicalClusterStrategy;
}

class ProposedPillar
{
    #[SchemaProperty(
        description: 'Unique identifier for this pillar proposal',
        required: true
    )]
    public string $id;

    #[SchemaProperty(
        description: 'The pillar keyword phrase',
        required: true
    )]
    #[NotBlank]
    public string $name;

    #[SchemaProperty(
        description: 'Brief explanation of why this pillar was chosen',
        required: true
    )]
    public string $rationale;

    #[SchemaProperty(
        description: 'Primary search intent: informational, commercial, transactional, navigational',
        required: true
    )]
    public string $primaryIntent;

    #[SchemaProperty(
        description: 'Estimated difficulty to rank: low, medium, high, very_high',
        required: true
    )]
    public string $estimatedDifficulty;

    #[SchemaProperty(
        description: 'Strategic role: core_offering, supporting, differentiator, competitor_counter',
        required: true
    )]
    public string $strategicRole;

    #[SchemaProperty(
        description: 'Content type recommendation: pillar_page, landing_page, guide, comparison',
        required: true
    )]
    public string $recommendedContentType;

    #[SchemaProperty(
        description: 'Example search queries users might use for this pillar',
        required: true
    )]
    public array $exampleQueries;

    #[SchemaProperty(
        description: 'Potential cluster topics that would support this pillar',
        required: true
    )]
    public array $potentialClusters;
}
```

---

## Pillar Proposal Node

```php
<?php

namespace App\Neuron\Nodes;

use App\Neuron\Events\ProposePillarsEvent;
use App\Neuron\Events\PillarsApprovedEvent;
use App\Neuron\Dto\PillarProposalResponse;
use App\Neuron\Agents\PillarProposalAgent;
use App\Models\ApprovedPillar;
use App\Enums\AssistantMessageType;
use App\Enums\GenerationRunStatus;
use App\Enums\WorkflowStep;
use NeuronAI\Workflow\WorkflowState;
use NeuronAI\Chat\Messages\UserMessage;

class PillarProposalNode extends BaseTopicalMapNode
{
    public function __invoke(
        ProposePillarsEvent $event,
        WorkflowState $state
    ): ProposePillarsEvent|PillarsApprovedEvent {

        // Check if resuming from interruption (user provided feedback)
        $feedback = $this->consumeInterruptFeedback();

        if ($feedback !== null) {
            // Process user approval/edits
            return $this->processApprovalFeedback($feedback, $state);
        }

        // First time through - generate proposals
        $this->updateRunStatus(
            GenerationRunStatus::PROCESSING,
            WorkflowStep::PILLAR_PROPOSAL
        );

        // Generate pillar proposals using Claude
        $proposals = $this->checkpoint('pillar_generation', function () use ($event) {
            return $this->generatePillarProposals($event->businessContext);
        });

        // Store proposals in state
        $state->set('proposed_pillars', $this->serializeProposals($proposals));

        // Interrupt for user approval
        return $this->presentProposalsForApproval($proposals, $state);
    }

    /**
     * Generate pillar proposals using Claude
     */
    private function generatePillarProposals(array $businessContext): PillarProposalResponse
    {
        $agent = PillarProposalAgent::make();

        $prompt = $this->buildProposalPrompt($businessContext);

        return $agent->structured(
            new UserMessage($prompt),
            PillarProposalResponse::class,
            maxRetries: 2
        );
    }

    /**
     * Build the prompt for pillar generation
     */
    private function buildProposalPrompt(array $businessContext): string
    {
        $prompt = "Based on this business context, generate 4-8 pillar keywords for their SEO topical map.\n\n";

        $prompt .= "## Business Context\n";
        $prompt .= json_encode($businessContext, JSON_PRETTY_PRINT) . "\n\n";

        $prompt .= "## Guidelines\n";
        $prompt .= "- Focus on the 80/20: Keywords that represent core SEO opportunities\n";
        $prompt .= "- Include a mix of intent types (informational for authority, commercial/transactional for conversion)\n";
        $prompt .= "- Consider the competitive landscape based on their business model\n";
        $prompt .= "- Each pillar should have clear cluster potential\n";
        $prompt .= "- Prioritize keywords where they can realistically compete\n";
        $prompt .= "- Avoid overly generic keywords that are too competitive\n";
        $prompt .= "- Avoid overly specific long-tail keywords (those are clusters, not pillars)\n\n";

        $prompt .= "Generate the pillar proposals now.";

        return $prompt;
    }

    /**
     * Present proposals to user for approval
     */
    private function presentProposalsForApproval(
        PillarProposalResponse $proposals,
        WorkflowState $state
    ): ProposePillarsEvent {

        // Build human-readable message
        $textContent = $this->buildProposalMessage($proposals);

        // Build structured content for UI
        $structuredContent = [
            'type' => 'proposal',
            'workflow_step' => 'pillar_proposal',
            'strategy_rationale' => $proposals->strategyRationale,
            'topical_strategy' => $proposals->topicalClusterStrategy,
            'items' => array_map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'rationale' => $p->rationale,
                'primary_intent' => $p->primaryIntent,
                'estimated_difficulty' => $p->estimatedDifficulty,
                'strategic_role' => $p->strategicRole,
                'recommended_content_type' => $p->recommendedContentType,
                'example_queries' => $p->exampleQueries,
                'potential_clusters' => $p->potentialClusters,
            ], $proposals->pillars),
            'actions_available' => [
                'approve_all',
                'approve_selected',
                'edit_pillar',
                'remove_pillar',
                'add_suggestion',
                'request_alternatives',
            ],
        ];

        // Interrupt and wait for approval
        $this->interruptWithMessage(
            AssistantMessageType::PROPOSAL,
            $textContent,
            $structuredContent,
            GenerationRunStatus::AWAITING_APPROVAL,
            WorkflowStep::PILLAR_PROPOSAL
        );

        // Won't be reached until wakeup
        return new ProposePillarsEvent(businessContext: $state->get('business_context'));
    }

    /**
     * Build human-readable proposal message
     */
    private function buildProposalMessage(PillarProposalResponse $proposals): string
    {
        $message = "Based on your business, I've identified these pillar keywords as the foundation of your topical map:\n\n";

        $message .= "**Strategy:** {$proposals->strategyRationale}\n\n";

        $message .= "---\n\n";

        foreach ($proposals->pillars as $i => $pillar) {
            $num = $i + 1;
            $difficultyEmoji = match($pillar->estimatedDifficulty) {
                'low' => '🟢',
                'medium' => '🟡',
                'high' => '🟠',
                'very_high' => '🔴',
                default => '⚪',
            };

            $message .= "### {$num}. {$pillar->name}\n";
            $message .= "**Why:** {$pillar->rationale}\n";
            $message .= "- Intent: {$pillar->primaryIntent}\n";
            $message .= "- Difficulty: {$difficultyEmoji} {$pillar->estimatedDifficulty}\n";
            $message .= "- Role: {$pillar->strategicRole}\n";
            $message .= "- Content Type: {$pillar->recommendedContentType}\n";
            $message .= "- Cluster Ideas: " . implode(', ', array_slice($pillar->potentialClusters, 0, 3)) . "\n\n";
        }

        $message .= "---\n\n";
        $message .= "**What would you like to do?**\n";
        $message .= "- Approve all pillars\n";
        $message .= "- Edit any pillar names\n";
        $message .= "- Remove pillars that don't fit\n";
        $message .= "- Suggest additional pillars\n";

        return $message;
    }

    /**
     * Process user's approval feedback
     */
    private function processApprovalFeedback(
        array $feedback,
        WorkflowState $state
    ): ProposePillarsEvent|PillarsApprovedEvent {

        $structuredInput = $feedback['structured_input'] ?? [];
        $userText = $feedback['user_input'] ?? '';

        // Determine action type
        $action = $structuredInput['action'] ?? $this->inferActionFromText($userText);

        switch ($action) {
            case 'approve_all':
                return $this->approveAllPillars($state);

            case 'approve_selected':
                return $this->approveSelectedPillars(
                    $structuredInput['approved_items'] ?? [],
                    $structuredInput['rejected_items'] ?? [],
                    $state
                );

            case 'edit_pillar':
                return $this->editPillars(
                    $structuredInput['modifications'] ?? [],
                    $state
                );

            case 'add_suggestion':
                return $this->addSuggestedPillars(
                    $structuredInput['additions'] ?? [],
                    $state
                );

            case 'request_alternatives':
                return $this->generateAlternatives($userText, $state);

            default:
                // Parse intent from text
                return $this->handleTextApproval($userText, $state);
        }
    }

    /**
     * Approve all proposed pillars
     */
    private function approveAllPillars(WorkflowState $state): PillarsApprovedEvent
    {
        $proposals = $state->get('proposed_pillars', []);

        // Create approved pillar records
        $approved = [];
        foreach ($proposals as $pillar) {
            $approvedPillar = ApprovedPillar::create([
                'generation_run_id' => $this->run->id,
                'proposed_name' => $pillar['name'],
                'approved_name' => $pillar['name'],
                'rationale' => $pillar['rationale'],
                'user_approved' => true,
                'approved_at' => now(),
            ]);

            $approved[] = array_merge($pillar, [
                'approved_pillar_id' => $approvedPillar->id,
            ]);
        }

        $state->set('approved_pillars', $approved);
        $this->markStepComplete($state, 'pillar_proposal');

        // Create confirmation message
        $this->createAssistantMessage(
            AssistantMessageType::CONFIRMATION,
            "All {count($approved)} pillars approved. Now validating against search results...",
            [
                'type' => 'confirmation',
                'action_taken' => 'pillars_approved',
                'approved_count' => count($approved),
                'pillars' => array_map(fn($p) => $p['name'], $approved),
            ]
        );

        return new PillarsApprovedEvent(approvedPillars: $approved);
    }

    /**
     * Approve only selected pillars
     */
    private function approveSelectedPillars(
        array $approvedIds,
        array $rejectedIds,
        WorkflowState $state
    ): ProposePillarsEvent|PillarsApprovedEvent {

        $proposals = $state->get('proposed_pillars', []);

        // Separate approved and rejected
        $approved = [];
        $rejected = [];

        foreach ($proposals as $pillar) {
            if (in_array($pillar['id'], $approvedIds)) {
                $approvedPillar = ApprovedPillar::create([
                    'generation_run_id' => $this->run->id,
                    'proposed_name' => $pillar['name'],
                    'approved_name' => $pillar['name'],
                    'rationale' => $pillar['rationale'],
                    'user_approved' => true,
                    'approved_at' => now(),
                ]);

                $approved[] = array_merge($pillar, [
                    'approved_pillar_id' => $approvedPillar->id,
                ]);
            } elseif (in_array($pillar['id'], $rejectedIds)) {
                $rejected[] = $pillar;
            }
        }

        $state->set('approved_pillars', $approved);
        $state->set('rejected_pillars', $rejected);

        // Need minimum 3 pillars
        if (count($approved) < 3) {
            return $this->requestMorePillars($approved, $rejected, $state);
        }

        $this->markStepComplete($state, 'pillar_proposal');

        return new PillarsApprovedEvent(approvedPillars: $approved);
    }

    /**
     * Edit pillar names/details
     */
    private function editPillars(array $modifications, WorkflowState $state): ProposePillarsEvent
    {
        $proposals = $state->get('proposed_pillars', []);

        foreach ($modifications as $pillarId => $changes) {
            foreach ($proposals as &$pillar) {
                if ($pillar['id'] === $pillarId) {
                    if (isset($changes['name'])) {
                        $pillar['original_name'] = $pillar['name'];
                        $pillar['name'] = $changes['name'];
                    }
                    if (isset($changes['rationale'])) {
                        $pillar['user_rationale'] = $changes['rationale'];
                    }
                }
            }
        }

        $state->set('proposed_pillars', $proposals);

        // Re-present for final approval
        return $this->rePresent($proposals, $state, 'Here are the updated pillars:');
    }

    /**
     * Add user-suggested pillars
     */
    private function addSuggestedPillars(array $additions, WorkflowState $state): ProposePillarsEvent
    {
        $proposals = $state->get('proposed_pillars', []);

        foreach ($additions as $addition) {
            $proposals[] = [
                'id' => 'user_' . uniqid(),
                'name' => $addition['name'],
                'rationale' => $addition['rationale'] ?? 'User suggested',
                'primaryIntent' => $addition['intent'] ?? 'informational',
                'estimatedDifficulty' => 'unknown',
                'strategicRole' => 'user_priority',
                'recommendedContentType' => 'pillar_page',
                'exampleQueries' => [],
                'potentialClusters' => [],
                'user_added' => true,
            ];
        }

        $state->set('proposed_pillars', $proposals);

        return $this->rePresent($proposals, $state, 'Added your suggestions. Here\'s the updated list:');
    }

    /**
     * Re-present proposals after modifications
     */
    private function rePresent(array $proposals, WorkflowState $state, string $intro): ProposePillarsEvent
    {
        $textContent = "{$intro}\n\n";

        foreach ($proposals as $i => $pillar) {
            $num = $i + 1;
            $edited = isset($pillar['original_name']) ? ' (edited)' : '';
            $userAdded = isset($pillar['user_added']) ? ' (your suggestion)' : '';

            $textContent .= "**{$num}. {$pillar['name']}**{$edited}{$userAdded}\n";
            $textContent .= "{$pillar['rationale']}\n\n";
        }

        $textContent .= "Ready to approve?";

        $this->interruptWithMessage(
            AssistantMessageType::PROPOSAL,
            $textContent,
            [
                'type' => 'proposal',
                'workflow_step' => 'pillar_proposal',
                'items' => $proposals,
                'actions_available' => ['approve_all', 'approve_selected', 'edit_pillar', 'add_suggestion'],
            ],
            GenerationRunStatus::AWAITING_APPROVAL,
            WorkflowStep::PILLAR_PROPOSAL
        );

        return new ProposePillarsEvent(businessContext: $state->get('business_context'));
    }

    /**
     * Infer action from user text
     */
    private function inferActionFromText(string $text): string
    {
        $lower = strtolower($text);

        if (str_contains($lower, 'approve') || str_contains($lower, 'looks good') || str_contains($lower, 'yes')) {
            return 'approve_all';
        }

        if (str_contains($lower, 'add') || str_contains($lower, 'suggest') || str_contains($lower, 'include')) {
            return 'add_suggestion';
        }

        if (str_contains($lower, 'edit') || str_contains($lower, 'change') || str_contains($lower, 'modify')) {
            return 'edit_pillar';
        }

        if (str_contains($lower, 'remove') || str_contains($lower, 'delete') || str_contains($lower, 'don\'t')) {
            return 'approve_selected';
        }

        return 'handle_text';
    }

    /**
     * Handle free-text approval/feedback
     */
    private function handleTextApproval(string $text, WorkflowState $state): ProposePillarsEvent|PillarsApprovedEvent
    {
        // Use Claude to interpret user's intent
        $agent = $this->getAgent();

        $proposals = $state->get('proposed_pillars', []);
        $pillarNames = array_map(fn($p) => $p['name'], $proposals);

        $prompt = "The user was asked to approve these pillars:\n";
        $prompt .= json_encode($pillarNames, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "User's response: \"{$text}\"\n\n";
        $prompt .= "Interpret what the user wants. Return JSON with:\n";
        $prompt .= "- action: approve_all, approve_selected, edit, or unclear\n";
        $prompt .= "- approved_ids: array of pillar indices to approve (if applicable)\n";
        $prompt .= "- edits: object of edits {pillar_index: new_name} (if applicable)\n";
        $prompt .= "- additions: array of new pillar names to add (if applicable)\n";

        $response = $agent->chat(new UserMessage($prompt));
        $content = $response->getContent();

        // Parse JSON
        preg_match('/\{.*\}/s', $content, $matches);
        if (empty($matches)) {
            // Couldn't parse - ask for clarification
            return $this->askForClarification($state);
        }

        $intent = json_decode($matches[0], true);

        if ($intent['action'] === 'approve_all') {
            return $this->approveAllPillars($state);
        }

        // Handle other cases...
        return $this->rePresent($proposals, $state, 'I wasn\'t sure what you meant. Here are the pillars again:');
    }

    /**
     * Serialize proposals for storage
     */
    private function serializeProposals(PillarProposalResponse $proposals): array
    {
        return array_map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'rationale' => $p->rationale,
            'primaryIntent' => $p->primaryIntent,
            'estimatedDifficulty' => $p->estimatedDifficulty,
            'strategicRole' => $p->strategicRole,
            'recommendedContentType' => $p->recommendedContentType,
            'exampleQueries' => $p->exampleQueries,
            'potentialClusters' => $p->potentialClusters,
        ], $proposals->pillars);
    }
}
```

---

## Pillar Proposal Agent

```php
<?php

namespace App\Neuron\Agents;

use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;

class PillarProposalAgent extends Agent
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
                "You are an expert SEO strategist specializing in topical authority and content marketing.",
                "Your task is to identify the pillar keywords that will form the foundation of a topical map.",
                "Pillar keywords are broad, high-value topics that have significant cluster potential.",
            ],
            steps: [
                "Analyze the business context to understand what they offer and who they serve.",
                "Identify 4-8 pillar topics that represent core SEO opportunities.",
                "For each pillar, assess search intent, competitive difficulty, and strategic value.",
                "Ensure pillars have clear cluster potential (supporting topics).",
                "Balance between different intent types and difficulty levels.",
                "Consider the business's ability to create authoritative content on each topic.",
            ],
            output: [
                "Generate exactly 4-8 pillar keywords - no more, no less.",
                "Each pillar should be specific enough to be actionable but broad enough to support clusters.",
                "Avoid generic industry terms that are too competitive.",
                "Avoid long-tail keywords that should be clusters instead.",
                "Provide clear rationale for why each pillar was chosen.",
                "Include realistic difficulty assessments based on typical SERP competition.",
                "Suggest 3-5 potential cluster topics for each pillar.",
            ]
        );
    }
}
```

---

## Frontend Integration

### Approval UI Component Structure

The frontend should render the proposal message with interactive elements:

```typescript
interface PillarProposal {
  id: string;
  name: string;
  rationale: string;
  primary_intent: string;
  estimated_difficulty: 'low' | 'medium' | 'high' | 'very_high';
  strategic_role: string;
  recommended_content_type: string;
  example_queries: string[];
  potential_clusters: string[];
}

interface ProposalResponse {
  type: 'proposal';
  workflow_step: string;
  strategy_rationale: string;
  items: PillarProposal[];
  actions_available: string[];
}

// User actions to send back
interface ApprovalAction {
  action: 'approve_all' | 'approve_selected' | 'edit_pillar' | 'add_suggestion';
  approved_items?: string[];  // pillar IDs
  rejected_items?: string[];  // pillar IDs
  modifications?: Record<string, { name?: string; rationale?: string }>;
  additions?: Array<{ name: string; rationale?: string }>;
}
```

### Example UI Flow

1. **Display pillars as cards** with checkbox selection
2. **Inline editing** for pillar names
3. **"Add Your Own"** button to suggest additional pillars
4. **Action buttons**: "Approve Selected", "Approve All", "Request Alternatives"

---

## Exit Criteria

- [ ] PillarProposalNode generates 4-8 pillars
- [ ] Each pillar has rationale, intent, difficulty, and cluster suggestions
- [ ] User can approve all pillars
- [ ] User can approve selected pillars
- [ ] User can edit pillar names
- [ ] User can add their own suggestions
- [ ] Minimum 3 pillars enforced before proceeding
- [ ] Approved pillars stored in database
- [ ] No clusters generated yet (deferred to Milestone 6)

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Neuron/Dto/PillarProposalResponse.php` | Pillar proposal response DTO |
| `app/Neuron/Dto/ProposedPillar.php` | Individual pillar DTO |
| `app/Neuron/Agents/PillarProposalAgent.php` | Pillar generation agent |
| `app/Neuron/Nodes/PillarProposalNode.php` | Pillar proposal workflow node |

---

## Next Milestone

With pillars approved, proceed to [Milestone 5: Pillar SERP Validation](./MILESTONE_5.md) to validate pillars against actual search results.
