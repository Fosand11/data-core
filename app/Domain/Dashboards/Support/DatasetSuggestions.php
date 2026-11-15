<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\Ai\Assistant\QuestionSuggester;
use App\Domain\Dashboards\Models\Dataset;

/**
 * The questions the Home and "Nueva pregunta" screens offer for an uploaded dataset, from
 * its column names and types alone.
 */
final class DatasetSuggestions
{
    public function __construct(
        private readonly QuestionSuggester $suggester,
        private readonly DatasetCatalogResolver $catalogs,
    ) {}

    /**
     * @return list<string>
     */
    public function for(Dataset $dataset, int $limit = 4): array
    {
        return $this->suggester->suggest($this->catalogs->forDataset($dataset), $limit);
    }
}
