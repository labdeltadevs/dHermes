<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qr = 'qr';
    case DepositTransfer = 'deposit_transfer';
    case Check = 'check';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Qr => 'QR',
            self::DepositTransfer => 'Depósito-Transferencia',
            self::Check => 'Cheque',
            self::Other => 'Otros',
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
