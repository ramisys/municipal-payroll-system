<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * routes/web.php sends an unauthenticated visitor straight to sign-in
     * (UC-01, AC-0.1.1 — no function is reachable without a session).
     */
    public function test_a_guest_visiting_the_root_is_redirected_to_sign_in(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
