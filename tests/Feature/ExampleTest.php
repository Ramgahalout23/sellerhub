<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests are sent to the login screen from the dashboard.
     */
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    /**
     * Authenticated users reach the dashboard.
     */
    public function test_authenticated_users_see_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertOk();
    }
}
