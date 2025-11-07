<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuditLogService
{
    /**
     * Log an audit event
     *
     * @param string $eventType Event type (e.g., 'api_key.created', 'user.updated')
     * @param array $payload Additional data to store with the event
     * @param array $options Optional parameters:
     *   - actor_id: User ID of the actor (defaults to current authenticated user)
     *   - actor_type: Type of actor ('user', 'ai', 'system', 'api')
     *   - conversation_id: Related conversation ID
     *   - subject_type: Type of subject entity
     *   - subject_id: ID of subject entity
     *   - channel: Channel where event occurred ('web', 'api', 'system')
     *   - occurred_at: When the event occurred (defaults to now)
     */
    public function log(string $eventType, array $payload = [], array $options = []): void
    {
        $actorId = $options['actor_id'] ?? auth()->id();
        $actorType = $options['actor_type'] ?? 'user';
        $conversationId = $options['conversation_id'] ?? null;
        $subjectType = $options['subject_type'] ?? null;
        $subjectId = $options['subject_id'] ?? null;
        $channel = $options['channel'] ?? $this->detectChannel();
        $occurredAt = $options['occurred_at'] ?? now();

        // Enrich payload with actor information
        $enrichedPayload = $this->enrichPayload($payload, $actorId, $actorType);

        AuditEvent::create([
            'event_type' => $eventType,
            'conversation_id' => $conversationId,
            'user_id' => $actorType === 'user' ? $actorId : null,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $enrichedPayload,
            'channel' => $channel,
            'occurred_at' => $occurredAt instanceof Carbon ? $occurredAt : Carbon::parse($occurredAt),
        ]);
    }

    /**
     * Log an API key action
     */
    public function logApiKeyAction(string $action, $apiKey, array $additionalPayload = []): void
    {
        $payload = array_merge([
            'api_key_id' => $apiKey->id ?? null,
            'api_key_name' => $apiKey->name ?? null,
            'scopes' => $apiKey->scopes ?? [],
        ], $additionalPayload);

        $this->log(
            "api_key.{$action}",
            $payload,
            [
                'subject_type' => get_class($apiKey),
                'subject_id' => $apiKey->id ?? null,
            ]
        );
    }

    /**
     * Log a user action
     */
    public function logUserAction(string $action, User $user, array $additionalPayload = []): void
    {
        $payload = array_merge([
            'target_user_id' => $user->id,
            'target_user_name' => $user->name,
            'target_user_email' => $user->email,
        ], $additionalPayload);

        $this->log(
            "user.{$action}",
            $payload,
            [
                'subject_type' => User::class,
                'subject_id' => $user->id,
            ]
        );
    }

    /**
     * Log a resource action (create, update, delete)
     */
    public function logResourceAction(string $resourceType, string $action, $resource, array $additionalPayload = []): void
    {
        $payload = array_merge([
            'resource_id' => $resource->id ?? null,
            'resource_data' => $this->sanitizeResourceData($resource),
        ], $additionalPayload);

        $this->log(
            "{$resourceType}.{$action}",
            $payload,
            [
                'subject_type' => is_object($resource) ? get_class($resource) : null,
                'subject_id' => $resource->id ?? null,
            ]
        );
    }

    /**
     * Log a message creation
     */
    public function logMessage(string $messageType, int $conversationId, array $messageData): void
    {
        $actorType = $messageData['actor_type'] ?? 'user';
        
        $this->log(
            "message.{$messageType}",
            [
                'message_id' => $messageData['id'] ?? null,
                'content_preview' => isset($messageData['content']) 
                    ? substr($messageData['content'], 0, 100) 
                    : null,
                'actor_type' => $actorType,
            ],
            [
                'conversation_id' => $conversationId,
                'actor_type' => $actorType,
                'subject_type' => $messageData['subject_type'] ?? null,
                'subject_id' => $messageData['subject_id'] ?? null,
            ]
        );
    }

    /**
     * Log an AI action
     */
    public function logAiAction(string $action, int $conversationId, array $payload = []): void
    {
        $this->log(
            "ai.{$action}",
            $payload,
            [
                'conversation_id' => $conversationId,
                'actor_type' => 'ai',
                'channel' => 'system',
            ]
        );
    }

    /**
     * Log a system action
     */
    public function logSystemAction(string $action, array $payload = []): void
    {
        $this->log(
            "system.{$action}",
            $payload,
            [
                'actor_type' => 'system',
                'channel' => 'system',
            ]
        );
    }

    /**
     * Enrich payload with actor information
     */
    protected function enrichPayload(array $payload, ?int $actorId, string $actorType): array
    {
        $enriched = $payload;

        if ($actorType === 'user' && $actorId) {
            $user = DB::table('users')->where('id', $actorId)->first();
            if ($user) {
                $enriched['actor'] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'type' => 'user',
                ];

                // Add roles
                $roles = DB::table('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('user_roles.user_id', $actorId)
                    ->select('roles.id', 'roles.name', 'roles.slug', 'roles.code')
                    ->get();
                if ($roles->isNotEmpty()) {
                    $enriched['actor']['roles'] = $roles->toArray();
                }

                // Add skills
                $skills = DB::table('user_skill')
                    ->join('skills', 'skills.id', '=', 'user_skill.skill_id')
                    ->where('user_skill.user_id', $actorId)
                    ->select('skills.id', 'skills.name', 'user_skill.level')
                    ->get();
                if ($skills->isNotEmpty()) {
                    $enriched['actor']['skills'] = $skills->toArray();
                }
            }
        } elseif ($actorType === 'ai') {
            $enriched['actor'] = [
                'type' => 'ai',
                'name' => 'AI Assistant',
            ];
        } elseif ($actorType === 'system') {
            $enriched['actor'] = [
                'type' => 'system',
                'name' => 'System',
            ];
        } elseif ($actorType === 'api') {
            $enriched['actor'] = [
                'type' => 'api',
                'name' => 'API',
            ];
        }

        return $enriched;
    }

    /**
     * Detect the channel based on the current request
     */
    protected function detectChannel(): string
    {
        if (app()->runningInConsole()) {
            return 'system';
        }

        if (request()->is('api/*')) {
            return 'api';
        }

        return 'web';
    }

    /**
     * Sanitize resource data for logging
     */
    protected function sanitizeResourceData($resource): array
    {
        if (!is_object($resource)) {
            return [];
        }

        $data = $resource->toArray();

        // Remove sensitive fields
        $sensitiveFields = ['password', 'key', 'token', 'secret', 'plain_text_key'];
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}
