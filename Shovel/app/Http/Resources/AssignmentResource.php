<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Assignment */
class AssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'queue_id' => $this->queue_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'assigned_at' => optional($this->assigned_at)->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)->toIso8601String(),
            'released_at' => optional($this->released_at)->toIso8601String(),
            'resolved_at' => optional($this->resolved_at)->toIso8601String(),
            'metadata' => $this->metadata,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'username' => $this->user->username,
                    'avatar' => $this->user->avatar,
                    'roles' => $this->user->relationLoaded('roles') ? $this->user->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'slug' => $role->slug,
                            'code' => $role->code,
                        ];
                    }) : null,
                    'skills' => $this->user->relationLoaded('skills') ? $this->user->skills->map(function ($skill) {
                        return [
                            'id' => $skill->id,
                            'name' => $skill->name,
                            'slug' => $skill->slug,
                            'code' => $skill->code,
                            'level' => $skill->pivot->level ?? null,
                        ];
                    }) : null,
                ];
            }),
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
                ];
            }),
        ];
    }
}
