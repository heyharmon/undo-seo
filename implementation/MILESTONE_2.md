# Milestone 2 — Neuron Workflow Skeleton (Chat-Driven)

> **Objective:** Create a workflow that advances one conversational step at a time.

This milestone establishes the core Neuron workflow architecture that powers the chat-driven topical map generation. The workflow is designed to never run end-to-end in one go—it always executes one step, returns output, and pauses for user input.

---

## Core Principle

```
This workflow never runs end-to-end in one go.
It always:
1. Does one step
2. Returns output
3. Pauses or waits for user input
```

---

## Workflow Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    TopicalMapChatWorkflow                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   StartEvent                                                             │
│       │                                                                  │
│       ▼                                                                  │
│   ┌───────────────────┐                                                  │
│   │ ContextIngestion  │──────────────────┐                               │
│   │      Node         │                  │                               │
│   └─────────┬─────────┘                  │                               │
│             │                            │                               │
│             │ has ambiguity?             │ clear                         │
│             ▼                            ▼                               │
│   ┌───────────────────┐        ┌───────────────────┐                     │
│   │  Clarification    │        │  PillarProposal   │                     │
│   │      Node         │───────▶│      Node         │                     │
│   └─────────┬─────────┘        └─────────┬─────────┘                     │
│             │                            │                               │
│     [INTERRUPT]                   [INTERRUPT]                            │
│     wait for answers              wait for approval                      │
│             │                            │                               │
│             ▼                            ▼                               │
│   ┌───────────────────┐        ┌───────────────────┐                     │
│   │  PillarValidation │◀───────│  (after approval) │                     │
│   │      Node         │        └───────────────────┘                     │
│   └─────────┬─────────┘                                                  │
│             │                                                            │
│     [INTERRUPT]                                                          │
│     show validation results                                              │
│             │                                                            │
│             ▼                                                            │
│   ┌───────────────────┐                                                  │
│   │ ClusterGeneration │◀──────────┐                                      │
│   │      Node         │           │ next pillar                          │
│   └─────────┬─────────┘           │                                      │
│             │                     │                                      │
│     [INTERRUPT]───────────────────┘                                      │
│     per pillar                                                           │
│             │                                                            │
│             ▼ all pillars done                                           │
│   ┌───────────────────┐                                                  │
│   │   Completion      │                                                  │
│   │      Node         │                                                  │
│   └─────────┬─────────┘                                                  │
│             │                                                            │
│             ▼                                                            │
│        StopEvent                                                         │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Event Definitions

### Custom Events

```php
<?php

namespace App\Neuron\Events;

use NeuronAI\Workflow\Event;

/**
 * Fired after context is ingested, triggers clarification or proposal
 */
class ContextIngestedEvent implements Event
{
    public function __construct(
        public readonly array $businessContext,
        public readonly bool $hasAmbiguity,
        public readonly ?array $questions = null
    ) {}
}

/**
 * Fired when clarification is needed
 */
class ClarificationNeededEvent implements Event
{
    public function __construct(
        public readonly array $questions
    ) {}
}

/**
 * Fired after clarification is complete, triggers pillar proposal
 */
class ClarificationCompleteEvent implements Event
{
    public function __construct(
        public readonly array $clarifications
    ) {}
}

/**
 * Fired to trigger pillar proposal generation
 */
class ProposePillarsEvent implements Event
{
    public function __construct(
        public readonly array $businessContext
    ) {}
}

/**
 * Fired after pillars are approved, triggers validation
 */
class PillarsApprovedEvent implements Event
{
    public function __construct(
        public readonly array $approvedPillars
    ) {}
}

/**
 * Fired after validation, triggers cluster generation
 */
class PillarsValidatedEvent implements Event
{
    public function __construct(
        public readonly array $validatedPillars
    ) {}
}

/**
 * Fired to generate clusters for a specific pillar
 */
class GenerateClustersEvent implements Event
{
    public function __construct(
        public readonly int $pillarIndex,
        public readonly array $pillar
    ) {}
}

/**
 * Fired when clusters for a pillar are approved
 */
class ClustersApprovedEvent implements Event
{
    public function __construct(
        public readonly int $pillarIndex,
        public readonly array $clusters
    ) {}
}

/**
 * Fired when all pillars have been processed
 */
class AllPillarsProcessedEvent implements Event
{
    public function __construct(
        public readonly array $summary
    ) {}
}
```

---

## Workflow State Schema

The workflow state persists critical information across interruptions:

```php
<?php

namespace App\Neuron\State;

/**
 * WorkflowState structure (stored as JSON in generation_runs.workflow_state)
 */
class TopicalMapState
{
    // Initial input
    public string $initialPrompt;

    // Inferred business context
    public array $businessContext = [
        'offerings' => [],        // Products/services offered
        'icp' => [],              // Ideal customer profile
        'geo' => [],              // Geographic targeting
        'monetization' => [],     // Revenue model
        'differentiators' => [],  // Unique selling points
    ];

    // Clarification tracking
    public array $pendingQuestions = [];
    public array $clarifiedAnswers = [];

    // Pillar tracking
    public array $proposedPillars = [];
    public array $approvedPillars = [];
    public array $rejectedPillars = [];
    public array $validatedPillars = [];

    // Cluster tracking
    public int $currentPillarIndex = 0;
    public array $generatedClusters = []; // Keyed by pillar index

    // Completed steps tracking
    public array $completedSteps = [];

    // Workflow metadata
    public ?string $lastInterruptReason = null;
    public ?array $lastInterruptData = null;
}
```

---

## Workflow Class

```php
<?php

namespace App\Neuron\Workflows;

use App\Models\GenerationRun;
use App\Neuron\Nodes\ContextIngestionNode;
use App\Neuron\Nodes\ClarificationNode;
use App\Neuron\Nodes\PillarProposalNode;
use App\Neuron\Nodes\PillarValidationNode;
use App\Neuron\Nodes\ClusterGenerationNode;
use App\Neuron\Nodes\CompletionNode;
use NeuronAI\Workflow\Workflow;
use NeuronAI\Workflow\Persistence\DatabasePersistence;

class TopicalMapChatWorkflow extends Workflow
{
    public function __construct(
        private GenerationRun $run
    ) {
        // Initialize with database persistence for interruption support
        parent::__construct(
            new DatabasePersistence(
                pdo: \DB::connection()->getPdo(),
                table: 'workflow_interrupts'
            ),
            $this->run->getWorkflowId()
        );
    }

    protected function nodes(): array
    {
        return [
            new ContextIngestionNode($this->run),
            new ClarificationNode($this->run),
            new PillarProposalNode($this->run),
            new PillarValidationNode($this->run),
            new ClusterGenerationNode($this->run),
            new CompletionNode($this->run),
        ];
    }

    /**
     * Get the generation run
     */
    public function getRun(): GenerationRun
    {
        return $this->run;
    }

    /**
     * Start or resume the workflow
     */
    public function process(string $userInput, ?array $structuredInput = null): array
    {
        // Store user input in state for nodes to access
        $this->getState()->set('current_user_input', $userInput);
        $this->getState()->set('current_structured_input', $structuredInput);

        try {
            // Try to wake up if interrupted, otherwise start fresh
            if ($this->isInterrupted()) {
                $handler = $this->wakeup([
                    'user_input' => $userInput,
                    'structured_input' => $structuredInput,
                ]);
            } else {
                $handler = $this->start();
            }

            // Stream events for real-time updates
            foreach ($handler->streamEvents() as $event) {
                // Events can be captured for logging/UI updates
            }

            return [
                'status' => 'completed',
                'result' => $handler->getResult(),
            ];

        } catch (\NeuronAI\Workflow\WorkflowInterrupt $interrupt) {
            // Workflow paused for user input
            return [
                'status' => 'interrupted',
                'interrupt_data' => $interrupt->getData(),
            ];
        }
    }

    /**
     * Check if workflow is currently interrupted
     */
    public function isInterrupted(): bool
    {
        // Check if there's saved state in persistence
        return $this->hasSavedState();
    }
}
```

---

## Base Node Class

```php
<?php

namespace App\Neuron\Nodes;

use App\Models\GenerationRun;
use App\Models\ChatMessage;
use App\Enums\AssistantMessageType;
use App\Enums\GenerationRunStatus;
use App\Enums\WorkflowStep;
use App\Repositories\ChatMessageRepository;
use NeuronAI\Workflow\Node;
use NeuronAI\Workflow\WorkflowState;

abstract class BaseTopicalMapNode extends Node
{
    protected ChatMessageRepository $messageRepo;

    public function __construct(
        protected GenerationRun $run
    ) {
        $this->messageRepo = app(ChatMessageRepository::class);
    }

    /**
     * Create an assistant message and store it
     */
    protected function createAssistantMessage(
        AssistantMessageType $type,
        ?string $textContent,
        array $structuredContent
    ): ChatMessage {
        return $this->messageRepo->createAssistantMessage(
            $this->run,
            $type,
            $textContent,
            $structuredContent
        );
    }

    /**
     * Update run status
     */
    protected function updateRunStatus(GenerationRunStatus $status, WorkflowStep $step): void
    {
        $this->run->update([
            'status' => $status,
            'current_step' => $step,
        ]);
    }

    /**
     * Mark step as complete
     */
    protected function markStepComplete(WorkflowState $state, string $stepName): void
    {
        $completed = $state->get('completed_steps', []);
        if (!in_array($stepName, $completed)) {
            $completed[] = $stepName;
            $state->set('completed_steps', $completed);
        }
    }

    /**
     * Check if step is complete
     */
    protected function isStepComplete(WorkflowState $state, string $stepName): bool
    {
        $completed = $state->get('completed_steps', []);
        return in_array($stepName, $completed);
    }

    /**
     * Get the Claude agent for this node
     */
    protected function getAgent(): \App\Neuron\Agents\TopicalMapAgent
    {
        return \App\Neuron\Agents\TopicalMapAgent::make($this->run);
    }

    /**
     * Interrupt with structured data for the UI
     */
    protected function interruptWithMessage(
        AssistantMessageType $messageType,
        string $textContent,
        array $structuredContent,
        GenerationRunStatus $awaitStatus,
        WorkflowStep $currentStep
    ): array {
        // Create the message
        $this->createAssistantMessage($messageType, $textContent, $structuredContent);

        // Update run status
        $this->updateRunStatus($awaitStatus, $currentStep);

        // Return interrupt data
        return $this->interrupt([
            'message_type' => $messageType->value,
            'text_content' => $textContent,
            'structured_content' => $structuredContent,
            'awaiting' => $awaitStatus->value,
            'current_step' => $currentStep->value,
        ]);
    }
}
```

---

## Workflow Service

Orchestrates workflow execution from the controller layer:

```php
<?php

namespace App\Services;

use App\Models\GenerationRun;
use App\Models\ChatMessage;
use App\Neuron\Workflows\TopicalMapChatWorkflow;
use App\Repositories\ChatMessageRepository;
use App\Enums\UserMessageType;
use NeuronAI\Workflow\WorkflowInterrupt;

class TopicalMapWorkflowService
{
    public function __construct(
        private ChatMessageRepository $messageRepo,
        private MessageTypeDetector $typeDetector
    ) {}

    /**
     * Process an incoming user message through the workflow
     */
    public function processMessage(
        GenerationRun $run,
        string $content,
        ?array $structuredContent = null
    ): array {
        // Detect message type
        $messageType = $this->typeDetector->detect($content, $run);

        // Store user message
        $this->messageRepo->createUserMessage(
            $run,
            $messageType,
            $content,
            $structuredContent
        );

        // Create workflow instance
        $workflow = new TopicalMapChatWorkflow($run);

        try {
            // Process through workflow
            $result = $workflow->process($content, $structuredContent);

            if ($result['status'] === 'interrupted') {
                // Workflow paused - return interrupt data for UI
                return [
                    'type' => 'awaiting_input',
                    'data' => $result['interrupt_data'],
                ];
            }

            // Workflow step completed
            return [
                'type' => 'step_completed',
                'data' => $result['result'],
            ];

        } catch (\Exception $e) {
            // Handle errors
            $run->update(['status' => 'failed']);

            return [
                'type' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get current workflow state for UI
     */
    public function getWorkflowState(GenerationRun $run): array
    {
        return [
            'status' => $run->status->value,
            'current_step' => $run->current_step?->value,
            'is_awaiting_input' => $run->isAwaitingInput(),
            'completed_steps' => $run->workflow_state['completed_steps'] ?? [],
        ];
    }
}
```

---

## Message Type Detector

```php
<?php

namespace App\Services;

use App\Models\GenerationRun;
use App\Enums\UserMessageType;
use App\Enums\GenerationRunStatus;

class MessageTypeDetector
{
    /**
     * Detect the type of incoming user message
     */
    public function detect(string $content, GenerationRun $run): UserMessageType
    {
        // If no workflow state or just started, this is initial prompt
        if ($run->status === GenerationRunStatus::STARTED && empty($run->workflow_state)) {
            return UserMessageType::INITIAL_PROMPT;
        }

        // If awaiting clarification, this is a clarification answer
        if ($run->status === GenerationRunStatus::AWAITING_CLARIFICATION) {
            return UserMessageType::CLARIFICATION_ANSWER;
        }

        // If awaiting approval, this is an approval
        if ($run->status === GenerationRunStatus::AWAITING_APPROVAL) {
            return UserMessageType::APPROVAL;
        }

        // Otherwise, assume refinement request
        return UserMessageType::REFINEMENT_REQUEST;
    }
}
```

---

## Integration with Existing Keyword Model

The workflow creates Keywords through the existing model:

```php
<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Keyword;
use App\Models\GenerationRun;
use App\Models\ApprovedPillar;

class KeywordCreationService
{
    /**
     * Create a pillar keyword from approved pillar
     */
    public function createPillarKeyword(
        Project $project,
        ApprovedPillar $approvedPillar,
        array $metadata = []
    ): Keyword {
        $keyword = Keyword::create([
            'project_id' => $project->id,
            'parent_id' => null, // Pillars are root level
            'name' => $approvedPillar->approved_name,
            'intent' => $metadata['intent'] ?? 'info',
            'status' => 'planned',
            'keyword_type' => $metadata['keyword_type'] ?? 'service',
            'content_type' => 'pillar_page',
            'strategic_role' => $metadata['strategic_role'] ?? null,
            'strategic_opportunity' => $metadata['strategic_opportunity'] ?? null,
            'position' => $this->getNextPosition($project),
        ]);

        // Link pillar to keyword
        $approvedPillar->update(['keyword_id' => $keyword->id]);

        return $keyword;
    }

    /**
     * Create cluster keywords under a pillar
     */
    public function createClusterKeywords(
        Keyword $pillarKeyword,
        array $clusters
    ): array {
        $keywords = [];

        foreach ($clusters as $index => $cluster) {
            $keywords[] = Keyword::create([
                'project_id' => $pillarKeyword->project_id,
                'parent_id' => $pillarKeyword->id,
                'name' => $cluster['name'],
                'intent' => $cluster['intent'] ?? 'info',
                'status' => 'planned',
                'keyword_type' => $cluster['keyword_type'] ?? 'service',
                'content_type' => $cluster['content_type'] ?? 'article',
                'strategic_role' => $cluster['tier'] ?? null,
                'strategic_opportunity' => $cluster['rationale'] ?? null,
                'position' => $index,
            ]);
        }

        return $keywords;
    }

    private function getNextPosition(Project $project): int
    {
        return Keyword::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->max('position') + 1 ?? 0;
    }
}
```

---

## Environment Configuration

Add to `.env`:

```env
# Neuron AI Configuration
ANTHROPIC_API_KEY=your-api-key
ANTHROPIC_MODEL=claude-sonnet-4-20250514

# Optional: Inspector for monitoring
INSPECTOR_INGESTION_KEY=your-inspector-key
```

Add to `config/neuron.php`:

```php
<?php

return [
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
    ],

    'workflow' => [
        'persistence_table' => 'workflow_interrupts',
    ],

    'monitoring' => [
        'enabled' => env('INSPECTOR_INGESTION_KEY') !== null,
    ],
];
```

---

## Exit Criteria

- [ ] TopicalMapChatWorkflow class created
- [ ] All event classes defined
- [ ] Base node class implemented
- [ ] Workflow service orchestration working
- [ ] Message type detection functional
- [ ] Interruption and resume working correctly
- [ ] State persists across interruptions
- [ ] Integration with existing Keyword model tested

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Neuron/Workflows/TopicalMapChatWorkflow.php` | Main workflow class |
| `app/Neuron/Events/*.php` | All workflow events |
| `app/Neuron/Nodes/BaseTopicalMapNode.php` | Base node class |
| `app/Services/TopicalMapWorkflowService.php` | Workflow orchestration |
| `app/Services/MessageTypeDetector.php` | Message type detection |
| `app/Services/KeywordCreationService.php` | Keyword creation from workflow |
| `config/neuron.php` | Neuron configuration |

---

## Next Milestone

With the workflow skeleton in place, proceed to [Milestone 3: Initial Chat Turn (Context Ingestion)](./MILESTONE_3.md) to implement the first workflow step.
