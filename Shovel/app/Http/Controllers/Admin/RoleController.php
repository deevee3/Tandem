<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    protected AuditLogService $auditLog;

    public function __construct(AuditLogService $auditLog)
    {
        $this->auditLog = $auditLog;
    }
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 50);
        $search = $request->input('search');

        $query = Role::query()
            ->with(['permissions']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name');

        $paginator = $query->paginate($perPage);

        return RoleResource::collection($paginator)
            ->additional([
                'meta' => [
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                    'filters' => [
                        'search' => $search,
                    ],
                ],
            ])
            ->response()
            ->setStatusCode(200);
    }

    public function all(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles)
            ->response()
            ->setStatusCode(200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:roles'],
            'description' => ['nullable', 'string'],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'guard_name' => $validated['guard_name'] ?? Role::defaultGuardName(),
            'hourly_rate' => !empty($validated['hourly_rate']) ? $validated['hourly_rate'] : null,
        ]);

        if (isset($validated['permission_ids'])) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        $role->load(['permissions']);

        $this->auditLog->logResourceAction('role', 'created', $role, [
            'permission_ids' => $validated['permission_ids'] ?? [],
        ]);

        return RoleResource::make($role)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions']);

        return RoleResource::make($role)
            ->response()
            ->setStatusCode(200);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => ['nullable', 'string'],
            'guard_name' => ['sometimes', 'required', 'string', 'max:255'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $original = $role->getOriginal();

        if (isset($validated['name'])) {
            $role->name = $validated['name'];
        }

        if (isset($validated['slug'])) {
            $role->slug = $validated['slug'];
        }

        if (isset($validated['guard_name']) && $validated['guard_name'] !== '') {
            $role->guard_name = $validated['guard_name'];
        }

        if (isset($validated['description'])) {
            $role->description = $validated['description'];
        }

        if (array_key_exists('hourly_rate', $validated)) {
            $role->hourly_rate = $validated['hourly_rate'] !== '' ? $validated['hourly_rate'] : null;
        }

        $role->save();

        if (isset($validated['permission_ids'])) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        $role->load(['permissions']);

        $this->auditLog->logResourceAction('role', 'updated', $role, [
            'changes' => $role->getChanges(),
            'original' => [
                'name' => $original['name'] ?? null,
                'slug' => $original['slug'] ?? null,
                'description' => $original['description'] ?? null,
                'hourly_rate' => $original['hourly_rate'] ?? null,
            ],
            'permission_ids' => $validated['permission_ids'] ?? null,
        ]);

        return RoleResource::make($role)
            ->response()
            ->setStatusCode(200);
    }

    public function showProfile(Role $role): Response
    {
        $role->load(['permissions', 'users']);

        return Inertia::render('admin/roles/show', [
            'role' => RoleResource::make($role)->resolve(),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->auditLog->logResourceAction('role', 'deleted', $role);

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ], 200);
    }
}
