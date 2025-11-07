<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Conversation;
use App\Models\Queue;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SLAService
{
    /**
     * Default SLA targets by priority.
     */
    private array $defaultSLATargets = [
        'high' => [
            'first_response_minutes' => 15,
            'resolution_hours' => 2,
        ],
        'standard' => [
            'first_response_minutes' => 60,
            'resolution_hours' => 24,
        ],
        'low' => [
            'first_response_minutes' => 240, // 4 hours
            'resolution_hours' => 72, // 3 days
        ],
    ];

    /**
     * Set SLA timers for a new conversation.
     */
    public function setSLATimers(Conversation $conversation): void
    {
        $queue = $conversation->currentQueueItem?->queue;
        $priority = $conversation->priority ?? 'standard';
        
        $slaTargets = $this->getSLATargets($queue, $priority);
        
        $now = now();
        
        $conversation->update([
            'first_response_due_at' => $now->addMinutes($slaTargets['first_response_minutes']),
            'resolution_due_at' => $now->addHours($slaTargets['resolution_hours']),
        ]);
    }

    /**
     * Mark first response time when agent replies.
     */
    public function markFirstResponse(Conversation $conversation): void
    {
        if (!$conversation->first_responded_at) {
            $conversation->update([
                'first_responded_at' => now(),
            ]);
        }
    }

    /**
     * Check SLA status for a conversation.
     */
    public function checkSLAStatus(Conversation $conversation): array
    {
        $now = now();
        $status = [
            'first_response' => [
                'due_at' => $conversation->first_response_due_at,
                'responded_at' => $conversation->first_responded_at,
                'status' => 'pending',
                'minutes_remaining' => null,
                'is_breached' => false,
            ],
            'resolution' => [
                'due_at' => $conversation->resolution_due_at,
                'status' => 'pending',
                'hours_remaining' => null,
                'is_breached' => false,
            ],
        ];

        // Check first response SLA
        if ($conversation->first_response_due_at) {
            if ($conversation->first_responded_at) {
                $status['first_response']['status'] = 'met';
                $status['first_response']['minutes_taken'] = $conversation->first_responded_at->diffInMinutes($conversation->created_at);
            } else {
                $minutesRemaining = $conversation->first_response_due_at->diffInMinutes($now, false);
                $status['first_response']['minutes_remaining'] = max(0, $minutesRemaining);
                $status['first_response']['is_breached'] = $minutesRemaining <= 0;
                $status['first_response']['status'] = $minutesRemaining <= 0 ? 'breached' : 'pending';
            }
        }

        // Check resolution SLA
        if ($conversation->resolution_due_at) {
            if ($conversation->status === Conversation::STATUS_RESOLVED) {
                $status['resolution']['status'] = 'met';
                $status['resolution']['hours_taken'] = $conversation->updated_at->diffInHours($conversation->created_at);
            } else {
                $hoursRemaining = $conversation->resolution_due_at->diffInHours($now, false);
                $status['resolution']['hours_remaining'] = max(0, $hoursRemaining);
                $status['resolution']['is_breached'] = $hoursRemaining <= 0;
                $status['resolution']['status'] = $hoursRemaining <= 0 ? 'breached' : 'pending';
            }
        }

        return $status;
    }

    /**
     * Get SLA targets for a queue and priority.
     */
    public function getSLATargets(?Queue $queue, string $priority): array
    {
        if ($queue && $queue->sla_targets) {
            return $queue->sla_targets[$priority] ?? $this->defaultSLATargets[$priority];
        }

        return $this->defaultSLATargets[$priority] ?? $this->defaultSLATargets['standard'];
    }

    /**
     * Find conversations approaching or breaching SLA.
     */
    public function getSLARiskConversations(): Collection
    {
        $now = now();
        
        return Conversation::query()
            ->whereIn('status', [
                Conversation::STATUS_AGENT_WORKING,
                Conversation::STATUS_QUEUED,
                Conversation::STATUS_ASSIGNED,
                Conversation::STATUS_HUMAN_WORKING,
            ])
            ->where(function ($query) use ($now) {
                $query->where('first_response_due_at', '<=', $now->copy()->addMinutes(30))
                      ->orWhere('resolution_due_at', '<=', $now->copy()->addHours(2));
            })
            ->with(['currentAssignment.user', 'currentQueueItem.queue'])
            ->get()
            ->map(function ($conversation) {
                $conversation->sla_status = $this->checkSLAStatus($conversation);
                return $conversation;
            });
    }

    /**
     * Update SLA breach flags.
     */
    public function updateSLABreaches(): int
    {
        $breachedCount = 0;
        $now = now();

        $conversations = Conversation::query()
            ->whereIn('status', [
                Conversation::STATUS_AGENT_WORKING,
                Conversation::STATUS_QUEUED,
                Conversation::STATUS_ASSIGNED,
                Conversation::STATUS_HUMAN_WORKING,
            ])
            ->where(function ($query) use ($now) {
                $query->where('first_response_due_at', '<=', $now)
                      ->orWhere('resolution_due_at', '<=', $now);
            })
            ->whereNull('sla_breached_at')
            ->get();

        foreach ($conversations as $conversation) {
            $slaStatus = $this->checkSLAStatus($conversation);
            $isBreached = $slaStatus['first_response']['is_breached'] || $slaStatus['resolution']['is_breached'];

            if ($isBreached) {
                $conversation->update([
                    'sla_breached_at' => $now,
                    'sla_status' => $slaStatus,
                ]);
                $breachedCount++;
            }
        }

        return $breachedCount;
    }

    /**
     * Seed default SLA targets for existing queues.
     */
    public function seedDefaultSLATargets(): void
    {
        Queue::query()->update([
            'sla_targets' => $this->defaultSLATargets,
        ]);
    }
}
