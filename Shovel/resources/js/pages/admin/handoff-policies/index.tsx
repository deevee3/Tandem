import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import axios from 'axios';
import { CheckCircle2, Edit, Plus, Search, ToggleLeft, ToggleRight, Trash2, XCircle } from 'lucide-react';
import HandoffPolicyRuleEditor, { type PolicyRuleFormValue, type RuleFieldErrors } from '@/components/admin/handoff-policy-rule-editor';
import SkillSelector from '@/components/admin/skill-selector';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Admin',
        href: '/admin',
    },
    {
        title: 'Handoff Policies',
        href: '/admin/handoff-policies',
    },
];

interface Skill {
    id: number;
    name: string;
    description?: string | null;
}

interface HandoffPolicyRule {
    id: number;
    trigger_type: PolicyRuleFormValue['trigger_type'];
    criteria: Record<string, unknown>;
    priority: number;
    active: boolean;
    created_at?: string | null;
    updated_at?: string | null;
}

interface HandoffPolicy {
    id: number;
    name: string;
    reason_code: string;
    confidence_threshold?: number | null;
    metadata?: Record<string, unknown> | null;
    active: boolean;
    created_at?: string;
    updated_at?: string;
    skills?: Skill[];
    rules?: HandoffPolicyRule[];
}

interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

interface RuleErrorsPayload {
    [index: number]: RuleFieldErrors;
}

export default function HandoffPoliciesIndex() {
    const [policies, setPolicies] = useState<HandoffPolicy[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [pagination, setPagination] = useState<PaginationMeta>({
        current_page: 1,
        per_page: 50,
        total: 0,
        last_page: 1,
    });
    const [availableSkills, setAvailableSkills] = useState<Skill[]>([]);

    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [selectedPolicy, setSelectedPolicy] = useState<HandoffPolicy | null>(null);

    const [formData, setFormData] = useState({
        name: '',
        reason_code: '',
        confidence_threshold: '',
        active: true,
        metadata: '',
        skill_ids: [] as number[],
        rules: [] as PolicyRuleFormValue[],
    });

    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});
    const [ruleErrors, setRuleErrors] = useState<RuleErrorsPayload>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const fetchPolicies = useCallback(async (page = 1, searchQuery = '') => {
        setLoading(true);
        try {
            const response = await axios.get('/admin/api/handoff-policies', {
                params: {
                    page,
                    per_page: 50,
                    search: searchQuery || undefined,
                },
            });

            setPolicies(response.data.data ?? []);
            setPagination((prev) => response.data.meta?.pagination ?? prev);
        } catch (error) {
            console.error('Failed to fetch handoff policies:', error);
        } finally {
            setLoading(false);
        }
    }, []);

    const fetchSkills = useCallback(async () => {
        try {
            const response = await axios.get('/admin/api/skills/all');
            setAvailableSkills(response.data.data ?? []);
        } catch (error) {
            console.error('Failed to fetch skills:', error);
        }
    }, []);

    useEffect(() => {
        fetchPolicies(1, search);
        fetchSkills();
    }, [fetchPolicies, fetchSkills]);

    const handleSearch = () => {
        fetchPolicies(1, search);
    };

    const handleSearchKeyPress = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            handleSearch();
        }
    };

    const openCreateDialog = () => {
        setFormErrors({});
        setRuleErrors({});
        setSelectedPolicy(null);
        setFormData({
            name: '',
            reason_code: '',
            confidence_threshold: '',
            active: true,
            metadata: '',
            skill_ids: [],
            rules: [],
        });
        setIsCreateDialogOpen(true);
    };

    const openEditDialog = (policy: HandoffPolicy) => {
        setSelectedPolicy(policy);
        setFormErrors({});
        setRuleErrors({});
        setFormData({
            name: policy.name ?? '',
            reason_code: policy.reason_code ?? '',
            confidence_threshold:
                policy.confidence_threshold !== null && policy.confidence_threshold !== undefined
                    ? String(policy.confidence_threshold)
                    : '',
            active: policy.active,
            metadata: policy.metadata ? JSON.stringify(policy.metadata, null, 2) : '',
            skill_ids: policy.skills?.map((skill) => skill.id) ?? [],
            rules:
                policy.rules?.map((rule) => ({
                    id: rule.id,
                    trigger_type: rule.trigger_type,
                    criteria: rule.criteria ?? {},
                    priority: rule.priority ?? 0,
                    active: rule.active,
                })) ?? [],
        });
        setIsEditDialogOpen(true);
    };

    const openDeleteDialog = (policy: HandoffPolicy) => {
        setSelectedPolicy(policy);
        setIsDeleteDialogOpen(true);
    };

    const resetAfterSubmit = async () => {
        setIsCreateDialogOpen(false);
        setIsEditDialogOpen(false);
        setSelectedPolicy(null);
        setFormData({
            name: '',
            reason_code: '',
            confidence_threshold: '',
            active: true,
            metadata: '',
            skill_ids: [],
            rules: [],
        });
        await fetchPolicies(pagination.current_page, search);
    };

    const parseMetadata = () => {
        if (!formData.metadata.trim()) {
            return null;
        }

        try {
            const parsed = JSON.parse(formData.metadata);
            if (parsed && typeof parsed === 'object') {
                return parsed as Record<string, unknown>;
            }
            return null;
        } catch (error) {
            setFormErrors((prev) => ({ ...prev, metadata: ['Metadata must be valid JSON.'] }));
            return null;
        }
    };

    const buildPayload = () => {
        const metadata = parseMetadata();
        if (formErrors.metadata?.length) {
            return null;
        }

        return {
            name: formData.name,
            reason_code: formData.reason_code,
            confidence_threshold:
                formData.confidence_threshold !== '' ? Number.parseFloat(formData.confidence_threshold) : null,
            active: formData.active,
            metadata,
            skill_ids: formData.skill_ids,
            rules: formData.rules,
        };
    };

    const handleCreatePolicy = async () => {
        setFormErrors({});
        setRuleErrors({});
        setIsSubmitting(true);

        const payload = buildPayload();
        if (!payload) {
            setIsSubmitting(false);
            return;
        }

        try {
            await axios.post('/admin/api/handoff-policies', payload);
            await resetAfterSubmit();
        } catch (error: any) {
            if (error.response?.data?.errors) {
                distributeErrors(error.response.data.errors);
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleEditPolicy = async () => {
        if (!selectedPolicy) {
            return;
        }

        setFormErrors({});
        setRuleErrors({});
        setIsSubmitting(true);

        const payload = buildPayload();
        if (!payload) {
            setIsSubmitting(false);
            return;
        }

        try {
            await axios.put(`/admin/api/handoff-policies/${selectedPolicy.id}`, payload);
            await resetAfterSubmit();
        } catch (error: any) {
            if (error.response?.data?.errors) {
                distributeErrors(error.response.data.errors);
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleDeletePolicy = async () => {
        if (!selectedPolicy) {
            return;
        }

        try {
            await axios.delete(`/admin/api/handoff-policies/${selectedPolicy.id}`);
            setIsDeleteDialogOpen(false);
            setSelectedPolicy(null);
            fetchPolicies(pagination.current_page, search);
        } catch (error) {
            console.error('Failed to delete handoff policy:', error);
        }
    };

    const distributeErrors = (errors: Record<string, unknown>) => {
        const fieldErrors: Record<string, string[]> = {};
        const ruleFieldErrors: RuleErrorsPayload = {};

        Object.entries(errors).forEach(([key, value]) => {
            if (key.startsWith('rules.')) {
                const [, index, ...rest] = key.split('.');
                const idx = Number.parseInt(index ?? '', 10);
                const field = rest.join('.');

                if (!Number.isNaN(idx) && field) {
                    const messages = Array.isArray(value) ? (value as string[]) : [];

                    if (messages.length > 0) {
                        ruleFieldErrors[idx] = {
                            ...(ruleFieldErrors[idx] ?? {}),
                            [field]: messages,
                        };
                    }
                }

                return;
            }

            fieldErrors[key] = Array.isArray(value) ? (value as string[]) : [];
        });

        setFormErrors(fieldErrors);
        setRuleErrors(ruleFieldErrors);
    };

    const metadataPreview = useMemo(() => {
        if (!formData.metadata.trim()) {
            return null;
        }

        try {
            return JSON.stringify(JSON.parse(formData.metadata), null, 2);
        } catch (error) {
            return formData.metadata;
        }
    }, [formData.metadata]);

    const metadataSummary = (policy: HandoffPolicy) => {
        if (!policy.metadata) {
            return '—';
        }

        const keys = Object.keys(policy.metadata ?? {});
        if (keys.length === 0) {
            return '—';
        }

        return keys.slice(0, 3).join(', ') + (keys.length > 3 ? ` +${keys.length - 3} more` : '');
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Handoff Policies" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Handoff Policies</h1>
                        <p className="text-muted-foreground mt-2">
                            Manage rules that control when conversations hand off to human agents.
                        </p>
                    </div>
                    <Button onClick={openCreateDialog} className="gap-2">
                        <Plus className="h-4 w-4" />
                        Add Policy
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Policies</CardTitle>
                        <CardDescription>{pagination.total} total policy{pagination.total !== 1 ? 'ies' : ''}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-2 mb-4">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search by name or reason code..."
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    onKeyDown={handleSearchKeyPress}
                                    className="pl-9"
                                />
                            </div>
                            <Button onClick={handleSearch} variant="secondary">
                                Search
                            </Button>
                        </div>

                        {loading ? (
                            <div className="text-center py-10 text-muted-foreground">Loading policies…</div>
                        ) : policies.length === 0 ? (
                            <div className="text-center py-10 text-muted-foreground">
                                No handoff policies found. Use the "Add Policy" button to create one.
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Reason Code</TableHead>
                                        <TableHead>Threshold</TableHead>
                                        <TableHead>Skills</TableHead>
                                        <TableHead>Rules</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Metadata</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {policies.map((policy) => (
                                        <TableRow key={policy.id}>
                                            <TableCell className="font-medium">{policy.name}</TableCell>
                                            <TableCell>
                                                <span className="text-xs bg-muted px-2 py-1 rounded font-mono">{policy.reason_code}</span>
                                            </TableCell>
                                            <TableCell>{policy.confidence_threshold ?? '—'}</TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {policy.skills && policy.skills.length > 0 ? (
                                                        policy.skills.slice(0, 3).map((skill) => (
                                                            <Badge key={skill.id} variant="secondary">
                                                                {skill.name}
                                                            </Badge>
                                                        ))
                                                    ) : (
                                                        <span className="text-muted-foreground text-sm">No skills</span>
                                                    )}
                                                    {policy.skills && policy.skills.length > 3 ? (
                                                        <Badge variant="secondary">+{policy.skills.length - 3}</Badge>
                                                    ) : null}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {policy.rules && policy.rules.length > 0 ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {policy.rules.slice(0, 3).map((rule) => (
                                                            <Badge key={rule.id} variant="outline" className="capitalize">
                                                                {rule.trigger_type.replaceAll('_', ' ')}
                                                            </Badge>
                                                        ))}
                                                        {policy.rules.length > 3 ? (
                                                            <Badge variant="outline">+{policy.rules.length - 3}</Badge>
                                                        ) : null}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground text-sm">No rules</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {policy.active ? (
                                                        <Badge className="gap-1" variant="default">
                                                            <CheckCircle2 className="h-3 w-3" /> Active
                                                        </Badge>
                                                    ) : (
                                                        <Badge className="gap-1" variant="outline">
                                                            <XCircle className="h-3 w-3" /> Inactive
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <span className="text-xs text-muted-foreground">{metadataSummary(policy)}</span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button variant="ghost" size="sm" onClick={() => openEditDialog(policy)}>
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="sm" onClick={() => openDeleteDialog(policy)}>
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        {pagination.last_page > 1 ? (
                            <div className="flex items-center justify-between mt-4">
                                <div className="text-sm text-muted-foreground">
                                    Page {pagination.current_page} of {pagination.last_page}
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={pagination.current_page === 1}
                                        onClick={() => fetchPolicies(pagination.current_page - 1, search)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={pagination.current_page === pagination.last_page}
                                        onClick={() => fetchPolicies(pagination.current_page + 1, search)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>

            <PolicyDialog
                title="Create Handoff Policy"
                open={isCreateDialogOpen}
                onOpenChange={setIsCreateDialogOpen}
                onSubmit={handleCreatePolicy}
                submitting={isSubmitting}
                formData={formData}
                setFormData={setFormData}
                formErrors={formErrors}
                ruleErrors={ruleErrors}
                availableSkills={availableSkills}
                metadataPreview={metadataPreview}
                mode="create"
            />

            <PolicyDialog
                title={`Edit Policy${selectedPolicy ? `: ${selectedPolicy.name}` : ''}`}
                open={isEditDialogOpen}
                onOpenChange={setIsEditDialogOpen}
                onSubmit={handleEditPolicy}
                submitting={isSubmitting}
                formData={formData}
                setFormData={setFormData}
                formErrors={formErrors}
                ruleErrors={ruleErrors}
                availableSkills={availableSkills}
                metadataPreview={metadataPreview}
                mode="edit"
            />

            <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Policy</DialogTitle>
                    </DialogHeader>
                    <div className="py-4 space-y-2">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to delete <span className="font-semibold">{selectedPolicy?.name}</span>? This action
                            cannot be undone.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsDeleteDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDeletePolicy}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

interface PolicyDialogProps {
    title: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: () => void;
    submitting: boolean;
    formData: {
        name: string;
        reason_code: string;
        confidence_threshold: string;
        metadata: string;
        active: boolean;
        skill_ids: number[];
        rules: PolicyRuleFormValue[];
    };
    setFormData: React.Dispatch<
        React.SetStateAction<{
            name: string;
            reason_code: string;
            confidence_threshold: string;
            metadata: string;
            active: boolean;
            skill_ids: number[];
            rules: PolicyRuleFormValue[];
        }>
    >;
    formErrors: Record<string, string[]>;
    ruleErrors: RuleErrorsPayload;
    availableSkills: Skill[];
    metadataPreview: string | null;
    mode: 'create' | 'edit';
}

function PolicyDialog({
    title,
    open,
    onOpenChange,
    onSubmit,
    submitting,
    formData,
    setFormData,
    formErrors,
    ruleErrors,
    availableSkills,
    metadataPreview,
    mode,
}: PolicyDialogProps) {
    const handleMetadataInput = (value: string) => {
        setFormData((prev) => ({ ...prev, metadata: value }));
    };

    const toggleActive = () => {
        setFormData((prev) => ({ ...prev, active: !prev.active }));
    };

    const updateRuleCollection = (rules: PolicyRuleFormValue[]) => {
        setFormData((prev) => ({ ...prev, rules }));
    };

    const handleSkillChange = (nextSkillIds: number[]) => {
        setFormData((prev) => ({ ...prev, skill_ids: nextSkillIds }));
    };

    const dialogDescription =
        mode === 'create' ? 'Define a new handoff policy and configure its rules.' : 'Update the selected handoff policy.';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-4xl max-h-[95vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <p className="text-sm text-muted-foreground">{dialogDescription}</p>
                </DialogHeader>

                <div className="grid gap-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="policy-name">Policy name</Label>
                            <Input
                                id="policy-name"
                                value={formData.name}
                                onChange={(event) => setFormData((prev) => ({ ...prev, name: event.target.value }))}
                                placeholder="Escalate low confidence conversations"
                            />
                            {formErrors.name?.map((message, idx) => (
                                <p key={idx} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="policy-reason-code">Reason code</Label>
                            <Input
                                id="policy-reason-code"
                                value={formData.reason_code}
                                onChange={(event) => setFormData((prev) => ({ ...prev, reason_code: event.target.value }))}
                                placeholder="low_confidence"
                            />
                            {formErrors.reason_code?.map((message, idx) => (
                                <p key={idx} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="policy-threshold">Confidence threshold (optional)</Label>
                            <Input
                                id="policy-threshold"
                                type="number"
                                step={0.01}
                                min={0}
                                max={1}
                                value={formData.confidence_threshold}
                                onChange={(event) => setFormData((prev) => ({ ...prev, confidence_threshold: event.target.value }))}
                                placeholder="0.6"
                            />
                            {formErrors.confidence_threshold?.map((message, idx) => (
                                <p key={idx} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>

                        <div className="grid gap-2">
                            <Label>Active</Label>
                            <Button variant="outline" type="button" onClick={toggleActive} className="justify-start gap-2">
                                {formData.active ? (
                                    <>
                                        <ToggleRight className="h-4 w-4 text-green-500" /> Policy is active
                                    </>
                                ) : (
                                    <>
                                        <ToggleLeft className="h-4 w-4 text-muted-foreground" /> Policy is inactive
                                    </>
                                )}
                            </Button>
                            {formErrors.active?.map((message, idx) => (
                                <p key={idx} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="policy-metadata">Metadata (JSON, optional)</Label>
                        <Textarea
                            id="policy-metadata"
                            value={formData.metadata}
                            onChange={(event) => handleMetadataInput(event.target.value)}
                            placeholder='{ "seeded": true }'
                            rows={6}
                        />
                        {formErrors.metadata?.map((message, idx) => (
                            <p key={idx} className="text-xs text-destructive">
                                {message}
                            </p>
                        ))}
                        {metadataPreview !== null ? (
                            <div className="rounded-md border bg-muted/40 p-3">
                                <p className="text-xs font-medium text-muted-foreground mb-1">Preview</p>
                                <pre className="max-h-40 overflow-auto text-xs text-muted-foreground whitespace-pre-wrap">{metadataPreview}</pre>
                            </div>
                        ) : null}
                    </div>

                    <div className="grid gap-2">
                        <Label>Required skills</Label>
                        <SkillSelector
                            skills={availableSkills}
                            selectedIds={formData.skill_ids}
                            onChange={handleSkillChange}
                            emptyMessage="No skills configured yet"
                        />
                        {formErrors.skill_ids?.map((message, idx) => (
                            <p key={idx} className="text-xs text-destructive">
                                {message}
                            </p>
                        ))}
                    </div>

                    <div className="grid gap-2">
                        <HandoffPolicyRuleEditor rules={formData.rules} onChange={updateRuleCollection} errors={ruleErrors} />
                        {formErrors['rules']?.map((message, idx) => (
                            <p key={idx} className="text-xs text-destructive">
                                {message}
                            </p>
                        ))}
                    </div>
                </div>

                <DialogFooter className="mt-4">
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={submitting}>
                        Cancel
                    </Button>
                    <Button onClick={onSubmit} disabled={submitting}>
                        {submitting ? (mode === 'create' ? 'Creating…' : 'Saving…') : mode === 'create' ? 'Create Policy' : 'Save Changes'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
