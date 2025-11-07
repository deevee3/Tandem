<?php

namespace App\Services;

use App\Models\HandoffRule;
use Illuminate\Support\Collection;

class HandoffRulesService
{
    /**
     * Evaluate all active handoff rules against the given evaluation data.
     */
    public function evaluate(array $evaluation): array
    {
        $rules = HandoffRule::active()->get();
        $matchedRules = [];

        foreach ($rules as $rule) {
            if ($rule->matches($evaluation)) {
                $matchedRules[] = $rule;
            }
        }

        return $matchedRules;
    }

    /**
     * Get the first matching rule (highest priority).
     */
    public function getFirstMatchingRule(array $evaluation): ?HandoffRule
    {
        $rules = HandoffRule::active()->get();

        foreach ($rules as $rule) {
            if ($rule->matches($evaluation)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Check if any handoff rules match.
     */
    public function shouldHandoff(array $evaluation): bool
    {
        return $this->getFirstMatchingRule($evaluation) !== null;
    }

    /**
     * Get handoff actions from matching rules.
     */
    public function getHandoffActions(array $evaluation): array
    {
        $matchedRules = $this->evaluate($evaluation);
        $actions = [];

        foreach ($matchedRules as $rule) {
            $actions = array_merge($actions, $rule->actions ?? []);
        }

        return $actions;
    }

    /**
     * Seed default handoff rules.
     */
    public function seedDefaultRules(): void
    {
        $defaultRules = [
            [
                'name' => 'Low Confidence Handoff',
                'description' => 'Handoff when agent confidence is below 0.6',
                'priority' => 100,
                'conditions' => [
                    'confidence_threshold' => 0.6,
                ],
                'actions' => [
                    'reason_code' => 'low_confidence',
                    'required_skills' => ['general_support'],
                ],
            ],
            [
                'name' => 'Policy Flag Handoff',
                'description' => 'Handoff when specific policy flags are triggered',
                'priority' => 90,
                'conditions' => [
                    'policy_flags' => ['urgent_request', 'legal_issue', 'compliance_required'],
                ],
                'actions' => [
                    'reason_code' => 'policy_flag',
                    'required_skills' => ['escalation'],
                ],
            ],
            [
                'name' => 'Billing Issues Handoff',
                'description' => 'Handoff billing and payment related issues',
                'priority' => 80,
                'conditions' => [
                    'policy_flags' => ['billing_issue', 'payment_problem', 'refund_request'],
                ],
                'actions' => [
                    'reason_code' => 'billing_specialist',
                    'required_skills' => ['billing', 'refund'],
                ],
            ],
            [
                'name' => 'Technical Issues Handoff',
                'description' => 'Handoff complex technical problems',
                'priority' => 70,
                'conditions' => [
                    'policy_flags' => ['technical_issue', 'system_error', 'account_access'],
                ],
                'actions' => [
                    'reason_code' => 'technical_support',
                    'required_skills' => ['technical', 'troubleshooting'],
                ],
            ],
        ];

        foreach ($defaultRules as $rule) {
            HandoffRule::updateOrCreate(
                ['name' => $rule['name']],
                $rule
            );
        }
    }
}
