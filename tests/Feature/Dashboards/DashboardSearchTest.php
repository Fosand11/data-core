<?php

namespace Tests\Feature\Dashboards;

use App\Domain\Dashboards\Models\Dashboard;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardSearchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->memberOf($this->tenant)->create();
    }

    public function test_finds_dashboards_by_title_or_original_prompt_ignoring_case(): void
    {
        $this->dashboard(['title' => 'Ventas por Región', 'original_prompt' => 'algo distinto']);
        $this->dashboard(['title' => 'Otro tablero', 'original_prompt' => 'Quiero ver las VENTAS del trimestre']);
        $this->dashboard(['title' => 'Inventario', 'original_prompt' => 'stock disponible']);

        $titles = $this->search('ventas')->json('data.*.title');

        $this->assertEqualsCanonicalizing(['Ventas por Región', 'Otro tablero'], $titles);
    }

    public function test_never_returns_dashboards_from_another_tenant(): void
    {
        $foreign = Tenant::factory()->create();
        $this->dashboard(['title' => 'Ventas propias']);
        Dashboard::factory()->for($foreign)->create(['title' => 'Ventas ajenas', 'original_prompt' => 'ventas']);

        $titles = $this->search('ventas')->json('data.*.title');

        $this->assertSame(['Ventas propias'], $titles);
    }

    public function test_an_empty_term_returns_the_latest_dashboards_of_the_current_tenant_up_to_the_limit(): void
    {
        foreach (range(1, 10) as $index) {
            $this->dashboard(['title' => "Tablero {$index}", 'created_at' => now()->subMinutes(20 - $index)]);
        }
        Dashboard::factory()->for(Tenant::factory()->create())->create(['title' => 'Ajeno']);

        $titles = $this->search('')->json('data.*.title');

        $this->assertCount(8, $titles);
        $this->assertSame('Tablero 10', $titles[0]);
        $this->assertNotContains('Ajeno', $titles);
    }

    public function test_like_wildcards_in_the_term_are_treated_literally(): void
    {
        $this->dashboard(['title' => 'Crecimiento 100% anual', 'original_prompt' => 'x']);
        $this->dashboard(['title' => 'Ventas normales', 'original_prompt' => 'y']);
        $this->dashboard(['title' => 'snake_case title', 'original_prompt' => 'z']);

        $this->assertSame(['Crecimiento 100% anual'], $this->search('%')->json('data.*.title'));
        $this->assertSame(['snake_case title'], $this->search('_')->json('data.*.title'));
        $this->assertSame([], $this->search("' OR '1'='1")->json('data'));
    }

    public function test_results_are_summaries_without_the_full_spec(): void
    {
        $this->dashboard([
            'title' => 'Con spec',
            'dashboard_spec' => ['visualizations' => [['type' => 'kpi'], ['type' => 'bar'], ['type' => 'kpi']]],
        ]);

        $result = $this->search('spec')->json('data.0');

        $this->assertSame(['kpi', 'bar'], $result['visualization_types']);
        $this->assertArrayNotHasKey('dashboard_spec', $result);
        $this->assertSame(['id', 'title', 'description', 'status', 'is_pinned', 'created_at', 'author', 'visualization_types'], array_keys($result));
    }

    public function test_the_term_is_validated(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('dashboards.search', ['q' => str_repeat('a', 101)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_a_client_supplied_tenant_id_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('dashboards.search', ['q' => 'x', 'tenant_id' => Tenant::factory()->create()->getKey()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tenant_id');
    }

    public function test_guests_cannot_search(): void
    {
        $this->getJson(route('dashboards.search', ['q' => 'x']))->assertUnauthorized();
    }

    public function test_users_without_an_organization_are_forbidden(): void
    {
        $loner = User::factory()->create();

        $this->actingAs($loner)
            ->getJson(route('dashboards.search'))
            ->assertForbidden();
    }

    public function test_recent_dashboards_are_shared_for_the_current_tenant_only(): void
    {
        foreach (range(1, 7) as $index) {
            $this->dashboard(['title' => "Reciente {$index}", 'created_at' => now()->subMinutes(10 - $index)]);
        }
        Dashboard::factory()->for(Tenant::factory()->create())->create(['title' => 'De otra organización']);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recentDashboards', 5)
                ->where('recentDashboards.0.title', 'Reciente 7')
                ->where('recentDashboards.0.status', 'queued')
                ->missing('recentDashboards.0.dashboard_spec'));
    }

    public function test_recent_dashboards_follow_the_selected_organization(): void
    {
        $second = Tenant::factory()->create();
        $user = User::factory()->ownerOf($this->tenant)->memberOf($second)->create();
        $this->dashboard(['title' => 'En la primera']);
        Dashboard::factory()->for($second)->create(['title' => 'En la segunda']);

        $this->actingAs($user)
            ->withSession([(string) config('tenancy.session_key') => $second->getKey()])
            ->get(route('dashboards.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('recentDashboards', 1)
                ->where('recentDashboards.0.title', 'En la segunda'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function dashboard(array $attributes = []): Dashboard
    {
        return Dashboard::factory()->for($this->tenant)->create($attributes);
    }

    private function search(string $term): TestResponse
    {
        return $this->actingAs($this->user)
            ->getJson(route('dashboards.search', ['q' => $term]))
            ->assertOk();
    }
}
