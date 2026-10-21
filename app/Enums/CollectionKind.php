<?php

namespace App\Enums;

enum CollectionKind: string
{
    case Full = 'full';
    case Partial = 'partial';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
