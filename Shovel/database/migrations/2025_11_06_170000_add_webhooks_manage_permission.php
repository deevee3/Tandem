<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissionId = DB::table('permissions')
            ->where('slug', 'webhooks.manage')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'Manage Webhooks',
                'slug' => 'webhooks.manage',
                'guard_name' => 'web',
                'description' => 'Allows administrators to configure outbound webhooks.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('permissions')
                ->where('id', $permissionId)
                ->update([
                    'name' => 'Manage Webhooks',
                    'updated_at' => $now,
                ]);
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['admin', 'administrator'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ], [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('slug', 'webhooks.manage')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['admin', 'administrator'])
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($roleIds->isNotEmpty()) {
            DB::table('role_permissions')
                ->whereIn('role_id', $roleIds)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
};
