import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { BookOpen, ClipboardCheck, Copy, Code2, Key, Server } from 'lucide-react';

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
                        <CardTitle>Sample Request</CardTitle>
                        <CardDescription>
                            Use the snippet below to create a conversation and enqueue it for human follow-up.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <pre className="rounded-md border bg-muted/60 p-4 text-xs leading-6 text-muted-foreground overflow-x-auto">
                            {sampleCurl}
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
