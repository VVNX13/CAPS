<?php

namespace App\Jobs;

use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends a web push notification to the devices of the given users.
 *
 * Runs on the queue so a slow or unreachable push service can never delay a
 * customer's checkout. Requires a worker: `php artisan queue:work`.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $userIds,
        public array $payload,
        public int $tries = 2,
    ) {}

    public function handle(): void
    {
        if ($this->userIds === []) {
            return;
        }

        PushNotificationService::sendToUsers($this->userIds, $this->payload);
    }
}
