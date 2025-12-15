# Milestone 1 — Chat Persistence Layer

> **Objective:** Store chat messages and tie them to workflow state.

This milestone establishes the database schema and persistence layer for the chat-driven workflow system. All messages, generation runs, and workflow state must be durably stored to support resumability and conversation replay.

---

## Overview

The persistence layer serves three critical functions:

1. **Chat History Storage** — Store all messages for UI replay and context
2. **Generation Run Tracking** — Track the lifecycle of each topical map generation
3. **Workflow State Persistence** — Enable Neuron workflow interruption and resumption

---

## Database Schema

### 1. Generation Runs Table

Tracks each topical map generation attempt within a project.

```php
// Migration: create_generation_runs_table.php

Schema::create('generation_runs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->onDelete('cascade');

    // Status tracking
    $table->string('status')->default('started');
    // Enum: started, awaiting_clarification, awaiting_approval, processing, completed, failed, cancelled

    // Current workflow position
    $table->string('current_step')->nullable();
    // Enum: context_ingestion, clarification, pillar_proposal, pillar_validation, cluster_generation, completion

    // Workflow state (JSON blob for Neuron state)
    $table->json('workflow_state')->nullable();

    // Inferred business context from initial prompt
    $table->json('business_context')->nullable();
    // Structure: { offerings: [], icp: {}, geo: {}, monetization: {} }

    // Clarification Q&A history
    $table->json('clarifications')->nullable();
    // Structure: [{ question_id: '', question: '', answer: '' }, ...]

    // Tracking
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    // Indexes
    $table->index(['project_id', 'status']);
    $table->index('created_at');
});
```

### 2. Chat Messages Table

Stores all messages exchanged during a generation run.

```php
// Migration: create_chat_messages_table.php

Schema::create('chat_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->onDelete('cascade');
    $table->foreignId('generation_run_id')->nullable()->constrained()->onDelete('cascade');

    // Message details
    $table->string('role'); // user, assistant, system
    $table->string('message_type'); // From UserMessageType or AssistantMessageType enum

    // Content storage
    $table->text('content_text')->nullable(); // Plain text for display
    $table->json('content_structured')->nullable(); // Structured data (proposals, questions, etc.)

    // Workflow context at time of message
    $table->string('workflow_step')->nullable();

    // Metadata
    $table->json('meta')->nullable();
    // Structure: { tokens_used: int, model: string, duration_ms: int }

    $table->timestamps();

    // Indexes for efficient querying
    $table->index(['project_id', 'generation_run_id', 'created_at']);
    $table->index(['generation_run_id', 'role']);
});
```

### 3. Workflow Interrupts Table (Neuron Persistence)

Required by Neuron's DatabasePersistence for workflow interruption.

```php
// Migration: create_workflow_interrupts_table.php

Schema::create('workflow_interrupts', function (Blueprint $table) {
    $table->string('workflow_id', 255)->primary();
    $table->binary('data'); // LONGBLOB for MySQL
    $table->timestamp('created_at');
    $table->timestamp('updated_at');

    $table->index('workflow_id');
    $table->index('updated_at');
});
```

### 4. Approved Pillars Table

Tracks pillars as they move through the approval process.

```php
// Migration: create_approved_pillars_table.php

Schema::create('approved_pillars', function (Blueprint $table) {
    $table->id();
    $table->foreignId('generation_run_id')->constrained()->onDelete('cascade');

    // Pillar details
    $table->string('proposed_name'); // Original proposed name
    $table->string('approved_name'); // Final approved name (may differ)
    $table->text('rationale')->nullable();

    // Approval tracking
    $table->boolean('user_approved')->default(false);
    $table->timestamp('approved_at')->nullable();

    // SERP validation
    $table->boolean('serp_validated')->default(false);
    $table->timestamp('validated_at')->nullable();
    $table->json('serp_data')->nullable();
    // Structure: { competition_level: '', intent_match: '', top_competitors: [] }

    // Link to actual keyword (created after final approval)
    $table->foreignId('keyword_id')->nullable()->constrained()->onDelete('set null');

    $table->timestamps();

    $table->index(['generation_run_id', 'user_approved']);
});
```

---

## Eloquent Models

### GenerationRun Model

```php
<?php

namespace App\Models;

use App\Enums\GenerationRunStatus;
use App\Enums\WorkflowStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationRun extends Model
{
    protected $fillable = [
        'project_id',
        'status',
        'current_step',
        'workflow_state',
        'business_context',
        'clarifications',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => GenerationRunStatus::class,
        'current_step' => WorkflowStep::class,
        'workflow_state' => 'array',
        'business_context' => 'array',
        'clarifications' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function approvedPillars(): HasMany
    {
        return $this->hasMany(ApprovedPillar::class);
    }

    // Helper methods
    public function isActive(): bool
    {
        return in_array($this->status, [
            GenerationRunStatus::STARTED,
            GenerationRunStatus::AWAITING_CLARIFICATION,
            GenerationRunStatus::AWAITING_APPROVAL,
            GenerationRunStatus::PROCESSING,
        ]);
    }

    public function isAwaitingInput(): bool
    {
        return in_array($this->status, [
            GenerationRunStatus::AWAITING_CLARIFICATION,
            GenerationRunStatus::AWAITING_APPROVAL,
        ]);
    }

    public function markAsAwaiting(GenerationRunStatus $status, WorkflowStep $step): void
    {
        $this->update([
            'status' => $status,
            'current_step' => $step,
        ]);
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => GenerationRunStatus::PROCESSING]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => GenerationRunStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function getWorkflowId(): string
    {
        return "generation_run_{$this->id}";
    }
}
```

### ChatMessage Model

```php
<?php

namespace App\Models;

use App\Enums\AssistantMessageType;
use App\Enums\UserMessageType;
use App\Enums\WorkflowStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'project_id',
        'generation_run_id',
        'role',
        'message_type',
        'content_text',
        'content_structured',
        'workflow_step',
        'meta',
    ];

    protected $casts = [
        'content_structured' => 'array',
        'workflow_step' => WorkflowStep::class,
        'meta' => 'array',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(GenerationRun::class);
    }

    // Scopes
    public function scopeForRun($query, int $runId)
    {
        return $query->where('generation_run_id', $runId);
    }

    public function scopeUserMessages($query)
    {
        return $query->where('role', 'user');
    }

    public function scopeAssistantMessages($query)
    {
        return $query->where('role', 'assistant');
    }

    // Helpers
    public function isUserMessage(): bool
    {
        return $this->role === 'user';
    }

    public function isAssistantMessage(): bool
    {
        return $this->role === 'assistant';
    }

    public function getStructuredContent(): ?array
    {
        return $this->content_structured;
    }

    public function getDisplayContent(): string
    {
        return $this->content_text ?? json_encode($this->content_structured, JSON_PRETTY_PRINT);
    }
}
```

### ApprovedPillar Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovedPillar extends Model
{
    protected $fillable = [
        'generation_run_id',
        'proposed_name',
        'approved_name',
        'rationale',
        'user_approved',
        'approved_at',
        'serp_validated',
        'validated_at',
        'serp_data',
        'keyword_id',
    ];

    protected $casts = [
        'user_approved' => 'boolean',
        'approved_at' => 'datetime',
        'serp_validated' => 'boolean',
        'validated_at' => 'datetime',
        'serp_data' => 'array',
    ];

    public function generationRun(): BelongsTo
    {
        return $this->belongsTo(GenerationRun::class);
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function markAsApproved(string $approvedName = null): void
    {
        $this->update([
            'user_approved' => true,
            'approved_at' => now(),
            'approved_name' => $approvedName ?? $this->proposed_name,
        ]);
    }

    public function markAsValidated(array $serpData): void
    {
        $this->update([
            'serp_validated' => true,
            'validated_at' => now(),
            'serp_data' => $serpData,
        ]);
    }
}
```

---

## Repository / Service Layer

### ChatMessageRepository

```php
<?php

namespace App\Repositories;

use App\Models\ChatMessage;
use App\Models\GenerationRun;
use App\Enums\AssistantMessageType;
use App\Enums\UserMessageType;
use Illuminate\Support\Collection;

class ChatMessageRepository
{
    /**
     * Create a user message
     */
    public function createUserMessage(
        GenerationRun $run,
        UserMessageType $type,
        string $textContent,
        ?array $structuredContent = null
    ): ChatMessage {
        return ChatMessage::create([
            'project_id' => $run->project_id,
            'generation_run_id' => $run->id,
            'role' => 'user',
            'message_type' => $type->value,
            'content_text' => $textContent,
            'content_structured' => $structuredContent,
            'workflow_step' => $run->current_step,
        ]);
    }

    /**
     * Create an assistant message
     */
    public function createAssistantMessage(
        GenerationRun $run,
        AssistantMessageType $type,
        ?string $textContent,
        array $structuredContent,
        ?array $meta = null
    ): ChatMessage {
        return ChatMessage::create([
            'project_id' => $run->project_id,
            'generation_run_id' => $run->id,
            'role' => 'assistant',
            'message_type' => $type->value,
            'content_text' => $textContent,
            'content_structured' => $structuredContent,
            'workflow_step' => $run->current_step,
            'meta' => $meta,
        ]);
    }

    /**
     * Get all messages for a run
     */
    public function getMessagesForRun(GenerationRun $run): Collection
    {
        return $run->messages()->orderBy('created_at')->get();
    }

    /**
     * Get messages for Neuron chat history format
     */
    public function getMessagesForNeuron(GenerationRun $run): array
    {
        return $run->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content_text ?? json_encode($msg->content_structured),
            ])
            ->toArray();
    }
}
```

### GenerationRunService

```php
<?php

namespace App\Services;

use App\Models\Project;
use App\Models\GenerationRun;
use App\Enums\GenerationRunStatus;
use App\Enums\WorkflowStep;
use App\Exceptions\ActiveRunExistsException;

class GenerationRunService
{
    /**
     * Start a new generation run for a project
     */
    public function startNewRun(Project $project, string $initialPrompt): GenerationRun
    {
        // Check for existing active run
        $activeRun = $project->generationRuns()
            ->whereIn('status', [
                GenerationRunStatus::STARTED,
                GenerationRunStatus::AWAITING_CLARIFICATION,
                GenerationRunStatus::AWAITING_APPROVAL,
                GenerationRunStatus::PROCESSING,
            ])
            ->first();

        if ($activeRun) {
            throw new ActiveRunExistsException(
                "Project already has an active generation run (ID: {$activeRun->id})"
            );
        }

        return GenerationRun::create([
            'project_id' => $project->id,
            'status' => GenerationRunStatus::STARTED,
            'current_step' => WorkflowStep::CONTEXT_INGESTION,
            'started_at' => now(),
            'workflow_state' => [
                'initial_prompt' => $initialPrompt,
            ],
        ]);
    }

    /**
     * Get the active run for a project
     */
    public function getActiveRun(Project $project): ?GenerationRun
    {
        return $project->generationRuns()
            ->whereIn('status', [
                GenerationRunStatus::STARTED,
                GenerationRunStatus::AWAITING_CLARIFICATION,
                GenerationRunStatus::AWAITING_APPROVAL,
                GenerationRunStatus::PROCESSING,
            ])
            ->first();
    }

    /**
     * Update workflow state
     */
    public function updateWorkflowState(GenerationRun $run, array $state): void
    {
        $currentState = $run->workflow_state ?? [];
        $run->update([
            'workflow_state' => array_merge($currentState, $state),
        ]);
    }

    /**
     * Update business context
     */
    public function updateBusinessContext(GenerationRun $run, array $context): void
    {
        $run->update(['business_context' => $context]);
    }

    /**
     * Add clarification Q&A
     */
    public function addClarification(GenerationRun $run, string $questionId, string $question, string $answer): void
    {
        $clarifications = $run->clarifications ?? [];
        $clarifications[] = [
            'question_id' => $questionId,
            'question' => $question,
            'answer' => $answer,
            'answered_at' => now()->toISOString(),
        ];
        $run->update(['clarifications' => $clarifications]);
    }

    /**
     * Cancel a run
     */
    public function cancelRun(GenerationRun $run): void
    {
        $run->update(['status' => GenerationRunStatus::CANCELLED]);
    }
}
```

---

## API Endpoints

### ChatController

```php
<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\GenerationRun;
use App\Services\GenerationRunService;
use App\Services\TopicalMapWorkflowService;
use App\Repositories\ChatMessageRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(
        private GenerationRunService $runService,
        private ChatMessageRepository $messageRepository,
        private TopicalMapWorkflowService $workflowService
    ) {}

    /**
     * Get chat history for a project
     */
    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $activeRun = $this->runService->getActiveRun($project);

        return response()->json([
            'has_active_run' => $activeRun !== null,
            'run' => $activeRun ? [
                'id' => $activeRun->id,
                'status' => $activeRun->status,
                'current_step' => $activeRun->current_step,
                'started_at' => $activeRun->started_at,
            ] : null,
            'messages' => $activeRun
                ? $this->messageRepository->getMessagesForRun($activeRun)
                : [],
        ]);
    }

    /**
     * Send a message (advances workflow or answers interruption)
     */
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

    /**
     * Get run status (for polling)
     */
    public function status(Project $project, GenerationRun $run): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'id' => $run->id,
            'status' => $run->status,
            'current_step' => $run->current_step,
            'is_awaiting_input' => $run->isAwaitingInput(),
        ]);
    }

    /**
     * Cancel active run
     */
    public function cancel(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $activeRun = $this->runService->getActiveRun($project);

        if (!$activeRun) {
            return response()->json(['message' => 'No active run to cancel'], 404);
        }

        $this->runService->cancelRun($activeRun);

        return response()->json(['message' => 'Run cancelled']);
    }
}
```

### API Routes

```php
// routes/api.php

Route::middleware('auth:sanctum')->group(function () {
    // ... existing routes ...

    // Chat / Generation Run endpoints
    Route::prefix('projects/{project}/chat')->group(function () {
        Route::get('/', [ChatController::class, 'index']);
        Route::post('/', [ChatController::class, 'store']);
        Route::delete('/', [ChatController::class, 'cancel']);
    });

    Route::get('projects/{project}/runs/{run}/status', [ChatController::class, 'status']);
});
```

---

## Neuron Chat History Integration

### NeuronChatHistory Adapter

Bridge between Laravel's ChatMessage model and Neuron's ChatHistoryInterface:

```php
<?php

namespace App\Neuron;

use App\Models\GenerationRun;
use App\Models\ChatMessage;
use NeuronAI\Chat\History\AbstractChatHistory;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Enums\MessageRole;

class EloquentChatHistory extends AbstractChatHistory implements ChatHistoryInterface
{
    public function __construct(
        private GenerationRun $run,
        int $contextWindow = 50000
    ) {
        parent::__construct($contextWindow);

        // Load existing messages
        $this->history = $this->loadMessages();
    }

    private function loadMessages(): array
    {
        return $this->run->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (ChatMessage $msg) => $this->deserializeMessage([
                'role' => $msg->role,
                'content' => $msg->content_text ?? json_encode($msg->content_structured),
            ]))
            ->toArray();
    }

    protected function storeMessage(Message $message): ChatHistoryInterface
    {
        ChatMessage::create([
            'project_id' => $this->run->project_id,
            'generation_run_id' => $this->run->id,
            'role' => $message->getRole()->value,
            'message_type' => $this->inferMessageType($message),
            'content_text' => $message->getContent(),
            'content_structured' => null,
            'workflow_step' => $this->run->current_step,
        ]);

        return $this;
    }

    private function inferMessageType(Message $message): string
    {
        // Infer type based on role and content
        if ($message->getRole() === MessageRole::USER) {
            return 'user_input';
        }
        return 'assistant_response';
    }

    public function flushAll(): ChatHistoryInterface
    {
        $this->run->messages()->delete();
        $this->history = [];
        return $this;
    }
}
```

---

## UI Requirements

### Chat Interface Features

1. **Message List**
   - Display all messages in chronological order
   - Different styling for user vs assistant messages
   - Render structured content appropriately (proposals, questions, etc.)

2. **Input Area**
   - Text input for free-form messages
   - Contextual action buttons based on workflow state
   - "Claude is waiting for your input" indicator

3. **Status Bar**
   - Current workflow step indicator
   - Run status (processing, awaiting input, etc.)
   - Progress indicator for multi-step operations

4. **Actions**
   - "Start New" button (when no active run)
   - "Cancel" button (when run is active)
   - Contextual approval/rejection buttons

---

## Exit Criteria

- [ ] All migrations created and run successfully
- [ ] Models created with proper relationships
- [ ] Repository/Service layer implemented
- [ ] API endpoints functional
- [ ] Chat history persists across page reloads
- [ ] Messages correctly attach to generation runs
- [ ] UI can display "Claude is waiting for input" state
- [ ] Conversation can be replayed from database

---

## Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_create_generation_runs_table.php` | Generation runs schema |
| `database/migrations/xxxx_create_chat_messages_table.php` | Chat messages schema |
| `database/migrations/xxxx_create_workflow_interrupts_table.php` | Neuron persistence |
| `database/migrations/xxxx_create_approved_pillars_table.php` | Pillar tracking |
| `app/Models/GenerationRun.php` | Generation run model |
| `app/Models/ChatMessage.php` | Chat message model |
| `app/Models/ApprovedPillar.php` | Approved pillar model |
| `app/Repositories/ChatMessageRepository.php` | Message repository |
| `app/Services/GenerationRunService.php` | Run management service |
| `app/Http/Controllers/ChatController.php` | Chat API controller |
| `app/Neuron/EloquentChatHistory.php` | Neuron chat history adapter |
| `app/Exceptions/ActiveRunExistsException.php` | Custom exception |

---

## Next Milestone

Once the persistence layer is complete, proceed to [Milestone 2: Neuron Workflow Skeleton](./MILESTONE_2.md) to build the chat-driven workflow orchestration.
