<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'p256dh',
        'auth',
        'user_agent',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    /**
     * Never expose the subscription keys in array/JSON output.
     *
     * @var list<string>
     */
    protected $hidden = [
        'endpoint',
        'p256dh',
        'auth',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $subscription): void {
            $subscription->endpoint = trim((string) $subscription->endpoint);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convert the stored row into the array shape minishlink expects.
     *
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}}
     */
    public function toSubscription(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'keys' => [
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ],
        ];
    }
}
