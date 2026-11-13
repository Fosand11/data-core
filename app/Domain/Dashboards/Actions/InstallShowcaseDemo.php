<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Ai\Data\DashboardSpec;
use App\Domain\Ai\Validation\DashboardSpecValidator;
use App\Domain\Dashboards\Enums\DashboardStatus;
use App\Domain\Dashboards\Models\DemoSale;
use App\Domain\Dashboards\Support\DashboardVersions;
use App\Domain\Tenancy\Actions\RegisterTenantOwner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Creates the account used by a public demo: one owner (credentials come from
 * the environment, never from the repository), the demo sales data and a few
 * ready-made dashboards. Safe to run again: an existing account is left alone.
 *
 * It does not use factories, so it also works in the production image, which
 * ships without development packages.
 */
final class InstallShowcaseDemo
{
    private const FIXTURES = 'database/demo/showcase-dashboards.json';

    private const MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private readonly RegisterTenantOwner $registerOwner,
        private readonly SeedDemoSales $seedDemoSales,
        private readonly DashboardSpecValidator $validator,
        private readonly DashboardVersions $versions,
    ) {}

    /**
     * @return array{created: bool, email: string, dashboards: int}
     *
     * @throws InvalidArgumentException when the demo credentials are missing or weak
     */
    public function execute(): array
    {
        $name = trim((string) config('demo.name'));
        $email = Str::lower(trim((string) config('demo.email')));
        $password = (string) config('demo.password');

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Set DEMO_USER_EMAIL to a valid email address.');
        }

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Set DEMO_USER_PASSWORD to at least '.self::MIN_PASSWORD_LENGTH.' characters.');
        }

        if (User::query()->where('email', $email)->exists()) {
            return ['created' => false, 'email' => $email, 'dashboards' => 0];
        }

        $fixtures = $this->fixtures();

        return DB::transaction(function () use ($name, $email, $password, $fixtures): array {
            $user = $this->registerOwner->execute(['name' => $name, 'email' => $email, 'password' => $password]);
            // There is no mail server in the demo, so the address is trusted as given.
            $user->forceFill(['email_verified_at' => now()])->save();

            $tenant = $user->tenants()->firstOrFail();

            if (! DemoSale::query()->forTenant($tenant)->exists()) {
                $this->seedDemoSales->execute($tenant);
            }

            foreach ($fixtures as $fixture) {
                $spec = $this->validator->validate(DashboardSpec::fromArray($fixture['spec']))->toArray();

                $dashboard = $tenant->dashboards()->create([
                    'created_by' => $user->getKey(),
                    'title' => $fixture['title'],
                    'description' => $fixture['description'],
                    'original_prompt' => $fixture['original_prompt'],
                    'status' => DashboardStatus::Ready,
                    'spec_version' => (string) $spec['version'],
                    'dashboard_spec' => $spec,
                    'provider' => 'gemini',
                    'generation_completed_at' => now(),
                ]);

                $this->versions->ensureInitial($dashboard);
            }

            return ['created' => true, 'email' => $email, 'dashboards' => count($fixtures)];
        });
    }

    /**
     * @return list<array{title: string, description: ?string, original_prompt: string, spec: array<string, mixed>}>
     */
    private function fixtures(): array
    {
        $path = base_path(self::FIXTURES);

        if (! is_file($path)) {
            throw new RuntimeException('Missing demo fixtures: '.self::FIXTURES);
        }

        $fixtures = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($fixtures) || $fixtures === []) {
            throw new RuntimeException('The demo fixtures file is empty.');
        }

        return array_values($fixtures);
    }
}
