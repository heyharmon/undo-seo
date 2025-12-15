# Milestone 5 — Pillar SERP Validation (Silent but Explainable)

> **Objective:** Validate pillars without breaking chat flow.

This milestone implements SERP validation for approved pillars. Claude uses a SERP search tool to verify that each pillar has real competition, proper intent alignment, and is worth pursuing. Validation happens "silently" (automatically) but results are presented for user review and potential override.

---

## Flow Overview

```
Approved Pillars (from Milestone 4)
                │
                ▼
    ┌───────────────────────────┐
    │   PillarValidationNode    │
    └───────────────┬───────────┘
                    │
            For each pillar:
                    │
                    ▼
    ┌───────────────────────────┐
    │   Call SerpSearchTool     │
    │   (deterministic web      │
    │    access)                │
    └───────────────┬───────────┘
                    │
                    ▼
    ┌───────────────────────────┐
    │   Claude analyzes:        │
    │   • Real competition?     │
    │   • Intent matches?       │
    │   • Recommend changes?    │
    └───────────────┬───────────┘
                    │
                    ▼
    ┌───────────────────────────┐
    │      INTERRUPT            │
    │   Present validation      │
    │   results to user         │
    └───────────────┬───────────┘
                    │
    ┌───────────────┴───────────┐
    │                           │
    ▼                           ▼
Accept all                  Override some
adjustments                 adjustments
    │                           │
    └───────────────────────────┘
                    │
                    ▼
    ┌───────────────────────────┐
    │   Finalize pillars with   │
    │   validated_at timestamp  │
    └───────────────┬───────────┘
                    │
                    ▼
         PillarsValidatedEvent
         (triggers cluster generation)
```

---

## SERP Search Tool

### Custom Tool Implementation

```php
<?php

namespace App\Neuron\Tools;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;
use GuzzleHttp\Client;

class SerpSearchTool extends Tool
{
    protected Client $client;

    public function __construct(
        protected string $apiKey,
        protected string $searchEngine = 'google'
    ) {
        parent::__construct(
            'serp_search',
            'Search Google and return top organic results for a keyword. Use this to validate keywords against real search results.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'The search query/keyword to look up',
                required: true
            ),
            new ToolProperty(
                name: 'location',
                type: PropertyType::STRING,
                description: 'Geographic location for search (e.g., "United States")',
                required: false
            ),
            new ToolProperty(
                name: 'num_results',
                type: PropertyType::NUMBER,
                description: 'Number of results to return (max 10)',
                required: false
            ),
        ];
    }

    public function __invoke(
        string $query,
        ?string $location = 'United States',
        ?int $num_results = 10
    ): string {
        $num_results = min($num_results, 10);

        try {
            $response = $this->getClient()->get('search', [
                'query' => [
                    'q' => $query,
                    'location' => $location,
                    'num' => $num_results,
                    'api_key' => $this->apiKey,
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $this->formatResults($data, $query);

        } catch (\Exception $e) {
            return json_encode([
                'error' => true,
                'message' => 'SERP search failed: ' . $e->getMessage(),
            ]);
        }
    }

    private function formatResults(array $data, string $query): string
    {
        $results = [
            'query' => $query,
            'organic_results' => [],
            'search_metadata' => [
                'total_results' => $data['search_information']['total_results'] ?? 'unknown',
            ],
        ];

        foreach ($data['organic_results'] ?? [] as $i => $result) {
            $results['organic_results'][] = [
                'position' => $i + 1,
                'title' => $result['title'] ?? '',
                'url' => $result['link'] ?? '',
                'domain' => parse_url($result['link'] ?? '', PHP_URL_HOST),
                'snippet' => $result['snippet'] ?? '',
            ];
        }

        // Include featured snippets if present
        if (isset($data['answer_box'])) {
            $results['featured_snippet'] = [
                'type' => $data['answer_box']['type'] ?? 'unknown',
                'title' => $data['answer_box']['title'] ?? '',
            ];
        }

        // Include People Also Ask if present
        if (isset($data['related_questions'])) {
            $results['people_also_ask'] = array_slice(
                array_map(fn($q) => $q['question'], $data['related_questions']),
                0,
                5
            );
        }

        return json_encode($results, JSON_PRETTY_PRINT);
    }

    protected function getClient(): Client
    {
        return $this->client ??= new Client([
            'base_uri' => 'https://serpapi.com/',
            'timeout' => 30,
        ]);
    }
}
```

---

## Structured Output Classes

### Validation Result DTO

```php
<?php

namespace App\Neuron\Dto;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;

class PillarValidationResult
{
    /**
     * @var \App\Neuron\Dto\ValidatedPillar[]
     */
    #[SchemaProperty(
        description: 'Validation results for each pillar',
        required: true
    )]
    #[ArrayOf(ValidatedPillar::class)]
    public array $pillars;

    #[SchemaProperty(
        description: 'Overall assessment of the pillar set quality',
        required: true
    )]
    public string $overallAssessment;

    #[SchemaProperty(
        description: 'Any recommendations for the overall strategy',
        required: false
    )]
    public ?string $strategyRecommendations = null;
}

class ValidatedPillar
{
    #[SchemaProperty(description: 'The pillar ID being validated', required: true)]
    public string $pillarId;

    #[SchemaProperty(description: 'Original pillar name', required: true)]
    public string $originalName;

    #[SchemaProperty(description: 'Is this pillar validated as worth pursuing?', required: true)]
    public bool $isValid;

    #[SchemaProperty(description: 'Confidence score 0-100 in validation', required: true)]
    public int $confidenceScore;

    #[SchemaProperty(description: 'Suggested adjustment to pillar name if any', required: false)]
    public ?string $suggestedName = null;

    #[SchemaProperty(description: 'Reason for adjustment or validation status', required: true)]
    public string $reason;

    #[SchemaProperty(description: 'Competition level observed: low, medium, high, very_high', required: true)]
    public string $competitionLevel;

    #[SchemaProperty(description: 'Does search intent align with business goals?', required: true)]
    public string $intentAlignment;

    #[SchemaProperty(description: 'Top competitor domains found', required: true)]
    public array $topCompetitors;

    #[SchemaProperty(description: 'SERP feature opportunities observed', required: false)]
    public array $serpFeatures = [];

    #[SchemaProperty(description: 'Notable insights from SERP analysis', required: false)]
    public ?string $insights = null;
}
```

---

## Pillar Validation Node

```php
<?php

namespace App\Neuron\Nodes;

use App\Neuron\Events\PillarsApprovedEvent;
use App\Neuron\Events\PillarsValidatedEvent;
use App\Neuron\Dto\PillarValidationResult;
use App\Neuron\Agents\PillarValidationAgent;
use App\Neuron\Tools\SerpSearchTool;
use App\Models\ApprovedPillar;
use App\Enums\AssistantMessageType;
use App\Enums\GenerationRunStatus;
use App\Enums\WorkflowStep;
use NeuronAI\Workflow\WorkflowState;
use NeuronAI\Chat\Messages\UserMessage;

class PillarValidationNode extends BaseTopicalMapNode
{
    public function __invoke(
        PillarsApprovedEvent $event,
        WorkflowState $state
    ): PillarsValidatedEvent {

        // Check if resuming from interruption
        $feedback = $this->consumeInterruptFeedback();

        if ($feedback !== null) {
            // Process user's acceptance or override of validation results
            return $this->processValidationFeedback($feedback, $state);
        }

        // First time through - perform validation
        $this->updateRunStatus(
            GenerationRunStatus::PROCESSING,
            WorkflowStep::PILLAR_VALIDATION
        );

        // Validate each pillar
        $validationResults = $this->checkpoint('serp_validation', function () use ($event) {
            return $this->validatePillars($event->approvedPillars);
        });

        // Store results in state
        $state->set('validation_results', $this->serializeValidation($validationResults));

        // Present results for user review
        return $this->presentValidationResults($validationResults, $state);
    }

    /**
     * Validate pillars against SERP data
     */
    private function validatePillars(array $approvedPillars): PillarValidationResult
    {
        // Create agent with SERP tool
        $agent = PillarValidationAgent::make()->addTool(
            new SerpSearchTool(config('services.serpapi.key'))
        );

        // Build validation prompt
        $prompt = $this->buildValidationPrompt($approvedPillars);

        // Claude will call SERP tool for each pillar and analyze results
        return $agent->structured(
            new UserMessage($prompt),
            PillarValidationResult::class,
            maxRetries: 2
        );
    }

    /**
     * Build prompt for validation
     */
    private function buildValidationPrompt(array $approvedPillars): string
    {
        $prompt = "Validate each of these approved pillar keywords against real Google search results.\n\n";

        $prompt .= "## Pillars to Validate\n";
        foreach ($approvedPillars as $pillar) {
            $prompt .= "- {$pillar['name']} (ID: {$pillar['id']})\n";
        }

        $prompt .= "\n## Validation Process\n";
        $prompt .= "For each pillar:\n";
        $prompt .= "1. Use the serp_search tool to get top 10 results\n";
        $prompt .= "2. Analyze the SERP to determine:\n";
        $prompt .= "   - Is there real competition (not just spam/low-quality)?\n";
        $prompt .= "   - Does the search intent match what we expect?\n";
        $prompt .= "   - What are the top competitor domains?\n";
        $prompt .= "   - Are there SERP feature opportunities?\n";
        $prompt .= "3. If the pillar is problematic, suggest an alternative wording\n\n";

        $prompt .= "## Validation Criteria\n";
        $prompt .= "- Mark as VALID if: real competition exists, intent matches, reasonable difficulty\n";
        $prompt .= "- Mark as INVALID if: no real competition, mixed/wrong intent, or dominated by giant brands\n";
        $prompt .= "- Suggest rewording if: slight adjustment could improve targeting\n";

        return $prompt;
    }

    /**
     * Present validation results for user review
     */
    private function presentValidationResults(
        PillarValidationResult $results,
        WorkflowState $state
    ): PillarsValidatedEvent {

        // Build human-readable summary
        $textContent = $this->buildValidationMessage($results);

        // Build structured content for UI
        $structuredContent = [
            'type' => 'validation_summary',
            'workflow_step' => 'pillar_validation',
            'overall_assessment' => $results->overallAssessment,
            'strategy_recommendations' => $results->strategyRecommendations,
            'results' => array_map(fn($p) => [
                'pillar_id' => $p->pillarId,
                'original_name' => $p->originalName,
                'validated' => $p->isValid,
                'confidence' => $p->confidenceScore,
                'suggested_name' => $p->suggestedName,
                'reason' => $p->reason,
                'competition_level' => $p->competitionLevel,
                'intent_alignment' => $p->intentAlignment,
                'top_competitors' => $p->topCompetitors,
                'serp_features' => $p->serpFeatures,
                'insights' => $p->insights,
            ], $results->pillars),
            'actions_available' => [
                'accept_all',
                'accept_adjustments',
                'override_adjustment',
                'request_revalidation',
            ],
        ];

        // Interrupt and wait for user confirmation
        $this->interruptWithMessage(
            AssistantMessageType::VALIDATION_SUMMARY,
            $textContent,
            $structuredContent,
            GenerationRunStatus::AWAITING_APPROVAL,
            WorkflowStep::PILLAR_VALIDATION
        );

        // Won't reach until wakeup
        return new PillarsValidatedEvent(validatedPillars: []);
    }

    /**
     * Build human-readable validation message
     */
    private function buildValidationMessage(PillarValidationResult $results): string
    {
        $validCount = count(array_filter($results->pillars, fn($p) => $p->isValid));
        $totalCount = count($results->pillars);
        $adjustmentCount = count(array_filter($results->pillars, fn($p) => $p->suggestedName !== null));

        $message = "## SERP Validation Complete\n\n";
        $message .= "**Overall:** {$validCount}/{$totalCount} pillars validated successfully\n";

        if ($adjustmentCount > 0) {
            $message .= "**Note:** {$adjustmentCount} pillar(s) have suggested adjustments\n";
        }

        $message .= "\n{$results->overallAssessment}\n\n";
        $message .= "---\n\n";

        foreach ($results->pillars as $pillar) {
            $statusEmoji = $pillar->isValid ? '✅' : '⚠️';
            $message .= "### {$statusEmoji} {$pillar->originalName}\n";

            if ($pillar->suggestedName && $pillar->suggestedName !== $pillar->originalName) {
                $message .= "**Suggested:** {$pillar->suggestedName}\n";
            }

            $message .= "**Status:** {$pillar->reason}\n";
            $message .= "- Competition: {$pillar->competitionLevel}\n";
            $message .= "- Intent Match: {$pillar->intentAlignment}\n";
            $message .= "- Top Competitors: " . implode(', ', array_slice($pillar->topCompetitors, 0, 3)) . "\n";

            if (!empty($pillar->serpFeatures)) {
                $message .= "- SERP Opportunities: " . implode(', ', $pillar->serpFeatures) . "\n";
            }

            if ($pillar->insights) {
                $message .= "\n_{$pillar->insights}_\n";
            }

            $message .= "\n";
        }

        $message .= "---\n\n";
        $message .= "**Would you like to:**\n";
        $message .= "- Accept all results and adjustments\n";
        $message .= "- Override any suggested changes (keep original names)\n";
        $message .= "- Re-validate any specific pillars\n";

        return $message;
    }

    /**
     * Process user feedback on validation results
     */
    private function processValidationFeedback(
        array $feedback,
        WorkflowState $state
    ): PillarsValidatedEvent {

        $structuredInput = $feedback['structured_input'] ?? [];
        $userText = $feedback['user_input'] ?? '';

        $action = $structuredInput['action'] ?? $this->inferAction($userText);

        $validationResults = $state->get('validation_results', []);
        $approvedPillars = $state->get('approved_pillars', []);

        // Process based on action
        $finalPillars = [];

        foreach ($validationResults as $result) {
            $pillar = collect($approvedPillars)->firstWhere('id', $result['pillar_id']);

            if (!$pillar) continue;

            // Check if user wants to override this specific adjustment
            $overrides = $structuredInput['overrides'] ?? [];

            if (in_array($result['pillar_id'], $overrides)) {
                // Keep original name
                $finalName = $result['original_name'];
            } elseif ($result['suggested_name'] && $action === 'accept_adjustments') {
                // Accept suggested name
                $finalName = $result['suggested_name'];
            } else {
                // Keep original name
                $finalName = $result['original_name'];
            }

            // Update the approved pillar record
            $approvedPillarModel = ApprovedPillar::find($pillar['approved_pillar_id']);
            if ($approvedPillarModel) {
                $approvedPillarModel->markAsValidated([
                    'competition_level' => $result['competition_level'],
                    'intent_alignment' => $result['intent_alignment'],
                    'top_competitors' => $result['top_competitors'],
                    'serp_features' => $result['serp_features'] ?? [],
                    'insights' => $result['insights'] ?? null,
                ]);

                // Update name if changed
                if ($finalName !== $pillar['name']) {
                    $approvedPillarModel->update(['approved_name' => $finalName]);
                }
            }

            $finalPillars[] = array_merge($pillar, [
                'validated_name' => $finalName,
                'serp_data' => $result,
            ]);
        }

        $state->set('validated_pillars', $finalPillars);
        $this->markStepComplete($state, 'pillar_validation');

        // Create confirmation message
        $this->createAssistantMessage(
            AssistantMessageType::CONFIRMATION,
            "Pillars validated and finalized. Now generating cluster keywords...",
            [
                'type' => 'confirmation',
                'action_taken' => 'pillars_validated',
                'pillar_count' => count($finalPillars),
                'pillars' => array_map(fn($p) => [
                    'name' => $p['validated_name'],
                    'competition' => $p['serp_data']['competition_level'],
                ], $finalPillars),
            ]
        );

        return new PillarsValidatedEvent(validatedPillars: $finalPillars);
    }

    /**
     * Infer action from text
     */
    private function inferAction(string $text): string
    {
        $lower = strtolower($text);

        if (str_contains($lower, 'accept') || str_contains($lower, 'looks good') || str_contains($lower, 'yes')) {
            return 'accept_adjustments';
        }

        if (str_contains($lower, 'override') || str_contains($lower, 'keep original')) {
            return 'override';
        }

        return 'accept_adjustments'; // Default
    }

    /**
     * Serialize validation results for storage
     */
    private function serializeValidation(PillarValidationResult $results): array
    {
        return array_map(fn($p) => [
            'pillar_id' => $p->pillarId,
            'original_name' => $p->originalName,
            'is_valid' => $p->isValid,
            'confidence' => $p->confidenceScore,
            'suggested_name' => $p->suggestedName,
            'reason' => $p->reason,
            'competition_level' => $p->competitionLevel,
            'intent_alignment' => $p->intentAlignment,
            'top_competitors' => $p->topCompetitors,
            'serp_features' => $p->serpFeatures,
            'insights' => $p->insights,
        ], $results->pillars);
    }
}
```

---

## Pillar Validation Agent

```php
<?php

namespace App\Neuron\Agents;

use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;

class PillarValidationAgent extends Agent
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
                "You are an SEO analyst validating pillar keywords against real Google search results.",
                "Your job is to ensure each pillar keyword is worth pursuing by analyzing actual SERP data.",
                "You have access to a SERP search tool to query Google.",
            ],
            steps: [
                "For EACH pillar keyword, call the serp_search tool to get top results.",
                "Analyze the organic results to assess competition quality and intent.",
                "Identify the top competitor domains ranking for each keyword.",
                "Note any SERP features (featured snippets, PAA, etc.) as opportunities.",
                "Determine if the keyword needs slight rewording for better targeting.",
                "Provide a validation verdict with clear reasoning.",
            ],
            output: [
                "You MUST call the serp_search tool for each pillar - do not guess.",
                "Mark a pillar as invalid only if there's a clear problem with it.",
                "Suggest rewording only if it would meaningfully improve targeting.",
                "Keep suggested names close to originals - don't change strategy, just optimize.",
                "Provide actionable insights about what makes each SERP competitive.",
                "Note specific SERP features the business could target.",
            ]
        );
    }
}
```

---

## Configuration

Add SERP API configuration:

```env
# .env
SERPAPI_KEY=your-serpapi-key
```

```php
// config/services.php
return [
    // ... other services

    'serpapi' => [
        'key' => env('SERPAPI_KEY'),
    ],
];
```

---

## Guardrails

### Validation Rules

1. **All web access through SerpSearchTool** — Claude cannot access arbitrary URLs
2. **Pillars must be validated** — Cannot skip validation step
3. **User can override** — User has final say on pillar names
4. **Adjustments are suggestions** — Never auto-apply changes without user consent

### Rate Limiting

```php
// Implement rate limiting for SERP API
class RateLimitedSerpSearchTool extends SerpSearchTool
{
    private static array $requestTimestamps = [];
    private int $maxRequestsPerMinute = 30;

    public function __invoke(string $query, ?string $location = null, ?int $num_results = 10): string
    {
        $this->enforceRateLimit();
        return parent::__invoke($query, $location, $num_results);
    }

    private function enforceRateLimit(): void
    {
        $now = time();
        self::$requestTimestamps = array_filter(
            self::$requestTimestamps,
            fn($ts) => $ts > ($now - 60)
        );

        if (count(self::$requestTimestamps) >= $this->maxRequestsPerMinute) {
            sleep(2); // Wait before retrying
        }

        self::$requestTimestamps[] = $now;
    }
}
```

---

## Exit Criteria

- [ ] SerpSearchTool implemented and working
- [ ] PillarValidationNode performs SERP lookups for each pillar
- [ ] Competition level assessed from real data
- [ ] Intent alignment verified
- [ ] Top competitors identified
- [ ] Suggested adjustments presented clearly
- [ ] User can accept or override adjustments
- [ ] Validated pillars stored with `validated_at` timestamp
- [ ] All web access goes through SerpSearchTool (no arbitrary URLs)

---

## Files to Create

| File | Purpose |
|------|---------|
| `app/Neuron/Tools/SerpSearchTool.php` | SERP search tool |
| `app/Neuron/Dto/PillarValidationResult.php` | Validation result DTO |
| `app/Neuron/Dto/ValidatedPillar.php` | Individual validated pillar DTO |
| `app/Neuron/Agents/PillarValidationAgent.php` | Validation agent |
| `app/Neuron/Nodes/PillarValidationNode.php` | Validation workflow node |

---

## Next Milestone

With pillars validated, proceed to [Milestone 6: Cluster Generation](./MILESTONE_6.md) to generate cluster keywords for each pillar.
