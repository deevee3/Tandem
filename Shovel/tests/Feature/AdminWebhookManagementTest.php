<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AdminWebhookManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $authorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::query()->firstOrCreate(
            [
                'slug' => 'webhooks.manage',
                'guard_name' => 'web',
            ],
            [
                'name' => 'Manage Webhooks',
            ]
        );

        $role = Role::query()->firstOrCreate(
            [
                'slug' => 'administrator',
                'guard_name' => 'web',
            ],
            [
                'name' => 'Administrator',
            ]
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->roles()->syncWithoutDetaching([$role->id]);
    }

    public function test_index_returns_filtered_webhooks(): void
    {
        Webhook::query()->create([
            'name' => 'Support Notifications',
            'url' => 'https://example.com/support-webhook',
            'events' => ['message.created', 'handoff.created'],
            'secret' => Crypt::encryptString('whsk_secret_support_5678'),
            'active' => true,
            'metadata' => ['team' => 'support'],
        ]);

        Webhook::query()->create([
            'name' => 'Legacy Integration',
            'url' => 'https://legacy.example.com/webhook',
            'events' => ['assignment.created'],
            'secret' => Crypt::encryptString('whsk_secret_legacy_9012'),
            'active' => false,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->getJson('/admin/api/webhooks?search=Support&active=1&event=message.created');

        $response->assertStatus(200)
            ->assertJsonPath('meta.filters.search', 'Support')
            ->assertJsonPath('meta.filters.active', true)
            ->assertJsonPath('meta.filters.event', 'message.created')
            ->assertJsonPath('meta.available_events', config('webhooks.available'));

        $this->assertCount(1, $response->json('data'));
        $webhook = $response->json('data.0');

        $this->assertEquals('Support Notifications', $webhook['name']);
        $this->assertEquals(['message.created', 'handoff.created'], $webhook['events']);
        $this->assertEquals('5678', $webhook['secret_last_four']);
        $this->assertEquals('******5678', $webhook['masked_secret']);
    }

    public function test_store_creates_webhook_and_returns_plaintext_secret(): void
    {
        $events = collect(config('webhooks.available'))
            ->take(2)
            ->flatMap(fn ($event) => [$event, " {$event} "])
            ->all();

        $response = $this->actingAs($this->authorizedUser)->postJson('/admin/api/webhooks', [
            'name' => 'Operations Events',
            'url' => 'https://example.com/webhooks/ops',
            'events' => $events,
            'metadata' => ['environment' => 'staging'],
        ]);

        $response->assertStatus(201);

        $plainSecret = $response->json('plain_text_secret');
        $this->assertNotNull($plainSecret);
        $this->assertStringStartsWith('whsk_', $plainSecret);

        $record = Webhook::query()->where('name', 'Operations Events')->first();
        $this->assertNotNull($record);
        $this->assertTrue($record->active);
        $this->assertEqualsCanonicalizing(collect(config('webhooks.available'))->take(2)->all(), $record->events);
        $this->assertEquals(['environment' => 'staging'], $record->metadata);
        $this->assertEquals($plainSecret, Crypt::decryptString($record->secret));
    }

    public function test_show_returns_masked_secret_without_plaintext(): void
    {
        $webhook = Webhook::query()->create([
            'name' => 'QA Webhook',
            'url' => 'https://example.com/webhooks/qa',
            'events' => ['conversation.updated'],
            'secret' => Crypt::encryptString('whsk_qa_secret_3456'),
            'active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)->getJson("/admin/api/webhooks/{$webhook->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals('3456', $data['secret_last_four']);
        $this->assertEquals('******3456', $data['masked_secret']);
        $this->assertArrayNotHasKey('plain_text_secret', $response->json());
    }

    public function test_update_allows_field_changes_without_secret_rotation(): void
    {
        $webhook = Webhook::query()->create([
            'name' => 'Automation Hook',
            'url' => 'https://example.com/webhooks/automation',
            'events' => ['assignment.created'],
            'secret' => Crypt::encryptString('whsk_automation_secret'),
            'active' => true,
            'metadata' => ['source' => 'initial'],
        ]);

        $newEvent = collect(config('webhooks.available'))->last();

        $response = $this->actingAs($this->authorizedUser)->putJson("/admin/api/webhooks/{$webhook->id}", [
            'name' => 'Automation Hook v2',
            'url' => 'https://example.net/webhooks/automation',
            'events' => [$newEvent],
            'metadata' => ['source' => 'updated'],
            'active' => false,
        ]);

        $response->assertStatus(200);
        $this->assertNull($response->json('plain_text_secret'));

        $webhook->refresh();
        $this->assertEquals('Automation Hook v2', $webhook->name);
        $this->assertEquals('https://example.net/webhooks/automation', $webhook->url);
        $this->assertEquals([$newEvent], $webhook->events);
        $this->assertEquals(['source' => 'updated'], $webhook->metadata);
        $this->assertFalse($webhook->active);
        $this->assertEquals('whsk_automation_secret', Crypt::decryptString($webhook->secret));
    }

    public function test_update_can_rotate_secret(): void
    {
        $webhook = Webhook::query()->create([
            'name' => 'Rotation Hook',
            'url' => 'https://example.com/webhooks/rotation',
            'events' => ['handoff.created'],
            'secret' => Crypt::encryptString('whsk_original_secret'),
            'active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)->putJson("/admin/api/webhooks/{$webhook->id}", [
            'rotate_secret' => true,
        ]);

        $response->assertStatus(200);
        $newSecret = $response->json('plain_text_secret');
        $this->assertNotNull($newSecret);
        $this->assertStringStartsWith('whsk_', $newSecret);

        $webhook->refresh();
        $this->assertEquals($newSecret, Crypt::decryptString($webhook->secret));
    }

    public function test_destroy_deletes_webhook(): void
    {
        $webhook = Webhook::query()->create([
            'name' => 'Temporary Hook',
            'url' => 'https://example.com/webhooks/temp',
            'events' => ['conversation.resolved'],
            'secret' => Crypt::encryptString('whsk_temp_secret'),
            'active' => true,
        ]);

        $response = $this->actingAs($this->authorizedUser)->deleteJson("/admin/api/webhooks/{$webhook->id}");
        $response->assertStatus(200)
            ->assertJson(['message' => 'Webhook deleted successfully.']);

        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    public function test_permission_is_required_for_webhook_routes(): void
    {
        $unauthorized = User::factory()->create();

        $this->actingAs($unauthorized)
            ->getJson('/admin/api/webhooks')
            ->assertForbidden();

        $this->actingAs($unauthorized)
            ->postJson('/admin/api/webhooks', ['name' => 'Unauthorized'])
            ->assertForbidden();
    }
}
