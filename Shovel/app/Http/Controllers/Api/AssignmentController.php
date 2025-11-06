<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcceptAssignmentRequest;
use App\Http\Requests\Api\ReleaseAssignmentRequest;
use App\Http\Requests\Api\ResolveAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use SM\Factory\FactoryInterface;

class AssignmentController extends Controller
{
    public function __construct(private readonly FactoryInterface $stateMachineFactory)
    {
    }

    public function accept(AcceptAssignmentRequest $request, Assignment $assignment): JsonResponse
    {
        if ($assignment->status !== Assignment::STATUS_ASSIGNED) {
            abort(409, 'Assignment cannot be accepted in its current state.');
        }

        $accepted = DB::transaction(function () use ($assignment, $request) {
            /** @var Assignment $lockedAssignment */
            $lockedAssignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ($lockedAssignment->status !== Assignment::STATUS_ASSIGNED) {
                abort(409, 'Assignment cannot be accepted in its current state.');
            }

            /** @var Conversation $conversation */
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($lockedAssignment->conversation_id);

            $stateMachine = $this->stateMachineFactory->get($conversation, 'conversation');

            if (! $stateMachine->can('human_accepts')) {
                abort(409, 'Conversation is not ready for human acceptance.');
            }

            $stateMachine->apply('human_accepts', false, [
                'actor_id' => $request->actorId(),
                'assignment_user_id' => $lockedAssignment->user_id,
                'accepted_at' => now(),
                'channel' => 'api',
            ]);

            return $lockedAssignment->fresh(['user.roles', 'user.skills', 'queue']);
        });

        return AssignmentResource::make($accepted)
            ->response()
            ->setStatusCode(200);
    }

    public function release(ReleaseAssignmentRequest $request, Assignment $assignment): JsonResponse
    {
        if ($assignment->status !== Assignment::STATUS_HUMAN_WORKING) {
            abort(409, 'Assignment cannot be released in its current state.');
        }

        $released = DB::transaction(function () use ($assignment, $request) {
            /** @var Assignment $lockedAssignment */
            $lockedAssignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ($lockedAssignment->status !== Assignment::STATUS_HUMAN_WORKING) {
                abort(409, 'Assignment cannot be released in its current state.');
            }

            /** @var Conversation $conversation */
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($lockedAssignment->conversation_id);

            $stateMachine = $this->stateMachineFactory->get($conversation, 'conversation');

            if (! $stateMachine->can('return_to_agent')) {
                abort(409, 'Conversation cannot be returned to agent in its current state.');
            }

            $stateMachine->apply('return_to_agent', false, [
                'actor_id' => $request->actorId(),
                'assignment_user_id' => $lockedAssignment->user_id,
                'released_at' => now(),
                'channel' => 'api',
                'release_reason' => $request->reason(),
            ]);

            $lockedAssignment->refresh();

            if ($request->reason()) {
                $lockedAssignment->metadata = array_merge($lockedAssignment->metadata ?? [], [
                    'release_reason' => $request->reason(),
                ]);
                $lockedAssignment->save();
            }

            return $lockedAssignment->fresh(['user.roles', 'user.skills', 'queue']);
        });

        return AssignmentResource::make($released)
            ->response()
            ->setStatusCode(200);
    }

    public function resolve(ResolveAssignmentRequest $request, Assignment $assignment): JsonResponse
    {
        if ($assignment->status !== Assignment::STATUS_HUMAN_WORKING) {
            abort(409, 'Assignment cannot be resolved in its current state.');
        }

        $resolved = DB::transaction(function () use ($assignment, $request) {
            /** @var Assignment $lockedAssignment */
            $lockedAssignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ($lockedAssignment->status !== Assignment::STATUS_HUMAN_WORKING) {
                abort(409, 'Assignment cannot be resolved in its current state.');
            }

            /** @var Conversation $conversation */
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($lockedAssignment->conversation_id);

            $stateMachine = $this->stateMachineFactory->get($conversation, 'conversation');

            if (! $stateMachine->can('resolve')) {
                abort(409, 'Conversation cannot be resolved in its current state.');
            }

            $context = [
                'actor_id' => $request->actorId(),
                'assignment_user_id' => $lockedAssignment->user_id,
                'resolved_at' => now(),
                'resolution_summary' => $request->summary(),
                'channel' => 'api',
            ];

            $stateMachine->apply('resolve', false, $context);

            if ($stateMachine->can('archive')) {
                $stateMachine->apply('archive', false, $context);
            }

            $lockedAssignment->refresh();

            $lockedAssignment->metadata = array_merge($lockedAssignment->metadata ?? [], [
                'resolution_summary' => $request->summary(),
            ]);
            $lockedAssignment->save();

            return $lockedAssignment->fresh(['user.roles', 'user.skills', 'queue']);
        });

        return AssignmentResource::make($resolved)
            ->response()
            ->setStatusCode(200);
    }
}
