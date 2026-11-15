<?php

namespace App\Domain\Dashboards\Datasets;

use Illuminate\Support\Str;

/**
 * Derives the name dashboards use for each column ("Monto total" becomes
 * "monto_total") from the file's headers. The result is always a simple identifier
 * (letters, digits, underscore; starts with a letter), unique within the file.
 */
final class ColumnKeyGenerator
{
    private const MAX_LENGTH = 50;

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    public function generate(array $labels): array
    {
        $keys = [];
        $used = [];

        foreach ($labels as $index => $label) {
            $base = $this->slug($label) ?: 'columna_'.($index + 1);
            $key = $base;
            $suffix = 2;

            while (isset($used[$key])) {
                $key = mb_substr($base, 0, self::MAX_LENGTH - strlen((string) $suffix) - 1).'_'.$suffix;
                $suffix++;
            }

            $used[$key] = true;
            $keys[] = $key;
        }

        return $keys;
    }

    private function slug(string $label): string
    {
        $slug = Str::of(Str::ascii($label))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

        if ($slug === '') {
            return '';
        }

        // The validator wants an identifier that starts with a letter.
        if (preg_match('/^[a-z]/', $slug) !== 1) {
            $slug = 'c_'.$slug;
        }

        return mb_substr($slug, 0, self::MAX_LENGTH);
    }
}
