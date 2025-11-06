<?php

namespace Tests\Feature;

use App\Models\HandoffPolicy;
use App\Models\HandoffPolicyRule;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HandoffPolicyCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_it_lists_policies_with_pagination(): void
    {
        $policy = HandoffPolicy::factory()->create([
            'name' => 'Low Confidence Escalation',
            'reason_code' => 'low_confidence',
        ]);

        $skill = Skill::factory()->create([
            'name' => 'Compliance',
        ]);

        $policy->skills()->attach($skill);

        $policy->rules()->create([
            'trigger_type' => 'confidence_below_threshold',
            'criteria' => ['threshold' => 0.6],
            'priority' => 100,
            'active' => true,
        ]);

        HandoffPolicy::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/admin/api/handoff-policies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'reason_code',
                        'confidence_threshold',
                        'metadata',
                        'active',
                        'skills',
                        'rules',
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

        $this->assertNotEmpty($response->json('data'));
        $first = collect($response->json('data'))->firstWhere('reason_code', 'low_confidence');
        $this->assertNotNull($first);
        $this->assertNotEmpty($first['skills']);
        $this->assertNotEmpty($first['rules']);
    }

    public function test_it_creates_a_policy_with_skills_and_rules(): void
    {
        $skill = Skill::factory()->create();

        $payload = [
            'name' => 'PII Escalation',
            'reason_code' => 'PII Escalation',
            'confidence_threshold' => 0.55,
            'metadata' => ['seeded' => true],
            'active' => true,
            'skill_ids' => [$skill->id],
            'rules' => [[
                'trigger_type' => 'policy_flag_detected',
                'criteria' => ['flags' => ['pii', 'legal']],
                'priority' => 90,
                'active' => true,
            ]],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/handoff-policies', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'PII Escalation',
                'reason_code' => 'pii_escalation',
            ]);

        $policyId = $response->json('data.id');
        $this->assertNotNull($policyId);

        $this->assertDatabaseHas('handoff_policies', [
            'id' => $policyId,
            'name' => 'PII Escalation',
            'reason_code' => 'pii_escalation',
            'active' => true,
        ]);

        $this->assertDatabaseHas('handoff_policy_rules', [
            'handoff_policy_id' => $policyId,
            'trigger_type' => 'policy_flag_detected',
            'priority' => 90,
        ]);

        $this->assertDatabaseHas('handoff_policy_skills', [
            'handoff_policy_id' => $policyId,
            'skill_id' => $skill->id,
        ]);
    }

    public function test_it_validates_nested_rule_criteria(): void
    {
        $payload = [
            'name' => 'Tool Error Escalation',
            'reason_code' => 'Tool Error',
            'rules' => [[
                'trigger_type' => 'tool_error',
                'criteria' => ['retryable' => 'yes'],
                'priority' => 10,
            ]],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/handoff-policies', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rules.0.criteria.retryable']);
    }

    public function test_it_requires_unique_reason_codes(): void
    {
        HandoffPolicy::factory()->create([
            'reason_code' => 'duplicate_reason',
        ]);

        $payload = [
            'name' => 'Duplicate Reason Policy',
            'reason_code' => 'Duplicate Reason',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/handoff-policies', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason_code']);
    }

    public function test_it_updates_policy_rules_and_skills(): void
    {
        $originalSkill = Skill::factory()->create();
        $replacementSkill = Skill::factory()->create();

        $policy = HandoffPolicy::factory()->create([
            'name' => 'Original Policy',
            'reason_code' => 'original_policy',
            'active' => true,
        ]);

        $policy->skills()->attach($originalSkill);

        $existingRule = $policy->rules()->create([
            'trigger_type' => 'confidence_below_threshold',
            'criteria' => ['threshold' => 0.5],
            'priority' => 40,
            'active' => true,
        ]);

        $initiallyRemovedRule = $policy->rules()->create([
            'trigger_type' => 'policy_flag_detected',
            'criteria' => ['flags' => ['legal']],
            'priority' => 30,
            'active' => true,
        ]);

        $payload = [
            'name' => 'Updated Policy',
            'reason_code' => 'Updated Policy',
            'active' => false,
            'skill_ids' => [$replacementSkill->id],
            'rules' => [
                [
                    'id' => $existingRule->id,
                    'trigger_type' => 'confidence_below_threshold',
                    'criteria' => ['threshold' => 0.3],
                    'priority' => 80,
                    'active' => true,
                ],
                [
                    'trigger_type' => 'agent_requested_handoff',
                    'criteria' => [],
                    'priority' => 120,
                    'active' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/admin/api/handoff-policies/{$policy->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Policy',
                'reason_code' => 'updated_policy',
                'active' => false,
            ]);

        $policy->refresh();
        $policy->load('skills', 'rules');

        $this->assertEquals('Updated Policy', $policy->name);
        $this->assertFalse($policy->active);
        $this->assertEquals('updated_policy', $policy->reason_code);
        $this->assertEqualsCanonicalizing([$replacementSkill->id], $policy->skills->pluck('id')->all());

        $this->assertCount(2, $policy->rules);

        /** @var HandoffPolicyRule $updatedRule */
        $updatedRule = $policy->rules->firstWhere('id', $existingRule->id);
        $this->assertNotNull($updatedRule);
        $this->assertEquals(['threshold' => 0.3], $updatedRule->criteria);
        $this->assertEquals(80, $updatedRule->priority);

        $this->assertNotNull($policy->rules->firstWhere('trigger_type', 'agent_requested_handoff'));
        $this->assertDatabaseMissing('handoff_policy_rules', [
            'id' => $initiallyRemovedRule->id,
        ]);
    }

    public function test_it_deletes_a_policy(): void
    {
        $policy = HandoffPolicy::factory()->create([
            'name' => 'Disposable Policy',
        ]);

        $rule = $policy->rules()->create([
            'trigger_type' => 'tool_error',
            'criteria' => ['retryable' => false],
            'priority' => 10,
            'active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/admin/api/handoff-policies/{$policy->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Handoff policy deleted successfully',
            ]);

        $this->assertDatabaseMissing('handoff_policies', [
            'id' => $policy->id,
        ]);

        $this->assertDatabaseMissing('handoff_policy_rules', [
            'id' => $rule->id,
        ]);
    }

    public function test_metadata_must_be_an_array(): void
    {
        $payload = [
            'name' => 'Metadata Policy',
            'reason_code' => 'metadata_policy',
            'metadata' => 'not-an-array',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/admin/api/handoff-policies', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['metadata']);
    }
}
