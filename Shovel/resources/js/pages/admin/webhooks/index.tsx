import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState, type ChangeEvent, type FocusEvent } from 'react';
import axios from 'axios';
import {
    CheckCircle2,
    Clipboard,
    ClipboardCheck,
    Loader2,
    Plus,
    RefreshCcw,
    Search,
    ShieldAlert,
    ShieldCheck,
    TestTube,
    ToggleLeft,
    ToggleRight,
    Trash2,
} from 'lucide-react';

import AdminLayout from '@/layouts/admin-layout';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type { BreadcrumbItem, PaginatedWebhookResponse, Webhook } from '@/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';

type ActiveFilter = 'all' | 'active' | 'inactive';

type WebhookFormState = {
    name: string;
    url: string;
    events: string[];
    metadata: string;
    active: boolean;
};

type FormErrors = Record<string, string[]>;

type TestFormState = {
    event: string;
    payload: string;
};

type TestResult = {
    status: number | null;
    successful: boolean;
    body: unknown;
    headers: Record<string, unknown>;
    request_payload: Record<string, unknown>;
    error?: string | null;
} | null;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Admin', href: '/admin' },
    { title: 'Webhooks', href: '/admin/webhooks' },
];

const defaultForm: WebhookFormState = {
    name: '',
    url: '',
    events: [],
    metadata: '',
    active: true,
};

const defaultPagination: PaginatedWebhookResponse['meta']['pagination'] = {
    current_page: 1,
    per_page: 25,
    total: 0,
    last_page: 1,
};

export default function AdminWebhooksIndex() {
    const [webhooks, setWebhooks] = useState<Webhook[]>([]);
    const [loading, setLoading] = useState(true);

    const [search, setSearch] = useState('');
    const [activeFilter, setActiveFilter] = useState<ActiveFilter>('all');
    const [eventFilter, setEventFilter] = useState('');

    const [pagination, setPagination] = useState(defaultPagination);
    const [availableEvents, setAvailableEvents] = useState<string[]>([]);

    const [form, setForm] = useState<WebhookFormState>(defaultForm);
    const [formErrors, setFormErrors] = useState<FormErrors>({});
    const [selectedWebhook, setSelectedWebhook] = useState<Webhook | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [isTestDialogOpen, setIsTestDialogOpen] = useState(false);

    const [deletePending, setDeletePending] = useState(false);
    const [rotatePendingId, setRotatePendingId] = useState<number | null>(null);

    const [plainTextSecret, setPlainTextSecret] = useState<string | null>(null);
    const [secretCopied, setSecretCopied] = useState(false);

    const [testForm, setTestForm] = useState<TestFormState>({ event: '', payload: '' });
    const [testErrors, setTestErrors] = useState<FormErrors>({});
    const [testPending, setTestPending] = useState(false);
    const [testResult, setTestResult] = useState<TestResult>(null);

    const fetchWebhooks = useCallback(
        async (page = 1, searchTerm = search, status = activeFilter, event = eventFilter) => {
            setLoading(true);
            try {
                const params: Record<string, string | number | boolean> = {
                    page,
                    per_page: pagination.per_page,
                };

                if (searchTerm.trim() !== '') {
                    params.search = searchTerm.trim();
                }

                if (status !== 'all') {
                    params.active = status === 'active';
                }

                if (event.trim() !== '') {
                    params.event = event.trim();
                }

                const response = await axios.get<PaginatedWebhookResponse>(admin.api.webhooks.index().url, {
                    params,
                });

                setWebhooks(response.data.data ?? []);
                setPagination(response.data.meta.pagination ?? defaultPagination);
                setAvailableEvents(response.data.meta.available_events ?? []);
            } catch (error) {
                console.error('Failed to load webhooks', error);
            } finally {
                setLoading(false);
            }
        },
        [activeFilter, eventFilter, pagination.per_page, search],
    );

    useEffect(() => {
        fetchWebhooks().catch(() => {
            /* logged above */
        });
    }, [fetchWebhooks]);

    const activeFilterLabel = useMemo(() => {
        switch (activeFilter) {
            case 'active':
                return 'Active';
            case 'inactive':
                return 'Inactive';
            default:
                return 'All statuses';
        }
    }, [activeFilter]);

    const resetForm = () => {
        setForm(defaultForm);
        setFormErrors({});
    };

    const openCreateDialog = () => {
        resetForm();
        setSelectedWebhook(null);
        setIsCreateDialogOpen(true);
    };

    const openEditDialog = (webhook: Webhook) => {
        setSelectedWebhook(webhook);
        setForm({
            name: webhook.name ?? '',
            url: webhook.url ?? '',
            events: [...(webhook.events ?? [])],
            metadata: webhook.metadata ? JSON.stringify(webhook.metadata, null, 2) : '',
            active: Boolean(webhook.active),
        });
        setFormErrors({});
        setIsEditDialogOpen(true);
    };

    const openDeleteDialog = (webhook: Webhook) => {
        setSelectedWebhook(webhook);
        setDeletePending(false);
        setIsDeleteDialogOpen(true);
    };

    const openTestDialog = (webhook: Webhook) => {
        setSelectedWebhook(webhook);
        setTestForm({ event: webhook.events?.[0] ?? '', payload: '' });
        setTestErrors({});
        setTestResult(null);
        setTestPending(false);
        setIsTestDialogOpen(true);
    };

    const parseMetadata = (): Record<string, unknown> | null => {
        const raw = form.metadata.trim();
        if (raw === '') {
            return null;
        }

        try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                return parsed as Record<string, unknown>;
            }

            setFormErrors((prev) => ({ ...prev, metadata: ['Metadata must be a JSON object.'] }));
        } catch (error) {
            setFormErrors((prev) => ({ ...prev, metadata: ['Metadata must be valid JSON.'] }));
        }

        return null;
    };

    const buildFormPayload = () => {
        const metadata = parseMetadata();
        if (formErrors.metadata?.length) {
            return null;
        }

        if (form.events.length === 0) {
            setFormErrors((prev) => ({ ...prev, events: ['Select at least one event.'] }));
            return null;
        }

        return {
            name: form.name,
            url: form.url,
            events: form.events,
            metadata,
            active: form.active,
        };
    };

    const handleCreate = async () => {
        setIsSubmitting(true);
        setFormErrors({});

        const payload = buildFormPayload();
        if (!payload) {
            setIsSubmitting(false);
            return;
        }

        try {
            const response = await axios.post(admin.api.webhooks.store().url, payload);
            const secret = response.data?.plain_text_secret ?? null;
            setPlainTextSecret(secret);
            setSecretCopied(false);
            setIsCreateDialogOpen(false);
            resetForm();
            await fetchWebhooks(pagination.current_page);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors as FormErrors);
            } else {
                console.error('Failed to create webhook', error);
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleUpdate = async () => {
        if (!selectedWebhook) {
            return;
        }

        setIsSubmitting(true);
        setFormErrors({});

        const payload = buildFormPayload();
        if (!payload) {
            setIsSubmitting(false);
            return;
        }

        try {
            const response = await axios.put(admin.api.webhooks.update({ webhook: selectedWebhook.id }).url, payload);
            const secret = response.data?.plain_text_secret ?? null;
            if (secret) {
                setPlainTextSecret(secret);
                setSecretCopied(false);
            }
            setIsEditDialogOpen(false);
            setSelectedWebhook(null);
            resetForm();
            await fetchWebhooks(pagination.current_page);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors as FormErrors);
            } else {
                console.error('Failed to update webhook', error);
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleDelete = async () => {
        if (!selectedWebhook) {
            return;
        }

        setDeletePending(true);
        try {
            await axios.delete(admin.api.webhooks.destroy({ webhook: selectedWebhook.id }).url);
            setIsDeleteDialogOpen(false);
            setSelectedWebhook(null);

            const nextPage = webhooks.length === 1 && pagination.current_page > 1
                ? pagination.current_page - 1
                : pagination.current_page;

            await fetchWebhooks(nextPage);
        } catch (error) {
            console.error('Failed to delete webhook', error);
        } finally {
            setDeletePending(false);
        }
    };

    const handleRotateSecret = async (webhook: Webhook) => {
        setRotatePendingId(webhook.id);
        try {
            const response = await axios.put(admin.api.webhooks.update({ webhook: webhook.id }).url, {
                rotate_secret: true,
            });

            const secret = response.data?.plain_text_secret ?? null;
            if (secret) {
                setPlainTextSecret(secret);
                setSecretCopied(false);
            }

            await fetchWebhooks(pagination.current_page);
        } catch (error) {
            console.error('Failed to rotate webhook secret', error);
        } finally {
            setRotatePendingId(null);
        }
    };

    const handleTestWebhook = async () => {
        if (!selectedWebhook) {
            return;
        }

        const eventValue = testForm.event.trim();
        if (eventValue === '') {
            setTestErrors({ event: ['Event is required.'] });
            return;
        }

        let payload: Record<string, unknown> | undefined;
        if (testForm.payload.trim() !== '') {
            try {
                payload = JSON.parse(testForm.payload) as Record<string, unknown>;
            } catch (error) {
                setTestErrors({ payload: ['Payload must be valid JSON.'] });
                return;
            }
        }

        setTestErrors({});
        setTestPending(true);
        setTestResult(null);

        try {
            const response = await axios.post(admin.api.webhooks.test({ webhook: selectedWebhook.id }).url, {
                event: eventValue,
                payload: payload ?? {},
            });

            setTestResult(response.data as TestResult);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setTestErrors(error.response.data.errors as FormErrors);
            } else {
                setTestResult({
                    status: error.response?.status ?? null,
                    successful: false,
                    body: error.response?.data ?? null,
                    headers: error.response?.headers ?? {},
                    request_payload: payload ?? {},
                    error: error.message,
                });
            }
        } finally {
            setTestPending(false);
        }
    };

    const handleSearch = () => {
        void fetchWebhooks(1, search, activeFilter, eventFilter);
    };

    const copySecret = async () => {
        if (!plainTextSecret) {
            return;
        }

        try {
            await navigator.clipboard.writeText(plainTextSecret);
            setSecretCopied(true);
            setTimeout(() => setSecretCopied(false), 2500);
        } catch (error) {
            console.error('Failed to copy secret', error);
        }
    };

    const eventOptions = useMemo(() => {
        if (availableEvents.length > 0) {
            return availableEvents;
        }

        const uniqueFromWebhooks = new Set<string>();
        webhooks.forEach((webhook) => {
            webhook.events?.forEach((eventName) => uniqueFromWebhooks.add(eventName));
        });

        return Array.from(uniqueFromWebhooks.values());
    }, [availableEvents, webhooks]);

    const renderEventSelector = (selected: string[], onChange: (next: string[]) => void) => {
        if (eventOptions.length === 0) {
            return <p className="text-sm text-muted-foreground">No webhook events have been configured yet.</p>;
        }

        const toggleEvent = (eventName: string, checked: boolean) => {
            if (checked) {
                onChange(selected.includes(eventName) ? selected : [...selected, eventName]);
                return;
            }

            onChange(selected.filter((value) => value !== eventName));
        };

        return (
            <div className="grid gap-2">
                {eventOptions.map((eventName) => (
                    <label key={eventName} className="flex items-center space-x-2">
                        <Checkbox
                            checked={selected.includes(eventName)}
                            onCheckedChange={(checked) => toggleEvent(eventName, Boolean(checked))}
                        />
                        <span className="text-sm font-medium text-foreground">{eventName}</span>
                    </label>
                ))}
            </div>
        );
    };

    const formatDateTime = (value: string | null | undefined) => {
        if (!value) {
            return '—';
        }

        try {
            return new Date(value).toLocaleString();
        } catch (error) {
            return value;
        }
    };

    const renderFormFields = (mode: 'create' | 'edit') => (
        <div className="grid gap-4 py-4">
            <div className="grid gap-2">
                <Label htmlFor={`${mode}-webhook-name`}>Name</Label>
                <Input
                    id={`${mode}-webhook-name`}
                    value={form.name}
                    onChange={(event: ChangeEvent<HTMLInputElement>) => setForm((prev) => ({ ...prev, name: event.target.value }))}
                    aria-invalid={formErrors.name ? 'true' : 'false'}
                />
                {formErrors.name?.map((message, index) => (
                    <p key={index} className="text-xs text-destructive">
                        {message}
                    </p>
                ))}
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${mode}-webhook-url`}>Destination URL</Label>
                <Input
                    id={`${mode}-webhook-url`}
                    value={form.url}
                    onChange={(event: ChangeEvent<HTMLInputElement>) => setForm((prev) => ({ ...prev, url: event.target.value }))}
                    aria-invalid={formErrors.url ? 'true' : 'false'}
                />
                {formErrors.url?.map((message, index) => (
                    <p key={index} className="text-xs text-destructive">
                        {message}
                    </p>
                ))}
            </div>

            <div className="grid gap-2">
                <Label>Subscribed events</Label>
                {renderEventSelector(form.events, (events) => setForm((prev) => ({ ...prev, events })))}
                {formErrors.events?.map((message, index) => (
                    <p key={index} className="text-xs text-destructive">
                        {message}
                    </p>
                ))}
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${mode}-webhook-metadata`}>Metadata (optional JSON object)</Label>
                <Textarea
                    id={`${mode}-webhook-metadata`}
                    rows={4}
                    placeholder={'{\n  "environment": "staging"\n}'}
                    value={form.metadata}
                    onChange={(event: ChangeEvent<HTMLTextAreaElement>) => setForm((prev) => ({ ...prev, metadata: event.target.value }))}
                    aria-invalid={formErrors.metadata ? 'true' : 'false'}
                />
                {formErrors.metadata?.map((message, index) => (
                    <p key={index} className="text-xs text-destructive">
                        {message}
                    </p>
                ))}
            </div>

            <label className="flex items-center space-x-2">
                <Checkbox
                    checked={form.active}
                    onCheckedChange={(checked) => setForm((prev) => ({ ...prev, active: Boolean(checked) }))}
                />
                <span className="text-sm">{mode === 'create' ? 'Activate immediately' : 'Webhook is active'}</span>
            </label>
        </div>
    );

    const renderTestResult = () => {
        if (!testResult) {
            return null;
        }

        const stringify = (data: unknown) => {
            if (data === undefined || data === null) {
                return '—';
            }

            if (typeof data === 'string') {
                return data;
            }

            try {
                return JSON.stringify(data, null, 2);
            } catch (error) {
                return String(data);
            }
        };

        return (
            <Alert variant={testResult.successful ? 'default' : 'destructive'}>
                <AlertTitle>
                    {testResult.successful ? 'Delivery succeeded' : 'Delivery failed'} (status {testResult.status ?? 'n/a'})
                </AlertTitle>
                <AlertDescription>
                    <div className="mt-3 space-y-3">
                        {testResult.error ? <p className="text-sm">Error: {testResult.error}</p> : null}
                        <details className="text-sm">
                            <summary className="cursor-pointer font-medium">Response body</summary>
                            <pre className="mt-2 whitespace-pre-wrap rounded-md bg-muted p-2 text-xs">
                                {stringify(testResult.body)}
                            </pre>
                        </details>
                        <details className="text-sm">
                            <summary className="cursor-pointer font-medium">Request payload</summary>
                            <pre className="mt-2 whitespace-pre-wrap rounded-md bg-muted p-2 text-xs">
                                {stringify(testResult.request_payload)}
                            </pre>
                        </details>
                    </div>
                </AlertDescription>
            </Alert>
        );
    };

    const webhooksEmpty = !loading && webhooks.length === 0;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Webhooks" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Webhooks</h1>
                        <p className="mt-2 text-muted-foreground">
                            Configure outbound notifications for conversation and assignment events.
                        </p>
                    </div>
                    <Button onClick={openCreateDialog} className="gap-2">
                        <Plus className="h-4 w-4" />
                        Create Webhook
                    </Button>
                </div>

                {plainTextSecret ? (
                    <Alert className="items-start gap-3">
                        <ShieldAlert className="h-5 w-5" />
                        <div className="flex flex-col gap-3">
                            <div>
                                <AlertTitle>Store this webhook secret securely</AlertTitle>
                                <AlertDescription>
                                    This is the only time we will display the plaintext secret. Copy it now and keep it safe.
                                </AlertDescription>
                            </div>
                            <div className="rounded-md border bg-muted/40 p-3 font-mono text-sm break-all">
                                {plainTextSecret}
                            </div>
                            <Button variant="secondary" size="sm" className="gap-2 self-start" onClick={copySecret}>
                                {secretCopied ? <ClipboardCheck className="h-4 w-4" /> : <Clipboard className="h-4 w-4" />}
                                {secretCopied ? 'Copied' : 'Copy secret'}
                            </Button>
                        </div>
                    </Alert>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Configured Webhooks</CardTitle>
                        <CardDescription>
                            {pagination.total} total webhook{pagination.total === 1 ? '' : 's'} · {activeFilterLabel}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search by name or URL..."
                                    value={search}
                                    onChange={(event: ChangeEvent<HTMLInputElement>) => setSearch(event.target.value)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            handleSearch();
                                        }
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button variant="secondary" onClick={handleSearch}>
                                    Search
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setSearch('');
                                        setActiveFilter('all');
                                        setEventFilter('');
                                        void fetchWebhooks(1, '', 'all', '');
                                    }}
                                >
                                    Reset
                                </Button>
                            </div>
                        </div>

                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">Status:</span>
                                <div className="flex gap-2">
                                    {(['all', 'active', 'inactive'] as ActiveFilter[]).map((value) => (
                                        <Button
                                            key={value}
                                            size="sm"
                                            variant={activeFilter === value ? 'default' : 'outline'}
                                            onClick={() => {
                                                setActiveFilter(value);
                                                void fetchWebhooks(1, search, value, eventFilter);
                                            }}
                                        >
                                            {value === 'all' ? 'All' : value === 'active' ? 'Active' : 'Inactive'}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                            <div className="flex items-center gap-2 md:w-72">
                                <Label htmlFor="event-filter" className="text-sm text-muted-foreground">
                                    Filter by event
                                </Label>
                                <Input
                                    id="event-filter"
                                    value={eventFilter}
                                    placeholder="message.created"
                                    onChange={(event: ChangeEvent<HTMLInputElement>) => setEventFilter(event.target.value)}
                                    onBlur={(event: FocusEvent<HTMLInputElement>) => void fetchWebhooks(1, search, activeFilter, event.target.value)}
                                />
                            </div>
                        </div>

                        {loading ? (
                            <div className="flex flex-col items-center gap-2 py-10 text-muted-foreground">
                                <Loader2 className="h-5 w-5 animate-spin" />
                                Loading webhooks…
                            </div>
                        ) : webhooksEmpty ? (
                            <div className="py-10 text-center text-muted-foreground">
                                No webhooks found. Create one to start receiving events.
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>URL</TableHead>
                                        <TableHead>Events</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Secret</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {webhooks.map((webhook) => (
                                        <TableRow key={webhook.id}>
                                            <TableCell className="font-medium">{webhook.name}</TableCell>
                                            <TableCell>
                                                <div className="max-w-[260px] truncate text-sm text-muted-foreground">
                                                    {webhook.url}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {(webhook.events ?? []).map((eventName) => (
                                                        <Badge key={eventName} variant="outline" className="font-mono text-xs">
                                                            {eventName}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={webhook.active ? 'default' : 'outline'} className="gap-1">
                                                    {webhook.active ? (
                                                        <>
                                                            <CheckCircle2 className="h-3 w-3" /> Active
                                                        </>
                                                    ) : (
                                                        <>
                                                            <ToggleLeft className="h-3 w-3" /> Inactive
                                                        </>
                                                    )}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {webhook.masked_secret ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDateTime(webhook.created_at)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button variant="ghost" size="sm" className="gap-1" onClick={() => openEditDialog(webhook)}>
                                                        <ShieldCheck className="h-4 w-4" /> Edit
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="gap-1"
                                                        onClick={() => handleRotateSecret(webhook)}
                                                        disabled={rotatePendingId === webhook.id}
                                                    >
                                                        {rotatePendingId === webhook.id ? (
                                                            <Loader2 className="h-4 w-4 animate-spin" />
                                                        ) : (
                                                            <RefreshCcw className="h-4 w-4" />
                                                        )}
                                                        Rotate
                                                    </Button>
                                                    <Button variant="ghost" size="sm" className="gap-1" onClick={() => openTestDialog(webhook)}>
                                                        <TestTube className="h-4 w-4" /> Test
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="gap-1 text-destructive"
                                                        onClick={() => openDeleteDialog(webhook)}
                                                    >
                                                        <Trash2 className="h-4 w-4" /> Delete
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        {pagination.last_page > 1 ? (
                            <div className="flex items-center justify-between">
                                <div className="text-sm text-muted-foreground">
                                    Page {pagination.current_page} of {pagination.last_page}
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={pagination.current_page === 1 || loading}
                                        onClick={() => fetchWebhooks(pagination.current_page - 1)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={pagination.current_page === pagination.last_page || loading}
                                        onClick={() => fetchWebhooks(pagination.current_page + 1)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Create Webhook</DialogTitle>
                        <p className="text-sm text-muted-foreground">
                            Provide a friendly name, destination URL, and select which events should trigger this webhook.
                        </p>
                    </DialogHeader>
                    {renderFormFields('create')}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button onClick={handleCreate} disabled={isSubmitting}>
                            {isSubmitting ? 'Creating…' : 'Create webhook'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit Webhook</DialogTitle>
                    </DialogHeader>
                    {renderFormFields('edit')}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditDialogOpen(false)} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button onClick={handleUpdate} disabled={isSubmitting}>
                            {isSubmitting ? 'Saving…' : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete webhook</DialogTitle>
                        <p className="text-sm text-muted-foreground">
                            This action cannot be undone. Existing deliveries are unaffected, but no future events will be sent.
                        </p>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsDeleteDialogOpen(false)} disabled={deletePending}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete} disabled={deletePending}>
                            {deletePending ? 'Deleting…' : 'Delete webhook'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={isTestDialogOpen} onOpenChange={setIsTestDialogOpen}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Send test delivery</DialogTitle>
                        <p className="text-sm text-muted-foreground">
                            Use this tool to send a one-off delivery and confirm the receiving system accepts the request.
                        </p>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="test-event">Event</Label>
                            <Input
                                id="test-event"
                                value={testForm.event}
                                placeholder={eventOptions[0] ?? 'message.created'}
                                onChange={(event: ChangeEvent<HTMLInputElement>) => setTestForm((prev) => ({ ...prev, event: event.target.value }))}
                                aria-invalid={testErrors.event ? 'true' : 'false'}
                            />
                            {testErrors.event?.map((message, index) => (
                                <p key={index} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="test-payload">Payload (JSON)</Label>
                            <Textarea
                                id="test-payload"
                                rows={6}
                                placeholder={'{\n  "example": true\n}'}
                                value={testForm.payload}
                                onChange={(event: ChangeEvent<HTMLTextAreaElement>) => setTestForm((prev) => ({ ...prev, payload: event.target.value }))}
                                aria-invalid={testErrors.payload ? 'true' : 'false'}
                            />
                            {testErrors.payload?.map((message, index) => (
                                <p key={index} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>
                        {renderTestResult()}
                    </div>
                    <DialogFooter className="flex-col gap-2 sm:flex-row sm:gap-0">
                        <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <Button variant="outline" onClick={() => setIsTestDialogOpen(false)} disabled={testPending}>
                                Close
                            </Button>
                            <div className="flex gap-2">
                                <Button variant="secondary" onClick={() => setTestResult(null)} disabled={testPending}>
                                    Clear result
                                </Button>
                                <Button onClick={handleTestWebhook} disabled={testPending}>
                                    {testPending ? 'Sending…' : 'Send test delivery'}
                                </Button>
                            </div>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
