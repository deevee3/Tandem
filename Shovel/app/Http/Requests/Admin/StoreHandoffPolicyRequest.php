<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\HandlesHandoffPolicyRuleValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHandoffPolicyRequest extends FormRequest
{
    use HandlesHandoffPolicyRuleValidation;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reason_code' => ['required', 'string', 'max:100', Rule::unique('handoff_policies', 'reason_code')],
            'confidence_threshold' => ['nullable', 'numeric', 'between:0,1'],
            'metadata' => ['nullable', 'array'],
            'active' => ['nullable', 'boolean'],
            'skill_ids' => ['nullable', 'array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
            'rules' => ['nullable', 'array'],
            'rules.*.trigger_type' => ['required_with:rules', 'string', Rule::in($this->triggerOptions())],
            'rules.*.criteria' => ['nullable', 'array'],
            'rules.*.priority' => ['nullable', 'integer', 'min:0'],
            'rules.*.active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason_code')) {
            $this->merge([
                'reason_code' => Str::slug((string) $this->input('reason_code'), '_'),
            ]);
        }

        if ($this->has('rules') && is_array($this->input('rules'))) {
            $normalizedRules = collect($this->input('rules'))
                ->map(function ($rule) {
                    if (! is_array($rule)) {
                        return $rule;
                    }

                    return array_filter([
                        'trigger_type' => Arr::get($rule, 'trigger_type'),
                        'criteria' => Arr::get($rule, 'criteria', []),
                        'priority' => Arr::get($rule, 'priority'),
                        'active' => Arr::get($rule, 'active'),
                    ], static fn ($value) => $value !== null);
                })
                ->all();

            $this->merge(['rules' => $normalizedRules]);
        }
    }

    protected function getValidatorInstance(): Validator
    {
        $validator = parent::getValidatorInstance();
        $this->configureRuleValidator($validator);

        return $validator;
    }

    public function policyAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => Arr::get($validated, 'name'),
            'reason_code' => Arr::get($validated, 'reason_code'),
            'confidence_threshold' => Arr::has($validated, 'confidence_threshold')
                ? round((float) Arr::get($validated, 'confidence_threshold'), 4)
                : null,
            'metadata' => Arr::get($validated, 'metadata'),
            'active' => Arr::get($validated, 'active', true),
        ];
    }

    public function hasRules(): bool
    {
        return $this->has('rules');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rulePayloads(): array
    {
        return $this->normalizeRulePayloads($this->validated('rules', []));
    }

    /**
     * @return array<int, int>
     */
    public function skillIds(): array
    {
        $skills = $this->validated('skill_ids', []);

        return collect($skills)
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
