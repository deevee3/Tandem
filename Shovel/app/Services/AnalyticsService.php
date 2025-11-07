<?php

namespace App\Services;

use App\Models\AnalyticsDailyMetric;
use App\Models\Queue;
use App\Models\User;
use App\Support\Authorization\Roles;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public const METRIC_HANDOFF_RATE = 'handoff_rate';
    public const METRIC_TIME_TO_FIRST_RESPONSE = 'time_to_first_response';
    public const METRIC_TIME_TO_RESOLUTION = 'time_to_resolution';
    public const METRIC_SLA_COMPLIANCE = 'sla_compliance';
    public const METRIC_COST_ESTIMATE = 'cost_estimate';
    public const METRIC_REOPEN_RATE = 'reopen_rate';

    private const CACHE_PREFIX = 'analytics.metrics.v1';
    private const CACHE_TTL_MINUTES = 15;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ConnectionInterface $connection,
    ) {
    }

    /**
     * Get dashboard overview metrics
     */
    public function getDashboardMetrics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $cacheKey = sprintf(
            '%s.dashboard.%s.%s',
            self::CACHE_PREFIX,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        return $this->cache->remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($startDate, $endDate) {
            return [
                'overview' => $this->getOverviewMetrics($startDate, $endDate),
                'conversations' => $this->getConversationMetrics($startDate, $endDate),
                'performance' => $this->getPerformanceMetrics($startDate, $endDate),
                'queues' => $this->getQueueMetrics($startDate, $endDate),
                'agents' => $this->getAgentMetrics($startDate, $endDate),
                'trends' => $this->getTrendData($startDate, $endDate),
            ];
        });
    }

    /**
     * Get high-level overview metrics
     */
    private function getOverviewMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $totalConversations = DB::table('conversations')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $resolvedConversations = DB::table('conversations')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'resolved')
            ->count();

        $handoffConversations = DB::table('audit_events')
            ->where('event_type', 'conversation.human_working')
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->distinct('conversation_id')
            ->count('conversation_id');

        $totalMessages = DB::table('messages')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $aiMessages = DB::table('messages')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('role', 'assistant')
            ->count();

        $humanMessages = DB::table('messages')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('role', 'user')
            ->count();

        $avgFirstResponse = DB::table('conversations')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('first_responded_at')
            ->selectRaw('AVG((julianday(first_responded_at) - julianday(created_at)) * 86400) as avg_seconds')
            ->value('avg_seconds');

        $avgResolution = DB::table('conversations')
            ->join('audit_events', function($join) {
                $join->on('conversations.id', '=', 'audit_events.conversation_id')
                     ->where('audit_events.event_type', '=', 'conversation.resolved');
            })
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->where('conversations.status', 'resolved')
            ->selectRaw('AVG((julianday(audit_events.occurred_at) - julianday(conversations.created_at)) * 86400) as avg_seconds')
            ->value('avg_seconds');

        return [
            'total_conversations' => (int) $totalConversations,
            'resolved_conversations' => (int) $resolvedConversations,
            'resolution_rate' => $totalConversations > 0 ? round(($resolvedConversations / $totalConversations) * 100, 2) : 0,
            'handoff_conversations' => (int) $handoffConversations,
            'handoff_rate' => $totalConversations > 0 ? round(($handoffConversations / $totalConversations) * 100, 2) : 0,
            'total_messages' => (int) $totalMessages,
            'ai_messages' => (int) $aiMessages,
            'human_messages' => (int) $humanMessages,
            'automation_rate' => $totalMessages > 0 ? round(($aiMessages / $totalMessages) * 100, 2) : 0,
            'avg_first_response_seconds' => $avgFirstResponse ? (int) round($avgFirstResponse) : null,
            'avg_first_response_minutes' => $avgFirstResponse ? round($avgFirstResponse / 60, 2) : null,
            'avg_resolution_seconds' => $avgResolution ? (int) round($avgResolution) : null,
            'avg_resolution_hours' => $avgResolution ? round($avgResolution / 3600, 2) : null,
        ];
    }

    /**
     * Get conversation breakdown metrics
     */
    private function getConversationMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $statusBreakdown = DB::table('conversations')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $priorityBreakdown = DB::table('conversations')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('priority', DB::raw('COUNT(*) as count'))
            ->groupBy('priority')
            ->get()
            ->pluck('count', 'priority')
            ->toArray();

        return [
            'by_status' => $statusBreakdown,
            'by_priority' => $priorityBreakdown,
        ];
    }

    /**
     * Get performance and SLA metrics
     */
    private function getPerformanceMetrics(Carbon $startDate, Carbon $endDate): array
    {
        // SLA compliance for first response
        $slaFirstResponse = DB::table('conversations')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('first_response_due_at')
            ->whereNotNull('first_responded_at')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN first_responded_at <= first_response_due_at THEN 1 ELSE 0 END) as met
            ')
            ->first();

        // SLA compliance for resolution
        $slaResolution = DB::table('conversations')
            ->join('audit_events', function($join) {
                $join->on('conversations.id', '=', 'audit_events.conversation_id')
                     ->where('audit_events.event_type', '=', 'conversation.resolved');
            })
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->whereNotNull('conversations.resolution_due_at')
            ->where('conversations.status', 'resolved')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN audit_events.occurred_at <= conversations.resolution_due_at THEN 1 ELSE 0 END) as met
            ')
            ->first();

        return [
            'sla_first_response' => [
                'total' => $slaFirstResponse->total ?? 0,
                'met' => $slaFirstResponse->met ?? 0,
                'compliance_rate' => $slaFirstResponse && $slaFirstResponse->total > 0 
                    ? round(($slaFirstResponse->met / $slaFirstResponse->total) * 100, 2) 
                    : 0,
            ],
            'sla_resolution' => [
                'total' => $slaResolution->total ?? 0,
                'met' => $slaResolution->met ?? 0,
                'compliance_rate' => $slaResolution && $slaResolution->total > 0 
                    ? round(($slaResolution->met / $slaResolution->total) * 100, 2) 
                    : 0,
            ],
        ];
    }

    /**
     * Get queue-specific metrics
     */
    private function getQueueMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $queueStats = DB::table('conversations')
            ->join('queue_items', 'conversations.id', '=', 'queue_items.conversation_id')
            ->join('queues', 'queue_items.queue_id', '=', 'queues.id')
            ->whereBetween('conversations.created_at', [$startDate, $endDate])
            ->select(
                'queues.id',
                'queues.name',
                DB::raw('COUNT(DISTINCT conversations.id) as total_conversations'),
                DB::raw('SUM(CASE WHEN conversations.status = "resolved" THEN 1 ELSE 0 END) as resolved_count'),
                DB::raw('AVG(CASE WHEN conversations.first_responded_at IS NOT NULL THEN (julianday(conversations.first_responded_at) - julianday(conversations.created_at)) * 86400 END) as avg_first_response_seconds')
            )
            ->groupBy('queues.id', 'queues.name')
            ->get()
            ->map(function ($queue) {
                return [
                    'id' => $queue->id,
                    'name' => $queue->name,
                    'total_conversations' => (int) $queue->total_conversations,
                    'resolved_count' => (int) $queue->resolved_count,
                    'resolution_rate' => $queue->total_conversations > 0 
                        ? round(($queue->resolved_count / $queue->total_conversations) * 100, 2) 
                        : 0,
                    'avg_first_response_minutes' => $queue->avg_first_response_seconds 
                        ? round($queue->avg_first_response_seconds / 60, 2) 
                        : null,
                ];
            })
            ->toArray();

        return $queueStats;
    }

    /**
     * Get agent performance metrics
     */
    private function getAgentMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $agentStats = DB::table('assignments')
            ->join('users', 'assignments.user_id', '=', 'users.id')
            ->join('conversations', 'assignments.conversation_id', '=', 'conversations.id')
            ->whereBetween('assignments.assigned_at', [$startDate, $endDate])
            ->select(
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(DISTINCT assignments.conversation_id) as total_assigned'),
                DB::raw('SUM(CASE WHEN conversations.status = "resolved" THEN 1 ELSE 0 END) as resolved_count'),
                DB::raw('AVG(CASE WHEN assignments.accepted_at IS NOT NULL THEN (julianday(assignments.accepted_at) - julianday(assignments.assigned_at)) * 86400 END) as avg_accept_seconds')
            )
            ->groupBy('users.id', 'users.name', 'users.email')
            ->get()
            ->map(function ($agent) {
                return [
                    'id' => (int) $agent->id,
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'total_assigned' => (int) $agent->total_assigned,
                    'resolved_count' => (int) $agent->resolved_count,
                    'resolution_rate' => $agent->total_assigned > 0 
                        ? round(($agent->resolved_count / $agent->total_assigned) * 100, 2) 
                        : 0,
                    'avg_accept_minutes' => $agent->avg_accept_seconds 
                        ? round($agent->avg_accept_seconds / 60, 2) 
                        : null,
                ];
            })
            ->sortByDesc('total_assigned')
            ->values()
            ->toArray();

        return $agentStats;
    }

    /**
     * Get trend data over time (daily breakdown)
     */
    private function getTrendData(Carbon $startDate, Carbon $endDate): array
    {
        $dailyStats = DB::table('conversations')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_conversations,
                SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END) as resolved_count,
                AVG(CASE WHEN first_responded_at IS NOT NULL THEN (julianday(first_responded_at) - julianday(created_at)) * 86400 END) as avg_first_response_seconds
            ')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(function ($day) {
                return [
                    'date' => $day->date,
                    'total_conversations' => (int) $day->total_conversations,
                    'resolved_count' => (int) $day->resolved_count,
                    'resolution_rate' => $day->total_conversations > 0 
                        ? round(($day->resolved_count / $day->total_conversations) * 100, 2) 
                        : 0,
                    'avg_first_response_minutes' => $day->avg_first_response_seconds 
                        ? round($day->avg_first_response_seconds / 60, 2) 
                        : null,
                ];
            })
            ->toArray();

        return $dailyStats;
    }

    /**
     * Clear analytics cache
     */
    public function clearCache(): void
    {
        // This would require a more sophisticated cache clearing strategy in production
        // For now, we rely on TTL expiration
    }
}
