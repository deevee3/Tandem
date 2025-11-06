import { Head, usePage } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { BarChart3, FileText, Key, Shield, Sparkles, Users, Webhook } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { useMemo } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Admin',
        href: '/admin',
    },
];

const allAdminSections = [
    {
        title: 'Users & Roles',
        description: 'Manage users, roles, and permissions',
        icon: Users,
        href: '/admin/users',
        color: 'text-blue-600 dark:text-blue-400',
    },
    {
        title: 'Skills',
        description: 'Configure skills and user skill levels',
        icon: Sparkles,
        href: '/admin/skills',
        color: 'text-purple-600 dark:text-purple-400',
    },
    {
        title: 'Handoff Policies',
        description: 'Set up handoff rules and thresholds',
        icon: Shield,
        href: '/admin/handoff-policies',
        color: 'text-green-600 dark:text-green-400',
    },
    {
        title: 'API Keys',
        description: 'Manage API keys and access tokens',
        icon: Key,
        href: '/admin/api-keys',
        color: 'text-yellow-600 dark:text-yellow-400',
        requiresPermission: 'api-keys.manage',
    },
    {
        title: 'Webhooks',
        description: 'Configure webhook endpoints and events',
        icon: Webhook,
        href: '/admin/webhooks',
        color: 'text-orange-600 dark:text-orange-400',
    },
    {
        title: 'Analytics',
        description: 'View metrics and performance reports',
        icon: BarChart3,
        href: '/admin/analytics',
        color: 'text-indigo-600 dark:text-indigo-400',
    },
    {
        title: 'Audit Logs',
        description: 'Review system audit trails and exports',
        icon: FileText,
        href: '/admin/audit-logs',
        color: 'text-gray-600 dark:text-gray-400',
    },
];

export default function AdminDashboard() {
    const page = usePage<{ auth: { permissions?: string[] | null } }>();
    const permissions = page.props.auth.permissions ?? [];

    const adminSections = useMemo(() => {
        return allAdminSections.filter(section => {
            if (section.requiresPermission) {
                return permissions.includes(section.requiresPermission);
            }
            return true;
        });
    }, [permissions]);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Administration</h1>
                    <p className="text-muted-foreground mt-2">
                        Manage system configuration, users, and settings
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {adminSections.map((section) => (
                        <Card key={section.title} className="hover:shadow-md transition-shadow">
                            <CardHeader>
                                <div className="flex items-center gap-4">
                                    <div className={`p-2 rounded-lg bg-muted ${section.color}`}>
                                        <section.icon className="h-6 w-6" />
                                    </div>
                                    <div className="flex-1">
                                        <CardTitle className="text-lg">{section.title}</CardTitle>
                                    </div>
                                </div>
                                <CardDescription>{section.description}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button variant="outline" size="sm" asChild className="w-full">
                                    <Link href={section.href}>
                                        Manage
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
