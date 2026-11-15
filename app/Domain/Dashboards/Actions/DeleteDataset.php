<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Models\Dataset;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a dataset with its rows, columns and file. The dashboards built on it go
 * too: a dashboard without its data would only show errors.
 */
final class DeleteDataset
{
    public function execute(User $user, Dataset $dataset): void
    {
        Gate::forUser($user)->authorize('delete', $dataset);

        if ($dataset->source_path !== null) {
            Storage::disk((string) config('datasets.disk'))->delete($dataset->source_path);
        }

        // Rows, columns and dashboards are removed by their foreign keys' cascade.
        $dataset->delete();
    }
}
