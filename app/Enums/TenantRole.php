<?php

namespace App\Enums;

enum TenantRole: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Seller = 'seller';
    case Collector = 'collector';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Supervisor => 'Supervisor',
            self::Seller => 'Vendedor',
            self::Collector => 'Cobrador',
        };
    }

    public function canManageTenant(): bool
    {
        return $this === self::Admin;
    }

    public function canApprove(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    public function canMatch(): bool
    {
        return $this->canApprove();
    }

    public function sells(): bool
    {
        return in_array($this, [self::Seller, self::Collector], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
