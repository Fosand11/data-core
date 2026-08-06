<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_is_created_with_a_uuid(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertTrue(Str::isUuid($tenant->getKey()));
        $this->assertSame(TenantStatus::Active, $tenant->status);
    }

    public function test_owner_and_member_roles_are_persisted(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->ownerOf($tenant)->create();
        $member = User::factory()->memberOf($tenant)->create();

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->getKey(),
            'user_id' => $owner->getKey(),
            'role' => TenantRole::Owner->value,
        ]);
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->getKey(),
            'user_id' => $member->getKey(),
            'role' => TenantRole::Member->value,
        ]);
    }

    public function test_a_user_can_belong_to_multiple_organizations(): void
    {
        $first = Tenant::factory()->create();
        $second = Tenant::factory()->create();
        $user = User::factory()->ownerOf($first)->memberOf($second)->create();

        $this->assertCount(2, $user->tenants);
        $this->assertEqualsCanonicalizing(
            [$first->getKey(), $second->getKey()],
            $user->tenants->modelKeys(),
        );
    }
}
