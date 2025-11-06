<?php

namespace App\Services;

use App\Jobs\RunAgentForConversation;
use App\Models\Conversation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Sebdesign\SM\Event\TransitionEvent;

class ConversationLifecycleService
{
    public function afterAgentBegins(TransitionEvent $event): void
    {
        $conversation = $this->refreshConversation($event);
        $this->touchConversation($conversation, $event, $event->getContext());
        $this->recordAudit($conversation, $event);
    }

    public function afterHandoffRequired(TransitionEvent $event): void
    {
        $conversation = $this->refreshConversation($event);
        $context = $event->getContext();

        $reason = Arr::get($context, 'reason_code');
        if (blank($reason)) {
            throw new InvalidArgumentException('Missing reason_code when triggering handoff_required transition.');
        }

        DB::table('handoffs')->updateOrInsert([
            'conversation_id' => $conversation->id,
            'reason_code' => $reason,
        ], [
            'confidence' => Arr::get($context, 'confidence'),
            'policy_hits' => $this->encodeNullableArray($context, 'policy_hits'),
            'required_skills' => $this->encodeNullableArray($context, 'required_skills'),
            'metadata' => $this->encodeNullableArray($context, 'handoff_metadata'),
            'created_at' => $this->resolveTimestamp($context, 'handoff_at'),
        ]);

        $this->touchConversation($conversation, $event, $context);
        $this->recordAudit($conversation, $event, [
            'reason_code' => $reason,
            'confidence' => Arr::get($context, 'confidence'),
        ]);
    }

    public function afterEnqueueForHuman(TransitionEvent $event): void
    {
        $conversation = $this->refreshConversation($event);
        $context = $event->getContext();

        $queueId = Arr::get($context, 'queue_id');
        if (! $queueId) {
            throw new InvalidArgumentException('Missing queue_id when triggering enqueue_for_human transition.');
        }

        $enqueuedAt = $this->resolveTimestamp($context, 'enqueued_at');

        DB::table('queue_items')->updateOrInsert([
            'queue_id' => $queueId,
            'conversation_id' => $conversation->id,
            'state' => 'queued',
        ], [
            'enqueued_at' => $enqueuedAt,
            'dequeued_at' => null,
            'metadata' => $this->encodeNullableArray($context, 'queue_item_metadata'),
            'created_at' => $enqueuedAt,
            'updated_at' => $enqueuedAt,
        ]);

        $this->touchConversation($conversation, $event, $context);
        $this->recordAudit($conversation, $event, [
            'queue_id' => $queueId,
        ]);
    }

    public function afterAssignHuman(TransitionEvent $event): void
    {
        $conversation = $this->refreshConversation($event);
        $context = $event->getContext();

        $queueId = Arr::get($context, 'queue_id');
        $userId = Arr::get($context, 'assignment_user_id');

        if (! $queueId || ! $userId) {
            throw new InvalidArgumentException('Missing queue_id or assignment_user_id when triggering assign_human transition.');
        }

        $assignedAt = $this->resolveTimestamp($context, 'assigned_at');

        DB::table('queue_items')
            ->where('queue_id', $queueId)
            ->where('conversation_id', $conversation->id)
            ->where('state', 'queued')
            ->update([
                'state' => 'hot',
                'dequeued_at' => $assignedAt,
                'updated_at' => $assignedAt,
            ]);

        DB::table('assignments')->insert([
            'conversation_id' => $conversation->id,
            'queue_id' => $queueId,
            'user_id' => $userId,
            'assigned_at' => $assignedAt,
            'accepted_at' => null,
            'released_at' => null,
            'resolved_at' => null,
            'status' => 'assigned',
            'metadata' => $this->encodeNullableArray($context, 'assignment_metadata'),
            'created_at' => $assignedAt,
            'updated_at' => $assignedAt,
        ]);

        $this->touchConversation($conversation, $event, $context);
        $this->recordAudit($conversation, $event, [
            'queue_id' => $queueId,
            'user_id' => $userId,
        ]);
    }

    public function afterHumanAccepts(TransitionEvent $event): void
    {
        $conversation = $this->refreshConversation($event);
        $context = $event->getContext();

        $userId = Arr::get($context, 'assignment_user_id');
        if (! $userId) {
            throw new InvalidArgumentException('Missing assignment_user_id when triggering human_accepts transition.');
        }

        $acceptedAt = $this->resolveTimestamp($context, 'accepted_at');

        $assignment = $this->latestAssignment($conversation->id, $userId);
        if (! $assignment) {
            throw new InvalidArgumentException('No assignment found to accept for the provided user.');
        }

        DB::table('assignments')
            ->where('id', $assignment->id)
            ->update([
                'status' => 'human_working',
                'accepted_at' => $acceptedAt,
                'updated_at' => $acceptedAt,
            ]);

        $this->touchConversation($conversation, $event, $context);
        $this->recordAudit($conversation, $event, [
            'user_id' => $userId,
        ]);

        RunAgentForConversation::dispatch($conversation->id);
    }

    public function afterReturnToAgent(TransitionEvent $event): void
    {
        $conversation = $this->refreshConversation($event);
        $context = $event->getContext();

        $userId = Arr::get($context, 'assignment_user_id');
        if (! $userId) {
            throw new InvalidArgumentException('Missing assignment_user_id when triggering return_to_agent transition.');
        }

        $releasedAt = $this->resolveTimestamp($context, 'released_at');

        $assignment = $this->latestAssignment($conversation->id, $userId);
        if (! $assignment) {
            throw new InvalidArgumentException('No assignment found to release for the provided user.');
        }

        DB::table('assignments')
            ->where('id', $assignment->id)
            ->update([
                'status' => 'released',
                'released_at' => $releasedAt,
                'updated_at' => $releasedAt,
            ]);

        $this->touchConversation($conversation, $event, $context);
        $this->recordAudit($conversation, $event, [
            'user_id' => $userId,
        ]);

        RunAgentForConversation::dispatch($conversation->id);
    }

    public function afterResolve(TransitionEvent $event): void
    {
        $conversation = $this->refreshConversation($event);
        $context = $event->getContext();

        $resolvedAt = $this->resolveTimestamp($context, 'resolved_at');
        $userId = Arr::get($context, 'assignment_user_id');

        $assignment = $this->latestAssignment($conversation->id, $userId);
        if ($assignment) {
            DB::table('assignments')
                ->where('id', $assignment->id)
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => $resolvedAt,
                    'updated_at' => $resolvedAt,
                ]);
        }

        DB::table('queue_items')
            ->where('conversation_id', $conversation->id)
            ->whereIn('state', ['queued', 'hot'])
            ->update([
                'state' => 'completed',
                'updated_at' => $resolvedAt,
                'dequeued_at' => $resolvedAt,
            ]);

        $this->touchConversation($conversation, $event, $context, $resolvedAt);
        $this->recordAudit($conversation, $event, [
            'user_id' => $userId,
            'summary' => Arr::get($context, 'resolution_summary'),
        ]);
    }

    public function afterArchive(TransitionEvent $event): void
    {
        $conversation = $this->refreshConversation($event);
        $this->touchConversation($conversation, $event, $event->getContext());
        $this->recordAudit($conversation, $event);
    }

    protected function refreshConversation(TransitionEvent $event): Conversation
    {
        $object = $event->getStateMachine()->getObject();

        if (! $object instanceof Conversation) {
            throw new InvalidArgumentException('State machine subject must be a Conversation instance.');
        }
        return $object;
    }

    protected function touchConversation(Conversation $conversation, TransitionEvent $event, array $context, ?Carbon $timestamp = null): void
    {
        $time = $timestamp ?? $this->resolveTimestamp($context, 'occurred_at', false);
        if ($time) {
            $conversation->last_activity_at = $time;
            $conversation->updated_at = $time;
        } else {
            $conversation->updated_at = now();
        }

        $target = Arr::get($event->getConfig(), 'to');
        if ($target) {
            $conversation->status = $target;
        }

        $conversation->save();
    }

    protected function recordAudit(Conversation $conversation, TransitionEvent $event, array $payload = []): void
    {
        $context = $event->getContext();
        $actorId = Arr::get($context, 'actor_id');
        $now = now();

        // Enrich payload with user context (roles, skills, queue)
        $enrichedPayload = array_merge([
            'from' => $event->getState(),
            'to' => Arr::get($event->getConfig(), 'to'),
        ], array_filter($payload, fn ($value) => $value !== null));

        // Add user context if actor is present
        if ($actorId) {
            $user = DB::table('users')->where('id', $actorId)->first();
            if ($user) {
                $enrichedPayload['actor'] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                ];

                // Add roles
                $roles = DB::table('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('user_roles.user_id', $actorId)
                    ->select('roles.id', 'roles.name', 'roles.slug', 'roles.code')
                    ->get();
                if ($roles->isNotEmpty()) {
                    $enrichedPayload['actor']['roles'] = $roles->toArray();
                }

                // Add skills
                $skills = DB::table('user_skill')
                    ->join('skills', 'skills.id', '=', 'user_skill.skill_id')
                    ->where('user_skill.user_id', $actorId)
                    ->select('skills.id', 'skills.name', 'user_skill.level')
                    ->get();
                if ($skills->isNotEmpty()) {
                    $enrichedPayload['actor']['skills'] = $skills->toArray();
                }
            }
        }

        // Add queue context if present
        if (isset($payload['queue_id'])) {
            $queue = DB::table('queues')->where('id', $payload['queue_id'])->first();
            if ($queue) {
                $enrichedPayload['queue'] = [
                    'id' => $queue->id,
                    'name' => $queue->name,
                    'slug' => $queue->slug,
                    'sla_first_response_minutes' => $queue->sla_first_response_minutes,
                    'sla_resolution_minutes' => $queue->sla_resolution_minutes,
                    'skills_required' => json_decode($queue->skills_required ?? '[]', true),
                    'priority_policy' => json_decode($queue->priority_policy ?? '{}', true),
                ];
            }
        }

        // Add assigned user context if present
        if (isset($payload['user_id']) && $payload['user_id'] !== $actorId) {
            $assignedUser = DB::table('users')->where('id', $payload['user_id'])->first();
            if ($assignedUser) {
                $enrichedPayload['assigned_user'] = [
                    'id' => $assignedUser->id,
                    'name' => $assignedUser->name,
                    'email' => $assignedUser->email,
                    'username' => $assignedUser->username,
                ];

                // Add assigned user's roles
                $assignedRoles = DB::table('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('user_roles.user_id', $payload['user_id'])
                    ->select('roles.id', 'roles.name', 'roles.slug', 'roles.code')
                    ->get();
                if ($assignedRoles->isNotEmpty()) {
                    $enrichedPayload['assigned_user']['roles'] = $assignedRoles->toArray();
                }

                // Add assigned user's skills
                $assignedSkills = DB::table('user_skill')
                    ->join('skills', 'skills.id', '=', 'user_skill.skill_id')
                    ->where('user_skill.user_id', $payload['user_id'])
                    ->select('skills.id', 'skills.name', 'user_skill.level')
                    ->get();
                if ($assignedSkills->isNotEmpty()) {
                    $enrichedPayload['assigned_user']['skills'] = $assignedSkills->toArray();
                }
            }
        }

        DB::table('audit_events')->insert([
            'event_type' => 'conversation.' . $event->getTransition(),
            'conversation_id' => $conversation->id,
            'user_id' => $actorId,
            'subject_type' => Conversation::class,
            'subject_id' => $conversation->id,
            'payload' => json_encode($enrichedPayload),
            'channel' => Arr::get($context, 'channel', 'system'),
            'occurred_at' => $this->resolveTimestamp($context, 'occurred_at', false) ?? $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function latestAssignment(int $conversationId, ?int $userId = null): ?object
    {
        $query = DB::table('assignments')
            ->where('conversation_id', $conversationId)
            ->orderByDesc('assigned_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    protected function encodeNullableArray(array $context, string $key): ?string
    {
        $value = Arr::get($context, $key);

        return is_null($value) ? null : json_encode($value);
    }

    protected function resolveTimestamp(array $context, string $key, bool $fallbackNow = true): ?Carbon
    {
        $value = Arr::get($context, $key);

        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value)) {
            return Carbon::parse($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return $fallbackNow ? now() : null;
    }
}
