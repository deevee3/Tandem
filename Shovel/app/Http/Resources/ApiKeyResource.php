<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ApiKey */
class ApiKeyResource extends JsonResource
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
            'name' => $this->name,
            'active' => (bool) $this->active,
            'scopes' => $this->scopes ?? [],
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'last_used_at' => optional($this->last_used_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'metadata' => $this->metadata ?? [],
            'user' => $this->whenLoaded('user', function () {
                $user = $this->getRelation('user');

                if ($user === null) {
                    return null;
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            }),
        ];
    }
}
