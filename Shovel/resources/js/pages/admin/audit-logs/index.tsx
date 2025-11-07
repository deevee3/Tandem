import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Download } from 'lucide-react';

import AdminLayout from '@/layouts/admin-layout';
import { type AuditEventRecord, type BreadcrumbItem, type PaginatedAuditEventResponse } from '@/types';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';

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
        title: 'Audit Logs',
        href: '/admin/audit-logs',
    },
];

type AuditLogFilters = {
    conversationId: string;
    eventType: string;
    occurredFrom: string;
    occurredTo: string;
    actor: string;
};

const ALL_EVENT_TYPES_VALUE = '__all__';

const createEmptyFilters = (): AuditLogFilters => ({
    conversationId: '',
    eventType: '',
    occurredFrom: '',
    occurredTo: '',
    actor: '',
});

const DEFAULT_PAGINATION = {
    current_page: 1,
    per_page: 50,
    total: 0,
    last_page: 1,
};

const sanitizeFilters = (filters: AuditLogFilters): AuditLogFilters => {
    const trimmedActor = filters.actor.trim();
    const parsedConversationId = Number.parseInt(filters.conversationId.trim(), 10);

    return {
        conversationId: Number.isNaN(parsedConversationId) ? '' : parsedConversationId.toString(),
        eventType: filters.eventType,
        occurredFrom: filters.occurredFrom,
        occurredTo: filters.occurredTo,
        actor: trimmedActor,
    };
};

const resolveActorDisplay = (event: AuditEventRecord) => {
    const payloadActor = (event.payload?.actor ?? null) as
        | { name?: string | null; email?: string | null; username?: string | null }
        | null;

    return {
        name: event.user?.name ?? payloadActor?.name ?? null,
        email: event.user?.email ?? payloadActor?.email ?? null,
        username: event.user?.username ?? payloadActor?.username ?? null,
    };
};

export default function AuditLogsIndex() {
    const [formFilters, setFormFilters] = useState<AuditLogFilters>(() => createEmptyFilters());
    const [appliedFilters, setAppliedFilters] = useState<AuditLogFilters>(() => createEmptyFilters());
    const [events, setEvents] = useState<AuditEventRecord[]>([]);
    const [pagination, setPagination] = useState(DEFAULT_PAGINATION);
    const [responseMeta, setResponseMeta] = useState<PaginatedAuditEventResponse['meta'] | null>(null);
    const [availableEventTypes, setAvailableEventTypes] = useState<string[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [isDownloading, setIsDownloading] = useState<boolean>(false);

    const createParams = useCallback(
        (page: number, perPage: number, filters: AuditLogFilters) => {
            const params: Record<string, string | number> = {
                page,
                per_page: perPage,
            };

            const conversationId = Number.parseInt(filters.conversationId, 10);
            if (!Number.isNaN(conversationId) && conversationId > 0) {
                params.conversation_id = conversationId;
            }

            if (filters.eventType) {
                params.event_type = filters.eventType;
            }

            if (filters.occurredFrom) {
                params.occurred_from = filters.occurredFrom;
            }

            if (filters.occurredTo) {
                params.occurred_to = filters.occurredTo;
            }

            if (filters.actor.trim()) {
                params.actor = filters.actor.trim();
            }

            return params;
        },
        [],
    );

    const fetchAuditLogs = useCallback(
        async (page: number, filters: AuditLogFilters, options?: { syncForm?: boolean }): Promise<boolean> => {
            setLoading(true);
            setErrorMessage(null);

            try {
                const route = admin.api.auditLogs.index();
                const params = createParams(page, DEFAULT_PAGINATION.per_page, filters);
                const response = await axios.get<PaginatedAuditEventResponse>(route.url, {
                    params,
                });

                const fetchedEvents = response.data.data ?? [];
                const meta = response.data.meta ?? null;

                const paginationMeta = meta?.pagination ?? DEFAULT_PAGINATION;

                setEvents(fetchedEvents);
                setResponseMeta(meta);
                setPagination({
                    current_page: paginationMeta.current_page ?? page,
                    per_page: paginationMeta.per_page ?? DEFAULT_PAGINATION.per_page,
                    total: paginationMeta.total ?? 0,
                    last_page: paginationMeta.last_page ?? 1,
                });

                const newEventTypes = fetchedEvents
                    .map((event) => event.event_type)
                    .filter((type): type is string => Boolean(type));

                setAvailableEventTypes((prev) => Array.from(new Set([...prev, ...newEventTypes])));

                setAppliedFilters(filters);

                if (options?.syncForm) {
                    setFormFilters(filters);
                }

                return true;
            } catch (error: unknown) {
                if (axios.isAxiosError(error)) {
                    const validationErrors = (error.response?.data as { errors?: Record<string, string[]> } | undefined)?.errors;
                    if (validationErrors) {
                        const firstKey = Object.keys(validationErrors)[0];
                        const firstMessage = firstKey ? validationErrors[firstKey]?.[0] : null;
                        setErrorMessage(firstMessage ?? 'Please review the filters and try again.');
                    } else {
                        const message = (error.response?.data as { message?: string } | undefined)?.message;
                        setErrorMessage(message ?? 'Unable to load audit logs right now.');
                    }
                } else {
                    setErrorMessage('Unexpected error loading audit logs.');
                }

                console.error('Failed to load audit logs', error);
                return false;
            } finally {
                setLoading(false);
            }
        },
        [createParams],
    );

    useEffect(() => {
        const initialFilters = createEmptyFilters();
        void fetchAuditLogs(1, initialFilters, { syncForm: true });
    }, [fetchAuditLogs]);

    const sortedEventTypes = useMemo(() => {
        return [...availableEventTypes].sort((a, b) => a.localeCompare(b));
    }, [availableEventTypes]);

    const formatDateTime = useCallback((value: string | null) => {
        if (!value) {
            return '—';
        }

        try {
            const date = new Date(value);
            return date.toLocaleString();
        } catch (error) {
            return value;
        }
    }, []);

    const handleApplyFilters = async () => {
        const sanitized = sanitizeFilters(formFilters);
        await fetchAuditLogs(1, sanitized, { syncForm: true });
    };

    const handleResetFilters = async () => {
        const resetFilters = createEmptyFilters();
        setAvailableEventTypes([]);
        await fetchAuditLogs(1, resetFilters, { syncForm: true });
    };

    const handlePageChange = async (direction: 'previous' | 'next') => {
        const nextPage = direction === 'previous' ? pagination.current_page - 1 : pagination.current_page + 1;

        if (nextPage < 1 || nextPage > pagination.last_page) {
            return;
        }

        await fetchAuditLogs(nextPage, appliedFilters);

    };

    const handleDownload = async () => {
        if (events.length === 0) {
            return;
        }

        setIsDownloading(true);

        try {
            const payload = {
                data: events,
                meta: responseMeta ?? {
                    filters: {
                        conversation_id: appliedFilters.conversationId ? Number.parseInt(appliedFilters.conversationId, 10) : null,
                        event_type: appliedFilters.eventType || null,
                        occurred_from: appliedFilters.occurredFrom || null,
                        occurred_to: appliedFilters.occurredTo || null,
                        actor: appliedFilters.actor || null,
                    },
                    pagination,
                },
            } satisfies Pick<PaginatedAuditEventResponse, 'data' | 'meta'>;

            const blob = new Blob([JSON.stringify(payload, null, 2)], {
                type: 'application/json',
            });

            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `audit-logs-${new Date().toISOString()}.json`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        } finally {
            setIsDownloading(false);
        }
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Logs" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Audit Logs</h1>
                        <p className="text-muted-foreground mt-2">
                            Inspect every state change, assignment, and admin action for compliance and debugging.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => {
                            void handleDownload();
                        }}
                        disabled={events.length === 0 || isDownloading}
                        className="gap-2"
                    >
                        <Download className="h-4 w-4" />
                        {isDownloading ? 'Preparing…' : 'Download JSON'}
                    </Button>
                </div>

                {errorMessage ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load audit logs</AlertTitle>
                        <AlertDescription>{errorMessage}</AlertDescription>
                    </Alert>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Narrow results by conversation, event type, actor, or date range.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="audit-filter-conversation">Conversation ID</Label>
                                <Input
                                    id="audit-filter-conversation"
                                    inputMode="numeric"
                                    value={formFilters.conversationId}
                                    placeholder="e.g. 1024"
                                    onChange={(event) =>
                                        setFormFilters((prev) => ({
                                            ...prev,
                                            conversationId: event.target.value,
                                        }))
                                    }
                                />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="audit-filter-event-type">Event type</Label>
                                <Select
                                    value={formFilters.eventType || ALL_EVENT_TYPES_VALUE}
                                    onValueChange={(value) =>
                                        setFormFilters((prev) => ({
                                            ...prev,
                                            eventType: value === ALL_EVENT_TYPES_VALUE ? '' : value,
                                        }))
                                    }
                                >
                                    <SelectTrigger id="audit-filter-event-type">
                                        <SelectValue placeholder="All event types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL_EVENT_TYPES_VALUE}>All event types</SelectItem>
                                        {sortedEventTypes.map((eventType) => (
                                            <SelectItem key={eventType} value={eventType}>
                                                {eventType}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="audit-filter-occurred-from">Occurred from</Label>
                                <Input
                                    id="audit-filter-occurred-from"
                                    type="date"
                                    value={formFilters.occurredFrom}
                                    onChange={(event) =>
                                        setFormFilters((prev) => ({
                                            ...prev,
                                            occurredFrom: event.target.value,
                                        }))
                                    }
                                />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="audit-filter-occurred-to">Occurred to</Label>
                                <Input
                                    id="audit-filter-occurred-to"
                                    type="date"
                                    value={formFilters.occurredTo}
                                    onChange={(event) =>
                                        setFormFilters((prev) => ({
                                            ...prev,
                                            occurredTo: event.target.value,
                                        }))
                                    }
                                />
                            </div>

                            <div className="flex flex-col gap-2 md:col-span-2 xl:col-span-4">
                                <Label htmlFor="audit-filter-actor">Actor</Label>
                                <Input
                                    id="audit-filter-actor"
                                    value={formFilters.actor}
                                    placeholder="Name, email, or username"
                                    onChange={(event) =>
                                        setFormFilters((prev) => ({
                                            ...prev,
                                            actor: event.target.value,
                                        }))
                                    }
                                />
                            </div>
                        </div>

                        <div className="mt-6 flex flex-wrap justify-end gap-2">
                            <Button variant="outline" onClick={() => void handleResetFilters()}>
                                Reset
                            </Button>
                            <Button onClick={() => void handleApplyFilters()} disabled={loading}>
                                {loading ? 'Applying…' : 'Apply filters'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Audit events</CardTitle>
                        <CardDescription>
                            {pagination.total} event{pagination.total === 1 ? '' : 's'} across {pagination.last_page} page
                            {pagination.last_page === 1 ? '' : 's'}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="py-10 text-center text-muted-foreground">Loading audit logs…</div>
                        ) : events.length === 0 ? (
                            <div className="py-10 text-center text-muted-foreground">
                                No audit events found for the selected filters.
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Occurred at</TableHead>
                                            <TableHead>Event</TableHead>
                                            <TableHead>Conversation</TableHead>
                                            <TableHead>Actor</TableHead>
                                            <TableHead>Channel</TableHead>
                                            <TableHead>Subject</TableHead>
                                            <TableHead>Payload</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {events.map((event) => {
                                            const actor = resolveActorDisplay(event);
                                            const payloadPreview = event.payload ? JSON.stringify(event.payload, null, 2) : null;

                                            return (
                                                <TableRow key={event.id}>
                                                    <TableCell>{formatDateTime(event.occurred_at)}</TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-col gap-1">
                                                            <Badge variant="outline" className="w-max font-mono text-xs">
                                                                {event.event_type}
                                                            </Badge>
                                                            <span className="text-xs text-muted-foreground">#{event.id}</span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>{event.conversation_id ?? '—'}</TableCell>
                                                    <TableCell>
                                                        {actor.name ? (
                                                            <div className="flex flex-col text-sm">
                                                                <span className="font-medium">{actor.name}</span>
                                                                {actor.email ? (
                                                                    <span className="text-muted-foreground">{actor.email}</span>
                                                                ) : null}
                                                                {actor.username ? (
                                                                    <span className="text-xs text-muted-foreground">@{actor.username}</span>
                                                                ) : null}
                                                            </div>
                                                        ) : (
                                                            <span className="text-sm text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>{event.channel ?? '—'}</TableCell>
                                                    <TableCell>
                                                        {event.subject_type && event.subject_id ? (
                                                            <div className="flex flex-col text-sm">
                                                                <span>{event.subject_type}</span>
                                                                <span className="text-muted-foreground">ID {event.subject_id}</span>
                                                            </div>
                                                        ) : (
                                                            <span className="text-sm text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {payloadPreview ? (
                                                            <pre className="max-h-48 overflow-auto rounded-md bg-muted/40 p-3 text-xs">
                                                                {payloadPreview}
                                                            </pre>
                                                        ) : (
                                                            <span className="text-sm text-muted-foreground">—</span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>

                                {pagination.last_page > 1 ? (
                                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                        <div className="text-sm text-muted-foreground">
                                            Page {pagination.current_page} of {pagination.last_page}
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={pagination.current_page === 1 || loading}
                                                onClick={() => void handlePageChange('previous')}
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={pagination.current_page === pagination.last_page || loading}
                                                onClick={() => void handlePageChange('next')}
                                            >
                                                Next
                                            </Button>
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
