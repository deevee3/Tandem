<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Queue;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class QueueResolverService
{
    private const CACHE_KEY = 'queues.routing.cache.v1';

    public function __construct(private readonly CacheRepository $cache)
    {
    }

    public function resolveForEvaluation(
        PolicyEvaluationResult $evaluation,
        ?Conversation $conversation = null,
        ?Queue $preferredQueue = null
    ): ?Queue {
        if ($preferredQueue !== null) {
            return $preferredQueue;
        }

        $queues = $this->cachedQueues();
        $requiredSkills = array_map('intval', $evaluation->requiredSkills());

        $matchingQueues = $queues->map(function (Queue $queue) use ($requiredSkills, $conversation) {
            $skills = array_map('intval', Arr::wrap($queue->skills_required ?? []));

            $matchCount = empty($requiredSkills)
                ? 0
                : count(array_intersect($requiredSkills, $skills));

            $priorityScore = $this->resolvePriorityScore($queue, $conversation);

            return [
                'queue' => $queue,
                'match_count' => $matchCount,
                'meets_requirements' => empty($requiredSkills)
                    || count(array_diff($requiredSkills, $skills)) === 0,
                'priority_score' => $priorityScore,
            ];
        })
            ->filter(fn (array $item) => $item['meets_requirements'])
            ->sortByDesc(fn (array $item) => [$item['match_count'], $item['priority_score']])
            ->values();

        $candidate = $matchingQueues->first();

        if ($candidate !== null) {
            return $candidate['queue'];
        }

        return $this->defaultQueue($queues) ?? $queues->first();
    }

    public function validateQueueSupportsEvaluation(Queue $queue, PolicyEvaluationResult $evaluation): bool
    {
        $requiredSkills = array_map('intval', $evaluation->requiredSkills());

        if (empty($requiredSkills)) {
            return true;
        }

        $queueSkills = array_map('intval', Arr::wrap($queue->skills_required ?? []));

        return count(array_diff($requiredSkills, $queueSkills)) === 0;
    }

    protected function resolvePriorityScore(Queue $queue, ?Conversation $conversation = null): int
    {
        $priorityPolicy = collect($queue->priority_policy ?? []);

        if ($priorityPolicy->isEmpty()) {
            return $queue->is_default ? 1 : 0;
        }

        $priority = $conversation?->priority ?? null;

        return match ($priority) {
            'critical' => 3,
            'high' => 2,
            default => 1,
        };
    }

    protected function cachedQueues(): Collection
    {
        return $this->cache->remember(self::CACHE_KEY, now()->addMinutes(5), function () {
            return Queue::query()->orderByDesc('is_default')->orderBy('name')->get();
        });
    }

    protected function defaultQueue(Collection $queues): ?Queue
    {
        return $queues->first(fn (Queue $queue) => $queue->is_default);
    }

    public function flushCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }
}

