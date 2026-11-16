<?php

namespace App\Support\Collection;

/**
 * Normaliza el código de cliente (CardCode de SAP) que los vendedores
 * escriben a mano en el cobro flash, para tolerar errores de tipeo:
 * sin espacios, sin símbolos y siempre en mayúsculas.
 */
final class CardcodeNormalizer
{
    public static function normalize(string $value): string
    {
        $stripped = preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '';

        return mb_strtoupper($stripped);
    }
}
