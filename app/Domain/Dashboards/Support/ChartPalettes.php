<?php

namespace App\Domain\Dashboards\Support;

/**
 * The colour palettes a dashboard can be drawn with. Each one is a fixed order of six
 * categorical colours, in a light and a dark version, so a chart is legible in either
 * theme and its neighbours stay distinguishable (including with colour-vision
 * deficiency). The six hues come from the app's own validated palette; the orders and
 * the accessible set were checked with the data-viz palette validator.
 *
 * Users pick a palette and, per chart, one of its six slots; nothing outside this
 * list is ever stored, so no free-form colour reaches a page.
 */
final class ChartPalettes
{
    public const DEFAULT = 'pino';

    public const SLOTS = 6;

    /**
     * @var array<string, array{name: string, light: list<string>, dark: list<string>}>
     */
    private const PALETTES = [
        'pino' => [
            'name' => 'Pino',
            'light' => ['#0E8F63', '#E0A31B', '#3B7DD8', '#E5634B', '#8A6FD6', '#14A3B8'],
            'dark' => ['#22A877', '#C98500', '#4A8EE8', '#D9694F', '#9480E0', '#12A0A8'],
        ],
        'oceano' => [
            'name' => 'Océano',
            'light' => ['#3B7DD8', '#0E8F63', '#8A6FD6', '#E0A31B', '#14A3B8', '#E5634B'],
            'dark' => ['#4A8EE8', '#22A877', '#9480E0', '#C98500', '#12A0A8', '#D9694F'],
        ],
        'coral' => [
            'name' => 'Coral',
            'light' => ['#E5634B', '#3B7DD8', '#0E8F63', '#8A6FD6', '#E0A31B', '#14A3B8'],
            'dark' => ['#D9694F', '#4A8EE8', '#22A877', '#9480E0', '#C98500', '#12A0A8'],
        ],
        'violeta' => [
            'name' => 'Violeta',
            'light' => ['#8A6FD6', '#0E8F63', '#3B7DD8', '#E0A31B', '#14A3B8', '#E5634B'],
            'dark' => ['#9480E0', '#22A877', '#4A8EE8', '#C98500', '#12A0A8', '#D9694F'],
        ],
        'turquesa' => [
            'name' => 'Turquesa',
            'light' => ['#14A3B8', '#E0A31B', '#8A6FD6', '#E5634B', '#3B7DD8', '#0E8F63'],
            'dark' => ['#12A0A8', '#C98500', '#9480E0', '#D9694F', '#4A8EE8', '#22A877'],
        ],
        'ambar' => [
            'name' => 'Ámbar',
            'light' => ['#E0A31B', '#3B7DD8', '#0E8F63', '#8A6FD6', '#E5634B', '#14A3B8'],
            'dark' => ['#C98500', '#4A8EE8', '#22A877', '#9480E0', '#D9694F', '#12A0A8'],
        ],
        'accesible' => [
            'name' => 'Accesible (daltonismo)',
            'light' => ['#0072B2', '#E69F00', '#009E73', '#D55E00', '#CC79A7', '#56B4E9'],
            'dark' => ['#2E8FC9', '#BF8200', '#12A07A', '#D2672A', '#B5679A', '#3F9AD0'],
        ],
    ];

    /**
     * @return array<string, array{name: string, light: list<string>, dark: list<string>}>
     */
    public static function all(): array
    {
        return self::PALETTES;
    }

    /**
     * The same list shaped for the page: id first, in display order.
     *
     * @return list<array{id: string, name: string, light: list<string>, dark: list<string>}>
     */
    public static function forPage(): array
    {
        $list = [];

        foreach (self::PALETTES as $id => $palette) {
            $list[] = ['id' => $id, ...$palette];
        }

        return $list;
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_keys(self::PALETTES);
    }

    public static function has(string $id): bool
    {
        return array_key_exists($id, self::PALETTES);
    }
}
