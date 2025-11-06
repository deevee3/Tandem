<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CoreDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Roles
        $roles = collect([
            ['name' => 'Administrator', 'slug' => 'admin', 'description' => 'Full platform access.'],
            ['name' => 'Supervisor', 'slug' => 'supervisor', 'description' => 'Oversees queues, QA, and guardrails.'],
            ['name' => 'Human Agent', 'slug' => 'human-agent', 'description' => 'Handles escalated conversations.'],
        ])->map(function (array $role) use ($now) {
            return array_merge($role, [
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        DB::table('roles')->upsert($roles->toArray(), ['slug', 'guard_name']);

        // Permissions (baseline set)
        $permissions = collect([
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view'],
            ['name' => 'Manage Users', 'slug' => 'users.manage'],
            ['name' => 'Manage Queues', 'slug' => 'queues.manage'],
            ['name' => 'Review Conversations', 'slug' => 'conversations.review'],
            ['name' => 'Adjust Guardrails', 'slug' => 'guardrails.adjust'],
            ['name' => 'Manage API Keys', 'slug' => 'api-keys.manage'],
        ])->map(fn (array $permission) => array_merge($permission, [
            'guard_name' => 'web',
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        DB::table('permissions')->upsert($permissions->toArray(), ['slug', 'guard_name']);

        // Role to permission mapping
        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');

        $rolePermissions = [
            'admin' => $permissionIds->values()->toArray(),
            'supervisor' => [
                $permissionIds['dashboard.view'],
                $permissionIds['conversations.review'],
                $permissionIds['queues.manage'],
            ],
            'human-agent' => [
                $permissionIds['dashboard.view'],
                $permissionIds['conversations.review'],
            ],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionList) {
            foreach ($permissionList as $permissionId) {
                DB::table('role_permissions')->updateOrInsert([
                    'role_id' => $roleIds[$roleSlug],
                    'permission_id' => $permissionId,
                ], ['created_at' => $now, 'updated_at' => $now]);
            }
        }

        // Default user acting as platform admin
        DB::table('users')->updateOrInsert([
            'email' => 'admin@tandem.app',
        ], [
            'name' => 'Tandem Admin',
            'password' => Hash::make('password'),
            'status' => 'active',
            'timezone' => 'UTC',
            'metadata' => json_encode(['seeded' => true]),
            'updated_at' => $now,
            'created_at' => $now,
        ]);

        $userId = DB::table('users')->where('email', 'admin@tandem.app')->value('id');

        DB::table('user_roles')->updateOrInsert([
            'user_id' => $userId,
            'role_id' => $roleIds['admin'],
        ], [
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Skills
        $skills = collect([
            ['name' => 'Billing'],
            ['name' => 'Compliance'],
            ['name' => 'Technical Support'],
        ])->map(fn (array $skill) => array_merge($skill, [
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        DB::table('skills')->upsert($skills->toArray(), ['name']);

        $technicalSkillId = DB::table('skills')->where('name', 'Technical Support')->value('id');

        DB::table('user_skill')->updateOrInsert([
            'user_id' => $userId,
            'skill_id' => $technicalSkillId,
        ], [
            'level' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->call(HandoffPolicySeeder::class);

        if ($seedKey = env('SEED_API_KEY')) {
            DB::table('api_keys')->updateOrInsert([
                'key' => hash('sha256', $seedKey),
            ], [
                'name' => 'Seed Developer Key',
                'scopes' => json_encode([]),
                'active' => true,
                'last_used_at' => null,
                'metadata' => json_encode(['seeded' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Default queue
        $queueId = DB::table('queues')->updateOrInsert([
            'slug' => 'default',
        ], [
            'name' => 'Default Queue',
            'description' => 'Primary queue for Tandem conversations.',
            'is_default' => true,
            'sla_first_response_minutes' => 15,
            'sla_resolution_minutes' => 120,
            'skills_required' => json_encode([]),
            'priority_policy' => json_encode(['high_threshold_minutes' => 60]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $queueId = DB::table('queues')->where('slug', 'default')->value('id');

        // Seed demo conversation
        $conversationId = DB::table('conversations')->insertGetId([
            'subject' => 'Demo conversation: Onboarding question',
            'status' => 'human_working',
            'priority' => 'standard',
            'requester_type' => 'customer',
            'requester_identifier' => 'cust_12345',
            'last_activity_at' => $now,
            'metadata' => json_encode(['source' => 'seed']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('messages')->upsert([
            [
                'conversation_id' => $conversationId,
                'sender_type' => 'requester',
                'user_id' => null,
                'content' => 'I need help finishing the Tandem onboarding.',
                'confidence' => null,
                'cost_usd' => null,
                'metadata' => json_encode(['channel' => 'email']),
                'created_at' => $now->copy()->subMinutes(10),
                'updated_at' => $now->copy()->subMinutes(10),
            ],
            [
                'conversation_id' => $conversationId,
                'sender_type' => 'agent',
                'user_id' => null,
                'content' => 'Thanks for reaching out! I am reviewing your onboarding progress now.',
                'confidence' => 0.82,
                'cost_usd' => 0.012,
                'metadata' => json_encode(['model' => 'gpt-4o']),
                'created_at' => $now->copy()->subMinutes(9),
                'updated_at' => $now->copy()->subMinutes(9),
            ],
            [
                'conversation_id' => $conversationId,
                'sender_type' => 'human',
                'user_id' => $userId,
                'content' => 'Hi! I stepped in to confirm the final configuration. Everything looks good—try connecting your CRM again.',
                'confidence' => null,
                'cost_usd' => null,
                'metadata' => json_encode(['note' => 'First-touch human reply']),
                'created_at' => $now->copy()->subMinutes(4),
                'updated_at' => $now->copy()->subMinutes(4),
            ],
        ], ['conversation_id', 'created_at'], ['content', 'confidence', 'cost_usd', 'metadata', 'updated_at']);

        DB::table('queue_items')->updateOrInsert([
            'queue_id' => $queueId,
            'conversation_id' => $conversationId,
            'state' => 'completed',
        ], [
            'enqueued_at' => $now->copy()->subMinutes(8),
            'dequeued_at' => $now->copy()->subMinutes(5),
            'metadata' => json_encode(['seeded' => true]),
            'created_at' => $now->copy()->subMinutes(8),
            'updated_at' => $now->copy()->subMinutes(5),
        ]);

        DB::table('assignments')->updateOrInsert([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
        ], [
            'queue_id' => $queueId,
            'assigned_at' => $now->copy()->subMinutes(8),
            'accepted_at' => $now->copy()->subMinutes(6),
            'released_at' => null,
            'resolved_at' => $now->copy()->subMinutes(2),
            'status' => 'resolved',
            'metadata' => json_encode(['seeded' => true]),
            'created_at' => $now->copy()->subMinutes(8),
            'updated_at' => $now->copy()->subMinutes(2),
        ]);

        DB::table('handoffs')->updateOrInsert([
            'conversation_id' => $conversationId,
            'reason_code' => 'low_confidence',
        ], [
            'confidence' => 0.35,
            'policy_hits' => json_encode([]),
            'required_skills' => json_encode(['Technical Support']),
            'metadata' => json_encode(['seeded' => true]),
            'created_at' => $now->copy()->subMinutes(8),
        ]);

        DB::table('evaluations')->updateOrInsert([
            'conversation_id' => $conversationId,
            'created_by' => $userId,
        ], [
            'user_id' => $userId,
            'score' => 95,
            'rubric' => json_encode(['resolution_quality' => 5, 'tone' => 5]),
            'comments' => 'Great handoff and resolution speed.',
            'created_at' => $now->copy()->subMinutes(1),
            'updated_at' => $now->copy()->subMinutes(1),
        ]);

        DB::table('audit_events')->updateOrInsert([
            'event_type' => 'conversation.resolved',
            'conversation_id' => $conversationId,
            'occurred_at' => $now->copy()->subMinutes(2),
        ], [
            'user_id' => $userId,
            'subject_type' => 'App\\Models\\Conversation',
            'subject_id' => $conversationId,
            'payload' => json_encode(['status_before' => 'human_working', 'status_after' => 'resolved']),
            'channel' => 'system',
            'created_at' => $now->copy()->subMinutes(2),
            'updated_at' => $now->copy()->subMinutes(2),
        ]);

        DB::table('guardrail_policies')->updateOrInsert([
            'scope' => 'cost_cap',
            'version' => 1,
        ], [
            'name' => 'Cost Cap Policy',
            'config' => json_encode(['cap_usd' => 0.25, 'fallback_model' => 'gpt-5-nano']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('guardrail_actions')->updateOrInsert([
            'action_id' => '01JSEEDCOSTCAP000000000000',
        ], [
            'guardrail' => 'cost_cap',
            'previous_config' => json_encode(['cap_usd' => 0.3]),
            'applied_config' => json_encode(['cap_usd' => 0.25]),
            'metrics_snapshot' => json_encode(['cost_per_conversation_p95' => 0.2]),
            'initiator' => 'orchestrator',
            'applied_at' => $now->copy()->subMinutes(3),
            'created_at' => $now->copy()->subMinutes(3),
            'updated_at' => $now->copy()->subMinutes(3),
        ]);

        DB::table('conversations')->where('id', $conversationId)->update([
            'status' => 'resolved',
            'last_activity_at' => $now->copy()->subMinutes(2),
            'updated_at' => $now->copy()->subMinutes(2),
        ]);
    }
}
