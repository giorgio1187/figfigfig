<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2025-06-11 14:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_weekly_report_returns_success_structure(): void
    {
        $user = User::factory()->create();
        Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 10000,
            'paid_at' => '2025-06-09 12:00:00',
        ]);

        $response = $this->getJson('/api/reports/weekly');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'week_start',
                    'week_end',
                    'total_revenue',
                    'order_count',
                    'formatted_revenue',
                    'days' => [
                        '*' => ['date', 'day_label', 'revenue', 'order_count', 'formatted_revenue'],
                    ],
                ],
            ]);

        $this->assertCount(7, $response->json('data.days'));
    }

    public function test_weekly_report_week_bounds_are_monday_to_sunday(): void
    {
        $response = $this->getJson('/api/reports/weekly');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame('2025-06-09', $data['week_start']);
        $this->assertSame('2025-06-15', $data['week_end']);
    }

    public function test_weekly_report_only_includes_paid_orders_in_range(): void
    {
        $user = User::factory()->create();

        Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 10000,
            'paid_at' => '2025-06-09 12:00:00',
        ]);
        Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 5000,
            'paid_at' => '2025-06-10 18:00:00',
        ]);

        $response = $this->getJson('/api/reports/weekly');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(15000.0, (float) $data['total_revenue']);
        $this->assertSame(2, $data['order_count']);
    }

    public function test_weekly_report_excludes_unpaid_orders(): void
    {
        $user = User::factory()->create();

        Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 10000,
            'paid_at' => '2025-06-09 12:00:00',
        ]);
        Order::factory()->pending()->create([
            'user_id' => $user->id,
            'total' => 3000,
        ]);
        Order::factory()->delivered()->create([
            'user_id' => $user->id,
            'total' => 7000,
        ]);

        $response = $this->getJson('/api/reports/weekly');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(10000.0, (float) $data['total_revenue']);
        $this->assertSame(1, $data['order_count']);
    }

    public function test_weekly_report_excludes_paid_orders_outside_week(): void
    {
        $user = User::factory()->create();

        Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 10000,
            'paid_at' => '2025-06-09 12:00:00',
        ]);
        Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 99999,
            'paid_at' => '2025-06-02 12:00:00',
        ]);

        $response = $this->getJson('/api/reports/weekly');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(10000.0, (float) $data['total_revenue']);
        $this->assertSame(1, $data['order_count']);
    }

    public function test_weekly_report_fills_missing_days_with_zero(): void
    {
        $user = User::factory()->create();

        Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 10000,
            'paid_at' => '2025-06-09 12:00:00',
        ]);

        $response = $this->getJson('/api/reports/weekly');

        $response->assertOk();
        $days = $response->json('data.days');

        $tuesday = collect($days)->firstWhere('date', '2025-06-10');

        $this->assertNotNull($tuesday);
        $this->assertSame(0.0, (float) $tuesday['revenue']);
        $this->assertSame(0, $tuesday['order_count']);
        $this->assertSame('$0', $tuesday['formatted_revenue']);
    }

    public function test_weekly_report_day_labels_are_lun_to_dom(): void
    {
        $response = $this->getJson('/api/reports/weekly');

        $response->assertOk();
        $labels = collect($response->json('data.days'))->pluck('day_label')->all();

        $this->assertSame(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'], $labels);
    }

    public function test_weekly_report_totals_match_sum_of_days(): void
    {
        $user = User::factory()->create();

        Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 10000,
            'paid_at' => '2025-06-09 12:00:00',
        ]);
        Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 5000,
            'paid_at' => '2025-06-09 18:00:00',
        ]);
        Order::factory()->paid()->create([
            'user_id' => $user->id,
            'total' => 8000,
            'paid_at' => '2025-06-11 10:00:00',
        ]);

        $response = $this->getJson('/api/reports/weekly');

        $response->assertOk();
        $data = $response->json('data');
        $days = collect($data['days']);

        $this->assertEquals($days->sum('revenue'), (float) $data['total_revenue']);
        $this->assertEquals($days->sum('order_count'), $data['order_count']);
    }
}
