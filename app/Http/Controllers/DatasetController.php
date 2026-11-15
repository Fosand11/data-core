<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Actions\ConfirmDatasetImport;
use App\Domain\Dashboards\Actions\DeleteDataset;
use App\Domain\Dashboards\Actions\UploadDataset;
use App\Domain\Dashboards\Datasets\DatasetImportException;
use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Models\Dataset;
use App\Domain\Dashboards\Models\DatasetColumn;
use App\Domain\Dashboards\Models\DatasetRow;
use App\Domain\Tenancy\CurrentTenant;
use App\Http\Controllers\Concerns\SharesNavigationProps;
use App\Http\Requests\Datasets\ConfirmDatasetImportRequest;
use App\Http\Requests\Datasets\StoreDatasetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's own data: upload a CSV or Excel file, review how its columns were
 * understood, import it, and use it. Rules live in the actions and the Policy; this
 * class only translates HTTP.
 */
class DatasetController extends Controller
{
    use SharesNavigationProps;

    private const PREVIEW_ROWS = 20;

    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Dataset::class);
        $tenant = $this->currentTenant->require();

        $datasets = Dataset::query()->withCount(['dashboards', 'columns'])->latest()->get();

        return Inertia::render('datasets/Index', [
            ...$this->navigationProps($request, $tenant),
            'datasets' => $datasets->map(fn (Dataset $dataset): array => $this->summary($dataset))->all(),
            'limits' => $this->limits(),
            'can' => ['create' => Gate::allows('create', Dataset::class)],
        ]);
    }

    public function show(Request $request, Dataset $dataset): Response
    {
        Gate::authorize('view', $dataset);
        $tenant = $this->currentTenant->require();
        $dataset->load('columns')->loadCount('dashboards');

        return Inertia::render('datasets/Show', [
            ...$this->navigationProps($request, $tenant),
            'dataset' => [
                ...$this->summary($dataset),
                'warning_count' => $dataset->warning_count,
                'error_code' => $dataset->error_code,
                'error_message' => $dataset->error_message,
            ],
            'columns' => $dataset->columns->map(fn (DatasetColumn $column): array => [
                'id' => $column->getKey(),
                'key' => $column->key,
                'label' => $column->label,
                'type' => $column->type->value,
                'included' => $column->included,
                'samples' => $column->samples ?? [],
            ])->all(),
            // Lazy: only computed when the page asks for it (and not while polling an import).
            'preview' => fn (): array => $dataset->status === DatasetStatus::Ready ? $this->preview($dataset) : [],
            'limits' => $this->limits(),
            'can' => [
                'update' => Gate::allows('update', $dataset),
                'delete' => Gate::allows('delete', $dataset),
            ],
        ]);
    }

    public function store(StoreDatasetRequest $request, UploadDataset $upload): RedirectResponse
    {
        try {
            $dataset = $upload->execute($request->user(), $request->file('file'), $request->validated('name'));
        } catch (DatasetImportException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return to_route('datasets.show', $dataset)->with('status', 'Archivo recibido. Revisa las columnas y confirma la importación.');
    }

    public function import(ConfirmDatasetImportRequest $request, Dataset $dataset, ConfirmDatasetImport $confirm): RedirectResponse
    {
        try {
            $confirm->execute($request->user(), $dataset, $request->validated('name'), $request->validated('columns'));
        } catch (DatasetImportException $exception) {
            throw ValidationException::withMessages(['columns' => $exception->getMessage()]);
        }

        return to_route('datasets.show', $dataset)->with('status', 'Importando tus datos…');
    }

    public function destroy(Request $request, Dataset $dataset, DeleteDataset $delete): RedirectResponse
    {
        $delete->execute($request->user(), $dataset);

        return to_route('datasets.index')->with('status', 'Conjunto de datos eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Dataset $dataset): array
    {
        return [
            'id' => $dataset->getKey(),
            'name' => $dataset->name,
            'status' => $dataset->status->value,
            'source_filename' => $dataset->source_filename,
            'source_type' => $dataset->source_type,
            'row_count' => $dataset->row_count,
            'columns_count' => $dataset->columns_count ?? $dataset->columns->count(),
            'dashboards_count' => $dataset->dashboards_count ?? 0,
            'created_at' => $dataset->created_at->toIso8601String(),
            'imported_at' => $dataset->imported_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function limits(): array
    {
        return [
            'max_file_kb' => (int) config('datasets.max_file_kb'),
            'max_rows' => (int) config('datasets.max_rows'),
            'max_columns' => (int) config('datasets.max_columns'),
            'max_datasets' => (int) config('datasets.max_per_tenant'),
            // How many columns of each type fit, so the review screen can say so before importing.
            'slots' => [
                'string' => (int) config('datasets.slots.string'),
                'numeric' => (int) config('datasets.slots.numeric'),
                'datetime' => (int) config('datasets.slots.datetime'),
            ],
        ];
    }

    /**
     * The first rows of an imported dataset, keyed by column, as the user would read them.
     *
     * @return list<array<string, mixed>>
     */
    private function preview(Dataset $dataset): array
    {
        $columns = $dataset->columns->filter(fn (DatasetColumn $column): bool => $column->included && $column->slot !== null);

        return DatasetRow::query()
            ->where('dataset_id', $dataset->getKey())
            ->orderBy('id')
            ->limit(self::PREVIEW_ROWS)
            ->get()
            ->map(function (DatasetRow $row) use ($columns): array {
                $cells = [];

                foreach ($columns as $column) {
                    $value = $row->getAttribute((string) $column->slot);
                    $cells[$column->key] = match (true) {
                        $value === null => null,
                        $column->type->value === 'numeric' => (float) $value,
                        default => (string) $value,
                    };
                }

                return $cells;
            })
            ->all();
    }
}
