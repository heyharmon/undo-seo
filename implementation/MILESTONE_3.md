# Milestone 3 — Initial Chat Turn (Context Ingestion)

> **Objective:** User pastes a natural language business description and hits "Start".

This milestone implements the first workflow step where Claude ingests the user's business description, extracts key SEO-relevant information, and either asks clarifying questions or proceeds directly to pillar proposal.

---

## Flow Overview

```
User Input (Natural Language Business Description)
                    │
                    ▼
         ┌─────────────────────┐
         │  ContextIngestion   │
         │       Node          │
         └──────────┬──────────┘
                    │
                    ▼
         ┌─────────────────────┐
         │   Claude extracts:  │
         │   • Offerings       │
         │   • ICP             │
         │   • Geography       │
         │   • Monetization    │
         │   • Differentiators │
         └──────────┬──────────┘
                    │
          ┌─────────┴─────────┐
          │                   │
    Ambiguity?           Clear?
          │                   │
          ▼                   ▼
   ┌──────────────┐   ┌──────────────┐
   │ INTERRUPT    │   │ Continue to  │
   │ Ask batched  │   │ Pillar       │
   │ questions    │   │ Proposal     │
   └──────────────┘   └──────────────┘
```

---

## Structured Output Classes

### BusinessContext DTO

```php
<?php

namespace App\Neuron\Dto;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;
use NeuronAI\StructuredOutput\Validation\Rules\Count;

class BusinessContext
{
    /**
     * @var \App\Neuron\Dto\Offering[]
     */
    #[SchemaProperty(
        description: 'Products or services the business offers. Extract specific offerings mentioned.',
        required: true
    )]
    #[ArrayOf(Offering::class)]
    #[Count(min: 1, max: 10)]
    public array $offerings;

    #[SchemaProperty(
        description: 'Ideal Customer Profile - who the business serves',
        required: true
    )]
    public IdealCustomerProfile $icp;

    #[SchemaProperty(
        description: 'Geographic targeting information',
        required: true
    )]
    public GeographicTarget $geography;

    #[SchemaProperty(
        description: 'How the business makes money',
        required: true
    )]
    public MonetizationModel $monetization;

    /**
     * @var string[]
     */
    #[SchemaProperty(
        description: 'Unique differentiators or competitive advantages mentioned',
        required: false
    )]
    public array $differentiators = [];

    /**
     * @var \App\Neuron\Dto\Ambiguity[]
     */
    #[SchemaProperty(
        description: 'Areas where the description is unclear and needs clarification',
        required: true
    )]
    #[ArrayOf(Ambiguity::class)]
    public array $ambiguities = [];

    #[SchemaProperty(
        description: 'Overall confidence score (0-100) in the extracted information',
        required: true
    )]
    public int $confidenceScore;
}
```

### Supporting DTOs

```php
<?php

namespace App\Neuron\Dto;

use NeuronAI\StructuredOutput\SchemaProperty;

class Offering
{
    #[SchemaProperty(description: 'Name of the product or service', required: true)]
    public string $name;

    #[SchemaProperty(description: 'Type: product, service, platform, tool', required: true)]
    public string $type;

    #[SchemaProperty(description: 'Brief description of what it does', required: true)]
    public string $description;

    #[SchemaProperty(description: 'Is this the primary/flagship offering?', required: true)]
    public bool $isPrimary;
}

class IdealCustomerProfile
{
    #[SchemaProperty(description: 'Industry or vertical (e.g., construction, healthcare)', required: false)]
    public ?string $industry = null;

    #[SchemaProperty(description: 'Company size (e.g., SMB, enterprise, 10-50 employees)', required: false)]
    public ?string $companySize = null;

    #[SchemaProperty(description: 'Job titles or roles of decision makers', required: false)]
    public array $decisionMakers = [];

    #[SchemaProperty(description: 'B2B or B2C or both', required: true)]
    public string $businessModel;

    #[SchemaProperty(description: 'Specific pain points or needs mentioned', required: false)]
    public array $painPoints = [];
}

class GeographicTarget
{
    #[SchemaProperty(description: 'Is this a local, regional, national, or global business?', required: true)]
    public string $scope;

    #[SchemaProperty(description: 'Specific countries targeted', required: false)]
    public array $countries = [];

    #[SchemaProperty(description: 'Specific regions or cities targeted', required: false)]
    public array $regions = [];

    #[SchemaProperty(description: 'Language of target market', required: false)]
    public ?string $language = null;
}

class MonetizationModel
{
    #[SchemaProperty(description: 'Primary revenue model: subscription, one-time, freemium, advertising, marketplace, services', required: true)]
    public string $primaryModel;

    #[SchemaProperty(description: 'Secondary revenue streams if mentioned', required: false)]
    public array $secondaryModels = [];

    #[SchemaProperty(description: 'Price point positioning: budget, mid-market, premium, enterprise', required: false)]
    public ?string $pricePoint = null;
}

class Ambiguity
{
    #[SchemaProperty(description: 'Unique identifier for this ambiguity', required: true)]
    public string $id;

    #[SchemaProperty(description: 'Category of ambiguity: offering, icp, geography, monetization, other', required: true)]
    public string $category;

    #[SchemaProperty(description: 'The specific question to ask the user', required: true)]
    public string $question;

    #[SchemaProperty(description: 'Suggested answers/options if applicable', required: false)]
    public array $suggestedOptions = [];

    #[SchemaProperty(description: 'Why this information matters for SEO', required: true)]
    public string $seoRationale;

    #[SchemaProperty(description: 'Is this question critical (must-answer) or nice-to-have?', required: true)]
    public bool $isCritical;
}
```

---

## Context Ingestion Node

```php
<?php

namespace App\Neuron\Nodes;

use App\Neuron\Events\ContextIngestedEvent;
use App\Neuron\Events\ClarificationNeededEvent;
use App\Neuron\Events\ProposePillarsEvent;
use App\Neuron\Dto\BusinessContext;
use App\Neuron\Agents\ContextIngestionAgent;
use App\Enums\AssistantMessageType;
use App\Enums\GenerationRunStatus;
use App\Enums\WorkflowStep;
use NeuronAI\Workflow\StartEvent;
use NeuronAI\Workflow\WorkflowState;
use NeuronAI\Chat\Messages\UserMessage;

class ContextIngestionNode extends BaseTopicalMapNode
{
    public function __invoke(
        StartEvent $event,
        WorkflowState $state
    ): ClarificationNeededEvent|ProposePillarsEvent {

        // Get user input from state
        $userInput = $state->get('current_user_input');

        // Update run status
        $this->updateRunStatus(
            GenerationRunStatus::PROCESSING,
            WorkflowStep::CONTEXT_INGESTION
        );

        // Use checkpoint to cache expensive LLM call
        $businessContext = $this->checkpoint('context_extraction', function () use ($userInput) {
            return $this->extractBusinessContext($userInput);
        });

        // Store in workflow state
        $state->set('business_context', $this->serializeContext($businessContext));

        // Update run with business context
        $this->run->update([
            'business_context' => $this->serializeContext($businessContext),
        ]);

        // Check for critical ambiguities
        $criticalAmbiguities = array_filter(
            $businessContext->ambiguities,
            fn($a) => $a->isCritical
        );

        if (count($criticalAmbiguities) > 0 || $businessContext->confidenceScore < 70) {
            // Need clarification - interrupt workflow
            return $this->handleAmbiguities($businessContext, $state);
        }

        // Clear enough to proceed - trigger pillar proposal
        $this->markStepComplete($state, 'context_ingestion');

        return new ProposePillarsEvent(
            businessContext: $this->serializeContext($businessContext)
        );
    }

    /**
     * Extract business context using Claude
     */
    private function extractBusinessContext(string $userInput): BusinessContext
    {
        $agent = ContextIngestionAgent::make();

        return $agent->structured(
            new UserMessage($userInput),
            BusinessContext::class,
            maxRetries: 2
        );
    }

    /**
     * Handle ambiguities by interrupting for clarification
     */
    private function handleAmbiguities(
        BusinessContext $context,
        WorkflowState $state
    ): ClarificationNeededEvent {

        // Build questions for the user
        $questions = [];
        foreach ($context->ambiguities as $ambiguity) {
            $questions[] = [
                'id' => $ambiguity->id,
                'category' => $ambiguity->category,
                'question' => $ambiguity->question,
                'options' => $ambiguity->suggestedOptions,
                'rationale' => $ambiguity->seoRationale,
                'required' => $ambiguity->isCritical,
            ];
        }

        // Store pending questions
        $state->set('pending_questions', $questions);

        // Build summary of what was understood
        $summary = $this->buildContextSummary($context);

        // Create assistant message with questions
        $textContent = $this->buildQuestionMessage($summary, $questions);

        $structuredContent = [
            'type' => 'question',
            'workflow_step' => 'context_ingestion',
            'understood_context' => $summary,
            'questions' => $questions,
        ];

        // Interrupt and wait for answers
        $this->interruptWithMessage(
            AssistantMessageType::QUESTION,
            $textContent,
            $structuredContent,
            GenerationRunStatus::AWAITING_CLARIFICATION,
            WorkflowStep::CLARIFICATION
        );

        // This won't be reached until wakeup
        return new ClarificationNeededEvent(questions: $questions);
    }

    /**
     * Build a summary of understood context
     */
    private function buildContextSummary(BusinessContext $context): array
    {
        return [
            'offerings' => array_map(fn($o) => [
                'name' => $o->name,
                'type' => $o->type,
                'isPrimary' => $o->isPrimary,
            ], $context->offerings),
            'icp' => [
                'industry' => $context->icp->industry,
                'businessModel' => $context->icp->businessModel,
                'companySize' => $context->icp->companySize,
            ],
            'geography' => [
                'scope' => $context->geography->scope,
                'countries' => $context->geography->countries,
            ],
            'monetization' => [
                'model' => $context->monetization->primaryModel,
            ],
            'confidence' => $context->confidenceScore,
        ];
    }

    /**
     * Build human-readable question message
     */
    private function buildQuestionMessage(array $summary, array $questions): string
    {
        $message = "Thanks for that overview! Here's what I understood:\n\n";

        // Summarize offerings
        if (!empty($summary['offerings'])) {
            $message .= "**Your offerings:**\n";
            foreach ($summary['offerings'] as $offering) {
                $primary = $offering['isPrimary'] ? ' (primary)' : '';
                $message .= "- {$offering['name']} ({$offering['type']}){$primary}\n";
            }
            $message .= "\n";
        }

        // Summarize ICP
        if ($summary['icp']['industry'] || $summary['icp']['businessModel']) {
            $message .= "**Target market:** {$summary['icp']['businessModel']}";
            if ($summary['icp']['industry']) {
                $message .= " in {$summary['icp']['industry']}";
            }
            $message .= "\n\n";
        }

        // Add questions
        $message .= "To build the most effective keyword strategy, I need a few clarifications:\n\n";

        foreach ($questions as $i => $q) {
            $num = $i + 1;
            $required = $q['required'] ? ' (important)' : '';
            $message .= "**{$num}. {$q['question']}**{$required}\n";

            if (!empty($q['options'])) {
                $message .= "   Options: " . implode(', ', $q['options']) . "\n";
            }

            $message .= "   _Why this matters: {$q['rationale']}_\n\n";
        }

        return $message;
    }

    /**
     * Serialize context for storage
     */
    private function serializeContext(BusinessContext $context): array
    {
        return [
            'offerings' => array_map(fn($o) => [
                'name' => $o->name,
                'type' => $o->type,
                'description' => $o->description,
                'isPrimary' => $o->isPrimary,
            ], $context->offerings),
            'icp' => [
                'industry' => $context->icp->industry,
                'companySize' => $context->icp->companySize,
                'decisionMakers' => $context->icp->decisionMakers,
                'businessModel' => $context->icp->businessModel,
                'painPoints' => $context->icp->painPoints,
            ],
            'geography' => [
                'scope' => $context->geography->scope,
                'countries' => $context->geography->countries,
                'regions' => $context->geography->regions,
                'language' => $context->geography->language,
            ],
            'monetization' => [
                'primaryModel' => $context->monetization->primaryModel,
                'secondaryModels' => $context->monetization->secondaryModels,
                'pricePoint' => $context->monetization->pricePoint,
            ],
            'differentiators' => $context->differentiators,
            'confidenceScore' => $context->confidenceScore,
        ];
    }
}
```

---

## Context Ingestion Agent

```php
<?php

namespace App\Neuron\Agents;

use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;

class ContextIngestionAgent extends Agent
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
                "You are an expert SEO strategist analyzing a business description to build a topical map.",
                "Your goal is to extract structured information about the business that will inform keyword research.",
                "You must identify any ambiguities that would prevent effective keyword targeting.",
            ],
            steps: [
                "Read the business description carefully.",
                "Extract specific products or services offered - be precise about what they actually sell.",
                "Identify the target customer profile - who they serve, their industry, company size.",
                "Determine geographic scope - local, regional, national, or global.",
                "Understand the business model - how they make money.",
                "Note any unique differentiators or competitive advantages.",
                "Identify critical ambiguities that must be clarified for effective SEO.",
                "Score your confidence (0-100) in the extracted information.",
            ],
            output: [
                "Only flag ambiguities that would significantly impact keyword strategy.",
                "Mark ambiguities as 'critical' only if they could lead to targeting wrong keywords.",
                "Batch related questions together - don't ask too many questions.",
                "Maximum 3-4 questions total, prioritize the most important.",
                "Provide concrete suggested options when possible.",
                "Explain WHY each question matters for SEO in plain language.",
            ]
        );
    }
}
```

---

## Clarification Node

Handles user responses to clarification questions:

```php
<?php

namespace App\Neuron\Nodes;

use App\Neuron\Events\ClarificationNeededEvent;
use App\Neuron\Events\ProposePillarsEvent;
use App\Neuron\Dto\ClarificationResponse;
use App\Enums\GenerationRunStatus;
use App\Enums\WorkflowStep;
use NeuronAI\Workflow\WorkflowState;

class ClarificationNode extends BaseTopicalMapNode
{
    public function __invoke(
        ClarificationNeededEvent $event,
        WorkflowState $state
    ): ClarificationNeededEvent|ProposePillarsEvent {

        // Check if we're resuming from interruption
        $feedback = $this->consumeInterruptFeedback();

        if ($feedback === null) {
            // First time through - interrupt for answers
            // This is handled by ContextIngestionNode
            throw new \RuntimeException('ClarificationNode should only be reached after interruption');
        }

        // We have user's answers
        $userAnswers = $feedback['structured_input'] ?? [];
        $userText = $feedback['user_input'] ?? '';

        // If structured answers provided, use those
        if (!empty($userAnswers)) {
            $answers = $userAnswers;
        } else {
            // Parse answers from text using Claude
            $answers = $this->parseAnswersFromText($userText, $state->get('pending_questions'));
        }

        // Store clarifications
        $this->storeClarifications($state, $answers);

        // Update business context with clarified information
        $this->updateBusinessContext($state, $answers);

        // Check if we need more clarification
        $pendingQuestions = $state->get('pending_questions', []);
        $answeredIds = array_keys($answers);
        $unanswered = array_filter($pendingQuestions, fn($q) => !in_array($q['id'], $answeredIds) && $q['required']);

        if (count($unanswered) > 0) {
            // Still need more answers
            return $this->askRemainingQuestions($unanswered, $state);
        }

        // All clarifications complete - proceed to pillar proposal
        $this->markStepComplete($state, 'clarification');

        $this->updateRunStatus(
            GenerationRunStatus::PROCESSING,
            WorkflowStep::PILLAR_PROPOSAL
        );

        return new ProposePillarsEvent(
            businessContext: $state->get('business_context')
        );
    }

    /**
     * Parse unstructured text answers using Claude
     */
    private function parseAnswersFromText(string $text, array $questions): array
    {
        // Use Claude to map user's text to question answers
        $agent = $this->getAgent();

        $prompt = "Given these questions:\n";
        foreach ($questions as $q) {
            $prompt .= "- {$q['id']}: {$q['question']}\n";
        }
        $prompt .= "\nUser's response:\n{$text}\n\n";
        $prompt .= "Extract the answers to each question. Return a JSON object mapping question IDs to answers.";

        $response = $agent->chat(new \NeuronAI\Chat\Messages\UserMessage($prompt));

        // Parse JSON from response
        $content = $response->getContent();
        preg_match('/\{.*\}/s', $content, $matches);

        if (!empty($matches)) {
            return json_decode($matches[0], true) ?? [];
        }

        return [];
    }

    /**
     * Store clarifications in run
     */
    private function storeClarifications(WorkflowState $state, array $answers): void
    {
        $questions = $state->get('pending_questions', []);
        $clarifications = $state->get('clarifications', []);

        foreach ($answers as $questionId => $answer) {
            $question = collect($questions)->firstWhere('id', $questionId);
            if ($question) {
                $clarifications[] = [
                    'question_id' => $questionId,
                    'question' => $question['question'],
                    'answer' => $answer,
                    'answered_at' => now()->toISOString(),
                ];
            }
        }

        $state->set('clarifications', $clarifications);

        // Also update the run model
        $this->run->update(['clarifications' => $clarifications]);
    }

    /**
     * Update business context with clarified answers
     */
    private function updateBusinessContext(WorkflowState $state, array $answers): void
    {
        $context = $state->get('business_context', []);

        foreach ($answers as $questionId => $answer) {
            // Map answer to appropriate context field based on question category
            $question = collect($state->get('pending_questions'))->firstWhere('id', $questionId);

            if ($question) {
                switch ($question['category']) {
                    case 'offering':
                        // Update offerings
                        $context['clarified_offerings'] = $answer;
                        break;
                    case 'icp':
                        // Update ICP
                        if (is_array($answer)) {
                            $context['icp'] = array_merge($context['icp'] ?? [], $answer);
                        } else {
                            $context['icp']['clarification'] = $answer;
                        }
                        break;
                    case 'geography':
                        // Update geography
                        $context['geography']['clarification'] = $answer;
                        break;
                    case 'monetization':
                        // Update monetization
                        $context['monetization']['clarification'] = $answer;
                        break;
                }
            }
        }

        // Increase confidence score after clarification
        $context['confidenceScore'] = min(95, ($context['confidenceScore'] ?? 70) + 15);

        $state->set('business_context', $context);
        $this->run->update(['business_context' => $context]);
    }

    /**
     * Ask remaining unanswered questions
     */
    private function askRemainingQuestions(array $unanswered, WorkflowState $state): ClarificationNeededEvent
    {
        $state->set('pending_questions', $unanswered);

        $textContent = "Thanks for those answers! I have a couple more questions:\n\n";

        foreach ($unanswered as $i => $q) {
            $num = $i + 1;
            $textContent .= "**{$num}. {$q['question']}**\n";
            if (!empty($q['options'])) {
                $textContent .= "   Options: " . implode(', ', $q['options']) . "\n";
            }
            $textContent .= "\n";
        }

        $this->interruptWithMessage(
            \App\Enums\AssistantMessageType::QUESTION,
            $textContent,
            [
                'type' => 'question',
                'workflow_step' => 'clarification',
                'questions' => $unanswered,
            ],
            GenerationRunStatus::AWAITING_CLARIFICATION,
            WorkflowStep::CLARIFICATION
        );

        return new ClarificationNeededEvent(questions: $unanswered);
    }
}
```

---

## API Response Examples

### Successful Context Ingestion (No Clarification Needed)

```json
{
  "run": {
    "id": 1,
    "status": "processing",
    "current_step": "pillar_proposal"
  },
  "response": {
    "type": "step_completed",
    "data": {
      "business_context": {
        "offerings": [
          {
            "name": "Construction Project Management Software",
            "type": "platform",
            "isPrimary": true
          }
        ],
        "icp": {
          "industry": "construction",
          "businessModel": "B2B",
          "companySize": "SMB"
        },
        "geography": {
          "scope": "national",
          "countries": ["United States"]
        },
        "monetization": {
          "primaryModel": "subscription"
        },
        "confidenceScore": 85
      }
    }
  }
}
```

### Clarification Needed Response

```json
{
  "run": {
    "id": 1,
    "status": "awaiting_clarification",
    "current_step": "clarification"
  },
  "response": {
    "type": "awaiting_input",
    "data": {
      "message_type": "question",
      "text_content": "Thanks for that overview! Here's what I understood:\n\n**Your offerings:**\n- Project Management Platform (platform) (primary)\n\n**Target market:** B2B in construction\n\nTo build the most effective keyword strategy, I need a few clarifications:\n\n**1. Are you targeting general contractors, subcontractors, or both?** (important)\n   Options: General contractors, Subcontractors, Both\n   _Why this matters: Different contractor types search for different solutions_\n\n**2. Do you serve residential, commercial, or both types of construction projects?**\n   Options: Residential, Commercial, Both\n   _Why this matters: This affects the competitive landscape and keyword difficulty_",
      "structured_content": {
        "type": "question",
        "workflow_step": "context_ingestion",
        "understood_context": {
          "offerings": [{"name": "Project Management Platform", "type": "platform", "isPrimary": true}],
          "icp": {"industry": "construction", "businessModel": "B2B"},
          "confidence": 65
        },
        "questions": [
          {
            "id": "q_contractor_type",
            "category": "icp",
            "question": "Are you targeting general contractors, subcontractors, or both?",
            "options": ["General contractors", "Subcontractors", "Both"],
            "rationale": "Different contractor types search for different solutions",
            "required": true
          },
          {
            "id": "q_project_type",
            "category": "icp",
            "question": "Do you serve residential, commercial, or both types of construction projects?",
            "options": ["Residential", "Commercial", "Both"],
            "rationale": "This affects the competitive landscape and keyword difficulty",
            "required": false
          }
        ]
      }
    }
  }
}
```

---

## Exit Criteria

- [ ] ContextIngestionNode extracts business context from natural language
- [ ] Ambiguities are correctly identified
- [ ] Questions are batched (max 3-4)
- [ ] Each question has SEO rationale
- [ ] Workflow correctly interrupts for clarification
- [ ] ClarificationNode processes user answers
- [ ] Business context is updated with clarified information
- [ ] Workflow proceeds to pillar proposal when clarification complete
- [ ] All responses follow structured output contracts

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Neuron/Dto/BusinessContext.php` | Business context DTO |
| `app/Neuron/Dto/Offering.php` | Offering DTO |
| `app/Neuron/Dto/IdealCustomerProfile.php` | ICP DTO |
| `app/Neuron/Dto/GeographicTarget.php` | Geography DTO |
| `app/Neuron/Dto/MonetizationModel.php` | Monetization DTO |
| `app/Neuron/Dto/Ambiguity.php` | Ambiguity DTO |
| `app/Neuron/Agents/ContextIngestionAgent.php` | Context ingestion agent |
| `app/Neuron/Nodes/ContextIngestionNode.php` | Context ingestion node |
| `app/Neuron/Nodes/ClarificationNode.php` | Clarification node |

---

## Next Milestone

With context ingestion complete, proceed to [Milestone 4: Pillar Proposal & Approval Loop](./MILESTONE_4.md) to generate and approve pillar keywords.
