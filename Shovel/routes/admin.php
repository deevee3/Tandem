<?php

use App\Http\Controllers\Admin\QueueController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SkillController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
    // TODO: Add admin permission middleware
])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return Inertia::render('admin/dashboard');
    })->name('dashboard');

    // Users & Roles Management
    Route::get('/users', function () {
        return Inertia::render('admin/users/index');
    })->name('users.index');

    Route::get('/users/{user}', [UserController::class, 'showProfile'])->name('users.show');

    // Users API endpoints
    Route::prefix('api/users')->name('api.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}', [UserController::class, 'show'])->name('show');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        Route::post('/{user}/skills', [UserController::class, 'syncSkills'])->name('sync-skills');
        Route::post('/{user}/avatar', [UserController::class, 'updateAvatar'])->name('update-avatar');
    });

    Route::get('/roles', function () {
        return Inertia::render('admin/roles/index');
    })->name('roles.index');

    Route::get('/roles/{role}', [RoleController::class, 'showProfile'])->name('roles.show');

    // Roles API endpoints
    Route::prefix('api/roles')->name('api.roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/all', [RoleController::class, 'all'])->name('all');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('/{role}', [RoleController::class, 'show'])->name('show');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    // Permissions API endpoints
    Route::prefix('api/permissions')->name('api.permissions.')->group(function () {
        Route::get('/all', function () {
            return response()->json([
                'data' => \App\Models\Permission::orderBy('name')->get(),
            ]);
        })->name('all');
    });

    // Skills Management
    Route::get('/skills', function () {
        return Inertia::render('admin/skills/index');
    })->name('skills.index');

    Route::get('/skills/{skill}', [SkillController::class, 'showProfile'])->name('skills.show');

    // Skills API endpoints
    Route::prefix('api/skills')->name('api.skills.')->group(function () {
        Route::get('/', [SkillController::class, 'index'])->name('index');
        Route::get('/all', [SkillController::class, 'all'])->name('all');
        Route::post('/', [SkillController::class, 'store'])->name('store');
        Route::get('/{skill}', [SkillController::class, 'show'])->name('show');
        Route::put('/{skill}', [SkillController::class, 'update'])->name('update');
        Route::delete('/{skill}', [SkillController::class, 'destroy'])->name('destroy');
    });

    // Queues Management
    Route::get('/queues', function () {
        return Inertia::render('admin/queues/index');
    })->name('queues.index');

    // Queues API endpoints
    Route::prefix('api/queues')->name('api.queues.')->group(function () {
        Route::get('/', [QueueController::class, 'index'])->name('index');
        Route::get('/all', [QueueController::class, 'all'])->name('all');
        Route::post('/', [QueueController::class, 'store'])->name('store');
        Route::get('/{queue}', [QueueController::class, 'show'])->name('show');
        Route::put('/{queue}', [QueueController::class, 'update'])->name('update');
        Route::delete('/{queue}', [QueueController::class, 'destroy'])->name('destroy');
    });

    // Handoff Policies Management
    Route::get('/handoff-policies', function () {
        return Inertia::render('admin/handoff-policies/index');
    })->name('handoff-policies.index');

    // API Keys Management
    Route::get('/api-keys', function () {
        return Inertia::render('admin/api-keys/index');
    })->name('api-keys.index');

    // Webhooks Management
    Route::get('/webhooks', function () {
        return Inertia::render('admin/webhooks/index');
    })->name('webhooks.index');

    // Analytics
    Route::get('/analytics', function () {
        return Inertia::render('admin/analytics/index');
    })->name('analytics.index');

    // Audit Logs
    Route::get('/audit-logs', function () {
        return Inertia::render('admin/audit-logs/index');
    })->name('audit-logs.index');
});
