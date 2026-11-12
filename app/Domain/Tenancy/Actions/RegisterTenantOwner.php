<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Dashboards\Actions\SeedDemoSales;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterTenantOwner
{
    public function __construct(private readonly SeedDemoSales $seedDemoSales) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function execute(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => Hash::make($attributes['password']),
            ]);

            $tenantName = $attributes['name'].' Organization';
            $slugBase = Str::slug(Str::limit($attributes['name'], 40, '')) ?: 'organization';

            $tenant = Tenant::create([
                'name' => $tenantName,
                'slug' => $slugBase.'-'.Str::lower(Str::random(8)),
            ]);

            $tenant->attachUser($user, TenantRole::Owner);

            if (config('tenancy.seed_demo_data')) {
                $this->seedDemoSales->execute($tenant);
            }

            return $user;
        });
    }
}
