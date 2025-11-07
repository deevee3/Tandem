<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HandoffRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'conditions',
        'actions',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get only active rules ordered by priority.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('priority', 'desc');
    }

    /**
     * Check if this rule matches the given evaluation data.
     */
    public function matches(array $evaluation): bool
    {
        $conditions = $this->conditions;

        // Check confidence threshold
        if (isset($conditions['confidence_threshold'])) {
            $confidence = $evaluation['confidence'] ?? 1.0;
            if ($confidence < $conditions['confidence_threshold']) {
                return true;
            }
        }

        // Check policy flags
        if (isset($conditions['policy_flags']) && !empty($conditions['policy_flags'])) {
            $policyFlags = $evaluation['policy_flags'] ?? [];
            foreach ($conditions['policy_flags'] as $flag) {
                if (in_array($flag, $policyFlags)) {
                    return true;
                }
            }
        }

        // Check reason codes
        if (isset($conditions['reason_codes']) && !empty($conditions['reason_codes'])) {
            $reason = $evaluation['reason'] ?? null;
            if (in_array($reason, $conditions['reason_codes'])) {
                return true;
            }
        }

        // Check custom conditions
        if (isset($conditions['custom']) && is_array($conditions['custom'])) {
            foreach ($conditions['custom'] as $condition) {
                if ($this->evaluateCustomCondition($condition, $evaluation)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Evaluate custom condition logic.
     */
    private function evaluateCustomCondition(array $condition, array $evaluation): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return false;
        }

        $fieldValue = data_get($evaluation, $field);

        return match ($operator) {
            'equals' => $fieldValue === $value,
            'not_equals' => $fieldValue !== $value,
            'greater_than' => $fieldValue > $value,
            'less_than' => $fieldValue < $value,
            'contains' => str_contains((string) $fieldValue, (string) $value),
            'in' => in_array($fieldValue, (array) $value),
            'not_in' => !in_array($fieldValue, (array) $value),
            default => false,
        };
    }
}
