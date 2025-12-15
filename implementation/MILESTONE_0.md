# Milestone 0 — UX Contract

> **Objective:** Define how chat messages map to workflow actions.

This milestone establishes the foundational contract between the user interface and the underlying workflow system. It is critical to complete this before any implementation begins, as it defines the behavioral constraints and interaction patterns that all subsequent milestones must follow.

---

## Core Philosophy

**This is not a chatbot. This is a workflow-driven system with a chat UI skin.**

Each "chat message":
- Either **advances the workflow**
- Or **answers a workflow interruption**
- Or **requests a refinement**

The workflow is the **source of truth**. Chat is just the interface.

---

## System Rules

### 1. One Active Generation Run Per Project
```
Rule: Only ONE active generation run can exist per project at any time.
```

**Implementation considerations:**
- Store `generation_run_id` on the project or in a dedicated table
- Check for existing active runs before starting new ones
- Provide clear UX when a run is already active (resume vs. cancel options)

### 2. Chat Messages are Workflow Inputs
```
Rule: Chat messages are inputs to a workflow, not free-form conversation.
```

**What this means:**
- Every user message must be categorized into a known type
- The system should not respond to off-topic messages with arbitrary Claude responses
- Invalid inputs should be gently redirected to the current workflow step

### 3. No Implicit Restarts
```
Rule: Claude never "starts over" unless explicitly requested.
```

**Implementation considerations:**
- Preserve all state across interruptions
- Only reset workflow state when user explicitly confirms
- Provide "Start Fresh" as a deliberate action, not a default

### 4. Workflow Step Correspondence
```
Rule: Every Claude response corresponds to a workflow step.
```

**What this means:**
- Responses are deterministic based on workflow state
- The UI can show which step the workflow is on
- Responses should be structured, not free-form

---

## Message Type Taxonomy

### User Message Types

```php
enum UserMessageType: string
{
    case INITIAL_PROMPT = 'initial_prompt';           // Starting the workflow
    case CLARIFICATION_ANSWER = 'clarification_answer'; // Answering Claude's question
    case APPROVAL = 'approval';                       // Approving proposals
    case REFINEMENT_REQUEST = 'refinement_request';   // Requesting changes
}
```

#### 1. `initial_prompt`
**When:** User starts a new generation run
**Contains:** Natural language business description
**Example:**
```
"We're a SaaS company that provides project management software for
construction teams. Our main product is a mobile-first platform that
helps contractors track job sites, manage crews, and handle invoicing.
We serve small to medium construction companies in the US."
```
**Workflow Action:** Creates new generation run, triggers context ingestion

#### 2. `clarification_answer`
**When:** Responding to Claude's questions
**Contains:** Answers to specific questions asked by the workflow
**Example:**
```json
{
  "type": "clarification_answer",
  "answers": {
    "primary_revenue_model": "subscription",
    "target_company_size": "10-50 employees",
    "geographic_focus": "United States only"
  }
}
```
**Workflow Action:** Updates workflow state, advances to next step

#### 3. `approval`
**When:** Approving, rejecting, or modifying proposals
**Contains:** Approval decisions with optional modifications
**Example:**
```json
{
  "type": "approval",
  "action": "partial_approve",
  "approved_items": ["pillar_1", "pillar_2", "pillar_4"],
  "rejected_items": ["pillar_3"],
  "modifications": {
    "pillar_2": {
      "name": "Construction Software Features",
      "reason": "More specific to our niche"
    }
  },
  "additions": [
    {
      "name": "Job Site Management",
      "rationale": "Core feature we want to rank for"
    }
  ]
}
```
**Workflow Action:** Updates approved items, may loop back or advance

#### 4. `refinement_request`
**When:** Requesting changes to generated content
**Contains:** Specific refinement instructions
**Example:**
```json
{
  "type": "refinement_request",
  "target": "pillar",
  "pillar_id": "pillar_2",
  "instruction": "Add more transactional keywords",
  "scope": "expand"  // expand | reduce | replace
}
```
**Workflow Action:** Re-runs specific workflow step with new parameters

---

### Assistant Message Types

```php
enum AssistantMessageType: string
{
    case QUESTION = 'question';                 // Asking for clarification
    case PROPOSAL = 'proposal';                 // Proposing pillars/clusters
    case VALIDATION_SUMMARY = 'validation_summary'; // SERP validation results
    case CONFIRMATION = 'confirmation';         // Confirming actions taken
}
```

#### 1. `question`
**When:** Workflow needs clarification before proceeding
**Structure:**
```json
{
  "type": "question",
  "workflow_step": "context_ingestion",
  "questions": [
    {
      "id": "q1",
      "question": "What is your primary revenue model?",
      "options": ["subscription", "one-time purchase", "freemium", "other"],
      "required": true
    },
    {
      "id": "q2",
      "question": "Are you targeting any specific geographic regions?",
      "input_type": "text",
      "required": false
    }
  ],
  "context": "I noticed your description mentions software but I want to clarify your business model to ensure accurate keyword targeting."
}
```

#### 2. `proposal`
**When:** Presenting pillars or clusters for approval
**Structure:**
```json
{
  "type": "proposal",
  "workflow_step": "pillar_proposal",
  "items": [
    {
      "id": "pillar_1",
      "name": "Construction Project Management Software",
      "rationale": "High-intent keyword targeting your core product category",
      "estimated_difficulty": "medium",
      "strategic_fit": "primary"
    }
  ],
  "summary": "Based on your business description, I've identified 6 pillar keywords that represent the core topics you should own.",
  "actions_available": ["approve_all", "approve_selected", "request_changes", "add_suggestion"]
}
```

#### 3. `validation_summary`
**When:** After SERP validation completes
**Structure:**
```json
{
  "type": "validation_summary",
  "workflow_step": "serp_validation",
  "results": [
    {
      "pillar_id": "pillar_1",
      "original_name": "Construction PM Software",
      "validated": true,
      "adjustments": null,
      "serp_insights": {
        "competition_level": "medium",
        "intent_match": "strong",
        "top_competitors": ["Procore", "Buildertrend", "CoConstruct"]
      }
    },
    {
      "pillar_id": "pillar_3",
      "original_name": "Building Apps",
      "validated": false,
      "adjustments": {
        "suggested_name": "Construction Management Apps",
        "reason": "Original term too ambiguous, SERP shows mixed intent"
      }
    }
  ],
  "actions_available": ["accept_adjustments", "override", "request_revalidation"]
}
```

#### 4. `confirmation`
**When:** Confirming completed actions
**Structure:**
```json
{
  "type": "confirmation",
  "workflow_step": "cluster_generation",
  "action_taken": "clusters_generated",
  "summary": "Generated 24 cluster keywords for pillar 'Construction Project Management Software'",
  "details": {
    "tier_1_count": 8,
    "tier_2_count": 12,
    "tier_3_count": 4
  },
  "next_step": "Would you like to review these clusters or proceed to the next pillar?"
}
```

---

## State Machine Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                        WORKFLOW STATES                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────┐    initial_prompt    ┌────────────────────┐           │
│  │  IDLE    │ ─────────────────────▶│ CONTEXT_INGESTION │           │
│  └──────────┘                       └─────────┬──────────┘           │
│                                               │                      │
│                    ┌──────────────────────────┼──────────────────┐   │
│                    │ ambiguity detected?      │ clear            │   │
│                    ▼                          ▼                  │   │
│           ┌────────────────┐         ┌────────────────┐          │   │
│           │ AWAITING_      │         │ PILLAR_        │          │   │
│           │ CLARIFICATION  │────────▶│ PROPOSAL       │          │   │
│           └────────────────┘         └───────┬────────┘          │   │
│                    ▲ question loop           │                   │   │
│                    └─────────────────────────┤                   │   │
│                                              │ approval          │   │
│                                              ▼                   │   │
│                                     ┌────────────────┐           │   │
│                                     │ PILLAR_        │           │   │
│                                     │ VALIDATION     │           │   │
│                                     └───────┬────────┘           │   │
│                                             │                    │   │
│                                             ▼                    │   │
│                   ┌─────────────────────────────────────┐        │   │
│                   │          CLUSTER_GENERATION          │        │   │
│                   │  (iterates per pillar)               │        │   │
│                   └─────────────────┬───────────────────┘        │   │
│                                     │                            │   │
│                                     ▼                            │   │
│                            ┌────────────────┐                    │   │
│                            │   COMPLETE     │                    │   │
│                            └────────────────┘                    │   │
│                                                                  │   │
└──────────────────────────────────────────────────────────────────┘
```

---

## Neuron AI Integration Points

### Workflow Interruption Pattern

The UX contract maps directly to Neuron's Human-in-the-Loop capability:

```php
// In a workflow node
public function __invoke(PillarProposalEvent $event, WorkflowState $state): ApprovalEvent|InterruptEvent
{
    // Generate proposal
    $proposal = $this->generatePillarProposal($state);

    // Interrupt and wait for user input
    $feedback = $this->interrupt([
        'type' => 'proposal',
        'workflow_step' => 'pillar_proposal',
        'items' => $proposal->items,
        'actions_available' => ['approve_all', 'approve_selected', 'request_changes']
    ]);

    // Process feedback and continue
    return new ApprovalEvent($feedback);
}
```

### Message Type Detection

```php
class MessageTypeDetector
{
    public function detect(string $content, WorkflowState $state): UserMessageType
    {
        // If no active run, must be initial prompt
        if (!$state->has('generation_run_id')) {
            return UserMessageType::INITIAL_PROMPT;
        }

        // If awaiting clarification, this is an answer
        if ($state->get('awaiting') === 'clarification') {
            return UserMessageType::CLARIFICATION_ANSWER;
        }

        // If awaiting approval, this is approval/rejection
        if ($state->get('awaiting') === 'approval') {
            return UserMessageType::APPROVAL;
        }

        // Otherwise, refinement request
        return UserMessageType::REFINEMENT_REQUEST;
    }
}
```

---

## Frontend Implementation Notes

### Chat UI Requirements

1. **Message Rendering**
   - Render assistant messages based on `type` field
   - Use structured components for proposals, questions, etc.
   - Don't render as plain text markdown

2. **Input Handling**
   - Show contextual input based on current workflow state
   - For questions: render form with options
   - For proposals: render approval buttons
   - For free text: show refinement input

3. **State Indicators**
   - Show current workflow step
   - Show "Claude is waiting for input" when interrupted
   - Show "Processing..." when workflow is running

4. **Action Buttons**
   - "Approve All" / "Approve Selected"
   - "Request Changes"
   - "Add Suggestion"
   - "Skip for Now"

---

## Data Contracts

### GenerationRun Status Enum

```php
enum GenerationRunStatus: string
{
    case STARTED = 'started';
    case AWAITING_CLARIFICATION = 'awaiting_clarification';
    case AWAITING_APPROVAL = 'awaiting_approval';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
```

### Workflow Step Enum

```php
enum WorkflowStep: string
{
    case CONTEXT_INGESTION = 'context_ingestion';
    case CLARIFICATION = 'clarification';
    case PILLAR_PROPOSAL = 'pillar_proposal';
    case PILLAR_VALIDATION = 'pillar_validation';
    case CLUSTER_GENERATION = 'cluster_generation';
    case COMPLETION = 'completion';
}
```

---

## Exit Criteria

- [ ] All message types are documented and agreed upon
- [ ] State machine diagram is finalized
- [ ] Frontend team understands interaction patterns
- [ ] API contracts for message types are defined
- [ ] Validation rules for each message type are specified

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Enums/UserMessageType.php` | User message type enum |
| `app/Enums/AssistantMessageType.php` | Assistant message type enum |
| `app/Enums/GenerationRunStatus.php` | Run status enum |
| `app/Enums/WorkflowStep.php` | Workflow step enum |
| `app/Services/MessageTypeDetector.php` | Detect incoming message types |

---

## Next Milestone

Once the UX contract is agreed upon, proceed to [Milestone 1: Chat Persistence Layer](./MILESTONE_1.md) to implement the database schema and message storage.
