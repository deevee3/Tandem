<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreApiKeyRequest;
use App\Http\Requests\Admin\UpdateApiKeyRequest;
use App\Http\Resources\ApiKeyResource;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->input('per_page', 50), 100));
        $search = trim((string) $request->input('search', ''));

        $query = ApiKey::query()
            ->with('user')
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('metadata', 'like', "%{$search}%");
            });
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $paginator = $query->paginate($perPage);

        return ApiKeyResource::collection($paginator)
            ->additional([
                'meta' => [
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                    'filters' => [
                        'search' => $search !== '' ? $search : null,
                        'active' => $request->has('active') ? $request->boolean('active') : null,
                    ],
                ],
            ])
            ->response()
            ->setStatusCode(200);
    }

    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        $plainTextKey = 'shovel_' . Str::random(48);
        $hashedKey = hash('sha256', $plainTextKey);

        $payload = $request->payload();

        $apiKey = ApiKey::query()->create([
            'name' => $payload['name'],
            'key' => $hashedKey,
            'scopes' => $payload['scopes'],
            'active' => true,
            'expires_at' => $payload['expires_at'],
            'user_id' => $payload['user_id'],
            'metadata' => [
                'created_via' => 'admin_ui',
            ],
        ]);
        $apiKey->setAttribute('plain_text_key', $plainTextKey);

        return ApiKeyResource::make($apiKey->loadMissing('user'))
            ->additional([
                'plain_text_key' => $plainTextKey,
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateApiKeyRequest $request, ApiKey $apiKey): JsonResponse
    {
        $apiKey->fill($request->updatePayload());
        $apiKey->save();

        return ApiKeyResource::make($apiKey->fresh('user'))
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(ApiKey $apiKey): JsonResponse
    {
        if (! $apiKey->active) {
            return response()->json([
                'message' => 'API key already inactive.',
            ], 200);
        }

        $apiKey->forceFill([
            'active' => false,
        ])->save();

        return response()->json([
            'message' => 'API key deactivated successfully.',
        ], 200);
    }
}
