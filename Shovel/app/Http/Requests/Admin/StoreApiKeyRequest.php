<?php

namespace App\Http\Requests\Admin;

use App\Support\Authorization\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission(Permissions::API_KEYS_MANAGE);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:128'],
            'expires_at' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
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
     * @return list<string>
     */
    public function sanitizedScopes(): array
    {
        $scopes = Arr::get($this->validated(), 'scopes', []);

        return collect($scopes)
            ->filter(static fn ($scope) => is_string($scope) && trim($scope) !== '')
            ->map(static fn ($scope) => trim((string) $scope))
            ->unique()
            ->values()
            ->all();
    }

    public function expiresAt(): ?Carbon
    {
        $value = Arr::get($this->validated(), 'expires_at');

        return $value ? Carbon::parse($value) : null;
    }

    public function ownerId(): ?int
    {
        $explicit = Arr::get($this->validated(), 'user_id');

        if ($explicit !== null) {
            return (int) $explicit;
        }

        return $this->user()?->id;
    }

    /**
     * @return array{name:string,scopes:list<string>,expires_at:?Carbon,user_id:?int}
     */
    public function payload(): array
    {
        return [
            'name' => Arr::get($this->validated(), 'name'),
            'scopes' => $this->sanitizedScopes(),
            'expires_at' => $this->expiresAt(),
            'user_id' => $this->ownerId(),
        ];
    }
}
