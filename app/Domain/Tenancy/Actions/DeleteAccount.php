<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Erases a person and what only they had: the right to be forgotten.
 *
 * An organization nobody else belongs to is deleted with everything in it (datasets, their rows
 * and columns, dashboards, versions, generations and any file still waiting to be imported). An
 * organization shared with others keeps going without this person; if they were its only owner,
 * the longest-standing remaining member takes over, so it is never left without one.
 */
final class DeleteAccount
{
    public function execute(User $user): void
    {
        /** @var list<string> $erased organizations whose stored files must be removed after the commit */
        $erased = [];

        DB::transaction(function () use ($user, &$erased): void {
            foreach ($user->tenants()->get() as $tenant) {
                $others = $tenant->users()->where('users.id', '!=', $user->getKey())->orderBy('tenant_user.created_at')->get();

                if ($others->isEmpty()) {
                    $erased[] = (string) $tenant->getKey();
                    $tenant->delete();

                    continue;
                }

                $this->keepAnOwner($tenant, $user, $others->first());
            }

            // The account's other traces: open sessions and any pending password reset.
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->delete();
        });

        // Files live outside the database; remove them only once the rows are really gone.
        $disk = Storage::disk((string) config('datasets.disk'));

        foreach ($erased as $tenantId) {
            $disk->deleteDirectory(config('datasets.directory').'/'.$tenantId);
        }
    }

    private function keepAnOwner(Tenant $tenant, User $leaving, User $successor): void
    {
        $leavingWasOwner = $tenant->users()->where('users.id', $leaving->getKey())->wherePivot('role', TenantRole::Owner->value)->exists();
        $otherOwners = $tenant->users()->where('users.id', '!=', $leaving->getKey())->wherePivot('role', TenantRole::Owner->value)->exists();

        if ($leavingWasOwner && ! $otherOwners) {
            $tenant->users()->updateExistingPivot($successor->getKey(), ['role' => TenantRole::Owner->value]);
        }
    }
}
