import { Head, Link } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Edit, Plus, Search, Trash2, UserPlus, Award, Eye } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
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
        title: 'Users',
        href: '/admin/users',
    },
];

interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at?: string;
    created_at: string;
    updated_at: string;
    total_hourly_rate?: number;
    roles?: Array<{
        id: number;
        name: string;
        slug: string;
        hourly_rate?: number;
    }>;
    skills?: Array<{
        id: number;
        name: string;
        slug: string;
        level?: number;
        hourly_rate?: number;
    }>;
}

interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

interface Skill {
    id: number;
    name: string;
    description?: string;
}

interface UserSkill {
    skill_id: number;
    level: number;
}

interface Role {
    id: number;
    name: string;
    slug: string;
}

export default function UsersIndex() {
    const [users, setUsers] = useState<User[]>([]);
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
    const [isSkillsDialogOpen, setIsSkillsDialogOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    
    const [availableSkills, setAvailableSkills] = useState<Skill[]>([]);
    const [userSkills, setUserSkills] = useState<UserSkill[]>([]);
    const [availableRoles, setAvailableRoles] = useState<Role[]>([]);

    const [formData, setFormData] = useState({
        name: '',
        email: '',
        role_ids: [] as number[],
    });

    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});

    const fetchUsers = useCallback(async (page = 1, searchQuery = '') => {
        setLoading(true);
        try {
            const response = await axios.get('/admin/api/users', {
                params: {
                    page,
                    per_page: 50,
                    search: searchQuery || undefined,
                },
            });

            setUsers(response.data.data);
            setPagination(response.data.meta.pagination);
        } catch (error) {
            console.error('Failed to fetch users:', error);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUsers(1, search);
        fetchAvailableSkills();
        fetchAvailableRoles();
    }, []);

    const fetchAvailableSkills = async () => {
        try {
            const response = await axios.get('/admin/api/skills/all');
            setAvailableSkills(response.data.data);
        } catch (error) {
            console.error('Failed to fetch skills:', error);
        }
    };

    const fetchAvailableRoles = async () => {
        try {
            const response = await axios.get('/admin/api/roles/all');
            setAvailableRoles(response.data.data);
        } catch (error) {
            console.error('Failed to fetch roles:', error);
        }
    };

    const handleSearch = () => {
        fetchUsers(1, search);
    };

    const handleSearchKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleSearch();
        }
    };

    const handleCreateUser = async () => {
        setFormErrors({});
        try {
            await axios.post('/admin/api/users', formData);
            setIsCreateDialogOpen(false);
            setFormData({ name: '', email: '', role_ids: [] });
            fetchUsers(pagination.current_page, search);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
            }
        }
    };

    const handleEditUser = async () => {
        if (!selectedUser) return;

        setFormErrors({});
        try {
            await axios.put(`/admin/api/users/${selectedUser.id}`, formData);
            setIsEditDialogOpen(false);
            setSelectedUser(null);
            setFormData({ name: '', email: '', role_ids: [] });
            fetchUsers(pagination.current_page, search);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setFormErrors(error.response.data.errors);
            }
        }
    };

    const handleDeleteUser = async () => {
        if (!selectedUser) return;

        try {
            await axios.delete(`/admin/api/users/${selectedUser.id}`);
            setIsDeleteDialogOpen(false);
            setSelectedUser(null);
            fetchUsers(pagination.current_page, search);
        } catch (error) {
            console.error('Failed to delete user:', error);
        }
    };

    const handleRoleToggle = (roleId: number, checked: boolean) => {
        if (checked) {
            setFormData({ ...formData, role_ids: [...formData.role_ids, roleId] });
        } else {
            setFormData({ ...formData, role_ids: formData.role_ids.filter(id => id !== roleId) });
        }
    };

    const openCreateDialog = () => {
        setFormData({ name: '', email: '', role_ids: [] });
        setFormErrors({});
        setIsCreateDialogOpen(true);
    };

    const openEditDialog = (user: User) => {
        setSelectedUser(user);
        setFormData({
            name: user.name,
            email: user.email,
            role_ids: user.roles?.map(r => r.id) || [],
        });
        setFormErrors({});
        setIsEditDialogOpen(true);
    };

    const openDeleteDialog = (user: User) => {
        setSelectedUser(user);
        setIsDeleteDialogOpen(true);
    };

    const openSkillsDialog = (user: User) => {
        setSelectedUser(user);
        // Map user's existing skills to the format needed
        const existingSkills = user.skills?.map(skill => ({
            skill_id: skill.id,
            level: skill.level || 1,
        })) || [];
        setUserSkills(existingSkills);
        setIsSkillsDialogOpen(true);
    };

    const handleAddSkill = () => {
        if (availableSkills.length > 0) {
            // Find first skill not already assigned
            const assignedSkillIds = userSkills.map(us => us.skill_id);
            const firstUnassigned = availableSkills.find(s => !assignedSkillIds.includes(s.id));
            if (firstUnassigned) {
                setUserSkills([...userSkills, { skill_id: firstUnassigned.id, level: 1 }]);
            }
        }
    };

    const handleRemoveSkill = (index: number) => {
        setUserSkills(userSkills.filter((_, i) => i !== index));
    };

    const handleSkillChange = (index: number, skillId: number) => {
        const updated = [...userSkills];
        updated[index].skill_id = skillId;
        setUserSkills(updated);
    };

    const handleLevelChange = (index: number, level: number) => {
        const updated = [...userSkills];
        updated[index].level = level;
        setUserSkills(updated);
    };

    const handleSaveSkills = async () => {
        if (!selectedUser) return;

        try {
            await axios.post(`/admin/api/users/${selectedUser.id}/skills`, {
                skills: userSkills,
            });
            setIsSkillsDialogOpen(false);
            setSelectedUser(null);
            setUserSkills([]);
            fetchUsers(pagination.current_page, search);
        } catch (error) {
            console.error('Failed to save skills:', error);
        }
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Users Management" />

            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Users</h1>
                        <p className="text-muted-foreground mt-2">Manage user accounts and access</p>
                    </div>
                    <Button onClick={openCreateDialog}>
                        <UserPlus className="mr-2 h-4 w-4" />
                        Add User
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Users</CardTitle>
                        <CardDescription>
                            {pagination.total} total user{pagination.total !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-2 mb-4">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Search by name or email..."
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
                            <div className="text-center py-8 text-muted-foreground">Loading users...</div>
                        ) : users.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">No users found</div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Roles</TableHead>
                                        <TableHead>Skills</TableHead>
                                        <TableHead>Hourly Rate</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">{user.name}</TableCell>
                                            <TableCell>{user.email}</TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {user.roles && user.roles.length > 0 ? (
                                                        user.roles.map((role) => (
                                                            <Badge key={role.id} variant="secondary">
                                                                {role.name}
                                                            </Badge>
                                                        ))
                                                    ) : (
                                                        <span className="text-muted-foreground text-sm">No roles</span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap gap-1">
                                                    {user.skills && user.skills.length > 0 ? (
                                                        user.skills.slice(0, 3).map((skill) => (
                                                            <Badge key={skill.id} variant="outline">
                                                                {skill.name}
                                                            </Badge>
                                                        ))
                                                    ) : (
                                                        <span className="text-muted-foreground text-sm">No skills</span>
                                                    )}
                                                    {user.skills && user.skills.length > 3 && (
                                                        <Badge variant="outline">+{user.skills.length - 3}</Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {user.total_hourly_rate !== undefined ? (
                                                    <span className="font-medium">${user.total_hourly_rate.toFixed(2)}/hr</span>
                                                ) : (
                                                    <span className="text-muted-foreground text-sm">N/A</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Link href={`/admin/users/${user.id}`}>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            title="View Profile"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openSkillsDialog(user)}
                                                        title="Manage Skills"
                                                    >
                                                        <Award className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openEditDialog(user)}
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openDeleteDialog(user)}
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
                                        onClick={() => fetchUsers(pagination.current_page - 1, search)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={pagination.current_page === pagination.last_page}
                                        onClick={() => fetchUsers(pagination.current_page + 1, search)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create User Dialog */}
            <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Create New User</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="create-name">Name</Label>
                            <Input
                                id="create-name"
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                placeholder="Enter user name"
                            />
                            {formErrors.name && (
                                <p className="text-sm text-destructive">{formErrors.name[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="create-email">Email</Label>
                            <Input
                                id="create-email"
                                type="email"
                                value={formData.email}
                                onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                placeholder="Enter email address"
                            />
                            {formErrors.email && (
                                <p className="text-sm text-destructive">{formErrors.email[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label>Roles</Label>
                            <div className="border rounded-md p-3 space-y-2 max-h-40 overflow-y-auto">
                                {availableRoles.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No roles available</p>
                                ) : (
                                    availableRoles.map((role) => (
                                        <div key={role.id} className="flex items-center space-x-2">
                                            <Checkbox
                                                id={`create-role-${role.id}`}
                                                checked={formData.role_ids.includes(role.id)}
                                                onCheckedChange={(checked) =>
                                                    handleRoleToggle(role.id, checked as boolean)
                                                }
                                            />
                                            <label
                                                htmlFor={`create-role-${role.id}`}
                                                className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                            >
                                                {role.name}
                                            </label>
                                        </div>
                                    ))
                                )}
                            </div>
                            {formErrors.role_ids && (
                                <p className="text-sm text-destructive">{formErrors.role_ids[0]}</p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleCreateUser}>Create User</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit User Dialog */}
            <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Edit User</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="edit-name">Name</Label>
                            <Input
                                id="edit-name"
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                placeholder="Enter user name"
                            />
                            {formErrors.name && (
                                <p className="text-sm text-destructive">{formErrors.name[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="edit-email">Email</Label>
                            <Input
                                id="edit-email"
                                type="email"
                                value={formData.email}
                                onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                                placeholder="Enter email address"
                            />
                            {formErrors.email && (
                                <p className="text-sm text-destructive">{formErrors.email[0]}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label>Roles</Label>
                            <div className="border rounded-md p-3 space-y-2 max-h-40 overflow-y-auto">
                                {availableRoles.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No roles available</p>
                                ) : (
                                    availableRoles.map((role) => (
                                        <div key={role.id} className="flex items-center space-x-2">
                                            <Checkbox
                                                id={`edit-role-${role.id}`}
                                                checked={formData.role_ids.includes(role.id)}
                                                onCheckedChange={(checked) =>
                                                    handleRoleToggle(role.id, checked as boolean)
                                                }
                                            />
                                            <label
                                                htmlFor={`edit-role-${role.id}`}
                                                className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                            >
                                                {role.name}
                                            </label>
                                        </div>
                                    ))
                                )}
                            </div>
                            {formErrors.role_ids && (
                                <p className="text-sm text-destructive">{formErrors.role_ids[0]}</p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleEditUser}>Save Changes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete User Dialog */}
            <Dialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete User</DialogTitle>
                    </DialogHeader>
                    <div className="py-4">
                        <p className="text-sm text-muted-foreground">
                            Are you sure you want to delete <strong>{selectedUser?.name}</strong>? This action cannot be
                            undone.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsDeleteDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDeleteUser}>
                            Delete User
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Manage Skills Dialog */}
            <Dialog open={isSkillsDialogOpen} onOpenChange={setIsSkillsDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Manage Skills - {selectedUser?.name}</DialogTitle>
                    </DialogHeader>
                    <div className="py-4">
                        <div className="space-y-4">
                            {userSkills.length === 0 ? (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    No skills assigned. Click "Add Skill" to get started.
                                </p>
                            ) : (
                                userSkills.map((userSkill, index) => (
                                    <div key={index} className="flex items-center gap-3">
                                        <div className="flex-1">
                                            <Label htmlFor={`skill-${index}`} className="text-xs mb-1">
                                                Skill
                                            </Label>
                                            <Select
                                                value={userSkill.skill_id.toString()}
                                                onValueChange={(value) => handleSkillChange(index, parseInt(value))}
                                            >
                                                <SelectTrigger id={`skill-${index}`}>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableSkills.map((skill) => (
                                                        <SelectItem key={skill.id} value={skill.id.toString()}>
                                                            {skill.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="w-32">
                                            <Label htmlFor={`level-${index}`} className="text-xs mb-1">
                                                Level (1-5)
                                            </Label>
                                            <Select
                                                value={userSkill.level.toString()}
                                                onValueChange={(value) => handleLevelChange(index, parseInt(value))}
                                            >
                                                <SelectTrigger id={`level-${index}`}>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {[1, 2, 3, 4, 5].map((level) => (
                                                        <SelectItem key={level} value={level.toString()}>
                                                            Level {level}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleRemoveSkill(index)}
                                            className="mt-5"
                                        >
                                            <Trash2 className="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                ))
                            )}
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleAddSkill}
                                className="w-full"
                                disabled={userSkills.length >= availableSkills.length}
                            >
                                <Plus className="h-4 w-4 mr-2" />
                                Add Skill
                            </Button>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsSkillsDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleSaveSkills}>Save Skills</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
