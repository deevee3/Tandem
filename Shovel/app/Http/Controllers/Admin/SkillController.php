<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SkillResource;
use App\Models\Skill;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
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

        $query = Skill::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name');

        $paginator = $query->paginate($perPage);

        return SkillResource::collection($paginator)
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
        $skills = Skill::query()
            ->orderBy('name')
            ->get();

        return SkillResource::collection($skills)
            ->response()
            ->setStatusCode(200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:skills'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $skill = Skill::create($validated);

        $this->auditLog->logResourceAction('skill', 'created', $skill);

        return SkillResource::make($skill)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Skill $skill): JsonResponse
    {
        return SkillResource::make($skill)
            ->response()
            ->setStatusCode(200);
    }

    public function update(Request $request, Skill $skill): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('skills')->ignore($skill->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $original = $skill->getOriginal();
        $skill->update($validated);

        $this->auditLog->logResourceAction('skill', 'updated', $skill, [
            'changes' => $skill->getChanges(),
            'original' => [
                'name' => $original['name'] ?? null,
                'description' => $original['description'] ?? null,
            ],
        ]);

        return SkillResource::make($skill)
            ->response()
            ->setStatusCode(200);
    }

    public function showProfile(Skill $skill): Response
    {
        $skill->load(['users']);

        return Inertia::render('admin/skills/show', [
            'skill' => SkillResource::make($skill)->resolve(),
        ]);
    }

    public function destroy(Skill $skill): JsonResponse
    {
        $this->auditLog->logResourceAction('skill', 'deleted', $skill);

        $skill->delete();

        return response()->json([
            'message' => 'Skill deleted successfully',
        ], 200);
    }
}
