<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Webhook extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * @return list<string>
     */
    public static function availableEvents(): array
    {
        return config('webhooks.available', []);
    }

    /**
     * @return string|null
     */
    public function secretLastFour(): ?string
    {
        $encrypted = $this->getAttribute('secret');

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $secret = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }

        return substr($secret, -4) ?: null;
    }

    /**
     * @return string|null
     */
    public function maskedSecret(): ?string
    {
        $lastFour = $this->secretLastFour();

        if ($lastFour === null) {
            return null;
        }

        return '******' . $lastFour;
    }
}
