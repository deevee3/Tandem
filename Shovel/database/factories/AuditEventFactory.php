<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    public function definition(): array
    {
        $event = Arr::random([
            'conversation.created',
            'conversation.enqueue_for_human',
            'conversation.assign_human',
            'conversation.resolved',
        ]);

        return [
            'event_type' => $event,
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'subject_type' => Conversation::class,
            'subject_id' => fn (array $attributes) => $attributes['conversation_id'],
            'payload' => [
                'from' => 'pending',
                'to' => 'resolved',
                'actor' => [
                    'id' => null,
                    'name' => $this->faker->name(),
                    'email' => $this->faker->safeEmail(),
                    'username' => 'USR' . str_pad((string) $this->faker->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
                ],
            ],
            'channel' => $this->faker->randomElement(['system', 'api', 'human']),
            'occurred_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    public function withoutUser(): self
    {
        return $this->state(fn () => [
            'user_id' => null,
        ]);
    }
}
