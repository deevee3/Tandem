import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { Plus, Search, ToggleLeft, ToggleRight, CheckCircle2, XCircle, LockKeyhole, Clipboard, ClipboardCheck } from 'lucide-react';

import AdminLayout from '@/layouts/admin-layout';
import {
    type ApiKey,
    type ApiKeyUserSummary,
    type PaginatedApiKeyResponse,
    type BreadcrumbItem,
} from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';

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
        title: 'API Keys',
        href: '/admin/api-keys',
    },
];

type ActiveFilter = 'all' | 'active' | 'revoked';

type CreateApiKeyForm = {
    name: string;
    scopesText: string;
    expiresAt: string;
    userId: string;
};

type FormErrors = Record<string, string[]>;

const initialForm: CreateApiKeyForm = {
    name: '',
    scopesText: '',
    expiresAt: '',
    userId: '',
};

const defaultPagination: PaginatedApiKeyResponse['meta']['pagination'] = {
    current_page: 1,
    per_page: 25,
    total: 0,
    last_page: 1,
};

export default function ApiKeysIndex() {
    const [apiKeys, setApiKeys] = useState<ApiKey[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [search, setSearch] = useState<string>('');
    const [activeFilter, setActiveFilter] = useState<ActiveFilter>('all');
    const [pagination, setPagination] = useState(defaultPagination);

    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [formData, setFormData] = useState<CreateApiKeyForm>(initialForm);
    const [formErrors, setFormErrors] = useState<FormErrors>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [availableUsers, setAvailableUsers] = useState<ApiKeyUserSummary[]>([]);
    const [plainTextKey, setPlainTextKey] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);
    const [updatingKeyId, setUpdatingKeyId] = useState<number | null>(null);

    const fetchApiKeys = useCallback(
        async (page = 1, searchQuery = search, filter: ActiveFilter = activeFilter) => {
            setLoading(true);
            try {
                const route = admin.api.apiKeys.index();
                const params: Record<string, string | number | boolean> = {
                    page,
                    per_page: pagination.per_page,
                };

                if (searchQuery.trim() !== '') {
                    params.search = searchQuery.trim();
                }

                if (filter !== 'all') {
                    params.active = filter === 'active';
                }

                const response = await axios.get<PaginatedApiKeyResponse>(route.url, {
                    params,
                });

                setApiKeys(response.data.data ?? []);
                setPagination(response.data.meta.pagination ?? defaultPagination);
            } catch (error) {
                console.error('Failed to load API keys', error);
            } finally {
                setLoading(false);
            }
        },
        [activeFilter, pagination.per_page, search],
    );

    const fetchUsers = useCallback(async () => {
        try {
            const route = admin.api.users.index();
            const response = await axios.get(route.url, {
                params: {
                    per_page: 100,
                },
            });

            const items = (response.data?.data ?? []) as Array<{ id: number; name: string; email: string }>;
            const summaries: ApiKeyUserSummary[] = items.map((item) => ({
                id: item.id,
                name: item.name,
                email: item.email,
            }));
            setAvailableUsers(summaries);
        } catch (error) {
            console.error('Failed to fetch users', error);
        }
    }, []);

    useEffect(() => {
        fetchUsers().catch(() => {
            /* handled above */
        });
    }, [fetchUsers]);

    useEffect(() => {
        fetchApiKeys().catch(() => {
            /* handled above */
        });
    }, [fetchApiKeys]);

    const handleSearch = () => {
        void fetchApiKeys(1, search, activeFilter);
    };

    const handleSearchKeyPress = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            handleSearch();
        }
    };

    const handleFilterChange = (value: ActiveFilter) => {
        setActiveFilter(value);
        void fetchApiKeys(1, search, value);
    };

    const openCreateDialog = () => {
        setFormErrors({});
        setFormData(initialForm);
        setIsCreateDialogOpen(true);
    };

    const parseScopes = (scopesText: string): string[] => {
        if (!scopesText.trim()) {
            return [];
        }

        return scopesText
            .split(/[\n,]/)
            .map((scope) => scope.trim())
            .filter((scope, index, arr) => scope !== '' && arr.indexOf(scope) === index);
    };

    const handleCreate = async () => {
        setFormErrors({});
        setIsSubmitting(true);

        try {
            const payload = {
                name: formData.name,
                scopes: parseScopes(formData.scopesText),
                expires_at: formData.expiresAt ? new Date(formData.expiresAt).toISOString() : null,
                user_id: formData.userId ? Number.parseInt(formData.userId, 10) : null,
            };

            const route = admin.api.apiKeys.store();
            const response = await axios.post(route.url, payload);

            const newPlainText = response.data?.plain_text_key ?? null;
            setPlainTextKey(newPlainText);
            setCopied(false);
            setIsCreateDialogOpen(false);
            setFormData(initialForm);

            await fetchApiKeys(pagination.current_page, search, activeFilter);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors as FormErrors);
            } else {
                console.error('Failed to create API key', error);
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const toggleApiKey = async (apiKey: ApiKey) => {
        setUpdatingKeyId(apiKey.id);
        try {
            const route = admin.api.apiKeys.update({ apiKey: apiKey.id });
            await axios.put(route.url, {
                active: !apiKey.active,
            });

            setApiKeys((prev) =>
                prev.map((item) =>
                    item.id === apiKey.id
                        ? {
                              ...item,
                              active: !item.active,
                          }
                        : item,
                ),
            );
        } catch (error) {
            console.error('Failed to update API key status', error);
        } finally {
            setUpdatingKeyId(null);
        }
    };

    const metadataSummary = useCallback((metadata: ApiKey['metadata']) => {
        if (!metadata || Object.keys(metadata).length === 0) {
            return '—';
        }

        const keys = Object.keys(metadata);
        return keys.slice(0, 3).join(', ') + (keys.length > 3 ? ` +${keys.length - 3}` : '');
    }, []);

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

    const scopesContent = (scopes: string[]) => {
        if (!scopes || scopes.length === 0) {
            return <span className="text-sm text-muted-foreground">No scopes</span>;
        }

        return (
            <div className="flex flex-wrap gap-1">
                {scopes.map((scope) => (
                    <Badge key={scope} variant="outline" className="font-mono text-xs">
                        {scope}
                    </Badge>
                ))}
            </div>
        );
    };

    const copyPlainTextKey = async () => {
        if (!plainTextKey) {
            return;
        }

        try {
            await navigator.clipboard.writeText(plainTextKey);
            setCopied(true);
            setTimeout(() => setCopied(false), 2500);
        } catch (error) {
            console.error('Unable to copy API key to clipboard', error);
        }
    };

    const activeFilterLabel = useMemo(() => {
        switch (activeFilter) {
            case 'active':
                return 'Active';
            case 'revoked':
                return 'Revoked';
            default:
                return 'All statuses';
        }
    }, [activeFilter]);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="API Keys" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">API Keys</h1>
                        <p className="text-muted-foreground mt-2">
                            Issue and manage API credentials for integrations and automation.
                        </p>
                    </div>
                    <Button onClick={openCreateDialog} className="gap-2">
                        <Plus className="h-4 w-4" />
                        Issue API Key
                    </Button>
                </div>

                {plainTextKey ? (
                    <Alert variant="default" className="items-start gap-3">
                        <LockKeyhole className="h-5 w-5" />
                        <div className="flex flex-col gap-3">
                            <div>
                                <AlertTitle>Store this API key securely</AlertTitle>
                                <AlertDescription>
                                    This is the only time we will show the plaintext token. Copy it now and keep it in a safe
                                    place.
                                </AlertDescription>
                            </div>
                            <div className="flex flex-col gap-2">
                                <div className="rounded-md border bg-muted/40 p-3 font-mono text-sm break-all">
                                    {plainTextKey}
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="secondary" size="sm" onClick={copyPlainTextKey} className="gap-2">
                                        {copied ? <ClipboardCheck className="h-4 w-4" /> : <Clipboard className="h-4 w-4" />}
                                        {copied ? 'Copied' : 'Copy key'}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </Alert>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Issued Keys</CardTitle>
                        <CardDescription>
                            {pagination.total} total key{pagination.total === 1 ? '' : 's'} issued across the workspace.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:gap-2 mb-4">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search by name or metadata..."
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    onKeyDown={handleSearchKeyPress}
                                    className="pl-9"
                                />
                            </div>
                            <Select value={activeFilter} onValueChange={(value) => handleFilterChange(value as ActiveFilter)}>
                                <SelectTrigger className="w-[160px]">
                                    <SelectValue placeholder="Status" aria-label={activeFilterLabel}>
                                        {activeFilterLabel}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All statuses</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="revoked">Revoked</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button onClick={handleSearch} variant="secondary">
                                Search
                            </Button>
                        </div>

                        {loading ? (
                            <div className="text-center py-10 text-muted-foreground">Loading API keys…</div>
                        ) : apiKeys.length === 0 ? (
                            <div className="text-center py-10 text-muted-foreground">
                                No API keys found. Issue one with the “Issue API Key” button.
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Owner</TableHead>
                                        <TableHead>Scopes</TableHead>
                                        <TableHead>Expires</TableHead>
                                        <TableHead>Last used</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead>Metadata</TableHead>
                                        <TableHead className="text-right">Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {apiKeys.map((apiKey) => (
                                        <TableRow key={apiKey.id}>
                                            <TableCell className="font-medium">{apiKey.name}</TableCell>
                                            <TableCell>
                                                {apiKey.user ? (
                                                    <div className="flex flex-col text-sm">
                                                        <span className="font-medium">{apiKey.user.name}</span>
                                                        <span className="text-muted-foreground">{apiKey.user.email}</span>
                                                    </div>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">Unassigned</span>
                                                )}
                                            </TableCell>
                                            <TableCell>{scopesContent(apiKey.scopes ?? [])}</TableCell>
                                            <TableCell>{formatDateTime(apiKey.expires_at)}</TableCell>
                                            <TableCell>{formatDateTime(apiKey.last_used_at)}</TableCell>
                                            <TableCell>{formatDateTime(apiKey.created_at)}</TableCell>
                                            <TableCell>
                                                <span className="text-xs text-muted-foreground">
                                                    {metadataSummary(apiKey.metadata ?? {})}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-3">
                                                    <Badge className="gap-1" variant={apiKey.active ? 'default' : 'outline'}>
                                                        {apiKey.active ? (
                                                            <>
                                                                <CheckCircle2 className="h-3 w-3" /> Active
                                                            </>
                                                        ) : (
                                                            <>
                                                                <XCircle className="h-3 w-3" /> Revoked
                                                            </>
                                                        )}
                                                    </Badge>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="gap-2"
                                                        onClick={() => toggleApiKey(apiKey)}
                                                        disabled={updatingKeyId === apiKey.id}
                                                    >
                                                        {apiKey.active ? (
                                                            <>
                                                                <ToggleRight className="h-4 w-4 text-green-500" />
                                                                Revoke
                                                            </>
                                                        ) : (
                                                            <>
                                                                <ToggleLeft className="h-4 w-4 text-muted-foreground" />
                                                                Restore
                                                            </>
                                                        )}
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
                                        disabled={pagination.current_page === 1 || loading}
                                        onClick={() => fetchApiKeys(pagination.current_page - 1, search, activeFilter)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={pagination.current_page === pagination.last_page || loading}
                                        onClick={() => fetchApiKeys(pagination.current_page + 1, search, activeFilter)}
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
                        <DialogTitle>Issue API Key</DialogTitle>
                        <p className="text-sm text-muted-foreground">
                            Provide a friendly name, optional scopes, and assign an owner for auditing.
                        </p>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="api-key-name">Name</Label>
                            <Input
                                id="api-key-name"
                                value={formData.name}
                                onChange={(event) => setFormData((prev) => ({ ...prev, name: event.target.value }))}
                                placeholder="Integration service"
                                aria-invalid={formErrors.name ? 'true' : 'false'}
                            />
                            {formErrors.name?.map((message, idx) => (
                                <p key={idx} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="api-key-owner">Owner (optional)</Label>
                            <Select
                                value={formData.userId}
                                onValueChange={(value) => setFormData((prev) => ({ ...prev, userId: value }))}
                            >
                                <SelectTrigger id="api-key-owner">
                                    <SelectValue placeholder="Unassigned" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Unassigned</SelectItem>
                                    {availableUsers.map((user) => (
                                        <SelectItem key={user.id} value={user.id.toString()}>
                                            {user.name} ({user.email})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {formErrors.user_id?.map((message, idx) => (
                                <p key={idx} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="api-key-scopes">Scopes (optional)</Label>
                            <Textarea
                                id="api-key-scopes"
                                value={formData.scopesText}
                                onChange={(event) => setFormData((prev) => ({ ...prev, scopesText: event.target.value }))}
                                placeholder="Enter scopes separated by commas or new lines"
                                rows={3}
                            />
                            {formErrors['scopes']?.map((message, idx) => (
                                <p key={idx} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="api-key-expires">Expires at (optional)</Label>
                            <Input
                                id="api-key-expires"
                                type="datetime-local"
                                value={formData.expiresAt}
                                onChange={(event) => setFormData((prev) => ({ ...prev, expiresAt: event.target.value }))}
                                aria-invalid={formErrors.expires_at ? 'true' : 'false'}
                            />
                            <p className="text-xs text-muted-foreground">Leave blank for no automatic expiration.</p>
                            {formErrors.expires_at?.map((message, idx) => (
                                <p key={idx} className="text-xs text-destructive">
                                    {message}
                                </p>
                            ))}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button onClick={handleCreate} disabled={isSubmitting}>
                            {isSubmitting ? 'Issuing…' : 'Issue Key'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
