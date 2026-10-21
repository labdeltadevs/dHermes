<?php

namespace App\Enums;

enum CollectionStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case Discrepancy = 'discrepancy';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
