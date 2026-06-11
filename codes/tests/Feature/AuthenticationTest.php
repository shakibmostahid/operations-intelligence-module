<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_log_in(): void
    {
        $user = $this->user([
            'status' => 'active',
            'must_change_password' => false,
        ]);
        $csrfToken = 'test-csrf-token';

        $response = $this->withSession(['_token' => $csrfToken])->post('/login', [
            '_token' => $csrfToken,
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_temporary_password_user_is_sent_to_password_change(): void
    {
        $user = $this->user([
            'status' => 'active',
            'must_change_password' => true,
        ]);
        $csrfToken = 'test-csrf-token';

        $this->withSession(['_token' => $csrfToken])->post('/login', [
            '_token' => $csrfToken,
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/change-password');

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_is_rejected(): void
    {
        $user = $this->user([
            'status' => 'inactive',
            'must_change_password' => false,
        ]);
        $csrfToken = 'test-csrf-token';

        $this->withSession(['_token' => $csrfToken])->post('/login', [
            '_token' => $csrfToken,
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'Your account is inactive. Please contact support.',
        ]);

        $this->assertGuest();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function user(array $overrides): User
    {
        $role = Role::query()->create(['role' => 'support_engineer']);

        return User::factory()->create([
            'role_id' => $role->id,
            'password' => 'password',
            ...$overrides,
        ]);
    }
}
