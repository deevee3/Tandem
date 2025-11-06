<?php

namespace App\Models;

use App\Models\Pivots\RolePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    use HasFactory;

    protected $guarded = [];
    
    protected $casts = [
        'guard_name' => 'string',
        'hourly_rate' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $role): void {
            // Auto-generate code if not provided
            if (blank($role->code)) {
                $role->code = self::generateCode();
            }
        });
        
        static::saved(fn (self $role) => static::flushCache($role->guard_name));
        static::deleted(fn (self $role) => static::flushCache($role->guard_name));
    }
    
    /**
     * Generate a unique role code in format: ROLE + 8 digits
     */
    public static function generateCode(): string
    {
        do {
            // Generate ROLE prefix + 8 random digits
            $code = 'ROLE' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (self::where('code', $code)->exists());
        
        return $code;
    }

    /**
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Permission>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->using(RolePermission::class)
            ->withTimestamps();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions()->where('slug', $permissionSlug)->exists();
    }

    public function scopeForGuard(Builder $query, ?string $guardName = null): Builder
    {
        return $query->where('guard_name', $guardName ?? static::defaultGuardName());
    }

    public static function defaultGuardName(): string
    {
        return (string) (config('auth.defaults.guard') ?? 'web');
    }

    public static function allCached(?string $guardName = null): Collection
    {
        $guard = $guardName ?? static::defaultGuardName();

        return Cache::rememberForever(static::cacheKey($guard), function () use ($guard) {
            return static::query()
                ->forGuard($guard)
                ->with('permissions')
                ->orderBy('name')
                ->get();
        });
    }

    public static function findBySlug(string $slug, ?string $guardName = null): ?self
    {
        return static::query()
            ->forGuard($guardName)
            ->where('slug', $slug)
            ->first();
    }

    public static function findBySlugOrFail(string $slug, ?string $guardName = null): self
    {
        return static::query()
            ->forGuard($guardName)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public static function flushCache(?string $guardName = null): void
    {
        if ($guardName !== null) {
            Cache::forget(static::cacheKey($guardName));

            return;
        }

        static::allGuardNames()->each(function (string $guard) {
            Cache::forget(static::cacheKey($guard));
        });
    }

    protected static function cacheKey(string $guardName): string
    {
        return "rbac.roles.{$guardName}";
    }

    /**
     * @return Collection<int, string>
     */
    protected static function allGuardNames(): Collection
    {
        $guards = collect(config('auth.guards', []))->keys();

        if ($guards->isEmpty()) {
            return collect([static::defaultGuardName()]);
        }

        if (! $guards->contains(static::defaultGuardName())) {
            $guards->push(static::defaultGuardName());
        }

        return $guards->unique()->values();
    }
}
