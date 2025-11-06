<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Queue;
use App\Models\Role;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HumanMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_human_agent_can_send_message_with_user_id(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create(['name' => 'Human Agent']);
        $skill = Skill::factory()->create(['name' => 'Customer Support']);
        
        $user->roles()->attach($role->id);
        $user->skills()->attach($skill->id, ['level' => 'expert']);

        $conversation = Conversation::factory()->create([
            'status' => Conversation::STATUS_HUMAN_WORKING,
        ]);

        $response = $this->actingAs($user)->postJson("/api/conversations/{$conversation->id}/human-messages", [
            'content' => 'Hi! I stepped in to confirm the final configuration.',
            'metadata' => ['note' => 'First touch human reply'],
        ]);

        $response->assertStatus(201);

        // Verify message was created with correct attributes
        $message = Message::where('conversation_id', $conversation->id)
            ->where('sender_type', Message::SENDER_HUMAN)
            ->first();

        $this->assertNotNull($message);
        $this->assertEquals($user->id, $message->user_id);
        $this->assertEquals('Hi! I stepped in to confirm the final configuration.', $message->content);
        $this->assertEquals(Message::SENDER_HUMAN, $message->sender_type);
        $this->assertEquals(['note' => 'First touch human reply'], $message->metadata);
    }

    public function test_human_message_includes_user_context(): void
    {
        $user = User::factory()->create(['name' => 'Jane Smith']);
        $role = Role::factory()->create(['name' => 'Senior Agent']);
        $skill = Skill::factory()->create(['name' => 'Technical Support']);
        
        $user->roles()->attach($role->id);
        $user->skills()->attach($skill->id, ['level' => 'advanced']);

        $conversation = Conversation::factory()->create([
            'status' => Conversation::STATUS_HUMAN_WORKING,
        ]);

        $response = $this->actingAs($user)->postJson("/api/conversations/{$conversation->id}/human-messages", [
            'content' => 'Let me help you with that.',
        ]);

        $response->assertStatus(201);

        // Verify the message has user context
        $message = Message::with('user.roles', 'user.skills')
            ->where('conversation_id', $conversation->id)
            ->where('sender_type', Message::SENDER_HUMAN)
            ->first();

        $this->assertEquals('Jane Smith', $message->user->name);
        $this->assertCount(1, $message->user->roles);
        $this->assertEquals('Senior Agent', $message->user->roles->first()->name);
        $this->assertCount(1, $message->user->skills);
        $this->assertEquals('Technical Support', $message->user->skills->first()->name);
        $this->assertEquals('advanced', $message->user->skills->first()->pivot->level);
    }

    public function test_unauthenticated_user_cannot_send_human_message(): void
    {
        $conversation = Conversation::factory()->create();

        $response = $this->postJson("/api/conversations/{$conversation->id}/human-messages", [
            'content' => 'This should fail',
        ]);

        $response->assertStatus(401);
    }
}
