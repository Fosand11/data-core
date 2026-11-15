<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Uploaded datasets
    |--------------------------------------------------------------------------
    |
    | Limits for the CSV / Excel files a customer can upload. They exist to keep a
    | small instance (and a free database) healthy, and are enforced twice: when the
    | file is received and again while it is imported.
    |
    */
    'max_file_kb' => (int) env('DATASETS_MAX_FILE_KB', 5120),
    'max_rows' => (int) env('DATASETS_MAX_ROWS', 20000),
    'max_columns' => (int) env('DATASETS_MAX_COLUMNS', 20),
    'max_per_tenant' => (int) env('DATASETS_MAX_PER_TENANT', 5),

    // Rows read to guess each column's type and show a preview.
    'sample_rows' => 1000,
    'preview_rows' => 5,

    // Typed storage slots per row (dataset_rows.s1.., n1.., d1..): the physical
    // columns a dataset's own columns are mapped onto. They cap how many columns of
    // each type one dataset can have, whatever max_columns says.
    'slots' => [
        'string' => 16,
        'numeric' => 12,
        'datetime' => 4,
    ],

    // Where the uploaded file waits between upload and import. Always private, and
    // always deleted once the import ends, whether it worked or not.
    'disk' => 'local',
    'directory' => 'datasets',
];
