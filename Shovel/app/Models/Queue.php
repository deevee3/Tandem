<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Queue extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
        'skills_required' => 'array',
        'priority_policy' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(QueueItem::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'queue_user')->withTimestamps();
    }
}
