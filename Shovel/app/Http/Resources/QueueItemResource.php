<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\QueueItem */
class QueueItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $conversation = $this->conversation;
        $latestMessage = $conversation?->messages?->first();
        $currentAssignment = $conversation?->currentAssignment;

        return [
            'id' => $this->id,
            'queue_id' => $this->queue_id,
            'state' => $this->state,
            'enqueued_at' => optional($this->enqueued_at)->toIso8601String(),
            'dequeued_at' => optional($this->dequeued_at)->toIso8601String(),
            'metadata' => $this->metadata,
            'queue' => $this->whenLoaded('queue', function () {
                return [
                    'id' => $this->queue->id,
                    'name' => $this->queue->name,
                    'slug' => $this->queue->slug,
                    'description' => $this->queue->description,
                    'sla_first_response_minutes' => $this->queue->sla_first_response_minutes,
                    'sla_resolution_minutes' => $this->queue->sla_resolution_minutes,
                    'skills_required' => $this->queue->skills_required,
                    'priority_policy' => $this->queue->priority_policy,
                    'assigned_users' => $this->queue->relationLoaded('users') ? $this->queue->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'username' => $user->username,
                            'avatar' => $user->avatar,
                        ];
                    }) : null,
                ];
            }),
            'conversation' => $conversation ? [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'status' => $conversation->status,
                'priority' => $conversation->priority,
                'last_activity_at' => optional($conversation->last_activity_at)->toIso8601String(),
                'requester' => [
                    'type' => $conversation->requester_type,
                    'identifier' => $conversation->requester_identifier,
                ],
                'latest_message' => $latestMessage ? [
                    'id' => $latestMessage->id,
                    'sender_type' => $latestMessage->sender_type,
                    'content' => $latestMessage->content,
                    'confidence' => $latestMessage->confidence,
                    'metadata' => $latestMessage->metadata,
                    'created_at' => optional($latestMessage->created_at)->toIso8601String(),
                ] : null,
                'current_assignment' => $currentAssignment ? [
                    'id' => $currentAssignment->id,
                    'user_id' => $currentAssignment->user_id,
                    'status' => $currentAssignment->status,
                    'assigned_at' => optional($currentAssignment->assigned_at)->toIso8601String(),
                    'accepted_at' => optional($currentAssignment->accepted_at)->toIso8601String(),
                    'user' => $currentAssignment->relationLoaded('user') ? [
                        'id' => $currentAssignment->user->id,
                        'name' => $currentAssignment->user->name,
                        'email' => $currentAssignment->user->email,
                        'username' => $currentAssignment->user->username,
                        'avatar' => $currentAssignment->user->avatar,
                        'roles' => $currentAssignment->user->relationLoaded('roles') ? $currentAssignment->user->roles->map(function ($role) {
                            return [
                                'id' => $role->id,
                                'name' => $role->name,
                                'slug' => $role->slug,
                                'code' => $role->code,
                            ];
                        }) : null,
                        'skills' => $currentAssignment->user->relationLoaded('skills') ? $currentAssignment->user->skills->map(function ($skill) {
                            return [
                                'id' => $skill->id,
                                'name' => $skill->name,
                                'slug' => $skill->slug,
                                'code' => $skill->code,
                                'level' => $skill->pivot->level ?? null,
                            ];
                        }) : null,
                    ] : null,
                ] : null,
            ] : null,
        ];
    }
}
