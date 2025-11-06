<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

trait HandlesHandoffPolicyRuleValidation
{
    /**
     * @return array<int, string>
     */
    protected function triggerOptions(): array
    {
        return [
            'confidence_below_threshold',
            'policy_flag_detected',
            'tool_error',
            'agent_requested_handoff',
        ];
    }

    protected function configureRuleValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rules = $this->input('rules', []);

            if (! is_array($rules)) {
                return;
            }

            foreach ($rules as $index => $rule) {
                $this->validateRuleCriteria($validator, (array) $rule, "rules.$index");
            }
        });
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function validateRuleCriteria(Validator $validator, array $rule, string $path): void
    {
        $trigger = Arr::get($rule, 'trigger_type');

        if (! $trigger || ! in_array($trigger, $this->triggerOptions(), true)) {
            return;
        }

        $criteria = Arr::get($rule, 'criteria', []);
        $criteriaPath = "$path.criteria";

        switch ($trigger) {
            case 'confidence_below_threshold':
                $threshold = Arr::get($criteria, 'threshold');

                if (! is_numeric($threshold) || $threshold < 0 || $threshold > 1) {
                    $validator->errors()->add("$criteriaPath.threshold", 'The threshold must be a number between 0 and 1.');
                }

                break;
            case 'policy_flag_detected':
                $flags = Arr::get($criteria, 'flags');

                if (! is_array($flags) || empty($flags)) {
                    $validator->errors()->add("$criteriaPath.flags", 'At least one flag must be provided.');
                } else {
                    foreach ($flags as $flagIndex => $flag) {
                        if (! is_string($flag) || Str::of($flag)->trim()->isEmpty()) {
                            $validator->errors()->add("$criteriaPath.flags.$flagIndex", 'Flags must be non-empty strings.');
                        }
                    }
                }

                break;
            case 'tool_error':
                $retryable = Arr::get($criteria, 'retryable');

                if (! is_bool($retryable)) {
                    $validator->errors()->add("$criteriaPath.retryable", 'The retryable value must be a boolean.');
                }

                break;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRulePayloads(array $rules): array
    {
        return collect($rules)
            ->map(fn ($rule) => $this->normalizeRulePayload((array) $rule))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>|null
     */
    protected function normalizeRulePayload(array $rule): ?array
    {
        $trigger = Arr::get($rule, 'trigger_type');

        if (! $trigger || ! in_array($trigger, $this->triggerOptions(), true)) {
            return null;
        }

        $criteria = $this->normalizeRuleCriteria($trigger, Arr::get($rule, 'criteria', []));

        return array_filter([
            'id' => isset($rule['id']) ? (int) $rule['id'] : null,
            'trigger_type' => $trigger,
            'criteria' => $criteria,
            'priority' => isset($rule['priority']) ? (int) $rule['priority'] : 0,
            'active' => array_key_exists('active', $rule) ? (bool) $rule['active'] : true,
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    protected function normalizeRuleCriteria(string $trigger, array $criteria): array
    {
        return match ($trigger) {
            'confidence_below_threshold' => [
                'threshold' => round((float) Arr::get($criteria, 'threshold'), 4),
            ],
            'policy_flag_detected' => [
                'flags' => collect(Arr::get($criteria, 'flags', []))
                    ->filter(fn ($flag) => is_string($flag) && ! Str::of($flag)->trim()->isEmpty())
                    ->map(fn ($flag) => Str::slug(Str::of($flag)->lower(), '_'))
                    ->unique()
                    ->values()
                    ->all(),
            ],
            'tool_error' => [
                'retryable' => (bool) Arr::get($criteria, 'retryable'),
            ],
            default => [],
        };
    }
}
