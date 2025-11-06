<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHandoffPolicyRequest;
use App\Http\Requests\Admin\UpdateHandoffPolicyRequest;
use App\Http\Resources\HandoffPolicyResource;
use App\Models\HandoffPolicy;
use App\Models\HandoffPolicyRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class HandoffPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min($perPage, 100));
        $search = $request->input('search');

        $query = HandoffPolicy::query()
            ->with([
                'skills',
                'rules' => fn ($rulesQuery) => $rulesQuery->orderByDesc('priority'),
            ])
            ->orderBy('name');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('reason_code', 'like', "%{$search}%");
            });
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $paginator = $query->paginate($perPage);

        return HandoffPolicyResource::collection($paginator)
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
                        'active' => $request->has('active') ? $request->boolean('active') : null,
                    ],
                ],
            ])
            ->response()
            ->setStatusCode(200);
    }

    public function store(StoreHandoffPolicyRequest $request): JsonResponse
    {
        $policy = null;

        DB::transaction(function () use ($request, &$policy) {
            $policy = HandoffPolicy::create($request->policyAttributes());

            if ($request->has('skill_ids')) {
                $this->syncSkills($policy, $request->skillIds());
            }

            $this->persistRules($policy, $request->rulePayloads());
        });

        $policy->load(['skills', 'rules' => fn ($query) => $query->orderByDesc('priority')]);

        return HandoffPolicyResource::make($policy)
            ->response()
            ->setStatusCode(201);
    }

    public function show(HandoffPolicy $handoffPolicy): JsonResponse
    {
        $handoffPolicy->load(['skills', 'rules' => fn ($query) => $query->orderByDesc('priority')]);

        return HandoffPolicyResource::make($handoffPolicy)
            ->response()
            ->setStatusCode(200);
    }

    public function update(UpdateHandoffPolicyRequest $request, HandoffPolicy $handoffPolicy): JsonResponse
    {
        DB::transaction(function () use ($request, $handoffPolicy) {
            $attributes = $request->policyAttributes();

            if ($attributes !== []) {
                $handoffPolicy->fill($attributes);
                $handoffPolicy->save();
            }

            if ($request->has('skill_ids')) {
                $this->syncSkills($handoffPolicy, $request->skillIds());
            }

            if ($request->hasRules()) {
                $this->persistRules($handoffPolicy, $request->rulePayloads());
            }
        });

        $handoffPolicy->load(['skills', 'rules' => fn ($query) => $query->orderByDesc('priority')]);

        return HandoffPolicyResource::make($handoffPolicy)
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(HandoffPolicy $handoffPolicy): JsonResponse
    {
        $handoffPolicy->delete();

        return response()->json([
            'message' => 'Handoff policy deleted successfully',
        ], 200);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    protected function persistRules(HandoffPolicy $policy, array $rules): void
    {
        $policy->loadMissing('rules');

        $existingRuleIds = $policy->rules->pluck('id')->all();
        $retainedRuleIds = [];

        foreach ($rules as $rulePayload) {
            $ruleAttributes = $this->ruleAttributes($rulePayload);

            if (isset($rulePayload['id'])) {
                $rule = $policy->rules->firstWhere('id', (int) $rulePayload['id']);

                if ($rule) {
                    $rule->fill($ruleAttributes);
                    $rule->save();
                    $retainedRuleIds[] = $rule->id;

                    continue;
                }
            }

            $rule = $policy->rules()->create($ruleAttributes);
            $retainedRuleIds[] = $rule->id;
        }

        $deleteIds = array_diff($existingRuleIds, $retainedRuleIds);

        if ($deleteIds !== []) {
            HandoffPolicyRule::query()
                ->whereIn('id', $deleteIds)
                ->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function ruleAttributes(array $payload): array
    {
        return [
            'trigger_type' => Arr::get($payload, 'trigger_type'),
            'criteria' => Arr::get($payload, 'criteria'),
            'priority' => Arr::get($payload, 'priority', 0),
            'active' => Arr::get($payload, 'active', true),
        ];
    }

    /**
     * @param  array<int, int>  $skillIds
     */
    protected function syncSkills(HandoffPolicy $policy, array $skillIds): void
    {
        $timestamp = now();

        $payload = collect($skillIds)
            ->mapWithKeys(fn ($id) => [
                $id => [
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            ])
            ->all();

        $policy->skills()->sync($payload);
    }
}
