<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsStore;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use BuildsStore, RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/admin/products')->assertRedirect('/login');
    }

    public function test_customers_cannot_open_admin(): void
    {
        $this->actingAs($this->user('customer'))->get('/admin')->assertForbidden();
    }

    public function test_role_permissions(): void
    {
        $matrix = [
            // route => [staff, manager, admin]
            '/admin' => [200, 200, 200],
            '/admin/orders' => [200, 200, 200],
            '/admin/inventory' => [200, 200, 200],
            '/admin/products' => [403, 200, 200],
            '/admin/customers' => [403, 200, 200],
            '/admin/discounts' => [403, 200, 200],
            '/admin/pages' => [403, 200, 200],
            '/admin/settings' => [403, 403, 200],
            '/admin/shipping-methods' => [403, 403, 200],
            '/admin/staff' => [403, 403, 200],
        ];
        $users = ['staff' => $this->user('staff'), 'manager' => $this->user('manager'), 'admin' => $this->user('admin')];

        foreach ($matrix as $url => $expected) {
            foreach (array_values($users) as $i => $user) {
                $this->actingAs($user)->get($url)->assertStatus($expected[$i]);
            }
        }
    }

    public function test_staff_are_redirected_to_admin_after_login(): void
    {
        $admin = $this->user('admin');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
    }

    public function test_deactivated_accounts_cannot_sign_in_or_keep_access(): void
    {
        $staff = User::factory()->role('manager')->create(['is_active' => false]);
        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($staff)->get('/admin')->assertRedirect('/login');
    }

    public function test_role_cannot_be_mass_assigned_through_registration_or_profile(): void
    {
        $this->post('/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'Password123!x', 'password_confirmation' => 'Password123!x', 'role' => 'admin']);
        $this->assertSame('customer', User::query()->where('email', 'eve@example.com')->value('role'));
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('store:create-admin', ['--name' => 'Owner', '--email' => 'owner@infinitenaturals.test', '--password' => 'a-long-secure-password'])
            ->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'owner@infinitenaturals.test', 'role' => 'admin', 'is_active' => true]);

        $this->artisan('store:create-admin', ['--name' => 'X', '--email' => 'x@example.com', '--password' => 'short'])->assertFailed();
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->put(route('admin.staff.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'staff', 'is_active' => 1,
        ])->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
    }
}
