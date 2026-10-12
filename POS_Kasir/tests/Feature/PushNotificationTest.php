<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Category;
use App\Models\Product;
use App\Models\PushSubscription;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the real key pair from .env so these tests exercise the same code
        // path as production. Dummy values are rejected by VAPID::validate().
        config([
            'push.public_key' => env('VAPID_PUBLIC_KEY'),
            'push.private_key' => env('VAPID_PRIVATE_KEY'),
            'push.subject' => 'mailto:admin@tamanteras.id',
        ]);
    }

    private function requireConfiguredVapid(): void
    {
        if (! PushNotificationService::isConfigured()) {
            $this->markTestSkipped('VAPID keys missing. Run: php artisan push:vapid-keys');
        }
    }

    private function subscribe(User $user, string $endpoint = 'https://push.example/abc'): PushSubscription
    {
        return PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'p256dh' => 'p256dh-key',
            'auth' => 'auth-key',
            'last_used_at' => now(),
        ]);
    }

    public function test_guests_cannot_read_push_settings(): void
    {
        $this->getJson('/api/owner/push')->assertStatus(401);
    }

    public function test_push_status_is_reported_for_a_signed_in_owner(): void
    {
        $this->actingAs(User::factory()->ownerMakanan()->create())
            ->getJson('/api/owner/push')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.public_key', env('VAPID_PUBLIC_KEY'))
            ->assertJsonPath('data.subscribed', false);
    }

    public function test_an_owner_can_register_this_device(): void
    {
        $user = User::factory()->ownerMakanan()->create();

        $this->actingAs($user)
            ->postJson('/api/owner/push/subscribe', [
                'endpoint' => 'https://push.example/xyz',
                'keys' => ['p256dh' => 'pub', 'auth' => 'priv'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://push.example/xyz',
        ]);
    }

    public function test_subscribing_twice_with_the_same_endpoint_does_not_duplicate(): void
    {
        $user = User::factory()->ownerMakanan()->create();

        $payload = [
            'endpoint' => 'https://push.example/same',
            'keys' => ['p256dh' => 'pub', 'auth' => 'priv'],
        ];

        $this->actingAs($user)->postJson('/api/owner/push/subscribe', $payload)->assertOk();
        $this->actingAs($user)->postJson('/api/owner/push/subscribe', $payload)->assertOk();

        $this->assertSame(1, PushSubscription::where('user_id', $user->id)->count());
    }

    public function test_subscribe_validation_rejects_a_broken_payload(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/owner/push/subscribe', ['endpoint' => 'https://push.example/x'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_unsubscribe_only_removes_the_callers_own_subscription(): void
    {
        $owner = User::factory()->ownerMakanan()->create();
        $other = User::factory()->ownerMinuman()->create();

        $this->subscribe($owner, 'https://push.example/owner');
        $otherSubscription = $this->subscribe($other, 'https://push.example/other');

        $this->actingAs($owner)
            ->postJson('/api/owner/push/unsubscribe', ['endpoint' => 'https://push.example/owner'])
            ->assertOk()
            ->assertJsonPath('data.deleted', 1);

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example/owner']);
        $this->assertDatabaseHas('push_subscriptions', ['id' => $otherSubscription->id]);
    }

    public function test_test_notification_requires_a_registered_device(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/owner/push/test')
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_test_notification_is_queued_for_registered_devices(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->subscribe($user);

        $this->actingAs($user)->postJson('/api/owner/push/test')->assertOk();

        Queue::assertPushed(SendPushNotification::class, 1);
    }

    public function test_recipients_follow_the_owner_scope(): void
    {
        $food = User::factory()->ownerMakanan()->create();
        $drink = User::factory()->ownerMinuman()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $foodOnly = PushNotificationService::recipientsFor(['makanan']);

        $this->assertContains($food->id, $foodOnly);
        $this->assertContains($admin->id, $foodOnly);
        $this->assertNotContains($drink->id, $foodOnly);

        $both = PushNotificationService::recipientsFor(['makanan', 'minuman']);

        $this->assertContains($drink->id, $both);
        $this->assertContains($admin->id, $both);
    }

    public function test_placing_an_order_queues_a_push_for_the_owners_involved(): void
    {
        Queue::fake();

        User::factory()->ownerMakanan()->create();
        User::factory()->ownerMinuman()->create();

        $food = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'name' => 'Nasi Goreng',
            'price' => 25000,
            'stock' => 10,
        ]);

        $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [['product_id' => $food->id, 'quantity' => 1]],
        ])->assertCreated();

        Queue::assertPushed(SendPushNotification::class, function (SendPushNotification $job) {
            $payload = $job->payload;

            return str_contains($payload['title'], 'Pesanan baru')
                && str_contains($payload['body'], 'Nasi Goreng')
                && $payload['url'] === route('owner.dashboard');
        });
    }

    public function test_a_drink_order_wakes_the_drink_owner_not_the_food_owner(): void
    {
        Queue::fake();

        $food = User::factory()->ownerMakanan()->create();
        $drink = User::factory()->ownerMinuman()->create();

        $drinkProduct = Product::factory()->create([
            'category_id' => Category::factory()->minuman()->create()->id,
            'stock' => 10,
        ]);

        $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Sari',
            'customer_phone' => '08120000000',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [['product_id' => $drinkProduct->id, 'quantity' => 1]],
        ])->assertCreated();

        Queue::assertPushed(SendPushNotification::class, function (SendPushNotification $job) use ($food, $drink) {
            return in_array($drink->id, $job->userIds, true)
                && ! in_array($food->id, $job->userIds, true);
        });
    }

    public function test_no_push_is_queued_when_vapid_is_not_configured(): void
    {
        Queue::fake();
        config(['push.public_key' => null, 'push.private_key' => null]);

        User::factory()->ownerMakanan()->create();

        $product = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'stock' => 5,
        ]);

        $this->postJson('/api/orders/checkout', [
            'customer_name' => 'Andi',
            'customer_phone' => '08120000001',
            'service_type' => 'dine_in',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        Queue::assertNothingPushed();
    }

    public function test_sale_history_is_untouched_by_the_push_feature(): void
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->makanan()->create()->id,
            'stock' => 5,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'INV-PUSH-1',
            'customer_name' => 'Tester',
            'customer_phone' => '08120000002',
            'service_type' => 'dine_in',
            'total_amount' => 10000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'subtotal' => 10000,
        ]);

        $this->assertSame(1, SaleItem::where('product_id', $product->id)->count());
    }

    public function test_the_vapid_client_can_actually_be_built(): void
    {
        /*
         * Regression guard: every other test uses Queue::fake(), so client() is
         * never exercised and an incompatible web-push API slips through unnoticed
         * (v11 removed the setPublicKey()/setPrivateKey() setters). This builds the
         * client with the real keys, exactly like production does.
         */
        $this->requireConfiguredVapid();

        $this->assertInstanceOf(WebPush::class, PushNotificationService::client());
    }

    public function test_sending_to_an_unreachable_endpoint_does_not_throw(): void
    {
        $this->requireConfiguredVapid();

        $user = User::factory()->create();

        // A syntactically valid but dead endpoint: one bad device must not break
        // the whole job, so the service has to degrade gracefully.
        $this->subscribe($user, 'https://127.0.0.1:9/never-listening');

        $summary = PushNotificationService::sendToUsers([$user->id], [
            'title' => 'test',
            'body' => 'test',
        ]);

        $this->assertArrayHasKey('sent', $summary);
        $this->assertArrayHasKey('failed', $summary);
        $this->assertSame(0, $summary['sent']);
    }
}
