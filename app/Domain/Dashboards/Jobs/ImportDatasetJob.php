<?php

namespace App\Domain\Dashboards\Jobs;

use App\Domain\Dashboards\Datasets\DatasetFileReader;
use App\Domain\Dashboards\Datasets\DatasetImportException;
use App\Domain\Dashboards\Datasets\ValueParser;
use App\Domain\Dashboards\Enums\DatasetColumnType;
use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Models\Dataset;
use App\Domain\Dashboards\Models\DatasetColumn;
use App\Domain\Dashboards\Models\DatasetRow;
use App\Domain\Dashboards\Support\ExecutionTime;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the confirmed file row by row and stores it in the dataset's typed slots.
 *
 * It is atomic: the rows go in one transaction, so a file that fails halfway leaves
 * nothing behind. The uploaded file is deleted when the job ends, whatever happened.
 */
final class ImportDatasetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 200;

    public function __construct(public readonly string $datasetId) {}

    public function handle(DatasetFileReader $reader): void
    {
        ExecutionTime::allow($this->timeout + 30);

        // A job has no request: enter the dataset's own tenant before touching data.
        $tenantId = Dataset::withoutTenantScope()->whereKey($this->datasetId)->value('tenant_id');
        $tenant = is_string($tenantId) ? Tenant::query()->find($tenantId) : null;

        if (! $tenant instanceof Tenant) {
            return;
        }

        app(TenantContext::class)->enter($tenant);

        $dataset = Dataset::query()->with('columns')->find($this->datasetId);

        if (! $dataset instanceof Dataset || $dataset->status !== DatasetStatus::Importing) {
            return;
        }

        try {
            [$rows, $warnings] = DB::transaction(fn (): array => $this->import($dataset, $reader));

            $dataset->update([
                'status' => DatasetStatus::Ready,
                'row_count' => $rows,
                'warning_count' => $warnings,
                'imported_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $import = $exception instanceof DatasetImportException ? $exception : DatasetImportException::unreadable();

            $dataset->update([
                'status' => DatasetStatus::Failed,
                'error_code' => $import->reason,
                'error_message' => $import->getMessage(),
            ]);

            Log::error('Dataset import failed.', [
                'dataset_id' => $this->datasetId,
                'exception' => $exception::class,
                'reason' => $import->reason,
            ]);
        } finally {
            $this->discardFile($dataset);
        }
    }

    /**
     * @return array{0: int, 1: int} rows stored and cells that could not be read as their column's type
     */
    private function import(Dataset $dataset, DatasetFileReader $reader): array
    {
        $columns = $dataset->columns->filter(fn (DatasetColumn $column): bool => $column->included && $column->slot !== null)->values();
        $disk = Storage::disk((string) config('datasets.disk'));

        if ($columns->isEmpty() || $dataset->source_path === null || ! $disk->exists($dataset->source_path)) {
            throw DatasetImportException::unreadable();
        }

        $maxRows = (int) config('datasets.max_rows');
        // SQLite allows 999 bound values per statement: size each batch to stay under it.
        $batchSize = max(1, intdiv(900, 3 + $columns->count()));
        $tenantId = $dataset->tenant_id;
        $batch = [];
        $rows = 0;
        $warnings = 0;
        $isHeader = true;

        foreach ($reader->rows($disk->path($dataset->source_path), $dataset->source_type) as $row) {
            if ($isHeader) {
                $isHeader = false;

                continue;
            }

            $record = ['id' => (string) Str::uuid7(), 'tenant_id' => $tenantId, 'dataset_id' => $dataset->getKey()];
            $hasValue = false;

            foreach ($columns as $column) {
                [$value, $lost] = $this->convert($column->type, $row[$column->position] ?? null);
                $record[(string) $column->slot] = $value;
                $hasValue = $hasValue || $value !== null;
                $warnings += $lost ? 1 : 0;
            }

            if (! $hasValue) {
                continue;
            }

            if (++$rows > $maxRows) {
                throw DatasetImportException::tooManyRows($maxRows);
            }

            $batch[] = $record;

            if (count($batch) >= $batchSize) {
                DatasetRow::query()->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DatasetRow::query()->insert($batch);
        }

        if ($rows === 0) {
            throw DatasetImportException::empty();
        }

        return [$rows, $warnings];
    }

    /**
     * @return array{0: ?string, 1: bool} the value as stored, and whether it had to be dropped because it did not fit the type
     */
    private function convert(DatasetColumnType $type, mixed $raw): array
    {
        if (ValueParser::isEmpty($raw)) {
            return [null, false];
        }

        return match ($type) {
            DatasetColumnType::String => [ValueParser::string($raw), false],
            DatasetColumnType::Numeric => ($number = ValueParser::numeric($raw)) === null
                ? [null, true]
                : [number_format($number, 6, '.', ''), false],
            DatasetColumnType::Datetime => ($moment = ValueParser::datetime($raw)) === null
                ? [null, true]
                : [$moment->format('Y-m-d H:i:s'), false],
        };
    }

    private function discardFile(Dataset $dataset): void
    {
        if ($dataset->source_path !== null) {
            Storage::disk((string) config('datasets.disk'))->delete($dataset->source_path);
        }

        // Dataset::update would run the tenant scope; the tenant is in context, so it is fine.
        $dataset->update(['source_path' => null]);
    }
}
