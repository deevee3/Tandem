<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $authorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::query()->create([
            'name' => 'Manage API Keys',
            'slug' => 'api-keys.manage',
            'guard_name' => 'web',
        ]);

        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
            'guard_name' => 'web',
        ]);

        $role->permissions()->attach($permission->id);

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->roles()->attach($role->id);
    }

    public function test_index_returns_filtered_api_keys(): void
    {
        ApiKey::query()->create([
            'name' => 'Support Integration',
            'key' => hash('sha256', 'support_key'),
            'scopes' => ['queues.read'],
            'active' => true,
            'metadata' => ['channel' => 'support'],
        ]);

        ApiKey::query()->create([
            'name' => 'Legacy Key',
            'key' => hash('sha256', 'legacy_key'),
            'scopes' => ['queues.read'],
            'active' => false,
            'metadata' => ['channel' => 'legacy'],
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->getJson('/admin/api/api-keys?search=Support&active=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.filters.search', 'Support')
            ->assertJsonPath('meta.filters.active', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Support Integration', $response->json('data.0.name'));
    }

    public function test_store_creates_api_key_and_returns_plaintext(): void
    {
        $expiresAt = Carbon::now()->addDays(7)->toISOString();

        $response = $this->actingAs($this->authorizedUser)->postJson('/admin/api/api-keys', [
            'name' => 'Operations Bot',
            'scopes' => [' queues.read ', 'queues.read', 'queues.write'],
            'expires_at' => $expiresAt,
        ]);

        $response->assertStatus(201);

        $plain = $response->json('plain_text_key');
        $this->assertNotNull($plain);
        $this->assertStringStartsWith('shovel_', $plain);

        $record = ApiKey::query()->where('name', 'Operations Bot')->first();
        $this->assertNotNull($record);
        $this->assertEquals(hash('sha256', $plain), $record->key);
        $this->assertEqualsCanonicalizing(['queues.read', 'queues.write'], $record->scopes);
        $this->assertTrue($record->active);
        $this->assertEquals($this->authorizedUser->id, $record->user_id);
        $this->assertEquals(Carbon::parse($expiresAt)->toDateTimeString(), $record->expires_at->toDateTimeString());
    }

    public function test_update_allows_toggle_and_field_updates(): void
    {
        $apiKey = ApiKey::query()->create([
            'name' => 'Workflow Runner',
            'key' => hash('sha256', Str::random(20)),
            'scopes' => ['automation.trigger'],
            'active' => true,
            'expires_at' => Carbon::now()->addDay(),
            'user_id' => $this->authorizedUser->id,
            'metadata' => ['source' => 'tests'],
        ]);

        $response = $this->actingAs($this->authorizedUser)->putJson("/admin/api/api-keys/{$apiKey->id}", [
            'active' => false,
            'scopes' => ['automation.trigger', 'automation.manage'],
            'expires_at' => null,
            'user_id' => null,
        ]);

        $response->assertStatus(200);

        $apiKey->refresh();

        $this->assertFalse($apiKey->active);
        $this->assertEqualsCanonicalizing(['automation.trigger', 'automation.manage'], $apiKey->scopes);
        $this->assertNull($apiKey->expires_at);
        $this->assertNull($apiKey->user_id);
    }

    public function test_destroy_deactivates_api_key(): void
    {
        $apiKey = ApiKey::query()->create([
            'name' => 'Deprecated Key',
            'key' => hash('sha256', Str::random(20)),
            'scopes' => [],
            'active' => true,
            'metadata' => [],
        ]);

        $response = $this->actingAs($this->authorizedUser)->deleteJson("/admin/api/api-keys/{$apiKey->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'API key deactivated successfully.']);

        $this->assertFalse($apiKey->fresh()->active);

        $response = $this->actingAs($this->authorizedUser)->deleteJson("/admin/api/api-keys/{$apiKey->id}");
        $response->assertStatus(200)
            ->assertJson(['message' => 'API key already inactive.']);
    }

    public function test_permission_is_required_for_api_key_routes(): void
    {
        $unauthorized = User::factory()->create();

        $this->actingAs($unauthorized)
            ->getJson('/admin/api/api-keys')
            ->assertForbidden();

        $this->actingAs($unauthorized)
            ->postJson('/admin/api/api-keys', ['name' => 'No Access'])
            ->assertForbidden();
    }
}
