<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_route_redirects_to_student_registration_flow(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect('/register/student');
    }

    public function test_legacy_register_post_endpoint_is_not_available(): void
    {
        $response = $this->post('/register');

        $response->assertRedirect('/register/student');
    }
}
