import { Head, usePage, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';

import AppLayout from '@/layouts/app-layout';
import ConversationRoutes from '@/actions/App/Http/Controllers/ConversationController';
import { inbox } from '@/routes';
import { type BreadcrumbItem, type ConversationAssignment, type ConversationAuditEvent, type ConversationDetail, type ConversationHandoff, type ConversationMessage, type ConversationQueueItem, type SharedData } from '@/types';
import Api from '@/actions/App/Http/Controllers/Api';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useAgentAuthToken } from '@/hooks/use-agent-auth';
import { createQueueItemsHeaders } from '@/hooks/use-queue-items';

interface PageProps extends SharedData {
    conversation: ConversationDetail;
    messages: ConversationMessage[];
    queueItems: ConversationQueueItem[];
    assignments: ConversationAssignment[];
    handoffs: ConversationHandoff[];
    auditEvents: ConversationAuditEvent[];
}

export default function ConversationPage() {
    const page = usePage<PageProps>();
    const { conversation, messages, queueItems, assignments, handoffs, auditEvents } = page.props;
    const agentToken = useAgentAuthToken();
    const authenticatedUserId = page.props.auth?.user?.id ?? null;
    const defaultActorId = authenticatedUserId ? String(authenticatedUserId) : '';

    const breadcrumbs = useMemo<BreadcrumbItem[]>(() => {
        const conversationUrl = ConversationRoutes.show({ conversation: conversation.id }).url;
        return [
            {
                title: 'Inbox',
                href: inbox().url,
            },
            {
                title: conversation.subject ?? `Conversation #${conversation.id}`,
                href: conversationUrl,
            },
        ];
    }, [conversation]);

    const activeAssignment = useMemo(() => {
        return assignments.find((assignment) => assignment.status === 'human_working') ?? null;
    }, [assignments]);

    const canReturnToAgent = conversation.status === 'human_working' && Boolean(activeAssignment);
    const canResolve = conversation.status === 'human_working' || conversation.status === 'agent_working';

    const canReply = useMemo(() => {
        // Must have active assignment
        if (!activeAssignment) return false;
        
        // Must be in human_working status
        if (conversation.status !== 'human_working') return false;
        
        // User must be assigned to this conversation
        return activeAssignment.user?.id === authenticatedUserId;
    }, [activeAssignment, conversation.status, authenticatedUserId]);

    const [releaseDialogOpen, setReleaseDialogOpen] = useState(false);
    const [releaseActorId, setReleaseActorId] = useState(defaultActorId);
    const [releaseReason, setReleaseReason] = useState('');
    const [releaseSubmitting, setReleaseSubmitting] = useState(false);
    const [releaseError, setReleaseError] = useState<string | null>(null);

    const [resolveDialogOpen, setResolveDialogOpen] = useState(false);
    const [resolveActorId, setResolveActorId] = useState(defaultActorId);
    const [resolveSummary, setResolveSummary] = useState('');
    const [resolveSubmitting, setResolveSubmitting] = useState(false);
    const [resolveError, setResolveError] = useState<string | null>(null);

    const [messageContent, setMessageContent] = useState('');
    const [messageSending, setMessageSending] = useState(false);
    const [messageError, setMessageError] = useState<string | null>(null);

    const resetReleaseForm = useCallback(() => {
        setReleaseActorId(defaultActorId);
        setReleaseReason('');
        setReleaseError(null);
    }, [defaultActorId]);

    const resetResolveForm = useCallback(() => {
        setResolveActorId(defaultActorId);
        setResolveSummary('');
        setResolveError(null);
    }, [defaultActorId]);

    const refreshConversation = useCallback(async () => {
        await router.reload({
            only: ['conversation', 'messages', 'queueItems', 'assignments', 'handoffs', 'auditEvents'],
        });
    }, []);

    const handleRelease = useCallback(async () => {
        if (!activeAssignment) {
            setReleaseError('No active assignment can be released.');
            return;
        }

        const parsedActorId = Number.parseInt(releaseActorId, 10);
        if (Number.isNaN(parsedActorId) || parsedActorId <= 0) {
            setReleaseError('Actor ID must be a valid positive number.');
            return;
        }

        setReleaseSubmitting(true);
        setReleaseError(null);

        try {
            const route = Api.AssignmentController.release({ assignment: activeAssignment.id });
            const headers = new Headers(createQueueItemsHeaders(agentToken));
            headers.set('Accept', 'application/json');

            const payload: { actor_id: number; reason?: string } = {
                actor_id: parsedActorId,
            };

            if (releaseReason.trim() !== '') {
                payload.reason = releaseReason.trim();
            }

            const response = await fetch(route.url, {
                method: 'POST',
                headers,
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data?.message ?? 'Failed to return conversation to agent.');
            }

            setReleaseDialogOpen(false);
            resetReleaseForm();
            await refreshConversation();
        } catch (error) {
            setReleaseError((error as Error).message);
        } finally {
            setReleaseSubmitting(false);
        }
    }, [activeAssignment, agentToken, refreshConversation, releaseActorId, releaseReason, resetReleaseForm]);

    const handleResolve = useCallback(async () => {
        if (resolveSummary.trim() === '') {
            setResolveError('Provide a resolution summary before submitting.');
            return;
        }

        const parsedActorId = resolveActorId.trim() === '' ? null : Number.parseInt(resolveActorId, 10);
        if (resolveActorId.trim() !== '' && (parsedActorId === null || Number.isNaN(parsedActorId) || parsedActorId <= 0)) {
            setResolveError('Actor ID must be left blank or set to a valid positive number.');
            return;
        }

        setResolveSubmitting(true);
        setResolveError(null);

        try {
            const route = Api.ConversationResolutionController.store({ conversation: conversation.id });
            const headers = new Headers(createQueueItemsHeaders(agentToken));
            headers.set('Accept', 'application/json');

            const payload: { summary: string; actor_id?: number } = {
                summary: resolveSummary.trim(),
            };

            if (parsedActorId) {
                payload.actor_id = parsedActorId;
            }

            const response = await fetch(route.url, {
                method: 'POST',
                headers,
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data?.message ?? 'Failed to resolve conversation.');
            }

            setResolveDialogOpen(false);
            resetResolveForm();
            await refreshConversation();
        } catch (error) {
            setResolveError((error as Error).message);
        } finally {
            setResolveSubmitting(false);
        }
    }, [agentToken, conversation.id, refreshConversation, resolveActorId, resolveSummary, resetResolveForm]);

    const handleSendMessage = useCallback(async () => {
        if (messageContent.trim() === '') {
            setMessageError('Message content cannot be empty.');
            return;
        }

        setMessageSending(true);
        setMessageError(null);

        try {
            const route = Api.ConversationMessageController.storeHumanMessage({ 
                conversation: conversation.id 
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            const response = await fetch(route.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                credentials: 'include',
                body: JSON.stringify({ content: messageContent.trim() }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data?.message ?? 'Failed to send message.');
            }

            setMessageContent('');
            await refreshConversation();
        } catch (error) {
            setMessageError((error as Error).message);
        } finally {
            setMessageSending(false);
        }
    }, [conversation.id, messageContent, refreshConversation]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={conversation.subject ?? 'Conversation'} />

            <div className="flex h-full flex-col gap-4">
                <header className="flex flex-col gap-2">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h1 className="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                                {conversation.subject ?? 'Untitled conversation'}
                            </h1>
                            <p className="text-sm text-neutral-500 dark:text-neutral-400">
                                Requester: {conversation.requester.identifier ?? 'Unknown'}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <span className="rounded-full border border-neutral-300 px-3 py-1 capitalize text-neutral-700 dark:border-neutral-700 dark:text-neutral-200">
                                Status: {conversation.status.replace('_', ' ')}
                            </span>
                            <span className="rounded-full border border-neutral-300 px-3 py-1 capitalize text-neutral-700 dark:border-neutral-700 dark:text-neutral-200">
                                Priority: {conversation.priority}
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            disabled={!canReturnToAgent || releaseSubmitting}
                            onClick={() => {
                                setReleaseDialogOpen(true);
                                resetReleaseForm();
                            }}
                        >
                            Return to Agent
                        </Button>
                        <Button
                            disabled={!canResolve || resolveSubmitting}
                            onClick={() => {
                                setResolveDialogOpen(true);
                                resetResolveForm();
                            }}
                        >
                            Resolve Conversation
                        </Button>
                    </div>
                </header>

                <main className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    <section className="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                            Transcript
                        </h2>
                        <div className="space-y-4">
                            {messages.length === 0 ? (
                                <p className="text-sm text-neutral-500 dark:text-neutral-400">No messages yet.</p>
                            ) : (
                                messages.map((message) => (
                                    <article
                                        key={message.id}
                                        className="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                                    >
                                        <header className="mb-2 flex items-center justify-between text-xs text-neutral-500 dark:text-neutral-400">
                                            <span className="uppercase tracking-wide">{message.sender_type}</span>
                                            <time>{message.created_at ? new Date(message.created_at).toLocaleString() : '—'}</time>
                                        </header>
                                        <p className="text-sm text-neutral-800 dark:text-neutral-200">{message.content}</p>
                                        {message.metadata && (
                                            <pre className="mt-2 overflow-x-auto rounded bg-neutral-100 p-2 text-xs text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                                                {JSON.stringify(message.metadata, null, 2)}
                                            </pre>
                                        )}
                                    </article>
                                ))
                            )}
                        </div>

                        {canReply && (
                            <div className="mt-4 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                                <label htmlFor="message-input" className="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                    Reply to Customer
                                </label>
                                <textarea
                                    id="message-input"
                                    value={messageContent}
                                    onChange={(e) => setMessageContent(e.target.value)}
                                    placeholder="Type your reply to the customer..."
                                    className="w-full rounded-lg border border-neutral-300 bg-white p-3 text-sm text-neutral-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:focus:border-blue-400"
                                    rows={4}
                                    disabled={messageSending}
                                />
                                <div className="mt-3 flex items-center justify-between">
                                    <span className="text-xs text-neutral-500 dark:text-neutral-400">
                                        {messageContent.length} character{messageContent.length !== 1 ? 's' : ''}
                                    </span>
                                    <Button 
                                        onClick={handleSendMessage}
                                        disabled={messageSending || !messageContent.trim()}
                                    >
                                        {messageSending ? 'Sending...' : 'Send Reply'}
                                    </Button>
                                </div>
                                {messageError && (
                                    <p className="mt-2 text-sm text-red-600 dark:text-red-400">{messageError}</p>
                                )}
                            </div>
                        )}
                    </section>

                    <aside className="flex flex-col gap-4">
                        <section className="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Assignments</h3>
                            {assignments.length === 0 ? (
                                <p className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">No assignments yet.</p>
                            ) : (
                                <ul className="mt-2 space-y-3 text-sm text-neutral-700 dark:text-neutral-300">
                                    {assignments.map((assignment) => (
                                        <li key={assignment.id} className="rounded-lg bg-neutral-100 p-3 dark:bg-neutral-900">
                                            <p className="font-medium text-neutral-800 dark:text-neutral-100">
                                                {assignment.user?.name ?? `User #${assignment.user?.id ?? 'unknown'}`}
                                            </p>
                                            <p className="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                                                {assignment.status.replace('_', ' ')}
                                            </p>
                                            <div className="mt-2 grid gap-1 text-xs text-neutral-500 dark:text-neutral-400">
                                                <span>Assigned: {assignment.assigned_at ? new Date(assignment.assigned_at).toLocaleString() : '—'}</span>
                                                <span>Accepted: {assignment.accepted_at ? new Date(assignment.accepted_at).toLocaleString() : '—'}</span>
                                                <span>Resolved: {assignment.resolved_at ? new Date(assignment.resolved_at).toLocaleString() : '—'}</span>
                                            </div>
                                            {assignment.user && (
                                                <>
                                                    {assignment.user.roles.length > 0 && (
                                                        <div className="mt-2">
                                                            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-400">Roles:</p>
                                                            <div className="mt-1 flex flex-wrap gap-1">
                                                                {assignment.user.roles.map((role) => (
                                                                    <span
                                                                        key={role.id}
                                                                        className="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                                                    >
                                                                        {role.name}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}
                                                    {assignment.user.skills.length > 0 && (
                                                        <div className="mt-2">
                                                            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-400">Skills:</p>
                                                            <div className="mt-1 flex flex-wrap gap-1">
                                                                {assignment.user.skills.map((skill) => (
                                                                    <span
                                                                        key={skill.id}
                                                                        className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700 dark:bg-green-900/30 dark:text-green-300"
                                                                    >
                                                                        {skill.name}{skill.level ? ` (${skill.level})` : ''}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}
                                                    {assignment.user.queues.length > 0 && (
                                                        <div className="mt-2">
                                                            <p className="text-xs font-medium text-neutral-600 dark:text-neutral-400">Queues:</p>
                                                            <div className="mt-1 flex flex-wrap gap-1">
                                                                {assignment.user.queues.map((queue) => (
                                                                    <span
                                                                        key={queue.id}
                                                                        className="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                                                                    >
                                                                        {queue.name}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Queue History</h3>
                            {queueItems.length === 0 ? (
                                <p className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">No queue history yet.</p>
                            ) : (
                                <ul className="mt-2 space-y-3 text-sm text-neutral-700 dark:text-neutral-300">
                                    {queueItems.map((item) => (
                                        <li key={item.id} className="rounded-lg bg-neutral-100 p-3 dark:bg-neutral-900">
                                            <p className="font-medium text-neutral-800 dark:text-neutral-100">
                                                {item.queue?.name ?? 'Queue'}
                                            </p>
                                            <p className="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                                                {item.state.replace('_', ' ')}
                                            </p>
                                            <div className="mt-2 grid gap-1 text-xs text-neutral-500 dark:text-neutral-400">
                                                <span>Enqueued: {item.enqueued_at ? new Date(item.enqueued_at).toLocaleString() : '—'}</span>
                                                <span>Dequeued: {item.dequeued_at ? new Date(item.dequeued_at).toLocaleString() : '—'}</span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Handoff History</h3>
                            {handoffs.length === 0 ? (
                                <p className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">No handoffs recorded.</p>
                            ) : (
                                <ul className="mt-2 space-y-3 text-sm text-neutral-700 dark:text-neutral-300">
                                    {handoffs.map((handoff) => (
                                        <li key={handoff.id} className="rounded-lg bg-neutral-100 p-3 dark:bg-neutral-900">
                                            <p className="font-medium text-neutral-800 dark:text-neutral-100">
                                                Reason: {handoff.reason_code.replace('_', ' ')}
                                            </p>
                                            <div className="mt-2 grid gap-1 text-xs text-neutral-500 dark:text-neutral-400">
                                                <span>Confidence: {handoff.confidence ?? '—'}</span>
                                                <span>Created: {handoff.created_at ? new Date(handoff.created_at).toLocaleString() : '—'}</span>
                                            </div>
                                            {(handoff.policy_hits || handoff.required_skills || handoff.metadata) && (
                                                <pre className="mt-2 overflow-x-auto rounded bg-neutral-100 p-2 text-xs text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                                                    {JSON.stringify(
                                                        {
                                                            policy_hits: handoff.policy_hits,
                                                            required_skills: handoff.required_skills,
                                                            metadata: handoff.metadata,
                                                        },
                                                        null,
                                                        2,
                                                    )}
                                                </pre>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="rounded-xl border border-sidebar-border/70 bg-background p-4 dark:border-sidebar-border">
                            <h3 className="text-sm font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Audit Trail</h3>
                            {auditEvents.length === 0 ? (
                                <p className="mt-2 text-sm text-neutral-500 dark:text-neutral-400">No audit events recorded.</p>
                            ) : (
                                <ul className="mt-2 space-y-3 text-sm text-neutral-700 dark:text-neutral-300">
                                    {auditEvents.map((event) => (
                                        <li key={event.id} className="rounded-lg bg-neutral-100 p-3 dark:bg-neutral-900">
                                            <div className="flex items-start justify-between">
                                                <p className="font-medium text-neutral-800 dark:text-neutral-100">
                                                    {event.event_type.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
                                                </p>
                                                <span className="text-xs text-neutral-500 dark:text-neutral-400">
                                                    {event.occurred_at ? new Date(event.occurred_at).toLocaleString() : '—'}
                                                </span>
                                            </div>
                                            {event.user && (
                                                <p className="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                                                    By: {event.user.name}
                                                </p>
                                            )}
                                            {event.subject && (
                                                <p className="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                                                    Subject: {event.subject.type.split('\\').pop()} #{event.subject.id}
                                                </p>
                                            )}
                                            {event.channel && (
                                                <p className="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                                                    Channel: {event.channel}
                                                </p>
                                            )}
                                            {event.payload && Object.keys(event.payload).length > 0 && (
                                                <pre className="mt-2 overflow-x-auto rounded bg-neutral-100 p-2 text-xs text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                                                    {JSON.stringify(event.payload, null, 2)}
                                                </pre>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </aside>
                </main>
            </div>

            <ReleaseDialog
                open={releaseDialogOpen}
                onOpenChange={(open) => {
                    setReleaseDialogOpen(open);
                    if (!open) {
                        resetReleaseForm();
                    }
                }}
                submitting={releaseSubmitting}
                actorId={releaseActorId}
                onActorIdChange={setReleaseActorId}
                reason={releaseReason}
                onReasonChange={setReleaseReason}
                error={releaseError}
                onSubmit={handleRelease}
                conversationSubject={conversation.subject}
                disabled={!activeAssignment}
            />

            <ResolveDialog
                open={resolveDialogOpen}
                onOpenChange={(open) => {
                    setResolveDialogOpen(open);
                    if (!open) {
                        resetResolveForm();
                    }
                }}
                submitting={resolveSubmitting}
                actorId={resolveActorId}
                onActorIdChange={setResolveActorId}
                summary={resolveSummary}
                onSummaryChange={setResolveSummary}
                error={resolveError}
                onSubmit={handleResolve}
                conversationSubject={conversation.subject}
            />
        </AppLayout>
    );
}

function ReleaseDialog({
    open,
    onOpenChange,
    submitting,
    actorId,
    onActorIdChange,
    reason,
    onReasonChange,
    error,
    onSubmit,
    conversationSubject,
    disabled,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    submitting: boolean;
    actorId: string;
    onActorIdChange: (value: string) => void;
    reason: string;
    onReasonChange: (value: string) => void;
    error: string | null;
    onSubmit: () => Promise<void>;
    conversationSubject: string | null;
    disabled: boolean;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {conversationSubject ? `Return “${conversationSubject}” to agent` : 'Return conversation to agent'}
                    </DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    <div className="space-y-2">
                        <label className="block text-sm font-medium text-neutral-600 dark:text-neutral-300" htmlFor="release-actor-id">
                            Actor ID
                        </label>
                        <Input
                            id="release-actor-id"
                            value={actorId}
                            onChange={(event) => onActorIdChange(event.target.value)}
                            placeholder="Your user ID"
                            type="number"
                            min={1}
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="block text-sm font-medium text-neutral-600 dark:text-neutral-300" htmlFor="release-reason">
                            Release reason (optional)
                        </label>
                        <textarea
                            id="release-reason"
                            className="border-input placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex min-h-[6rem] w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            value={reason}
                            onChange={(event) => onReasonChange(event.target.value)}
                            placeholder="Explain why the agent should resume."
                        />
                    </div>
                    {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
                </div>
                <DialogFooter className="flex items-center justify-between gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={submitting}>
                        Cancel
                    </Button>
                    <Button onClick={() => void onSubmit()} disabled={submitting || disabled || !actorId}>
                        {submitting ? 'Returning…' : 'Return to Agent'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ResolveDialog({
    open,
    onOpenChange,
    submitting,
    actorId,
    onActorIdChange,
    summary,
    onSummaryChange,
    error,
    onSubmit,
    conversationSubject,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    submitting: boolean;
    actorId: string;
    onActorIdChange: (value: string) => void;
    summary: string;
    onSummaryChange: (value: string) => void;
    error: string | null;
    onSubmit: () => Promise<void>;
    conversationSubject: string | null;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {conversationSubject ? `Resolve “${conversationSubject}”` : 'Resolve conversation'}
                    </DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    <div className="space-y-2">
                        <label className="block text-sm font-medium text-neutral-600 dark:text-neutral-300" htmlFor="resolve-summary">
                            Resolution summary
                        </label>
                        <textarea
                            id="resolve-summary"
                            className="border-input placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex min-h-[6rem] w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            value={summary}
                            onChange={(event) => onSummaryChange(event.target.value)}
                            placeholder="Summarize the outcome for audit trail."
                            required
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="block text-sm font-medium text-neutral-600 dark:text-neutral-300" htmlFor="resolve-actor-id">
                            Actor ID (optional override)
                        </label>
                        <Input
                            id="resolve-actor-id"
                            value={actorId}
                            onChange={(event) => onActorIdChange(event.target.value)}
                            placeholder="Human resolving user ID"
                            type="number"
                            min={1}
                        />
                    </div>
                    {error && <p className="text-sm text-red-600 dark:text-red-400">{error}</p>}
                </div>
                <DialogFooter className="flex items-center justify-between gap-2">
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={submitting}>
                        Cancel
                    </Button>
                    <Button onClick={() => void onSubmit()} disabled={submitting || summary.trim() === ''}>
                        {submitting ? 'Resolving…' : 'Resolve conversation'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
