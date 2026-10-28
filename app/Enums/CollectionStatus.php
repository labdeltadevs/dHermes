<?php

namespace App\Enums;

enum CollectionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En revisión',
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
            self::Cancelled => 'Anulado',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
