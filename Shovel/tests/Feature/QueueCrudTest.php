<?php

namespace Tests\Feature;

use App\Models\Queue;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_it_lists_queues_with_pagination(): void
    {
        Queue::factory()->count(5)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/admin/api/queues');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'is_default',
                        'sla_first_response_minutes',
                        'sla_resolution_minutes',
                        'skills_required',
                        'priority_policy',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                    ],
                ],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_it_creates_a_queue(): void
    {
        $skill = Skill::factory()->create();

        $queueData = [
            'name' => 'Support Queue',
            'slug' => 'support-queue',
            'description' => 'Queue for support conversations',
            'is_default' => false,
            'skills_required' => [$skill->id],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/queues', $queueData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Support Queue',
                'slug' => 'support-queue',
            ]);

        $this->assertDatabaseHas('queues', [
            'name' => 'Support Queue',
            'slug' => 'support-queue',
        ]);
    }

    public function test_it_auto_generates_slug_when_not_provided(): void
    {
        $queueData = [
            'name' => 'Sales Queue',
            'description' => 'Queue for sales conversations',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/queues', $queueData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Sales Queue',
                'slug' => 'sales-queue',
            ]);
    }

    public function test_it_unsets_other_default_queues_when_creating_a_new_default(): void
    {
        $existingDefault = Queue::factory()->create(['is_default' => true]);

        $queueData = [
            'name' => 'New Default Queue',
            'is_default' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/queues', $queueData);

        $response->assertStatus(201);

        $existingDefault->refresh();
        $this->assertFalse($existingDefault->is_default);

        $newQueue = Queue::where('name', 'New Default Queue')->first();
        $this->assertTrue($newQueue->is_default);
    }

    public function test_it_updates_a_queue(): void
    {
        $queue = Queue::factory()->create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/admin/api/queues/{$queue->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Name',
                'slug' => 'updated-slug',
            ]);

        $this->assertDatabaseHas('queues', [
            'id' => $queue->id,
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
        ]);
    }

    public function test_it_deletes_a_queue(): void
    {
        $queue = Queue::factory()->create(['is_default' => false]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/admin/api/queues/{$queue->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Queue deleted successfully',
            ]);

        $this->assertDatabaseMissing('queues', [
            'id' => $queue->id,
        ]);
    }

    public function test_it_prevents_deletion_of_default_queue(): void
    {
        $queue = Queue::factory()->create(['is_default' => true]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/admin/api/queues/{$queue->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete the default queue',
            ]);

        $this->assertDatabaseHas('queues', [
            'id' => $queue->id,
        ]);
    }

    public function test_it_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/queues', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_validates_unique_slug(): void
    {
        Queue::factory()->create(['slug' => 'existing-slug']);

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/queues', [
                'name' => 'New Queue',
                'slug' => 'existing-slug',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_it_creates_queue_with_sla_configuration(): void
    {
        $queueData = [
            'name' => 'SLA Queue',
            'sla_first_response_minutes' => 30,
            'sla_resolution_minutes' => 240,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/queues', $queueData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'SLA Queue',
                'sla_first_response_minutes' => 30,
                'sla_resolution_minutes' => 240,
            ]);

        $this->assertDatabaseHas('queues', [
            'name' => 'SLA Queue',
            'sla_first_response_minutes' => 30,
            'sla_resolution_minutes' => 240,
        ]);
    }

    public function test_it_creates_queue_with_default_sla_values(): void
    {
        $queueData = [
            'name' => 'Default SLA Queue',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/queues', $queueData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'sla_first_response_minutes' => 15,
                'sla_resolution_minutes' => 120,
            ]);
    }

    public function test_it_creates_queue_with_priority_policy(): void
    {
        $queueData = [
            'name' => 'Priority Queue',
            'priority_policy' => [
                'urgent_threshold_minutes' => 20,
                'high_threshold_minutes' => 45,
                'auto_escalate' => true,
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/queues', $queueData);

        $response->assertStatus(201);

        $queue = Queue::where('name', 'Priority Queue')->first();
        $this->assertNotNull($queue->priority_policy);
        $this->assertEquals(20, $queue->priority_policy['urgent_threshold_minutes']);
        $this->assertEquals(45, $queue->priority_policy['high_threshold_minutes']);
        $this->assertTrue($queue->priority_policy['auto_escalate']);
    }

    public function test_it_updates_queue_sla_configuration(): void
    {
        $queue = Queue::factory()->create([
            'sla_first_response_minutes' => 15,
            'sla_resolution_minutes' => 120,
        ]);

        $updateData = [
            'sla_first_response_minutes' => 45,
            'sla_resolution_minutes' => 300,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/admin/api/queues/{$queue->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'sla_first_response_minutes' => 45,
                'sla_resolution_minutes' => 300,
            ]);

        $this->assertDatabaseHas('queues', [
            'id' => $queue->id,
            'sla_first_response_minutes' => 45,
            'sla_resolution_minutes' => 300,
        ]);
    }

    public function test_it_validates_sla_minutes_are_positive(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/queues', [
                'name' => 'Invalid SLA Queue',
                'sla_first_response_minutes' => 0,
                'sla_resolution_minutes' => -10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sla_first_response_minutes', 'sla_resolution_minutes']);
    }

    public function test_it_assigns_users_to_queue(): void
    {
        $queue = Queue::factory()->create();
        $users = User::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->postJson("/admin/api/queues/{$queue->id}/users", [
                'user_ids' => $users->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Users assigned successfully',
            ]);

        $this->assertCount(3, $queue->fresh()->users);
        foreach ($users as $user) {
            $this->assertTrue($queue->users->contains($user));
        }
    }

    public function test_it_syncs_users_replacing_existing_assignments(): void
    {
        $queue = Queue::factory()->create();
        $oldUsers = User::factory()->count(2)->create();
        $newUsers = User::factory()->count(2)->create();

        $queue->users()->attach($oldUsers->pluck('id'));

        $response = $this->actingAs($this->user)
            ->postJson("/admin/api/queues/{$queue->id}/users", [
                'user_ids' => $newUsers->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200);

        $queue->refresh();
        $this->assertCount(2, $queue->users);
        foreach ($newUsers as $user) {
            $this->assertTrue($queue->users->contains($user));
        }
        foreach ($oldUsers as $user) {
            $this->assertFalse($queue->users->contains($user));
        }
    }

    public function test_it_unassigns_user_from_queue(): void
    {
        $queue = Queue::factory()->create();
        $users = User::factory()->count(3)->create();
        $queue->users()->attach($users->pluck('id'));

        $userToRemove = $users->first();

        $response = $this->actingAs($this->user)
            ->deleteJson("/admin/api/queues/{$queue->id}/users/{$userToRemove->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User unassigned successfully',
            ]);

        $queue->refresh();
        $this->assertCount(2, $queue->users);
        $this->assertFalse($queue->users->contains($userToRemove));
    }

    public function test_it_validates_user_ids_when_assigning(): void
    {
        $queue = Queue::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/admin/api/queues/{$queue->id}/users", [
                'user_ids' => [999, 1000], // Non-existent user IDs
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids.0', 'user_ids.1']);
    }

    public function test_it_includes_users_in_queue_list(): void
    {
        $queue = Queue::factory()->create();
        $users = User::factory()->count(2)->create();
        $queue->users()->attach($users->pluck('id'));

        $response = $this->actingAs($this->user)
            ->getJson('/admin/api/queues');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'users' => [
                            '*' => [
                                'id',
                                'name',
                                'email',
                                'username',
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
