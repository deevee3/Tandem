import { Head, Link } from '@inertiajs/react';
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
import { Edit, Plus, Search, Trash2, Shield, Eye } from 'lucide-react';
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
        title: 'Roles',
        href: '/admin/roles',
    },
];

interface Permission {
    id: number;
    name: string;
    slug: string;
    guard_name: string;
}

interface Role {
    id: number;
    code?: string;
    name: string;
    slug: string;
    description?: string;
    guard_name: string;
    hourly_rate?: number;
    created_at: string;
    updated_at: string;
    permissions?: Permission[];
}

interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export default function RolesIndex() {
    const [roles, setRoles] = useState<Role[]>([]);
    const [availablePermissions, setAvailablePermissions] = useState<Permission[]>([]);
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
    const [selectedRole, setSelectedRole] = useState<Role | null>(null);

    const [formData, setFormData] = useState({
        name: '',
        slug: '',
        description: '',
        guard_name: 'web',
        hourly_rate: '',
        permission_ids: [] as number[],
    });

    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const fetchRoles = useCallback(async (page = 1, searchQuery = '') => {
        setLoading(true);
        try {
            const response = await axios.get('/admin/api/roles', {
                params: {
                    page,
                    per_page: 50,
                    search: searchQuery || undefined,
                },
            });

            setRoles(response.data.data);
            setPagination(response.data.meta.pagination);
        } catch (error) {
            console.error('Failed to fetch roles:', error);
        } finally {
            setLoading(false);
        }
    }, []);

    const fetchPermissions = useCallback(async () => {
        try {
            const response = await axios.get('/admin/api/permissions/all');
            setAvailablePermissions(response.data.data || []);
        } catch (error) {
            console.error('Failed to fetch permissions:', error);
        }
    }, []);

    useEffect(() => {
        fetchRoles(1, search);
        fetchPermissions();
    }, []);

    const handleSearch = () => {
        fetchRoles(1, search);
    };

    const handleSearchKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleSearch();
        }
    };

    const handleCreateRole = async () => {
        setFormErrors({});
        setIsSubmitting(true);
        try {
            console.log('Creating role with data:', formData);
            await axios.post('/admin/api/roles', formData);
            setIsCreateDialogOpen(false);
            setFormData({ name: '', slug: '', description: '', guard_name: 'web', hourly_rate: '', permission_ids: [] });
            await fetchRoles(pagination.current_page, search);
        } catch (error: any) {
            console.error('Error creating role:', error);
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
            } else {
                alert(`Error: ${error.response?.data?.message || error.message || 'Unknown error occurred'}`);
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleEditRole = async () => {
        if (!selectedRole) return;

        setFormErrors({});
        setIsSubmitting(true);
        try {
            console.log('Updating role with data:', formData);
            await axios.put(`/admin/api/roles/${selectedRole.id}`, formData);
            setIsEditDialogOpen(false);
            setSelectedRole(null);
            setFormData({ name: '', slug: '', description: '', guard_name: 'web', hourly_rate: '', permission_ids: [] });
            await fetchRoles(pagination.current_page, search);
        } catch (error: any) {
            console.error('Error updating role:', error);
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
            } else {
                alert(`Error: ${error.response?.data?.message || error.message || 'Unknown error occurred'}`);
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleDeleteRole = async () => {
        if (!selectedRole) return;

        try {
            await axios.delete(`/admin/api/roles/${selectedRole.id}`);
            setIsDeleteDialogOpen(false);
            setSelectedRole(null);
            fetchRoles(pagination.current_page, search);
        } catch (error) {
            console.error('Failed to delete role:', error);
        }
    };

    const handlePermissionToggle = (permissionId: number, checked: boolean) => {
        if (checked) {
            setFormData({ ...formData, permission_ids: [...formData.permission_ids, permissionId] });
        } else {
            setFormData({ ...formData, permission_ids: formData.permission_ids.filter(id => id !== permissionId) });
        }
    };

    const openCreateDialog = () => {
        setFormData({ name: '', slug: '', description: '', guard_name: 'web', hourly_rate: '', permission_ids: [] });
        setFormErrors({});
        setIsCreateDialogOpen(true);
    };

    const openEditDialog = (role: Role) => {
        setSelectedRole(role);
        setFormData({
            name: role.name,
            slug: role.slug,
            description: role.description || '',
            guard_name: role.guard_name,
            hourly_rate: role.hourly_rate?.toString() || '',
            permission_ids: role.permissions?.map(p => p.id) || [],
        });
        setFormErrors({});
        setIsEditDialogOpen(true);
    };

    const openDeleteDialog = (role: Role) => {
        setSelectedRole(role);
        setIsDeleteDialogOpen(true);
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles Management" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Roles</h1>
                        <p className="text-muted-foreground mt-2">Manage roles and permissions</p>
                    </div>
                    <Button onClick={openCreateDialog}>
                        <Shield className="mr-2 h-4 w-4" />
                        Add Role
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Roles</CardTitle>
                        <CardDescription>
                            {pagination.total} total role{pagination.total !== 1 ? 's' : ''}
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
                            <div className="text-center py-8 text-muted-foreground">Loading roles...</div>
                        ) : roles.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">No roles found</div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Slug</TableHead>
                                        <TableHead>Guard</TableHead>
                                        <TableHead>Permissions</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {roles.map((role) => (
                                        <TableRow key={role.id}>
                                            <TableCell className="font-medium">{role.name}</TableCell>
                                            <TableCell>
                                                <code className="text-xs bg-muted px-2 py-1 rounded">{role.slug}</code>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">{role.guard_name}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {role.permissions && role.permissions.length > 0 ? (
                                                        <>
                                                            {role.permissions.slice(0, 3).map((permission) => (
                                                                <Badge key={permission.id} variant="secondary">
                                                                    {permission.name}
                                                                </Badge>
                                                            ))}
                                                            {role.permissions.length > 3 && (
                                                                <Badge variant="secondary">
                                                                    +{role.permissions.length - 3}
                                                                </Badge>
                                                            )}
                                                        </>
                                                    ) : (
                                                        <span className="text-muted-foreground text-sm">
                                                            No permissions
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Link href={`/admin/roles/${role.id}`}>
                                                        <Button variant="ghost" size="sm" title="View Profile">
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openEditDialog(role)}
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openDeleteDialog(role)}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
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
                                        onClick={() => fetchRoles(pagination.current_page - 1, search)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={pagination.current_page === pagination.last_page}
                                        onClick={() => fetchRoles(pagination.current_page + 1, search)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create Role Dialog */}
            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Create New Role</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="create-name">Name</Label>
                            <Input
                                id="create-name"
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                placeholder="Enter role name"
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
                                placeholder="Enter role description (optional)"
                                rows={3}
                            />
                            {formErrors.description && (
                                <p className="text-sm text-destructive">{formErrors.description[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="create-guard-name">Guard Name (Optional)</Label>
                            <Input
                                id="create-guard-name"
                                value={formData.guard_name}
                                onChange={(e) => setFormData({ ...formData, guard_name: e.target.value })}
                                placeholder="Leave empty to default to 'web'"
                            />
                            {formErrors.guard_name && (
                                <p className="text-sm text-destructive">{formErrors.guard_name[0]}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Defaults to 'web' for standard authentication. Use 'api' for API-only roles.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="create-hourly-rate">Hourly Rate</Label>
                            <Input
                                id="create-hourly-rate"
                                type="number"
                                step="0.01"
                                min="0"
                                value={formData.hourly_rate}
                                onChange={(e) => setFormData({ ...formData, hourly_rate: e.target.value })}
                                placeholder="0.00"
                            />
                            {formErrors.hourly_rate && (
                                <p className="text-sm text-destructive">{formErrors.hourly_rate[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label>Permissions</Label>
                            <div className="border rounded-md p-4 max-h-60 overflow-y-auto">
                                {availablePermissions.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No permissions available</p>
                                ) : (
                                    <div className="grid gap-3">
                                        {availablePermissions.map((permission) => (
                                            <div key={permission.id} className="flex items-center space-x-2">
                                                <Checkbox
                                                    id={`create-permission-${permission.id}`}
                                                    checked={formData.permission_ids.includes(permission.id)}
                                                    onCheckedChange={(checked) =>
                                                        handlePermissionToggle(permission.id, checked as boolean)
                                                    }
                                                />
                                                <label
                                                    htmlFor={`create-permission-${permission.id}`}
                                                    className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                                >
                                                    {permission.name}
                                                    <span className="text-muted-foreground ml-2">({permission.slug})</span>
                                                </label>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                            {formErrors.permission_ids && (
                                <p className="text-sm text-destructive">{formErrors.permission_ids[0]}</p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button onClick={handleCreateRole} disabled={isSubmitting}>
                            {isSubmitting ? 'Creating...' : 'Create Role'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Role Dialog */}
            <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Role</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-name">Name</Label>
                            <Input
                                id="edit-name"
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                placeholder="Enter role name"
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
                                placeholder="Enter role slug"
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
                                placeholder="Enter role description (optional)"
                                rows={3}
                            />
                            {formErrors.description && (
                                <p className="text-sm text-destructive">{formErrors.description[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-guard-name">Guard Name (Optional)</Label>
                            <Input
                                id="edit-guard-name"
                                value={formData.guard_name}
                                onChange={(e) => setFormData({ ...formData, guard_name: e.target.value })}
                                placeholder="web"
                            />
                            {formErrors.guard_name && (
                                <p className="text-sm text-destructive">{formErrors.guard_name[0]}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Defaults to 'web' for standard authentication. Use 'api' for API-only roles.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-hourly-rate">Hourly Rate</Label>
                            <Input
                                id="edit-hourly-rate"
                                type="number"
                                step="0.01"
                                min="0"
                                value={formData.hourly_rate}
                                onChange={(e) => setFormData({ ...formData, hourly_rate: e.target.value })}
                                placeholder="0.00"
                            />
                            {formErrors.hourly_rate && (
                                <p className="text-sm text-destructive">{formErrors.hourly_rate[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label>Permissions</Label>
                            <div className="border rounded-md p-4 max-h-60 overflow-y-auto">
                                {availablePermissions.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No permissions available</p>
                                ) : (
                                    <div className="grid gap-3">
                                        {availablePermissions.map((permission) => (
                                            <div key={permission.id} className="flex items-center space-x-2">
                                                <Checkbox
                                                    id={`edit-permission-${permission.id}`}
                                                    checked={formData.permission_ids.includes(permission.id)}
                                                    onCheckedChange={(checked) =>
                                                        handlePermissionToggle(permission.id, checked as boolean)
                                                    }
                                                />
                                                <label
                                                    htmlFor={`edit-permission-${permission.id}`}
                                                    className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                                >
                                                    {permission.name}
                                                    <span className="text-muted-foreground ml-2">({permission.slug})</span>
                                                </label>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                            {formErrors.permission_ids && (
                                <p className="text-sm text-destructive">{formErrors.permission_ids[0]}</p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditDialogOpen(false)} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button onClick={handleEditRole} disabled={isSubmitting}>
                            {isSubmitting ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Role Dialog */}
            <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Role</DialogTitle>
                    </DialogHeader>
                    <div className="py-4">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to delete <strong>{selectedRole?.name}</strong>? This action cannot be
                            undone.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsDeleteDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDeleteRole}>
                            Delete Role
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
