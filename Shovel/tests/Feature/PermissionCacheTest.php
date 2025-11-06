<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::setDefaultDriver('array');
    Cache::store()->flush();

    Role::flushCache();
    Permission::flushCache();
});

it('caches permissions per guard and invalidates on changes', function () {
    $initialCount = Permission::allCached()->count();

    Permission::query()->create([
        'name' => 'View Dashboard',
        'slug' => 'dashboard.view',
        'guard_name' => 'web',
    ]);

    Permission::query()->create([
        'name' => 'Manage Users',
        'slug' => 'users.manage',
        'guard_name' => 'web',
    ]);

    expect(Permission::allCached())->toHaveCount($initialCount + 2);

    Permission::query()->create([
        'name' => 'Manage Queues',
        'slug' => 'queues.manage',
        'guard_name' => 'web',
    ]);

    expect(Permission::allCached())->toHaveCount($initialCount + 3);

    Permission::flushCache();

    expect(Permission::allCached())->toHaveCount($initialCount + 3);
});

it('honors guard-specific permission lookups', function () {
    config(['auth.guards.api' => ['driver' => 'session', 'provider' => 'users']]);

    $initialApiCount = Permission::allCached('api')->count();
    $initialWebCount = Permission::allCached('web')->count();

    Permission::query()->create([
        'name' => 'API Metric View',
        'slug' => 'api.metric.view',
        'guard_name' => 'api',
    ]);

    Permission::query()->create([
        'name' => 'Web Metric View',
        'slug' => 'web.metric.view',
        'guard_name' => 'web',
    ]);

    expect(Permission::allCached('api'))->toHaveCount($initialApiCount + 1);
    expect(Permission::allCached('web'))->toHaveCount($initialWebCount + 1);
});
