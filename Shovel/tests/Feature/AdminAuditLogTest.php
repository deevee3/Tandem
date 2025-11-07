<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Conversation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::query()->create([
            'name' => 'View Audit Logs',
            'slug' => 'audit-logs.view',
            'guard_name' => 'web',
        ]);

        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
            'guard_name' => 'web',
        ]);

        $role->permissions()->attach($permission->id);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($role->id);
    }

    public function test_audit_log_endpoint_requires_permission(): void
    {
        $userWithoutPermission = User::factory()->create();

        $this->actingAs($userWithoutPermission)
            ->getJson('/admin/api/audit-logs')
            ->assertForbidden();
    }

    public function test_audit_log_endpoint_returns_filtered_results(): void
    {
        $conversation = Conversation::factory()->create();
        $otherConversation = Conversation::factory()->create();

        $actor = User::factory()->create([
            'name' => 'Alice Agent',
        ]);

        $matchedEvent = AuditEvent::factory()
            ->for($conversation)
            ->for($actor, 'user')
            ->state([
                'event_type' => 'conversation.resolved',
                'subject_type' => Conversation::class,
                'subject_id' => $conversation->id,
                'payload' => [
                    'actor' => [
                        'id' => $actor->id,
                        'name' => $actor->name,
                        'email' => $actor->email,
                        'username' => $actor->username,
                    ],
                ],
                'channel' => 'system',
                'occurred_at' => Carbon::parse('2025-01-10 10:00:00'),
            ])
            ->create();

        AuditEvent::factory()
            ->for($otherConversation)
            ->state([
                'event_type' => 'conversation.created',
                'subject_type' => Conversation::class,
                'subject_id' => $otherConversation->id,
                'payload' => [
                    'actor' => [
                        'id' => null,
                        'name' => 'Bob Builder',
                        'email' => 'bob@example.test',
                        'username' => 'USRB0B',
                    ],
                ],
                'channel' => 'api',
                'occurred_at' => Carbon::parse('2025-01-05 14:00:00'),
            ])
            ->create();

        $query = http_build_query([
            'conversation_id' => $conversation->id,
            'event_type' => 'conversation.resolved',
            'occurred_from' => '2025-01-09',
            'occurred_to' => '2025-01-11',
            'actor' => 'Alice',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/api/audit-logs?' . $query)
            ->assertOk()
            ->assertJsonPath('meta.filters.conversation_id', $conversation->id)
            ->assertJsonPath('meta.filters.event_type', 'conversation.resolved')
            ->assertJsonPath('meta.filters.actor', 'Alice');

        $response->assertJsonPath('data.0.id', $matchedEvent->id);
        $response->assertJsonPath('data.0.user.name', $actor->name);
        $response->assertJsonPath('data.0.payload.actor.id', $actor->id);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.pagination.current_page'));
    }

    public function test_actor_filter_matches_payload_when_user_missing(): void
    {
        $conversation = Conversation::factory()->create();

        $payloadOnlyEvent = AuditEvent::factory()
            ->for($conversation)
            ->withoutUser()
            ->state([
                'event_type' => 'conversation.enqueue_for_human',
                'payload' => [
                    'actor' => [
                        'id' => null,
                        'name' => 'Payload Matcher',
                        'email' => 'matcher@example.test',
                        'username' => 'PAYLOAD1',
                    ],
                ],
            ])
            ->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/api/audit-logs?actor=matcher')
            ->assertOk();

        $response->assertJsonPath('data.0.id', $payloadOnlyEvent->id);
        $response->assertJsonPath('data.0.payload.actor.name', 'Payload Matcher');
        $this->assertCount(1, $response->json('data'));
    }
}
