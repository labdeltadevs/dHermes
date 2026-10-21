<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Unknown = 'unknown';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
