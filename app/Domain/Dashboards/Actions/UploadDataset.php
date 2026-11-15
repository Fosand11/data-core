<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Datasets\DatasetAnalyzer;
use App\Domain\Dashboards\Datasets\DatasetImportException;
use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Models\Dataset;
use App\Domain\Tenancy\CurrentTenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Receives a CSV or Excel file, reads just enough of it to propose the columns and their
 * types, and keeps it (privately) until the user confirms the import. Nothing is
 * imported yet.
 */
final class UploadDataset
{
    /** A file nobody confirmed within this many hours is thrown away. */
    public const ABANDONED_AFTER_HOURS = 24;

    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly DatasetAnalyzer $analyzer,
    ) {}

    /**
     * @throws DatasetImportException when the file or the organization's limits do not allow it
     */
    public function execute(User $user, UploadedFile $file, ?string $name = null): Dataset
    {
        Gate::forUser($user)->authorize('create', Dataset::class);
        $tenant = $this->currentTenant->require();

        $this->discardAbandoned();

        $maxDatasets = (int) config('datasets.max_per_tenant');

        if (Dataset::query()->count() >= $maxDatasets) {
            throw DatasetImportException::limitReached($maxDatasets);
        }

        $type = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $disk = Storage::disk((string) config('datasets.disk'));
        // Named by us, never by the client, and kept under the tenant's own folder.
        $path = $file->storeAs(config('datasets.directory').'/'.$tenant->getKey(), Str::uuid().'.'.$type, ['disk' => config('datasets.disk')]);

        if ($path === false) {
            throw DatasetImportException::unreadable();
        }

        try {
            $columns = $this->analyzer->analyze($disk->path($path), $type);

            return DB::transaction(function () use ($user, $tenant, $file, $name, $type, $path, $columns): Dataset {
                $filename = $this->cleanFilename($file->getClientOriginalName());

                $dataset = $tenant->datasets()->create([
                    'created_by' => $user->getKey(),
                    'name' => $this->cleanName($name) ?? Str::limit(pathinfo($filename, PATHINFO_FILENAME), 120, ''),
                    'status' => DatasetStatus::Pending,
                    'source_filename' => $filename,
                    'source_type' => $type,
                    'source_path' => $path,
                ]);

                foreach ($columns as $position => $column) {
                    $dataset->columns()->create([
                        'tenant_id' => $tenant->getKey(),
                        'position' => $position,
                        'key' => $column['key'],
                        'label' => $column['label'],
                        'type' => $column['type'],
                        'included' => true,
                        'samples' => $column['samples'],
                    ]);
                }

                return $dataset;
            });
        } catch (Throwable $exception) {
            // Never leave a file behind for a dataset that was not created.
            $disk->delete($path);

            throw $exception;
        }
    }

    /**
     * Removes the organization's uploads that were never confirmed, and their files.
     */
    private function discardAbandoned(): void
    {
        $disk = Storage::disk((string) config('datasets.disk'));

        Dataset::query()
            ->where('status', DatasetStatus::Pending->value)
            ->where('created_at', '<', now()->subHours(self::ABANDONED_AFTER_HOURS))
            ->get()
            ->each(function (Dataset $dataset) use ($disk): void {
                if ($dataset->source_path !== null) {
                    $disk->delete($dataset->source_path);
                }

                $dataset->delete();
            });
    }

    private function cleanName(?string $name): ?string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

        return $clean === '' ? null : Str::limit($clean, 120, '');
    }

    private function cleanFilename(string $name): string
    {
        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename(str_replace('\\', '/', $name))));

        return Str::limit($clean === '' ? 'archivo' : $clean, 255, '');
    }
}
