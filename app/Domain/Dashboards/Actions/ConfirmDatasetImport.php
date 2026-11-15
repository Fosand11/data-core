<?php

namespace App\Domain\Dashboards\Actions;

use App\Domain\Dashboards\Datasets\DatasetImportException;
use App\Domain\Dashboards\Enums\DatasetColumnType;
use App\Domain\Dashboards\Enums\DatasetStatus;
use App\Domain\Dashboards\Jobs\ImportDatasetJob;
use App\Domain\Dashboards\Models\Dataset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Applies the user's choices (name, which columns, each column's type and display
 * name), maps the chosen columns onto the typed storage slots and queues the import.
 */
final class ConfirmDatasetImport
{
    /**
     * @param  list<array{id: string, label?: ?string, type?: ?string, included?: bool}>  $choices  one per column; a column left out keeps what was proposed
     *
     * @throws DatasetImportException when the choices do not fit the storage limits
     */
    public function execute(User $user, Dataset $dataset, string $name, array $choices): Dataset
    {
        Gate::forUser($user)->authorize('update', $dataset);

        if ($dataset->status !== DatasetStatus::Pending) {
            throw ValidationException::withMessages(['dataset' => 'Este conjunto de datos ya se importó o está en proceso.']);
        }

        $byId = collect($choices)->keyBy('id');
        $limits = (array) config('datasets.slots');
        $used = ['string' => 0, 'numeric' => 0, 'datetime' => 0];
        $plan = [];

        foreach ($dataset->columns as $column) {
            $choice = $byId->get((string) $column->getKey(), []);
            $included = (bool) ($choice['included'] ?? $column->included);
            $type = DatasetColumnType::tryFrom((string) ($choice['type'] ?? '')) ?? $column->type;
            $label = trim((string) ($choice['label'] ?? '')) !== '' ? Str::limit(trim((string) $choice['label']), 255, '') : $column->label;
            $slot = null;

            if ($included) {
                $used[$type->value]++;

                if ($used[$type->value] > (int) $limits[$type->value]) {
                    throw DatasetImportException::tooManyOfType($type->value, (int) $limits[$type->value]);
                }

                $slot = $type->slotPrefix().$used[$type->value];
            }

            $plan[] = ['column' => $column, 'included' => $included, 'type' => $type, 'label' => $label, 'slot' => $slot];
        }

        if (array_sum($used) === 0) {
            throw DatasetImportException::noColumns();
        }

        DB::transaction(function () use ($dataset, $name, $plan): void {
            foreach ($plan as $item) {
                $item['column']->update([
                    'included' => $item['included'],
                    'type' => $item['type'],
                    'label' => $item['label'],
                    'slot' => $item['slot'],
                ]);
            }

            $dataset->update([
                'name' => Str::limit(trim($name) !== '' ? trim($name) : $dataset->name, 120, ''),
                'status' => DatasetStatus::Importing,
                'error_code' => null,
                'error_message' => null,
            ]);
        });

        ImportDatasetJob::dispatch((string) $dataset->getKey())->afterCommit();

        return $dataset->refresh();
    }
}
