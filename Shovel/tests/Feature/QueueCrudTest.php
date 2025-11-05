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
}
