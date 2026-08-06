<?php

namespace App\Domain\Tenancy\Enums;

enum TenantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
}
