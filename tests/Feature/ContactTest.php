<?php

namespace Tests\Feature;

use App\Mail\ContactSubmissionMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_persists_submission_and_sends_email(): void
    {
        Mail::fake();

        $payload = [
            'name' => 'Riya Sharma',
            'email' => 'riya@example.com',
            'subject' => 'Partnership inquiry',
            'message' => 'We would like to discuss district-level onboarding.',
            'company' => '',
        ];

        $response = $this->post('/contact', $payload);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('contact_submissions', [
            'name' => 'Riya Sharma',
            'email' => 'riya@example.com',
            'subject' => 'Partnership inquiry',
        ]);

        Mail::assertSent(ContactSubmissionMail::class, function (ContactSubmissionMail $mail): bool {
            return $mail->submission->email === 'riya@example.com';
        });
    }

    public function test_contact_form_rejects_honeypot_submission(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Bot User',
            'email' => 'bot@example.com',
            'subject' => 'Spam',
            'message' => 'Spam message',
            'company' => 'bot',
        ]);

        $response->assertStatus(422);
    }
}
