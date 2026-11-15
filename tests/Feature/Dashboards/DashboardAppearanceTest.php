<?php

namespace Tests\Feature\Dashboards;

use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Dashboards\Support\ChartPalettes;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The palette and per-chart colours of a dashboard: who can change them, what is accepted,
 * and that nothing but catalog ids and slot numbers is ever stored.
 */
class DashboardAppearanceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $other;

    private User $owner;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->other = Tenant::factory()->create();
        $this->owner = User::factory()->ownerOf($this->tenant)->create();
        $this->member = User::factory()->memberOf($this->tenant)->create();
    }

    public function test_an_owner_saves_a_palette_and_per_chart_colours(): void
    {
        $dashboard = $this->readyDashboard($this->tenant);

        $this->actingAs($this->owner)
            ->patch(route('dashboards.appearance', $dashboard), ['palette' => 'oceano', 'colors' => ['by_region' => 3, 'trend' => 0]])
            ->assertRedirect();

        $this->assertSame(['palette' => 'oceano', 'colors' => ['by_region' => 3, 'trend' => 0]], $dashboard->refresh()->appearance);
    }

    public function test_the_page_receives_the_catalog_and_the_saved_choice_or_the_default(): void
    {
        $dashboard = $this->readyDashboard($this->tenant);

        $this->actingAs($this->owner)->get(route('dashboards.show', $dashboard))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('palettes', count(ChartPalettes::ids()))
                ->where('palettes.0.id', ChartPalettes::DEFAULT)
                ->has('palettes.0.light', 6)
                ->has('palettes.0.dark', 6)
                ->where('dashboard.appearance.palette', ChartPalettes::DEFAULT)
                ->where('dashboard.appearance.colors', []));

        $this->actingAs($this->owner)->patch(route('dashboards.appearance', $dashboard), ['palette' => 'coral', 'colors' => ['trend' => 2]]);

        $this->actingAs($this->owner)->get(route('dashboards.show', $dashboard))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.appearance.palette', 'coral')
                ->where('dashboard.appearance.colors.trend', 2));
    }

    public function test_choosing_the_default_without_custom_colours_clears_the_stored_choice(): void
    {
        $dashboard = $this->readyDashboard($this->tenant, ['palette' => 'violeta', 'colors' => ['trend' => 1]]);

        $this->actingAs($this->owner)
            ->patch(route('dashboards.appearance', $dashboard), ['palette' => ChartPalettes::DEFAULT, 'colors' => []])
            ->assertRedirect();

        $this->assertNull($dashboard->refresh()->appearance);
    }

    public function test_colours_of_charts_that_are_not_in_the_dashboard_are_dropped(): void
    {
        $dashboard = $this->readyDashboard($this->tenant);

        $this->actingAs($this->owner)
            ->patch(route('dashboards.appearance', $dashboard), ['palette' => 'pino', 'colors' => ['trend' => 4, 'removed_chart' => 2]])
            ->assertRedirect();

        $this->assertSame(['trend' => 4], $dashboard->refresh()->appearance['colors']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidChoices(): array
    {
        return [
            'an unknown palette' => [['palette' => 'neon', 'colors' => []]],
            'a hex colour instead of a palette' => [['palette' => '#ff0000', 'colors' => []]],
            'a missing palette' => [['colors' => []]],
            'a missing colours list' => [['palette' => 'pino']],
            'a slot above the palette' => [['palette' => 'pino', 'colors' => ['trend' => 6]]],
            'a negative slot' => [['palette' => 'pino', 'colors' => ['trend' => -1]]],
            'a text slot' => [['palette' => 'pino', 'colors' => ['trend' => 'red']]],
            'a free-form colour' => [['palette' => 'pino', 'colors' => ['trend' => '#ff0000']]],
            'a chart key with markup' => [['palette' => 'pino', 'colors' => ['<script>' => 1]]],
            'too many charts' => [['palette' => 'pino', 'colors' => array_fill_keys(array_map(fn (int $i): string => "k{$i}", range(1, 13)), 1)]],
            'a tenant id' => [['palette' => 'pino', 'colors' => [], 'tenant_id' => 'x']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidChoices')]
    public function test_only_catalog_palettes_and_slot_numbers_are_accepted(array $payload): void
    {
        $dashboard = $this->readyDashboard($this->tenant, ['palette' => 'oceano', 'colors' => []]);

        $this->actingAs($this->owner)
            ->patch(route('dashboards.appearance', $dashboard), $payload)
            ->assertSessionHasErrors();

        $this->assertSame(['palette' => 'oceano', 'colors' => []], $dashboard->refresh()->appearance, 'a rejected request changes nothing');
    }

    public function test_members_cannot_restyle_a_dashboard_and_guests_are_sent_to_log_in(): void
    {
        $dashboard = $this->readyDashboard($this->tenant);

        $this->actingAs($this->member)
            ->patch(route('dashboards.appearance', $dashboard), ['palette' => 'coral', 'colors' => []])
            ->assertForbidden();
        $this->assertNull($dashboard->refresh()->appearance);

        auth()->logout();
        $this->patch(route('dashboards.appearance', $dashboard), ['palette' => 'coral', 'colors' => []])->assertRedirect(route('login'));
    }

    public function test_another_organizations_dashboard_does_not_exist_for_this_route(): void
    {
        $foreign = $this->readyDashboard($this->other);

        $this->actingAs($this->owner)
            ->patch(route('dashboards.appearance', $foreign), ['palette' => 'coral', 'colors' => []])
            ->assertNotFound();

        $this->assertNull($foreign->refresh()->appearance);
    }

    public function test_a_duplicate_keeps_the_look_of_the_original(): void
    {
        $dashboard = $this->readyDashboard($this->tenant, ['palette' => 'violeta', 'colors' => ['trend' => 5]]);

        $this->actingAs($this->owner)->post(route('dashboards.duplicate', $dashboard))->assertRedirect();

        $copy = Dashboard::withoutTenantScope()->where('id', '!=', $dashboard->getKey())->where('tenant_id', $this->tenant->getKey())->sole();
        $this->assertSame(['palette' => 'violeta', 'colors' => ['trend' => 5]], $copy->appearance);
    }

    public function test_every_palette_is_six_valid_light_and_dark_colours(): void
    {
        $this->assertContains(ChartPalettes::DEFAULT, ChartPalettes::ids());

        foreach (ChartPalettes::all() as $id => $palette) {
            foreach (['light', 'dark'] as $mode) {
                $this->assertCount(ChartPalettes::SLOTS, $palette[$mode], "{$id} {$mode}");

                foreach ($palette[$mode] as $color) {
                    $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $color, "{$id} {$mode}");
                }

                $this->assertSame($palette[$mode], array_values(array_unique($palette[$mode])), "{$id} {$mode} repeats a colour");
            }

            $this->assertNotSame('', $palette['name']);
        }
    }

    // --- helpers -----------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $appearance
     */
    private function readyDashboard(Tenant $tenant, ?array $appearance = null): Dashboard
    {
        return app(TenantContext::class)->run($tenant, fn (): Dashboard => Dashboard::factory()->for($tenant)->create([
            'created_by' => $tenant->is($this->tenant) ? $this->owner->getKey() : null,
            'status' => DashboardStatus::Ready,
            'spec_version' => '1.2',
            'dashboard_spec' => [
                'version' => '1.2',
                'title' => 'Spec',
                'visualizations' => [['key' => 'by_region', 'type' => 'bar'], ['key' => 'trend', 'type' => 'line']],
            ],
            'original_prompt' => 'una pregunta cualquiera',
            'appearance' => $appearance,
        ]));
    }
}
