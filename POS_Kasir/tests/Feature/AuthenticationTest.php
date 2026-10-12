<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Portal Owner');
    }

    public function test_owner_area_redirects_guests_to_the_login_page(): void
    {
        $this->get('/owner/dashboard')->assertRedirect(route('login'));
    }

    public function test_users_can_authenticate_with_their_username(): void
    {
        $user = User::factory()->ownerMakanan()->create(['username' => 'owner_makanan']);

        $response = $this->postJson('/login', [
            'username' => 'owner_makanan',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'owner_makanan');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_only_accepts_a_username_and_not_an_email(): void
    {
        User::factory()->create(['email' => 'chef@example.com']);

        $this->postJson('/login', [
            'username' => 'chef@example.com',
            'password' => 'password123',
        ])->assertStatus(400)->assertJsonPath('success', false);

        $this->assertGuest();
    }

    public function test_login_is_rejected_with_a_wrong_password(): void
    {
        User::factory()->create(['username' => 'owner_minuman']);

        $this->postJson('/login', [
            'username' => 'owner_minuman',
            'password' => 'salah-total',
        ])->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Username atau password salah');

        $this->assertGuest();
    }

    public function test_api_endpoints_return_401_for_guests(): void
    {
        $this->getJson('/api/owner/orders')->assertStatus(401);
    }

    public function test_users_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
