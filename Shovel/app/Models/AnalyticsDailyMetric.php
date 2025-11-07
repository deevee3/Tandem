<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsDailyMetric extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'occurred_on' => 'date',
        'value' => 'float',
        'metadata' => 'array',
    ];
}
