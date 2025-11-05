import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Edit, Plus, Search, Trash2, List, Star } from 'lucide-react';
import axios from 'axios';
import { dashboard } from '@/routes';

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
        title: 'Queues',
        href: '/admin/queues',
    },
];

interface Skill {
    id: number;
    name: string;
    description?: string;
}

interface Queue {
    id: number;
    name: string;
    slug: string;
    description?: string;
    is_default: boolean;
    skills_required?: number[];
    priority_policy?: Record<string, any>;
    created_at: string;
    updated_at: string;
}

interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export default function QueuesIndex() {
    const [queues, setQueues] = useState<Queue[]>([]);
    const [availableSkills, setAvailableSkills] = useState<Skill[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [pagination, setPagination] = useState<PaginationMeta>({
        current_page: 1,
        per_page: 50,
        total: 0,
        last_page: 1,
    });

    const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [selectedQueue, setSelectedQueue] = useState<Queue | null>(null);

    const [formData, setFormData] = useState({
        name: '',
        slug: '',
        description: '',
        is_default: false,
        skills_required: [] as number[],
    });

    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});

    const fetchQueues = useCallback(async (page = 1, searchQuery = '') => {
        setLoading(true);
        try {
            const response = await axios.get('/admin/api/queues', {
                params: {
                    page,
                    per_page: 50,
                    search: searchQuery || undefined,
                },
            });

            setQueues(response.data.data);
            setPagination(response.data.meta.pagination);
        } catch (error) {
            console.error('Failed to fetch queues:', error);
        } finally {
            setLoading(false);
        }
    }, []);

    const fetchSkills = useCallback(async () => {
        try {
            const response = await axios.get('/admin/api/skills/all');
            setAvailableSkills(response.data.data || []);
        } catch (error) {
            console.error('Failed to fetch skills:', error);
        }
    }, []);

    useEffect(() => {
        fetchQueues(1, search);
        fetchSkills();
    }, []);

    const handleSearch = () => {
        fetchQueues(1, search);
    };

    const handleSearchKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleSearch();
        }
    };

    const handleCreateQueue = async () => {
        setFormErrors({});
        try {
            await axios.post('/admin/api/queues', formData);
            setIsCreateDialogOpen(false);
            setFormData({ name: '', slug: '', description: '', is_default: false, skills_required: [] });
            fetchQueues(pagination.current_page, search);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
            }
        }
    };

    const handleEditQueue = async () => {
        if (!selectedQueue) return;

        setFormErrors({});
        try {
            await axios.put(`/admin/api/queues/${selectedQueue.id}`, formData);
            setIsEditDialogOpen(false);
            setSelectedQueue(null);
            setFormData({ name: '', slug: '', description: '', is_default: false, skills_required: [] });
            fetchQueues(pagination.current_page, search);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
            }
        }
    };

    const handleDeleteQueue = async () => {
        if (!selectedQueue) return;

        try {
            await axios.delete(`/admin/api/queues/${selectedQueue.id}`);
            setIsDeleteDialogOpen(false);
            setSelectedQueue(null);
            fetchQueues(pagination.current_page, search);
        } catch (error: any) {
            if (error.response?.data?.message) {
                alert(error.response.data.message);
            } else {
                console.error('Failed to delete queue:', error);
            }
        }
    };

    const handleSkillToggle = (skillId: number, checked: boolean) => {
        if (checked) {
            setFormData({ ...formData, skills_required: [...formData.skills_required, skillId] });
        } else {
            setFormData({ ...formData, skills_required: formData.skills_required.filter(id => id !== skillId) });
        }
    };

    const openCreateDialog = () => {
        setFormData({ name: '', slug: '', description: '', is_default: false, skills_required: [] });
        setFormErrors({});
        setIsCreateDialogOpen(true);
    };

    const openEditDialog = (queue: Queue) => {
        setSelectedQueue(queue);
        setFormData({
            name: queue.name,
            slug: queue.slug,
            description: queue.description || '',
            is_default: queue.is_default,
            skills_required: queue.skills_required || [],
        });
        setFormErrors({});
        setIsEditDialogOpen(true);
    };

    const openDeleteDialog = (queue: Queue) => {
        setSelectedQueue(queue);
        setIsDeleteDialogOpen(true);
    };

    const getSkillNames = (skillIds?: number[]) => {
        if (!skillIds || skillIds.length === 0) return [];
        return availableSkills
            .filter(skill => skillIds.includes(skill.id))
            .map(skill => skill.name);
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Queues Management" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Queues</h1>
                        <p className="text-muted-foreground mt-2">Manage conversation queues</p>
                    </div>
                    <Button onClick={openCreateDialog}>
                        <List className="mr-2 h-4 w-4" />
                        Add Queue
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Queues</CardTitle>
                        <CardDescription>
                            {pagination.total} total queue{pagination.total !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-2 mb-4">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search by name or slug..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyPress={handleSearchKeyPress}
                                    className="pl-9"
                                />
                            </div>
                            <Button onClick={handleSearch} variant="secondary">
                                Search
                            </Button>
                        </div>

                        {loading ? (
                            <div className="text-center py-8 text-muted-foreground">Loading queues...</div>
                        ) : queues.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">No queues found</div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Slug</TableHead>
                                        <TableHead>Default</TableHead>
                                        <TableHead>Skills Required</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {queues.map((queue) => {
                                        const skillNames = getSkillNames(queue.skills_required);
                                        return (
                                            <TableRow key={queue.id}>
                                                <TableCell className="font-medium">{queue.name}</TableCell>
                                                <TableCell>
                                                    <code className="text-xs bg-muted px-2 py-1 rounded">{queue.slug}</code>
                                                </TableCell>
                                                <TableCell>
                                                    {queue.is_default ? (
                                                        <Badge variant="default" className="gap-1">
                                                            <Star className="h-3 w-3" />
                                                            Default
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground text-sm">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-wrap gap-1">
                                                        {skillNames.length > 0 ? (
                                                            <>
                                                                {skillNames.slice(0, 3).map((skillName, idx) => (
                                                                    <Badge key={idx} variant="secondary">
                                                                        {skillName}
                                                                    </Badge>
                                                                ))}
                                                                {skillNames.length > 3 && (
                                                                    <Badge variant="secondary">
                                                                        +{skillNames.length - 3}
                                                                    </Badge>
                                                                )}
                                                            </>
                                                        ) : (
                                                            <span className="text-muted-foreground text-sm">
                                                                No skills required
                                                            </span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => openEditDialog(queue)}
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => openDeleteDialog(queue)}
                                                        >
                                                            <Trash2 className="h-4 w-4 text-destructive" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}

                        {pagination.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4">
                                <div className="text-sm text-muted-foreground">
                                    Page {pagination.current_page} of {pagination.last_page}
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={pagination.current_page === 1}
                                        onClick={() => fetchQueues(pagination.current_page - 1, search)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={pagination.current_page === pagination.last_page}
                                        onClick={() => fetchQueues(pagination.current_page + 1, search)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create Queue Dialog */}
            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Create New Queue</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="create-name">Name</Label>
                            <Input
                                id="create-name"
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                placeholder="Enter queue name"
                            />
                            {formErrors.name && (
                                <p className="text-sm text-destructive">{formErrors.name[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="create-slug">Slug</Label>
                            <Input
                                id="create-slug"
                                value={formData.slug}
                                onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
                                placeholder="Leave empty to auto-generate"
                            />
                            {formErrors.slug && (
                                <p className="text-sm text-destructive">{formErrors.slug[0]}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                If left empty, will be auto-generated from the name
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="create-description">Description</Label>
                            <Textarea
                                id="create-description"
                                value={formData.description}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setFormData({ ...formData, description: e.target.value })}
                                placeholder="Enter queue description (optional)"
                                rows={3}
                            />
                            {formErrors.description && (
                                <p className="text-sm text-destructive">{formErrors.description[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="create-is-default"
                                    checked={formData.is_default}
                                    onCheckedChange={(checked) =>
                                        setFormData({ ...formData, is_default: checked as boolean })
                                    }
                                />
                                <label
                                    htmlFor="create-is-default"
                                    className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                >
                                    Set as default queue
                                </label>
                            </div>
                            {formErrors.is_default && (
                                <p className="text-sm text-destructive">{formErrors.is_default[0]}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                The default queue will be used for new conversations unless specified otherwise
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label>Skills Required</Label>
                            <div className="border rounded-md p-4 max-h-60 overflow-y-auto">
                                {availableSkills.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No skills available</p>
                                ) : (
                                    <div className="grid gap-3">
                                        {availableSkills.map((skill) => (
                                            <div key={skill.id} className="flex items-center space-x-2">
                                                <Checkbox
                                                    id={`create-skill-${skill.id}`}
                                                    checked={formData.skills_required.includes(skill.id)}
                                                    onCheckedChange={(checked) =>
                                                        handleSkillToggle(skill.id, checked as boolean)
                                                    }
                                                />
                                                <label
                                                    htmlFor={`create-skill-${skill.id}`}
                                                    className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                                >
                                                    {skill.name}
                                                    {skill.description && (
                                                        <span className="text-muted-foreground ml-2">({skill.description})</span>
                                                    )}
                                                </label>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                            {formErrors.skills_required && (
                                <p className="text-sm text-destructive">{formErrors.skills_required[0]}</p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleCreateQueue}>Create Queue</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Queue Dialog */}
            <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Queue</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-name">Name</Label>
                            <Input
                                id="edit-name"
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                placeholder="Enter queue name"
                            />
                            {formErrors.name && (
                                <p className="text-sm text-destructive">{formErrors.name[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-slug">Slug</Label>
                            <Input
                                id="edit-slug"
                                value={formData.slug}
                                onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
                                placeholder="Enter queue slug"
                            />
                            {formErrors.slug && (
                                <p className="text-sm text-destructive">{formErrors.slug[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-description">Description</Label>
                            <Textarea
                                id="edit-description"
                                value={formData.description}
                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setFormData({ ...formData, description: e.target.value })}
                                placeholder="Enter queue description (optional)"
                                rows={3}
                            />
                            {formErrors.description && (
                                <p className="text-sm text-destructive">{formErrors.description[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="edit-is-default"
                                    checked={formData.is_default}
                                    onCheckedChange={(checked) =>
                                        setFormData({ ...formData, is_default: checked as boolean })
                                    }
                                />
                                <label
                                    htmlFor="edit-is-default"
                                    className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                >
                                    Set as default queue
                                </label>
                            </div>
                            {formErrors.is_default && (
                                <p className="text-sm text-destructive">{formErrors.is_default[0]}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                The default queue will be used for new conversations unless specified otherwise
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label>Skills Required</Label>
                            <div className="border rounded-md p-4 max-h-60 overflow-y-auto">
                                {availableSkills.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No skills available</p>
                                ) : (
                                    <div className="grid gap-3">
                                        {availableSkills.map((skill) => (
                                            <div key={skill.id} className="flex items-center space-x-2">
                                                <Checkbox
                                                    id={`edit-skill-${skill.id}`}
                                                    checked={formData.skills_required.includes(skill.id)}
                                                    onCheckedChange={(checked) =>
                                                        handleSkillToggle(skill.id, checked as boolean)
                                                    }
                                                />
                                                <label
                                                    htmlFor={`edit-skill-${skill.id}`}
                                                    className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                                >
                                                    {skill.name}
                                                    {skill.description && (
                                                        <span className="text-muted-foreground ml-2">({skill.description})</span>
                                                    )}
                                                </label>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                            {formErrors.skills_required && (
                                <p className="text-sm text-destructive">{formErrors.skills_required[0]}</p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleEditQueue}>Save Changes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Queue Dialog */}
            <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Queue</DialogTitle>
                    </DialogHeader>
                    <div className="py-4">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to delete <strong>{selectedQueue?.name}</strong>? This action cannot be
                            undone.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsDeleteDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDeleteQueue}>
                            Delete Queue
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
