<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\Booking;
use App\Models\User;
class TempTest extends TestCase
{
    public function test_token_endpoint()
    {
        $booking = Booking::find(6);
        $user = User::find($booking->student_id);
        $response = $this->actingAs($user)->postJson('/api/video-sessions/6/token');
        dump($response->status(), $response->json());
    }
}
