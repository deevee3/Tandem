<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Message */
class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_type' => $this->sender_type,
            'user_id' => $this->user_id,
            'content' => $this->content,
            'confidence' => $this->confidence,
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
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
                        ];
                    }) : null,
                    'skills' => $this->user->relationLoaded('skills') ? $this->user->skills->map(function ($skill) {
                        return [
                            'id' => $skill->id,
                            'name' => $skill->name,
                            'level' => $skill->pivot->level ?? null,
                        ];
                    }) : null,
                ] : null;
            }),
        ];
    }
}
