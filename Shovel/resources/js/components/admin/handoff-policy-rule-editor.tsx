import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';

export type PolicyTriggerType =
    | 'confidence_below_threshold'
    | 'policy_flag_detected'
    | 'tool_error'
    | 'agent_requested_handoff';

export interface PolicyRuleFormValue {
    id?: number;
    trigger_type: PolicyTriggerType;
    criteria: Record<string, unknown>;
    priority: number;
    active: boolean;
}

export type RuleFieldErrors = Record<string, string[]>;

interface HandoffPolicyRuleEditorProps {
    rules: PolicyRuleFormValue[];
    onChange: (nextRules: PolicyRuleFormValue[]) => void;
    errors?: Record<number, RuleFieldErrors>;
}

const triggerOptions: Array<{ value: PolicyTriggerType; label: string; description: string }> = [
    {
        value: 'confidence_below_threshold',
        label: 'Confidence below threshold',
        description: 'Matches when the agent response confidence is lower than the rule threshold.',
    },
    {
        value: 'policy_flag_detected',
        label: 'Policy flag detected',
        description: 'Matches when any of the provided policy flags appear in the model response.',
    },
    {
        value: 'tool_error',
        label: 'Tool error',
        description: 'Matches when a tool error occurs, optionally filtered by retryable state.',
    },
    {
        value: 'agent_requested_handoff',
        label: 'Agent requested handoff',
        description: 'Matches when the agent explicitly requests a human handoff.',
    },
];

function defaultCriteriaForTrigger(trigger: PolicyTriggerType): Record<string, unknown> {
    switch (trigger) {
        case 'confidence_below_threshold':
            return { threshold: 0.5 };
        case 'policy_flag_detected':
            return { flags: [] };
        case 'tool_error':
            return { retryable: false };
        case 'agent_requested_handoff':
        default:
            return {};
    }
}

export default function HandoffPolicyRuleEditor({ rules, onChange, errors = {} }: HandoffPolicyRuleEditorProps) {
    const addRule = () => {
        const nextPriority = rules.length > 0 ? Math.max(...rules.map((rule) => rule.priority ?? 0)) + 10 : 100;
        const nextRules: PolicyRuleFormValue[] = [
            ...rules,
            {
                trigger_type: 'confidence_below_threshold',
                criteria: defaultCriteriaForTrigger('confidence_below_threshold'),
                priority: nextPriority,
                active: true,
            },
        ];
        onChange(nextRules);
    };

    const updateRule = (index: number, partial: Partial<PolicyRuleFormValue>) => {
        const nextRules = rules.map((rule, idx) => (idx === index ? { ...rule, ...partial } : rule));
        onChange(nextRules);
    };

    const updateRuleCriteria = (index: number, criteria: Record<string, unknown>) => {
        const nextRules = rules.map((rule, idx) => (idx === index ? { ...rule, criteria } : rule));
        onChange(nextRules);
    };

    const removeRule = (index: number) => {
        const nextRules = rules.filter((_, idx) => idx !== index);
        onChange(nextRules);
    };

    const moveRule = (index: number, direction: -1 | 1) => {
        const swapWith = index + direction;
        if (swapWith < 0 || swapWith >= rules.length) {
            return;
        }

        const nextRules = [...rules];
        const [current] = nextRules.splice(index, 1);
        nextRules.splice(swapWith, 0, current);
        onChange(nextRules);
    };

    const totalRules = rules.length;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold">Rules</h3>
                    <p className="text-sm text-muted-foreground">
                        Configure the triggers that will activate this handoff policy. Higher priority values run first.
                    </p>
                </div>
                <Button type="button" onClick={addRule} variant="secondary" size="sm" className="gap-2">
                    <Plus className="h-4 w-4" /> Add rule
                </Button>
            </div>

            {totalRules === 0 ? (
                <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                    No rules configured yet. Add a rule to start defining when this policy should trigger.
                </div>
            ) : (
                <div className="space-y-3">
                    {rules.map((rule, index) => (
                        <RuleCard
                            key={rule.id ?? index}
                            index={index}
                            rule={rule}
                            total={totalRules}
                            onMove={moveRule}
                            onRemove={removeRule}
                            onUpdate={updateRule}
                            onUpdateCriteria={updateRuleCriteria}
                            errors={errors[index] ?? {}}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

interface RuleCardProps {
    index: number;
    total: number;
    rule: PolicyRuleFormValue;
    onMove: (index: number, direction: -1 | 1) => void;
    onRemove: (index: number) => void;
    onUpdate: (index: number, partial: Partial<PolicyRuleFormValue>) => void;
    onUpdateCriteria: (index: number, criteria: Record<string, unknown>) => void;
    errors: RuleFieldErrors;
}

function RuleCard({ index, total, rule, onMove, onRemove, onUpdate, onUpdateCriteria, errors }: RuleCardProps) {
    const triggerMeta = useMemo(() => triggerOptions.find((option) => option.value === rule.trigger_type), [rule.trigger_type]);

    const handleTriggerChange = (value: PolicyTriggerType) => {
        onUpdate(index, {
            trigger_type: value,
            criteria: defaultCriteriaForTrigger(value),
        });
    };

    const handlePriorityChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const nextPriority = Number.parseInt(event.target.value, 10);
        onUpdate(index, { priority: Number.isNaN(nextPriority) ? 0 : nextPriority });
    };

    const handleActiveChange = (checked: boolean | string) => {
        onUpdate(index, { active: Boolean(checked) });
    };

    const criteriaErrors = (field: string) => errors[field] ?? errors[`criteria.${field}`] ?? [];

    return (
        <div className="rounded-lg border bg-card p-4 shadow-xs">
            <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div className="flex-1 space-y-4">
                    <div className="grid gap-3 md:grid-cols-[2fr_1fr]">
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">Trigger</Label>
                            <Select value={rule.trigger_type} onValueChange={handleTriggerChange}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select trigger" />
                                </SelectTrigger>
                                <SelectContent>
                                    {triggerOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            <div className="flex flex-col gap-1">
                                                <span className="font-medium">{option.label}</span>
                                                <span className="text-xs text-muted-foreground">{option.description}</span>
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.trigger_type?.map((message, idx) => (
                                <p key={idx} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>

                        <div className="grid gap-2">
                            <Label className="text-sm font-medium" htmlFor={`rule-${index}-priority`}>
                                Priority
                            </Label>
                            <Input
                                id={`rule-${index}-priority`}
                                type="number"
                                value={rule.priority}
                                onChange={handlePriorityChange}
                                min={0}
                            />
                            {errors.priority?.map((message, idx) => (
                                <p key={idx} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label className="text-sm font-medium">Criteria</Label>
                        <TriggerCriteriaFields
                            trigger={rule.trigger_type}
                            criteria={rule.criteria}
                            onChange={(criteria) => onUpdateCriteria(index, criteria)}
                            errors={criteriaErrors}
                        />
                    </div>
                </div>

                <div className="flex w-full flex-row items-start justify-between gap-3 md:w-auto md:flex-col">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id={`rule-${index}-active`}
                            checked={rule.active}
                            onCheckedChange={handleActiveChange}
                        />
                        <Label htmlFor={`rule-${index}-active`} className="text-sm">
                            Active
                        </Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            type="button"
                            onClick={() => onMove(index, -1)}
                            disabled={index === 0}
                            aria-label="Move rule up"
                        >
                            <ArrowUp className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            type="button"
                            onClick={() => onMove(index, 1)}
                            disabled={index === total - 1}
                            aria-label="Move rule down"
                        >
                            <ArrowDown className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            type="button"
                            onClick={() => onRemove(index)}
                            aria-label="Remove rule"
                        >
                            <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </div>
            </div>

            {triggerMeta ? (
                <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                    <Badge variant="outline" className="uppercase">
                        {triggerMeta.label}
                    </Badge>
                    <span className="leading-relaxed">{triggerMeta.description}</span>
                </div>
            ) : null}
        </div>
    );
}

interface TriggerCriteriaFieldsProps {
    trigger: PolicyTriggerType;
    criteria: Record<string, unknown>;
    onChange: (criteria: Record<string, unknown>) => void;
    errors: (field: string) => string[];
}

function TriggerCriteriaFields({ trigger, criteria, onChange, errors }: TriggerCriteriaFieldsProps) {
    switch (trigger) {
        case 'confidence_below_threshold':
            return (
                <div className="space-y-2">
                    <Label className="text-sm" htmlFor="criteria-threshold">
                        Threshold (0 - 1)
                    </Label>
                    <Input
                        id="criteria-threshold"
                        type="number"
                        min={0}
                        max={1}
                        step={0.01}
                        value={typeof criteria.threshold === 'number' ? criteria.threshold : ''}
                        onChange={(event) => {
                            const value = Number.parseFloat(event.target.value);
                            onChange({ threshold: Number.isNaN(value) ? undefined : Number(value.toFixed(4)) });
                        }}
                    />
                    {errors('threshold').map((message, idx) => (
                        <p key={idx} className="text-xs text-destructive">
                            {message}
                        </p>
                    ))}
                </div>
            );
        case 'policy_flag_detected': {
            const flagsValue = Array.isArray(criteria.flags)
                ? (criteria.flags as unknown[])
                      .map((flag) => (typeof flag === 'string' ? flag : String(flag)))
                      .join(', ')
                : '';

            return (
                <div className="space-y-2">
                    <Label className="text-sm" htmlFor="criteria-flags">
                        Flags (comma separated)
                    </Label>
                    <Input
                        id="criteria-flags"
                        value={flagsValue}
                        onChange={(event) => {
                            const nextFlags = event.target.value
                                .split(',')
                                .map((flag) => flag.trim())
                                .filter((flag) => flag.length > 0);
                            onChange({ flags: nextFlags });
                        }}
                        placeholder="pii, legal, compliance"
                    />
                    {errors('flags').map((message, idx) => (
                        <p key={idx} className="text-xs text-destructive">
                            {message}
                        </p>
                    ))}
                </div>
            );
        }
        case 'tool_error':
            return (
                <div className="space-y-2">
                    <Label className="text-sm" htmlFor="criteria-retryable">
                        Retryable error?
                    </Label>
                    <Select
                        value={String(criteria.retryable ?? false)}
                        onValueChange={(value) => {
                            onChange({ retryable: value === 'true' });
                        }}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="true">Yes, retryable</SelectItem>
                            <SelectItem value="false">No, unrecoverable</SelectItem>
                        </SelectContent>
                    </Select>
                    {errors('retryable').map((message, idx) => (
                        <p key={idx} className="text-xs text-destructive">
                            {message}
                        </p>
                    ))}
                </div>
            );
        case 'agent_requested_handoff':
            return (
                <p className="rounded-md border border-dashed bg-muted/40 p-3 text-sm text-muted-foreground">
                    No additional criteria required. This rule matches whenever the agent payload requests a handoff explicitly.
                </p>
            );
        default:
            return (
                <Textarea
                    value={JSON.stringify(criteria ?? {}, null, 2)}
                    onChange={(event) => {
                        try {
                            const parsed = JSON.parse(event.target.value);
                            if (parsed && typeof parsed === 'object') {
                                onChange(parsed as Record<string, unknown>);
                            }
                        } catch (error) {
                            // Ignore parse errors until valid JSON is entered.
                        }
                    }}
                />
            );
    }
}
