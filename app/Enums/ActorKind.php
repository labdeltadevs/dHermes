<?php

namespace App\Enums;

enum ActorKind: string
{
    case User = 'user';
    case System = 'system';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
