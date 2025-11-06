<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $skill): void {
            // Auto-generate code if not provided and column exists
            if (blank($skill->code) && \Illuminate\Support\Facades\Schema::hasColumn('skills', 'code')) {
                $skill->code = self::generateCode();
            }
            
            // Auto-generate slug if not provided and column exists
            if (blank($skill->slug) && \Illuminate\Support\Facades\Schema::hasColumn('skills', 'slug')) {
                $skill->slug = \Illuminate\Support\Str::slug($skill->name);
            }
        });
    }
    
    /**
     * Generate a unique skill code in format: SKILL + 8 digits
     */
    public static function generateCode(): string
    {
        do {
            // Generate SKILL prefix + 8 random digits
            $code = 'SKILL' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (self::where('code', $code)->exists());
        
        return $code;
    }

    /**
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_skill')
            ->withTimestamps()
            ->withPivot('level');
    }

    /**
     * @return BelongsToMany<HandoffPolicy>
     */
    public function policies(): BelongsToMany
    {
        return $this->belongsToMany(HandoffPolicy::class, 'handoff_policy_skills')
            ->withTimestamps();
    }
}
