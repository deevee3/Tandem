<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/** @var class-string<Role> */
use App\Models\Role;
/** @var class-string<Permission> */
use App\Models\Permission;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'workos_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'workos_id',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if ($user->workos_id && blank($user->password)) {
                $user->password = Str::random(40);
            }
            
            // Auto-generate username if not provided
            if (blank($user->username)) {
                $user->username = self::generateUsername();
            }
        });
    }
    
    /**
     * Generate a unique username in format: USR + 12 digits
     */
    public static function generateUsername(): string
    {
        do {
            // Generate USR prefix + 12 random digits
            $username = 'USR' . str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        } while (self::where('username', $username)->exists());
        
        return $username;
    }

    /**
     * @return BelongsToMany<Skill>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'user_skill')
            ->withTimestamps()
            ->withPivot('level');
    }

    /**
     * @return BelongsToMany<Role>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    public function hasAnyRole(array $roleSlugs): bool
    {
        if ($roleSlugs === []) {
            return false;
        }

        return $this->roles()->whereIn('slug', $roleSlugs)->exists();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permissionSlug))
            ->exists();
    }

    /**
     * @return BelongsToMany<Queue>
     */
    public function queues(): BelongsToMany
    {
        return $this->belongsToMany(Queue::class, 'queue_user')->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Assignment>
     */
    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
