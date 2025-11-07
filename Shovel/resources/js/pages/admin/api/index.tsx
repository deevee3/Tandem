import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { BookOpen, ClipboardCheck, Copy, Code2, Key, Server, Workflow, MessageSquare, Users, ArrowRight } from 'lucide-react';

import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Admin', href: '/admin' },
    { title: 'API', href: '/admin/api' },
];

const endpointCatalog: Array<{ method: string; path: string; description: string }> = [
    {
        method: 'POST',
        path: '/api/conversations',
        description: 'Create a new conversation and enqueue it for routing.',
    },
    {
        method: 'POST',
        path: '/api/conversations/{conversation}/messages',
        description: 'Append an agent message (AI or human) to an existing conversation.',
    },
    {
        method: 'POST',
        path: '/api/conversations/{conversation}/handoff',
        description: 'Signal a handoff to a human agent with policy metadata.',
    },
    {
        method: 'POST',
        path: '/api/conversations/{conversation}/resolve',
        description: 'Resolve a conversation once requirements are satisfied.',
    },
    {
        method: 'GET',
        path: '/api/queues/{queue}/items',
        description: 'List work items currently queued for a given human queue.',
    },
    {
        method: 'POST',
        path: '/api/queues/{queue}/items/{queueItem}/claim',
        description: 'Assign the next queue item to an available human agent.',
    },
];

export default function ApiOverview() {
    const page = usePage<{ auth: { permissions?: string[] | null } }>();
    const permissions = page.props.auth.permissions ?? [];
    const canManageApiKeys = permissions.includes('api-keys.manage');

    const [copiedUrl, setCopiedUrl] = useState(false);
    const [copiedCurl, setCopiedCurl] = useState(false);

    const baseUrl = useMemo(() => {
        if (typeof window === 'undefined') {
            return '{YOUR_BASE_URL}/api';
        }

        return `${window.location.origin.replace(/\/$/, '')}/api`;
    }, []);

    const sampleCurl = useMemo(
        () =>
            `curl -X POST "${baseUrl}/conversations" \\
  -H "Authorization: Bearer {YOUR_API_KEY}" \\
  -H "Content-Type: application/json" \\
  -d '{\n    "subject": "Customer onboarding help",\n    "requester": {\n      "type": "customer",\n      "identifier": "cust_123"\n    },\n    "metadata": {\n      "source": "web"\n    }\n  }'`,
        [baseUrl],
    );

    const copyToClipboard = async (value: string, setter: (state: boolean) => void) => {
        try {
            await navigator.clipboard.writeText(value);
            setter(true);
            window.setTimeout(() => setter(false), 2500);
        } catch (error) {
            console.error('Unable to copy value to clipboard', error);
        }
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="API" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">API Access</h1>
                        <p className="mt-2 text-muted-foreground max-w-2xl">
                            Integrate the Handshake platform into your own tooling. Generate scoped API keys, explore
                            endpoints, and follow the recommended request flow for conversational handoffs.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="secondary">
                            <Link href="https://docs.shovel.dev/api" target="_blank" rel="noreferrer">
                                <BookOpen className="mr-2 h-4 w-4" />
                                API Reference
                            </Link>
                        </Button>
                        {canManageApiKeys ? (
                            <Button asChild>
                                <Link href="/admin/api-keys">
                                    <Key className="mr-2 h-4 w-4" />
                                    Manage API Keys
                                </Link>
                            </Button>
                        ) : (
                            <Button 
                                disabled 
                                title="You need the Manage API Keys permission to access this section."
                            >
                                <Key className="mr-2 h-4 w-4" />
                                Manage API Keys
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Server className="h-4 w-4" /> Base URL
                            </CardTitle>
                            <CardDescription>All endpoints are namespaced under this base path.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="rounded-md border bg-muted/40 px-3 py-2 font-mono text-sm break-all">
                                {baseUrl}
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-2"
                                onClick={() => copyToClipboard(baseUrl, setCopiedUrl)}
                            >
                                {copiedUrl ? <ClipboardCheck className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                                {copiedUrl ? 'Copied' : 'Copy base URL'}
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="md:col-span-1 xl:col-span-2">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Code2 className="h-4 w-4" /> Quick Start
                            </CardTitle>
                            <CardDescription>Recommended sequence to authenticate and create conversations.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ol className="space-y-3 text-sm leading-6">
                                <li className="flex gap-3">
                                    <Badge variant="outline">1</Badge>
                                    <div>
                                        Issue a workspace API key from the <Link className="underline" href="/admin/api-keys">API Keys</Link> page. Keys
                                        are hashed immediately—store the plaintext token securely.
                                    </div>
                                </li>
                                <li className="flex gap-3">
                                    <Badge variant="outline">2</Badge>
                                    <div>
                                        Send requests with an <span className="font-mono">Authorization: Bearer</span> header.
                                        Each key may include optional scopes for downstream auditing.
                                    </div>
                                </li>
                                <li className="flex gap-3">
                                    <Badge variant="outline">3</Badge>
                                    <div>
                                        Create conversations, append AI summaries, and trigger handoffs as your workflow
                                        demands. Queue endpoints let human agents claim or release work.
                                    </div>
                                </li>
                            </ol>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Core Endpoints</CardTitle>
                        <CardDescription>High-usage REST endpoints secured with API key authentication.</CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[120px]">Method</TableHead>
                                    <TableHead>Path</TableHead>
                                    <TableHead>Description</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {endpointCatalog.map((endpoint) => (
                                    <TableRow key={`${endpoint.method}-${endpoint.path}`}>
                                        <TableCell>
                                            <span className="font-medium">{endpoint.method}</span>
                                        </TableCell>
                                        <TableCell>
                                            <code className="rounded bg-muted px-2 py-1 text-xs">{endpoint.path}</code>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">{endpoint.description}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Workflow className="h-5 w-5" />
                            Complete Workflow Guide
                        </CardTitle>
                        <CardDescription>
                            Step-by-step guide to create conversations, process them with AI, and handle human handoffs.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="space-y-4">
                            <div className="flex gap-3">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground font-semibold">
                                    1
                                </div>
                                <div className="space-y-2 flex-1">
                                    <h4 className="font-semibold flex items-center gap-2">
                                        <MessageSquare className="h-4 w-4" />
                                        Create a Conversation
                                    </h4>
                                    <p className="text-sm text-muted-foreground">
                                        Send a POST request to create a new conversation. The system will automatically dispatch it to the AI agent queue.
                                    </p>
                                    <pre className="rounded-md border bg-muted/60 p-3 text-xs overflow-x-auto">
{`curl -X POST "${baseUrl}/conversations" \\
  -H "X-Api-Key: {YOUR_API_KEY}" \\
  -H "Content-Type: application/json" \\
  -d '{
    "subject": "Account access issue",
    "priority": "high",
    "requester": {
      "type": "customer",
      "identifier": "customer-123"
    },
    "case_id": "CASE-123",
    "initial_message": {
      "content": "I cannot access my account and need urgent help!"
    }
  }'`}
                                    </pre>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground font-semibold">
                                    2
                                </div>
                                <div className="space-y-2 flex-1">
                                    <h4 className="font-semibold">Process the Agent Queue</h4>
                                    <p className="text-sm text-muted-foreground">
                                        Run the queue worker to process the conversation with the AI agent. The agent will analyze the request and respond.
                                    </p>
                                    <pre className="rounded-md border bg-muted/60 p-3 text-xs overflow-x-auto">
{`php artisan queue:work --once --queue=default`}
                                    </pre>
                                    <div className="rounded-md bg-blue-50 dark:bg-blue-950/30 p-3 text-sm">
                                        <p className="font-medium text-blue-900 dark:text-blue-100 mb-1">AI Agent Behavior:</p>
                                        <ul className="text-blue-800 dark:text-blue-200 space-y-1 ml-4 list-disc">
                                            <li><strong>High confidence (≥0.7):</strong> Agent responds directly, conversation stays in <code className="bg-blue-100 dark:bg-blue-900 px-1 rounded">agent_working</code> status</li>
                                            <li><strong>Low confidence (&lt;0.7):</strong> Triggers handoff to inbox with <code className="bg-blue-100 dark:bg-blue-900 px-1 rounded">queued</code> status</li>
                                            <li><strong>Policy flags:</strong> Complex requests automatically escalate to human agents</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground font-semibold">
                                    3
                                </div>
                                <div className="space-y-2 flex-1">
                                    <h4 className="font-semibold flex items-center gap-2">
                                        <Users className="h-4 w-4" />
                                        View in Inbox (Human Handoffs Only)
                                    </h4>
                                    <p className="text-sm text-muted-foreground">
                                        If the agent triggers a handoff (low confidence or policy flag), the conversation appears in the inbox.
                                    </p>
                                    <div className="rounded-md border bg-muted/60 p-3 space-y-2">
                                        <p className="text-xs font-medium">Navigate to:</p>
                                        <code className="text-xs">http://localhost:8000/inbox</code>
                                        <p className="text-xs text-muted-foreground mt-2">
                                            Click <strong>"Claim"</strong> to assign the conversation to yourself. Once claimed, you can view and reply to the customer.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground font-semibold">
                                    4
                                </div>
                                <div className="space-y-2 flex-1">
                                    <h4 className="font-semibold flex items-center gap-2">
                                        <ArrowRight className="h-4 w-4" />
                                        Reply to Customer
                                    </h4>
                                    <p className="text-sm text-muted-foreground">
                                        After claiming, open the conversation to view the full transcript and send messages back to the customer.
                                    </p>
                                    <div className="rounded-md bg-green-50 dark:bg-green-950/30 p-3 text-sm">
                                        <p className="font-medium text-green-900 dark:text-green-100 mb-1">✓ What You Can Do:</p>
                                        <ul className="text-green-800 dark:text-green-200 space-y-1 ml-4 list-disc">
                                            <li>View complete message history (requester, agent, human messages)</li>
                                            <li>Send replies directly to the customer via the message input</li>
                                            <li>Return conversation to agent or resolve it when done</li>
                                            <li>Track confidence scores and policy triggers</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="border-t pt-4">
                            <h4 className="font-semibold mb-3">Common Scenarios</h4>
                            <div className="space-y-3">
                                <div className="rounded-md border p-3 space-y-1">
                                    <p className="text-sm font-medium">🎯 Force a Handoff (for Testing)</p>
                                    <p className="text-xs text-muted-foreground">
                                        Create a complex request that will trigger low confidence:
                                    </p>
                                    <pre className="rounded bg-muted/60 p-2 text-xs overflow-x-auto mt-2">
{`"initial_message": {
  "content": "I need a refund for a transaction from 3 months ago but lost the receipt and my account was deleted. Very urgent!"
}`}
                                    </pre>
                                </div>

                                <div className="rounded-md border p-3 space-y-1">
                                    <p className="text-sm font-medium">💡 View Any Conversation by ID</p>
                                    <p className="text-xs text-muted-foreground">
                                        Access conversations directly even if they didn't trigger handoff:
                                    </p>
                                    <code className="text-xs block mt-2">http://localhost:8000/conversations/{'{'}id{'}'}</code>
                                </div>

                                <div className="rounded-md border p-3 space-y-1">
                                    <p className="text-sm font-medium">⚙️ Required Model Configuration</p>
                                    <p className="text-xs text-muted-foreground">
                                        Ensure your <code className="bg-muted px-1 rounded">.env</code> has the correct OpenAI model:
                                    </p>
                                    <pre className="rounded bg-muted/60 p-2 text-xs mt-2">
{`OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_TIMEOUT=120`}
                                    </pre>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Quick Start Example</CardTitle>
                        <CardDescription>
                            Copy and run this complete example to test the full workflow.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <pre className="rounded-md border bg-muted/60 p-4 text-xs leading-6 overflow-x-auto">
{`# 1. Create conversation
curl -X POST "${baseUrl}/conversations" \\
  -H "X-Api-Key: {YOUR_API_KEY}" \\
  -H "Content-Type: application/json" \\
  -d '{
    "subject": "Password reset help",
    "priority": "standard",
    "requester": {"type": "customer", "identifier": "cust-456"},
    "case_id": "CASE-456",
    "initial_message": {"content": "How do I reset my password?"}
  }'

# 2. Process with AI agent
php artisan queue:work --once --queue=default

# 3. If handoff triggered, view in browser:
# http://localhost:8000/inbox`}
                        </pre>
                        <Button
                            variant="outline"
                            size="sm"
                            className="gap-2"
                            onClick={() => copyToClipboard(sampleCurl, setCopiedCurl)}
                        >
                            {copiedCurl ? <ClipboardCheck className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                            {copiedCurl ? 'Copied sample' : 'Copy sample'}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
