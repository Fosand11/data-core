<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Tenant;
use LogicException;

class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->getKey();
    }

    public function require(): Tenant
    {
        return $this->tenant ?? throw new LogicException('No current tenant has been resolved.');
    }
}
