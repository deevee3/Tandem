import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowLeft, Mail, Calendar, Shield, Award, Upload, Copy, Check, DollarSign, Clock, CheckCircle } from 'lucide-react';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import axios from 'axios';

interface User {
    id: number;
    name: string;
    email: string;
    username?: string;
    avatar?: string;
    email_verified_at?: string;
    created_at: string;
    updated_at: string;
    roles?: Array<{
        id: number;
        code?: string;
        name: string;
        slug: string;
        hourly_rate?: number;
    }>;
    skills?: Array<{
        id: number;
        code?: string;
        name: string;
        slug: string;
        level?: number;
        hourly_rate?: number;
    }>;
}

interface ResourceMetrics {
    total_hourly_rate: number;
    role_hourly_rate: number;
    skill_hourly_rate: number;
    total_assignments: number;
    active_assignments: number;
    resolved_assignments: number;
    avg_resolution_time_minutes: number | null;
}

interface Props {
    user: User;
}

export default function UserShow({ user: initialUser }: Props) {
    const [user, setUser] = useState<User>(initialUser);
    const [isAvatarDialogOpen, setIsAvatarDialogOpen] = useState(false);
    const [avatarUrl, setAvatarUrl] = useState('');
    const [copied, setCopied] = useState(false);
    const [resourceMetrics, setResourceMetrics] = useState<ResourceMetrics | null>(null);
    const [loadingMetrics, setLoadingMetrics] = useState(true);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard().url,
        },
        {
            title: 'Admin',
            href: admin.dashboard().url,
        },
        {
            title: 'Users',
            href: admin.users.index().url,
        },
        {
            title: user.name,
            href: `/admin/users/${user.id}`,
        },
    ];

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const handleUpdateAvatar = async () => {
        try {
            const response = await axios.post(`/admin/api/users/${user.id}/avatar`, {
                avatar: avatarUrl,
            });
            setUser(response.data.data);
            setIsAvatarDialogOpen(false);
            setAvatarUrl('');
        } catch (error) {
            console.error('Failed to update avatar:', error);
        }
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    useEffect(() => {
        const fetchResourceMetrics = async () => {
            try {
                const response = await axios.get(`/admin/api/users/${user.id}/resource-management`);
                setResourceMetrics(response.data.data.resource_metrics);
            } catch (error) {
                console.error('Failed to fetch resource metrics:', error);
            } finally {
                setLoadingMetrics(false);
            }
        };

        fetchResourceMetrics();
    }, [user.id]);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={`${user.name} - User Profile`} />

            <div className="flex flex-col gap-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href={admin.users.index().url}>
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">User Profile</h1>
                            <p className="text-muted-foreground mt-2">View and manage user details</p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    {/* Profile Card */}
                    <Card className="md:col-span-1">
                        <CardHeader>
                            <CardTitle>Profile</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center gap-4">
                            <div className="relative group">
                                <Avatar className="h-32 w-32">
                                    <AvatarImage src={user.avatar} alt={user.name} />
                                    <AvatarFallback className="text-2xl">{getInitials(user.name)}</AvatarFallback>
                                </Avatar>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    className="absolute bottom-0 right-0 rounded-full h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity"
                                    onClick={() => setIsAvatarDialogOpen(true)}
                                >
                                    <Upload className="h-4 w-4" />
                                </Button>
                            </div>
                            <div className="text-center space-y-2 w-full">
                                <h2 className="text-2xl font-bold">{user.name}</h2>
                                <div className="flex items-center justify-center gap-2 text-muted-foreground">
                                    <Mail className="h-4 w-4" />
                                    <span className="text-sm">{user.email}</span>
                                </div>
                                <div className="flex items-center justify-center gap-2 pt-2">
                                    <span className="text-xs text-muted-foreground">Username:</span>
                                    <code className="text-xs bg-muted px-2 py-1 rounded font-mono">
                                        {user.username || `USR${String(user.id).padStart(12, '0')}`}
                                    </code>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 w-6 p-0"
                                        onClick={() => copyToClipboard(user.username || `USR${String(user.id).padStart(12, '0')}`)}
                                    >
                                        {copied ? <Check className="h-3 w-3" /> : <Copy className="h-3 w-3" />}
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Details Card */}
                    <Card className="md:col-span-2">
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                            <CardDescription>User information and metadata</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Roles Section */}
                            <div>
                                <div className="flex items-center gap-2 mb-3">
                                    <Shield className="h-4 w-4 text-muted-foreground" />
                                    <h3 className="font-semibold">Roles</h3>
                                </div>
                                <div className="space-y-2">
                                    {user.roles && user.roles.length > 0 ? (
                                        user.roles.map((role) => (
                                            <div
                                                key={role.id}
                                                className="flex items-center justify-between p-2 rounded-md bg-muted/50"
                                            >
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-medium">{role.name}</span>
                                                    {role.code && (
                                                        <code className="text-xs text-muted-foreground font-mono">
                                                            {role.code}
                                                        </code>
                                                    )}
                                                </div>
                                                {role.hourly_rate && (
                                                    <span className="text-sm text-muted-foreground">
                                                        ${role.hourly_rate.toFixed(2)}/hr
                                                    </span>
                                                )}
                                            </div>
                                        ))
                                    ) : (
                                        <span className="text-sm text-muted-foreground">No roles assigned</span>
                                    )}
                                </div>
                            </div>

                            {/* Skills Section */}
                            <div>
                                <div className="flex items-center gap-2 mb-3">
                                    <Award className="h-4 w-4 text-muted-foreground" />
                                    <h3 className="font-semibold">Skills</h3>
                                </div>
                                <div className="space-y-2">
                                    {user.skills && user.skills.length > 0 ? (
                                        user.skills.map((skill) => (
                                            <div
                                                key={skill.id}
                                                className="flex items-center justify-between p-2 rounded-md bg-muted/50"
                                            >
                                                <div className="flex flex-col flex-1">
                                                    <span className="text-sm font-medium">{skill.name}</span>
                                                    {skill.code && (
                                                        <code className="text-xs text-muted-foreground font-mono">
                                                            {skill.code}
                                                        </code>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    {skill.hourly_rate && (
                                                        <span className="text-xs text-muted-foreground">
                                                            ${skill.hourly_rate.toFixed(2)}/hr
                                                        </span>
                                                    )}
                                                    <div className="flex items-center gap-1">
                                                        {[1, 2, 3, 4, 5].map((level) => (
                                                            <div
                                                                key={level}
                                                                className={`h-2 w-2 rounded-full ${
                                                                    level <= (skill.level || 0)
                                                                        ? 'bg-primary'
                                                                        : 'bg-muted-foreground/20'
                                                                }`}
                                                            />
                                                        ))}
                                                        <span className="text-xs text-muted-foreground ml-2">
                                                            Level {skill.level}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <span className="text-sm text-muted-foreground">No skills assigned</span>
                                    )}
                                </div>
                            </div>

                            {/* Account Information */}
                            <div>
                                <div className="flex items-center gap-2 mb-3">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <h3 className="font-semibold">Account Information</h3>
                                </div>
                                <div className="grid gap-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Created:</span>
                                        <span>{formatDate(user.created_at)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Last Updated:</span>
                                        <span>{formatDate(user.updated_at)}</span>
                                    </div>
                                    {user.email_verified_at && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Email Verified:</span>
                                            <span>{formatDate(user.email_verified_at)}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Resource Management Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Resource Management</CardTitle>
                        <CardDescription>Hourly rates and assignment statistics</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loadingMetrics ? (
                            <div className="text-center py-8 text-muted-foreground">Loading metrics...</div>
                        ) : resourceMetrics ? (
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                                {/* Total Hourly Rate */}
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <DollarSign className="h-4 w-4" />
                                        <span className="text-sm font-medium">Total Hourly Rate</span>
                                    </div>
                                    <div className="text-2xl font-bold">
                                        ${resourceMetrics.total_hourly_rate.toFixed(2)}
                                    </div>
                                    <div className="text-xs text-muted-foreground space-y-1">
                                        <div>Roles: ${resourceMetrics.role_hourly_rate.toFixed(2)}</div>
                                        <div>Skills: ${resourceMetrics.skill_hourly_rate.toFixed(2)}</div>
                                    </div>
                                </div>

                                {/* Total Assignments */}
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <CheckCircle className="h-4 w-4" />
                                        <span className="text-sm font-medium">Total Assignments</span>
                                    </div>
                                    <div className="text-2xl font-bold">{resourceMetrics.total_assignments}</div>
                                    <div className="text-xs text-muted-foreground space-y-1">
                                        <div>Active: {resourceMetrics.active_assignments}</div>
                                        <div>Resolved: {resourceMetrics.resolved_assignments}</div>
                                    </div>
                                </div>

                                {/* Average Resolution Time */}
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Clock className="h-4 w-4" />
                                        <span className="text-sm font-medium">Avg Resolution Time</span>
                                    </div>
                                    <div className="text-2xl font-bold">
                                        {resourceMetrics.avg_resolution_time_minutes
                                            ? `${resourceMetrics.avg_resolution_time_minutes.toFixed(0)}m`
                                            : 'N/A'}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {resourceMetrics.avg_resolution_time_minutes
                                            ? `${(resourceMetrics.avg_resolution_time_minutes / 60).toFixed(1)} hours`
                                            : 'No resolved assignments'}
                                    </div>
                                </div>

                                {/* Resolution Rate */}
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <Award className="h-4 w-4" />
                                        <span className="text-sm font-medium">Resolution Rate</span>
                                    </div>
                                    <div className="text-2xl font-bold">
                                        {resourceMetrics.total_assignments > 0
                                            ? `${((resourceMetrics.resolved_assignments / resourceMetrics.total_assignments) * 100).toFixed(0)}%`
                                            : 'N/A'}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {resourceMetrics.resolved_assignments} of {resourceMetrics.total_assignments} resolved
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-8 text-muted-foreground">
                                Failed to load resource metrics
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Update Avatar Dialog */}
            <Dialog open={isAvatarDialogOpen} onOpenChange={setIsAvatarDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Update Avatar</DialogTitle>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="avatar-url">Avatar URL</Label>
                            <Input
                                id="avatar-url"
                                value={avatarUrl}
                                onChange={(e) => setAvatarUrl(e.target.value)}
                                placeholder="https://example.com/avatar.jpg"
                            />
                            <p className="text-xs text-muted-foreground">
                                Enter a URL to an image to use as the user's avatar
                            </p>
                        </div>
                        {avatarUrl && (
                            <div className="flex justify-center">
                                <Avatar className="h-24 w-24">
                                    <AvatarImage src={avatarUrl} alt="Preview" />
                                    <AvatarFallback>{getInitials(user.name)}</AvatarFallback>
                                </Avatar>
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsAvatarDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleUpdateAvatar} disabled={!avatarUrl}>
                            Update Avatar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
