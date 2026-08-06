<?php

namespace Database\Seeders;

use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $primaryTenant = Tenant::factory()->create([
            'name' => 'Demo Organization',
            'slug' => 'demo-organization',
        ]);

        User::factory()->ownerOf($primaryTenant)->create([
            'name' => 'Demo Owner',
            'email' => 'owner@example.test',
        ]);

        $member = User::factory()->memberOf($primaryTenant)->create([
            'name' => 'Demo Member',
            'email' => 'member@example.test',
        ]);

        $secondaryTenant = Tenant::factory()->create([
            'name' => 'Second Organization',
            'slug' => 'second-organization',
        ]);

        $secondaryTenant->attachUser($member, TenantRole::Admin);
    }
}
