<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['student', 'teacher', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create([
            'role'   => 'admin',
            'status' => 'active',
        ]);

        $user->assignRole('admin');

        return $user;
    }

    private function seedAnalyticsData(): void
    {
        $student = User::factory()->create([
            'role'   => 'student',
            'status' => 'active',
        ]);
        $student->assignRole('student');

        $teacher = User::factory()->create([
            'role'   => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        $slot = BookingSlot::create([
            'teacher_id'       => $teacher->id,
            'slot_date'        => now()->toDateString(),
            'start_time'       => '10:00:00',
            'end_time'         => '11:00:00',
            'duration_minutes' => 60,
        ]);

        $booking = Booking::create([
            'student_id'     => $student->id,
            'teacher_id'     => $teacher->id,
            'slot_id'        => $slot->id,
            'start_at'       => now()->subHour(),
            'end_at'         => now(),
            'status'         => 'completed',
            'session_type'   => 'solo',
            'subject'        => 'Math',
            'price'          => 100,
            'platform_fee'   => 10,
            'teacher_payout' => 90,
            'payment_status' => 'released',
        ]);

        Payment::create([
            'booking_id'        => $booking->id,
            'payer_id'          => $student->id,
            'amount'            => 100,
            'amount_paise'      => 10000,
            'platform_fee'      => 10,
            'teacher_payout'    => 90,
            'gateway'           => 'phonepe',
            'merchant_order_id' => 'ORDER-' . $booking->id,
            'phonepe_order_id'  => 'PP-' . $booking->id,
            'status'            => 'released',
            'paid_at'           => now(),
            'released_at'       => now(),
        ]);
    }

    private function analyticsQueryCount(): int
    {
        return collect(DB::getQueryLog())
            ->filter(function (array $query): bool {
                $sql = strtolower($query['query']);

                return preg_match('/\b(users|bookings|payments)\b/', $sql) === 1;
            })
            ->count();
    }

    public function test_overview_endpoint_returns_expected_keys(): void
    {
        $admin = $this->makeAdmin();
        $this->seedAnalyticsData();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/analytics/overview?from=' . now()->subDay()->toDateString() . '&to=' . now()->addDay()->toDateString());

        $response->assertOk();
        $response->assertJsonStructure([
            'total_users',
            'new_registrations',
            'sessions_completed',
            'revenue',
        ]);
    }

    public function test_revenue_endpoint_returns_expected_keys(): void
    {
        $admin = $this->makeAdmin();
        $this->seedAnalyticsData();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/analytics/revenue?from=' . now()->subDay()->toDateString() . '&to=' . now()->addDay()->toDateString());

        $response->assertOk();
        $response->assertJsonStructure([
            'daily_revenue',
            'platform_fees',
            'teacher_payouts',
        ]);
    }

    public function test_overview_endpoint_uses_cache_on_repeat_request(): void
    {
        $admin = $this->makeAdmin();
        $this->seedAnalyticsData();

        Sanctum::actingAs($admin);

        Cache::flush();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/admin/analytics/overview?from=' . now()->subDay()->toDateString() . '&to=' . now()->addDay()->toDateString())
            ->assertOk();

        $firstRequestQueryCount = $this->analyticsQueryCount();

        DB::flushQueryLog();

        $this->getJson('/api/admin/analytics/overview?from=' . now()->subDay()->toDateString() . '&to=' . now()->addDay()->toDateString())
            ->assertOk();

        $secondRequestQueryCount = $this->analyticsQueryCount();

        $this->assertGreaterThan(0, $firstRequestQueryCount);
        $this->assertSame(0, $secondRequestQueryCount);
    }
}
