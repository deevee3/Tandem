<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\HandoffPolicy;
use App\Models\HandoffPolicyRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class PolicyEvaluationService
{
    private HandoffRulesService $handoffRulesService;

    public function __construct(HandoffRulesService $handoffRulesService)
    {
        $this->handoffRulesService = $handoffRulesService;
    }

    public function evaluateAgentResponse(Conversation $conversation, array $payload): PolicyEvaluationResult
    {
        $confidence = $this->normalizeConfidence(Arr::get($payload, 'confidence'));
        $policyFlags = $this->normalizeFlags(Arr::get($payload, 'policy_flags', []));
        $agentReason = $this->normalizeReason(Arr::get($payload, 'reason'));
        $handoffRequested = (bool) Arr::get($payload, 'handoff', false);
        $handoffMetadata = Arr::get($payload, 'handoff_metadata', []);
        $toolError = (bool) Arr::get($payload, 'tool_error', false);

        // Check new configurable handoff rules first
        $evaluation = [
            'confidence' => $confidence,
            'policy_flags' => $policyFlags,
            'reason' => $agentReason,
            'handoff_requested' => $handoffRequested,
            'tool_error' => $toolError,
        ];

        $matchingRule = $this->handoffRulesService->getFirstMatchingRule($evaluation);
        
        if ($matchingRule) {
            return new PolicyEvaluationResult(
                shouldHandoff: true,
                reason: $matchingRule->actions['reason_code'] ?? 'rule_matched',
                confidence: $confidence,
                policyHits: [['rule_id' => $matchingRule->id, 'rule_name' => $matchingRule->name]],
                requiredSkills: $matchingRule->actions['required_skills'] ?? [],
                metadata: array_merge($handoffMetadata, ['matched_rule' => $matchingRule->name])
            );
        }

        // Fallback to legacy policy system
        $policyMatch = $this->findBestPolicyMatch($evaluation);

        $policyHits = [];
        $requiredSkills = [];
        $metadata = is_array($handoffMetadata) ? $handoffMetadata : [];
        $reason = $agentReason;

        if ($policyMatch !== null) {
            /** @var HandoffPolicy $policy */
            $policy = $policyMatch['policy'];
            /** @var HandoffPolicyRule $rule */
            $rule = $policyMatch['rule'];

            $policyHits[] = [
                'policy_id' => $policy->id,
                'policy_reason' => $policy->reason_code,
                'rule_id' => $rule->id,
                'trigger_type' => $rule->trigger_type,
            ];

            $requiredSkills = $policy->skills->pluck('name')->unique()->values()->all();

            $metadata['policy'] = array_filter([
                'id' => $policy->id,
                'name' => $policy->name,
                'reason_code' => $policy->reason_code,
                'rule_trigger' => $rule->trigger_type,
            ]);

            $reason = $policy->reason_code;
        }

        if ($reason === null && $handoffRequested) {
            $reason = $agentReason ?? 'agent_requested_handoff';
        }

        $queueMetadata = array_filter([
            'reason' => $reason,
            'policy_flags' => $policyFlags,
            'tool_error' => $toolError ? 'unrecoverable' : null,
        ], static fn ($value) => $value !== null && $value !== []);

        return new PolicyEvaluationResult(
            $handoffRequested || $policyMatch !== null,
            $reason,
            $confidence,
            $policyHits,
            $requiredSkills,
            $metadata,
            $queueMetadata,
        );
    }

    protected function findBestPolicyMatch(array $context): ?array
    {
        $policies = HandoffPolicy::query()
            ->where('active', true)
            ->with(['rules' => function ($query) {
                $query->where('active', true)->orderByDesc('priority');
            }, 'skills'])
            ->get();

        $bestMatch = null;

        foreach ($policies as $policy) {
            foreach ($policy->rules as $rule) {
                if (! $this->ruleMatches($policy, $rule, $context)) {
                    continue;
                }

                $priority = (int) $rule->priority;

                if ($context['agent_reason'] && $context['agent_reason'] === $policy->reason_code) {
                    $priority = max($priority, PHP_INT_MAX - 1);
                }

                if ($bestMatch === null || $priority > $bestMatch['priority']) {
                    $bestMatch = [
                        'policy' => $policy,
                        'rule' => $rule,
                        'priority' => $priority,
                    ];
                }
            }

            if ($bestMatch === null && ($context['agent_reason'] ?? $context['reason']) === $policy->reason_code) {
                $firstRule = $policy->rules->first();

                if ($firstRule) {
                    $bestMatch = [
                        'policy' => $policy,
                        'rule' => $firstRule,
                        'priority' => PHP_INT_MAX,
                    ];
                }
            }
        }

        return $bestMatch;
    }

    protected function ruleMatches(HandoffPolicy $policy, HandoffPolicyRule $rule, array $context): bool
    {
        $criteria = $rule->criteria ?? [];

        return match ($rule->trigger_type) {
            'confidence_below_threshold' => $this->confidenceBelowThreshold($policy, $criteria, $context),
            'policy_flag_detected' => $this->policyFlagDetected($criteria, $context),
            'tool_error' => (bool) Arr::get($context, 'tool_error', false),
            'agent_requested_handoff' => (bool) Arr::get($context, 'handoff_requested', false),
            default => false,
        };
    }

    protected function confidenceBelowThreshold(HandoffPolicy $policy, array $criteria, array $context): bool
    {
        $threshold = Arr::get($criteria, 'threshold');

        if ($threshold === null && $policy->confidence_threshold !== null) {
            $threshold = (float) $policy->confidence_threshold;
        }

        if ($threshold === null) {
            return false;
        }

        $confidence = Arr::get($context, 'confidence');

        return $confidence === null || $confidence < (float) $threshold;
    }

    protected function policyFlagDetected(array $criteria, array $context): bool
    {
        $expectedFlags = Collection::make(Arr::get($criteria, 'flags', []))
            ->filter(fn ($flag) => is_string($flag) && $flag !== '')
            ->map(fn ($flag) => strtolower(trim($flag)));

        if ($expectedFlags->isEmpty()) {
            return false;
        }

        $payloadFlags = Collection::make(Arr::get($context, 'policy_flags', []));

        return $payloadFlags->intersect($expectedFlags)->isNotEmpty();
    }

    protected function normalizeConfidence(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $confidence = (float) $value;

        if ($confidence < 0 || $confidence > 1) {
            return null;
        }

        return $confidence;
    }

    protected function normalizeFlags(mixed $flags): array
    {
        return Collection::make(Arr::wrap($flags))
            ->filter(fn ($flag) => is_string($flag) && $flag !== '')
            ->map(fn ($flag) => strtolower(trim($flag)))
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $trimmed = trim($reason);

        if ($trimmed === '') {
            return null;
        }

        return str_replace(' ', '_', strtolower($trimmed));
    }
}
