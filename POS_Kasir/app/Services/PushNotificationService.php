<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\OpenSslConfig;
use ErrorException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class PushNotificationService
{
    /**
     * Push services expect an https:// or mailto: contact for the VAPID subject.
     */
    public static function normaliseSubject(): string
    {
        $subject = (string) config('push.subject');

        return str_starts_with($subject, 'http')
            ? $subject
            : (str_starts_with($subject, 'mailto:') ? $subject : 'mailto:'.$subject);
    }

    public static function isConfigured(): bool
    {
        return (bool) config('push.public_key') && (bool) config('push.private_key');
    }

    /**
     * Build the VAPID client.
     *
     * minishlink/web-push v11 takes the VAPID credentials through the constructor's
     * `auth` array; the setPublicKey()/setPrivateKey() setters were removed in v11.
     */
    public static function client(): WebPush
    {
        OpenSslConfig::ensure();

        return new WebPush([
            'VAPID' => [
                'subject' => self::normaliseSubject(),
                'publicKey' => (string) config('push.public_key'),
                'privateKey' => (string) config('push.private_key'),
            ],
        ]);
    }

    /**
     * Send one notification to every subscription of the given users.
     *
     * Returns a summary so callers (and tests) can assert on the outcome.
     *
     * @param  list<int>  $userIds
     * @return array{sent: int, failed: int, removed: int}
     */
    public static function sendToUsers(array $userIds, array $payload, ?WebPush $client = null): array
    {
        $summary = ['sent' => 0, 'failed' => 0, 'removed' => 0];

        if ($userIds === [] || ! self::isConfigured()) {
            return $summary;
        }

        $subscriptions = PushSubscription::whereIn('user_id', $userIds)->get();

        if ($subscriptions->isEmpty()) {
            return $summary;
        }

        $client ??= self::client();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $row) {
            try {
                $subscription = Subscription::create($row->toSubscription());
            } catch (Throwable $e) {
                self::pruneInvalid($row, $summary);

                continue;
            }

            try {
                $result = $client->sendOneNotification($subscription, $json);

                if ($result->isSuccess()) {
                    $summary['sent']++;
                    $row->forceFill(['last_used_at' => now()])->saveQuietly();

                    continue;
                }

                $status = $result->getResponseStatusCode();

                if (in_array($status, [404, 410], true)) {
                    $summary['removed']++;
                    $row->delete();

                    continue;
                }

                $summary['failed']++;
                Log::warning('Push notification failed', [
                    'status' => $status,
                    'reason' => $result->getResponseReason(),
                    'user_id' => $row->user_id,
                ]);
            } catch (ErrorException|Throwable $e) {
                $summary['failed']++;
                Log::warning('Push notification error', ['message' => $e->getMessage()]);
            }
        }

        return $summary;
    }

    /**
     * Owner accounts that should be told about an order touching the given
     * owner types. Admins are always included.
     *
     * There are only a handful of staff accounts, so the scope check runs in
     * PHP through the same User::ownerTypes() the rest of the app relies on.
     *
     * @param  list<string>  $ownerTypes
     * @return list<int>
     */
    public static function recipientsFor(array $ownerTypes): array
    {
        $recipients = [];

        foreach (User::all() as $user) {
            $isInterested = $user->isAdmin()
                || count(array_intersect($user->ownerTypes(), $ownerTypes)) > 0;

            if ($isInterested) {
                $recipients[] = (int) $user->id;
            }
        }

        return $recipients;
    }

    /**
     * Endpoints that the push service no longer recognises are dead weight.
     */
    private static function pruneInvalid(PushSubscription $row, array &$summary): void
    {
        $summary['removed']++;
        $row->delete();
    }

    /**
     * @return Collection<int, PushSubscription>
     */
    public static function forUser(User $user): Collection
    {
        return PushSubscription::where('user_id', $user->id)->get();
    }
}
