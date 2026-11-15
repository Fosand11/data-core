<?php

namespace App\Domain\Dashboards\Datasets;

use RuntimeException;

/**
 * Something about an uploaded file that the user can act on. `reason` is a stable
 * code (for tests and logs); the message is written to be shown to the user as is.
 */
final class DatasetImportException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function unreadable(): self
    {
        return new self('FILE_UNREADABLE', 'No pudimos leer el archivo. Comprueba que sea un CSV o un Excel (.xlsx) válido y que no esté dañado o protegido con contraseña.');
    }

    public static function empty(): self
    {
        return new self('EMPTY_FILE', 'El archivo no tiene datos: necesita una fila de encabezados y al menos una fila de datos.');
    }

    public static function tooManyColumns(int $max): self
    {
        return new self('TOO_MANY_COLUMNS', "El archivo tiene demasiadas columnas. El máximo es {$max}.");
    }

    public static function tooManyOfType(string $type, int $max): self
    {
        $names = ['string' => 'de texto', 'numeric' => 'numéricas', 'datetime' => 'de fecha'];

        return new self('TOO_MANY_OF_TYPE', "Hay demasiadas columnas {$names[$type]} (máximo {$max}). Excluye algunas o cambia su tipo.");
    }

    public static function tooManyRows(int $max): self
    {
        return new self('TOO_MANY_ROWS', 'El archivo tiene demasiadas filas. El máximo es '.number_format($max, 0, ',', '.').'.');
    }

    public static function noColumns(): self
    {
        return new self('NO_COLUMNS', 'Selecciona al menos una columna para importar.');
    }

    public static function limitReached(int $max): self
    {
        return new self('DATASET_LIMIT', "Tu organización ya tiene {$max} conjuntos de datos, que es el máximo. Elimina alguno para subir otro.");
    }
}
