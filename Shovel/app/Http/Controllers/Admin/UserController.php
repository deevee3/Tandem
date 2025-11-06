<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 50);
        $search = $request->input('search');

        $query = User::query()
            ->with(['roles', 'skills']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name');

        $paginator = $query->paginate($perPage);

        return UserResource::collection($paginator)
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

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (isset($validated['role_ids'])) {
            $user->roles()->sync($validated['role_ids']);
        }

        $user->load(['roles', 'skills']);

        return UserResource::make($user)
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'skills']);

        return UserResource::make($user)
            ->response()
            ->setStatusCode(200);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        $user->save();

        if (isset($validated['role_ids'])) {
            $user->roles()->sync($validated['role_ids']);
        }

        $user->load(['roles', 'skills']);

        return UserResource::make($user)
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ], 200);
    }

    public function syncSkills(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'skills' => ['required', 'array'],
            'skills.*.skill_id' => ['required', 'exists:skills,id'],
            'skills.*.level' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Format skills for sync with pivot data
        $skillsToSync = [];
        foreach ($validated['skills'] as $skill) {
            $skillsToSync[$skill['skill_id']] = ['level' => $skill['level']];
        }

        $user->skills()->sync($skillsToSync);
        $user->load(['roles', 'skills']);

        return UserResource::make($user)
            ->response()
            ->setStatusCode(200);
    }

    public function showProfile(User $user): Response
    {
        $user->load(['roles', 'skills']);

        return Inertia::render('admin/users/show', [
            'user' => UserResource::make($user)->resolve(),
        ]);
    }

    public function updateAvatar(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $user->avatar = $validated['avatar'];
        $user->save();

        $user->load(['roles', 'skills']);

        return UserResource::make($user)
            ->response()
            ->setStatusCode(200);
    }

    public function resourceManagement(User $user): JsonResponse
    {
        $user->load(['roles', 'skills', 'queues']);

        // Calculate total hourly rate from roles and skills
        $roleHourlyRate = $user->roles->sum(function ($role) {
            return $role->hourly_rate ?? 0;
        });

        $skillHourlyRate = $user->skills->sum(function ($skill) {
            return $skill->hourly_rate ?? 0;
        });

        $totalHourlyRate = $roleHourlyRate + $skillHourlyRate;

        // Get assignment statistics
        $totalAssignments = $user->assignments()->count();
        $activeAssignments = $user->assignments()->whereNull('released_at')->count();
        $resolvedAssignments = $user->assignments()->whereNotNull('resolved_at')->count();

        // Get average resolution time (in minutes)
        $avgResolutionTime = $user->assignments()
            ->whereNotNull('resolved_at')
            ->whereNotNull('assigned_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, assigned_at, resolved_at)) as avg_time')
            ->value('avg_time');

        return response()->json([
            'data' => [
                'user' => UserResource::make($user)->resolve(),
                'resource_metrics' => [
                    'total_hourly_rate' => round($totalHourlyRate, 2),
                    'role_hourly_rate' => round($roleHourlyRate, 2),
                    'skill_hourly_rate' => round($skillHourlyRate, 2),
                    'total_assignments' => $totalAssignments,
                    'active_assignments' => $activeAssignments,
                    'resolved_assignments' => $resolvedAssignments,
                    'avg_resolution_time_minutes' => $avgResolutionTime ? round($avgResolutionTime, 2) : null,
                ],
            ],
        ], 200);
    }
}
