<?php

namespace App\Http\Requests\Admin;

use App\Support\Authorization\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class UpdateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission(Permissions::API_KEYS_MANAGE);
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'scopes' => ['sometimes', 'nullable', 'array'],
            'scopes.*' => ['string', 'max:128'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'API key name',
            'scopes.*' => 'scope',
            'expires_at' => 'expiration date',
            'user_id' => 'user',
        ];
    }

    /**
     * @return list<string>|null
     */
    public function sanitizedScopes(): ?array
    {
        if (! $this->has('scopes')) {
            return null;
        }

        $scopes = Arr::get($this->validated(), 'scopes', []);

        if ($scopes === null) {
            return [];
        }

        return collect($scopes)
            ->filter(static fn ($scope) => is_string($scope) && trim($scope) !== '')
            ->map(static fn ($scope) => trim((string) $scope))
            ->unique()
            ->values()
            ->all();
    }

    public function expiresAt(): bool|Carbon|null
    {
        if (! $this->has('expires_at')) {
            return null;
        }

        $value = Arr::get($this->validated(), 'expires_at');

        if ($value === null) {
            return false;
        }

        return Carbon::parse($value);
    }

    public function ownerId(): bool|int|null
    {
        if (! $this->has('user_id')) {
            return null;
        }

        $explicit = Arr::get($this->validated(), 'user_id');

        if ($explicit === null) {
            return false;
        }

        return (int) $explicit;
    }

    /**
     * @return array<string, mixed>
     */
    public function updatePayload(): array
    {
        $payload = [];
        $validated = $this->validated();

        if (array_key_exists('name', $validated)) {
            $payload['name'] = $validated['name'];
        }

        $scopes = $this->sanitizedScopes();
        if ($scopes !== null) {
            $payload['scopes'] = $scopes;
        }

        $expiresAt = $this->expiresAt();
        if ($expiresAt !== null) {
            $payload['expires_at'] = $expiresAt === false ? null : $expiresAt;
        }

        $ownerId = $this->ownerId();
        if ($ownerId !== null) {
            $payload['user_id'] = $ownerId === false ? null : $ownerId;
        }

        if (array_key_exists('active', $validated)) {
            $payload['active'] = (bool) $validated['active'];
        }

        return $payload;
    }
}
