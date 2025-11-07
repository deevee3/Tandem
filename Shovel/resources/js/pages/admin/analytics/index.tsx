import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import {
    Activity,
    BarChart3,
    Clock,
    MessageSquare,
    TrendingDown,
    TrendingUp,
    Users,
    CheckCircle2,
    AlertCircle,
    RefreshCcw,
    Calendar,
} from 'lucide-react';

import AdminLayout from '@/layouts/admin-layout';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type AnalyticsData = {
    overview: {
        total_conversations: number;
        resolved_conversations: number;
        resolution_rate: number;
        handoff_conversations: number;
        handoff_rate: number;
        total_messages: number;
        ai_messages: number;
        human_messages: number;
        automation_rate: number;
        avg_first_response_seconds: number | null;
        avg_first_response_minutes: number | null;
        avg_resolution_seconds: number | null;
        avg_resolution_hours: number | null;
    };
    conversations: {
        by_status: Record<string, number>;
        by_priority: Record<string, number>;
    };
    performance: {
        sla_first_response: {
            total: number;
            met: number;
            compliance_rate: number;
        };
        sla_resolution: {
            total: number;
            met: number;
            compliance_rate: number;
        };
    };
    queues: Array<{
        id: number;
        name: string;
        total_conversations: number;
        resolved_count: number;
        resolution_rate: number;
        avg_first_response_minutes: number | null;
    }>;
    agents: Array<{
        id: number;
        name: string;
        email: string;
        total_assigned: number;
        resolved_count: number;
        resolution_rate: number;
        avg_accept_minutes: number | null;
    }>;
    trends: Array<{
        date: string;
        total_conversations: number;
        resolved_count: number;
        resolution_rate: number;
        avg_first_response_minutes: number | null;
    }>;
};

type AnalyticsResponse = {
    data: AnalyticsData;
    meta: {
        start_date: string;
        end_date: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Admin', href: '/admin' },
    { title: 'Analytics', href: '/admin/analytics' },
];

export default function AdminAnalyticsIndex() {
    const [data, setData] = useState<AnalyticsData | null>(null);
    const [loading, setLoading] = useState(true);
    const [dateRange, setDateRange] = useState({
        start_date: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        end_date: new Date().toISOString().split('T')[0],
    });

    const fetchAnalytics = useCallback(async () => {
        setLoading(true);
        try {
            const response = await axios.get<AnalyticsResponse>(admin.api.analytics.index().url, {
                params: dateRange,
            });
            setData(response.data.data);
        } catch (error) {
            console.error('Failed to load analytics', error);
        } finally {
            setLoading(false);
        }
    }, [dateRange]);

    useEffect(() => {
        fetchAnalytics();
    }, [fetchAnalytics]);

    const formatNumber = (num: number | null | undefined): string => {
        if (num === null || num === undefined) return '—';
        return new Intl.NumberFormat().format(num);
    };

    const formatTime = (minutes: number | null | undefined): string => {
        if (!minutes) return '—';
        if (minutes < 60) return `${Math.round(minutes)}m`;
        const hours = Math.floor(minutes / 60);
        const mins = Math.round(minutes % 60);
        return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
    };

    const formatTimeHours = (hours: number | null | undefined): string => {
        if (!hours) return '—';
        if (hours < 1) return `${Math.round(hours * 60)}m`;
        return `${hours.toFixed(1)}h`;
    };

    const getStatusColor = (status: string): string => {
        const colors: Record<string, string> = {
            new: 'bg-blue-100 text-blue-800',
            working: 'bg-yellow-100 text-yellow-800',
            human_working: 'bg-purple-100 text-purple-800',
            resolved: 'bg-green-100 text-green-800',
            pending: 'bg-gray-100 text-gray-800',
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    const getPriorityColor = (priority: string): string => {
        const colors: Record<string, string> = {
            low: 'bg-gray-100 text-gray-800',
            normal: 'bg-blue-100 text-blue-800',
            high: 'bg-orange-100 text-orange-800',
            urgent: 'bg-red-100 text-red-800',
        };
        return colors[priority] || 'bg-gray-100 text-gray-800';
    };

    if (loading || !data) {
        return (
            <AdminLayout breadcrumbs={breadcrumbs}>
                <Head title="Analytics" />
                <div className="flex items-center justify-center py-12">
                    <RefreshCcw className="h-8 w-8 animate-spin text-muted-foreground" />
                </div>
            </AdminLayout>
        );
    }

    const { overview, conversations, performance, queues, agents, trends } = data;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Analytics" />

            <div className="flex flex-col gap-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Analytics</h1>
                        <p className="mt-2 text-muted-foreground">
                            Performance insights and key metrics for your support operations
                        </p>
                    </div>
                    <Button onClick={fetchAnalytics} variant="outline" className="gap-2">
                        <RefreshCcw className="h-4 w-4" />
                        Refresh
                    </Button>
                </div>

                {/* Date Range Info */}
                <Alert>
                    <Calendar className="h-4 w-4" />
                    <AlertTitle>Date Range</AlertTitle>
                    <AlertDescription>
                        Showing data from {dateRange.start_date} to {dateRange.end_date} (Last 30 days)
                    </AlertDescription>
                </Alert>

                {/* Overview Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Conversations</CardTitle>
                            <MessageSquare className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatNumber(overview.total_conversations)}</div>
                            <p className="text-xs text-muted-foreground">
                                {formatNumber(overview.resolved_conversations)} resolved ({overview.resolution_rate}%)
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Handoff Rate</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{overview.handoff_rate}%</div>
                            <p className="text-xs text-muted-foreground">
                                {formatNumber(overview.handoff_conversations)} conversations
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">AI Automation</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{overview.automation_rate}%</div>
                            <p className="text-xs text-muted-foreground">
                                {formatNumber(overview.ai_messages)} of {formatNumber(overview.total_messages)} messages
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Avg First Response</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatTime(overview.avg_first_response_minutes)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Resolution: {formatTimeHours(overview.avg_resolution_hours)}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* SLA Performance */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>SLA Compliance - First Response</CardTitle>
                            <CardDescription>Meeting first response time targets</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-4">
                                <div className="flex-1">
                                    <div className="text-3xl font-bold">
                                        {performance.sla_first_response.compliance_rate}%
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {formatNumber(performance.sla_first_response.met)} of{' '}
                                        {formatNumber(performance.sla_first_response.total)} met
                                    </p>
                                </div>
                                {performance.sla_first_response.compliance_rate >= 90 ? (
                                    <CheckCircle2 className="h-12 w-12 text-green-500" />
                                ) : (
                                    <AlertCircle className="h-12 w-12 text-orange-500" />
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>SLA Compliance - Resolution</CardTitle>
                            <CardDescription>Meeting resolution time targets</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-4">
                                <div className="flex-1">
                                    <div className="text-3xl font-bold">
                                        {performance.sla_resolution.compliance_rate}%
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {formatNumber(performance.sla_resolution.met)} of{' '}
                                        {formatNumber(performance.sla_resolution.total)} met
                                    </p>
                                </div>
                                {performance.sla_resolution.compliance_rate >= 90 ? (
                                    <CheckCircle2 className="h-12 w-12 text-green-500" />
                                ) : (
                                    <AlertCircle className="h-12 w-12 text-orange-500" />
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Conversation Breakdowns */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>By Status</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {Object.entries(conversations.by_status).map(([status, count]) => (
                                    <div key={status} className="flex items-center justify-between">
                                        <Badge className={getStatusColor(status)}>{status}</Badge>
                                        <span className="font-medium">{formatNumber(count as number)}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>By Priority</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {Object.entries(conversations.by_priority).map(([priority, count]) => (
                                    <div key={priority} className="flex items-center justify-between">
                                        <Badge className={getPriorityColor(priority)}>{priority}</Badge>
                                        <span className="font-medium">{formatNumber(count as number)}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Queue Performance */}
                <Card>
                    <CardHeader>
                        <CardTitle>Queue Performance</CardTitle>
                        <CardDescription>Metrics by support queue</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {queues.length === 0 ? (
                            <p className="text-center text-muted-foreground py-8">No queue data available</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Queue</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                        <TableHead className="text-right">Resolved</TableHead>
                                        <TableHead className="text-right">Resolution Rate</TableHead>
                                        <TableHead className="text-right">Avg First Response</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {queues.map((queue) => (
                                        <TableRow key={queue.id}>
                                            <TableCell className="font-medium">{queue.name}</TableCell>
                                            <TableCell className="text-right">
                                                {formatNumber(queue.total_conversations)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatNumber(queue.resolved_count)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Badge variant={queue.resolution_rate >= 80 ? 'default' : 'outline'}>
                                                    {queue.resolution_rate}%
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatTime(queue.avg_first_response_minutes)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Agent Performance */}
                <Card>
                    <CardHeader>
                        <CardTitle>Agent Performance</CardTitle>
                        <CardDescription>Top performing agents</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {agents.length === 0 ? (
                            <p className="text-center text-muted-foreground py-8">No agent data available</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Agent</TableHead>
                                        <TableHead className="text-right">Assigned</TableHead>
                                        <TableHead className="text-right">Resolved</TableHead>
                                        <TableHead className="text-right">Resolution Rate</TableHead>
                                        <TableHead className="text-right">Avg Accept Time</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {agents.slice(0, 10).map((agent) => (
                                        <TableRow key={agent.id}>
                                            <TableCell>
                                                <div>
                                                    <div className="font-medium">{agent.name}</div>
                                                    <div className="text-sm text-muted-foreground">{agent.email}</div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatNumber(agent.total_assigned)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatNumber(agent.resolved_count)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Badge variant={agent.resolution_rate >= 80 ? 'default' : 'outline'}>
                                                    {agent.resolution_rate}%
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatTime(agent.avg_accept_minutes)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Trend Chart (Simple Table for now) */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daily Trends</CardTitle>
                        <CardDescription>Conversation volume and resolution over time</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {trends.length === 0 ? (
                            <p className="text-center text-muted-foreground py-8">No trend data available</p>
                        ) : (
                            <div className="space-y-2 max-h-96 overflow-y-auto">
                                {trends.slice(-14).reverse().map((day) => (
                                    <div
                                        key={day.date}
                                        className="flex items-center justify-between rounded-lg border p-3"
                                    >
                                        <div className="flex-1">
                                            <div className="font-medium">{day.date}</div>
                                            <div className="text-sm text-muted-foreground">
                                                {formatNumber(day.total_conversations)} conversations
                                            </div>
                                        </div>
                                        <div className="flex gap-4 text-right">
                                            <div>
                                                <div className="text-sm font-medium">
                                                    {formatNumber(day.resolved_count)} resolved
                                                </div>
                                                <Badge variant="outline">{day.resolution_rate}%</Badge>
                                            </div>
                                            <div>
                                                <div className="text-sm text-muted-foreground">Avg Response</div>
                                                <div className="text-sm font-medium">
                                                    {formatTime(day.avg_first_response_minutes)}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
