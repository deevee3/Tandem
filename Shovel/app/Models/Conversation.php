<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_AGENT_WORKING = 'agent_working';
    public const STATUS_NEEDS_HUMAN = 'needs_human';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_HUMAN_WORKING = 'human_working';
    public const STATUS_BACK_TO_AGENT = 'back_to_agent';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'subject',
        'status',
        'priority',
        'requester_type',
        'requester_identifier',
        'case_id',
        'last_activity_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_activity_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_AGENT_WORKING,
            self::STATUS_NEEDS_HUMAN,
            self::STATUS_QUEUED,
            self::STATUS_ASSIGNED,
            self::STATUS_HUMAN_WORKING,
            self::STATUS_BACK_TO_AGENT,
            self::STATUS_RESOLVED,
            self::STATUS_ARCHIVED,
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function handoffs(): HasMany
    {
        return $this->hasMany(Handoff::class);
    }

    public function queueItems(): HasMany
    {
        return $this->hasMany(QueueItem::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(Assignment::class)->latestOfMany('assigned_at')->whereNull('released_at');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(Evaluation::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }
}
