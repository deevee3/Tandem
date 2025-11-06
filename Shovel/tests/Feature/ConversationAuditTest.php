<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Conversation;
use App\Models\Queue;
use App\Models\Role;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SM\Factory\FactoryInterface;
use Tests\TestCase;

class ConversationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Queue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->queue = Queue::factory()->create();
    }

    public function test_audit_event_captures_actor_with_roles_and_skills(): void
    {
        // Create roles and skills
        $role = Role::factory()->create(['name' => 'Human Agent', 'slug' => 'human-agent']);
        $skill = Skill::factory()->create(['name' => 'Technical Support']);
        
        // Assign to user
        $this->user->roles()->attach($role->id);
        $this->user->skills()->attach($skill->id, ['level' => 'expert']);

        // Create conversation and trigger state transition
        $conversation = Conversation::factory()->create(['status' => Conversation::STATUS_NEEDS_HUMAN]);
        
        $stateMachine = app(FactoryInterface::class)->get($conversation, 'conversation');
        $stateMachine->apply('enqueue_for_human', false, [
            'actor_id' => $this->user->id,
            'queue_id' => $this->queue->id,
            'channel' => 'api',
        ]);

        // Check audit event
        $auditEvent = AuditEvent::where('conversation_id', $conversation->id)
            ->where('event_type', 'conversation.enqueue_for_human')
            ->first();

        $this->assertNotNull($auditEvent);
        $this->assertEquals($this->user->id, $auditEvent->user_id);
        
        $payload = $auditEvent->payload;
        $this->assertArrayHasKey('actor', $payload);
        $this->assertEquals($this->user->id, $payload['actor']['id']);
        $this->assertEquals($this->user->name, $payload['actor']['name']);
        
        // Check roles in audit
        $this->assertArrayHasKey('roles', $payload['actor']);
        $this->assertCount(1, $payload['actor']['roles']);
        $this->assertEquals('Human Agent', $payload['actor']['roles'][0]['name']);
        
        // Check skills in audit
        $this->assertArrayHasKey('skills', $payload['actor']);
        $this->assertCount(1, $payload['actor']['skills']);
        $this->assertEquals('Technical Support', $payload['actor']['skills'][0]['name']);
        $this->assertEquals('expert', $payload['actor']['skills'][0]['level']);
    }

    public function test_audit_event_captures_queue_with_sla_and_policy(): void
    {
        $queue = Queue::factory()->create([
            'name' => 'Premium Support',
            'sla_first_response_minutes' => 5,
            'sla_resolution_minutes' => 30,
            'priority_policy' => [
                'urgent_threshold_minutes' => 10,
                'auto_escalate' => true,
            ],
        ]);

        $conversation = Conversation::factory()->create(['status' => Conversation::STATUS_NEEDS_HUMAN]);
        
        $stateMachine = app(FactoryInterface::class)->get($conversation, 'conversation');
        $stateMachine->apply('enqueue_for_human', false, [
            'actor_id' => $this->user->id,
            'queue_id' => $queue->id,
            'channel' => 'api',
        ]);

        $auditEvent = AuditEvent::where('conversation_id', $conversation->id)
            ->where('event_type', 'conversation.enqueue_for_human')
            ->first();

        $payload = $auditEvent->payload;
        $this->assertArrayHasKey('queue', $payload);
        $this->assertEquals('Premium Support', $payload['queue']['name']);
        $this->assertEquals(5, $payload['queue']['sla_first_response_minutes']);
        $this->assertEquals(30, $payload['queue']['sla_resolution_minutes']);
        $this->assertEquals(10, $payload['queue']['priority_policy']['urgent_threshold_minutes']);
        $this->assertTrue($payload['queue']['priority_policy']['auto_escalate']);
    }

    public function test_audit_event_captures_assigned_user_with_roles_and_skills(): void
    {
        $assignedUser = User::factory()->create();
        $role = Role::factory()->create(['name' => 'Senior Agent', 'slug' => 'senior-agent']);
        $skill = Skill::factory()->create(['name' => 'Billing']);
        
        $assignedUser->roles()->attach($role->id);
        $assignedUser->skills()->attach($skill->id, ['level' => 'advanced']);

        $conversation = Conversation::factory()->create(['status' => Conversation::STATUS_QUEUED]);
        
        $stateMachine = app(FactoryInterface::class)->get($conversation, 'conversation');
        $stateMachine->apply('assign_human', false, [
            'actor_id' => $this->user->id,
            'queue_id' => $this->queue->id,
            'assignment_user_id' => $assignedUser->id,
            'channel' => 'api',
        ]);

        $auditEvent = AuditEvent::where('conversation_id', $conversation->id)
            ->where('event_type', 'conversation.assign_human')
            ->first();

        $payload = $auditEvent->payload;
        $this->assertArrayHasKey('assigned_user', $payload);
        $this->assertEquals($assignedUser->id, $payload['assigned_user']['id']);
        $this->assertEquals($assignedUser->name, $payload['assigned_user']['name']);
        
        // Check assigned user's roles
        $this->assertArrayHasKey('roles', $payload['assigned_user']);
        $this->assertCount(1, $payload['assigned_user']['roles']);
        $this->assertEquals('Senior Agent', $payload['assigned_user']['roles'][0]['name']);
        
        // Check assigned user's skills
        $this->assertArrayHasKey('skills', $payload['assigned_user']);
        $this->assertCount(1, $payload['assigned_user']['skills']);
        $this->assertEquals('Billing', $payload['assigned_user']['skills'][0]['name']);
        $this->assertEquals('advanced', $payload['assigned_user']['skills'][0]['level']);
    }

    public function test_assignment_resource_includes_complete_user_context(): void
    {
        $assignedUser = User::factory()->create();
        $role = Role::factory()->create();
        $skill = Skill::factory()->create();
        
        $assignedUser->roles()->attach($role->id);
        $assignedUser->skills()->attach($skill->id, ['level' => 'intermediate']);

        $conversation = Conversation::factory()->create(['status' => Conversation::STATUS_QUEUED]);
        
        $assignment = $conversation->assignments()->create([
            'queue_id' => $this->queue->id,
            'user_id' => $assignedUser->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        // Load relationships
        $assignment->load('user.roles', 'user.skills', 'queue');

        // Test that the assignment has complete context
        $this->assertNotNull($assignment->user);
        $this->assertCount(1, $assignment->user->roles);
        $this->assertCount(1, $assignment->user->skills);
        $this->assertEquals('intermediate', $assignment->user->skills->first()->pivot->level);
        $this->assertNotNull($assignment->queue);
    }
}
