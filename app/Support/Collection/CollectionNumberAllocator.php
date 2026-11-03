<?php

namespace App\Support\Collection;

use App\Models\CollectionEvent;
use Illuminate\Support\Facades\DB;

/**
 * Asigna el número correlativo de cobranza por empresa, con el formato
 * CODE-######### (prefijo del tenant + 9 dígitos).
 */
final class CollectionNumberAllocator
{
    /**
     * @return array{number: int, number_code: string}
     */
    public function next(string $tenantId): array
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql') {
            // Serializa las asignaciones concurrentes de la misma empresa.
            $connection->table('tenants')->where('id', $tenantId)->lockForUpdate()->first();
        }

        $number = (int) CollectionEvent::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->max('number') + 1;

        $code = (string) ($connection->table('tenants')->where('id', $tenantId)->value('code') ?? '');

        return [
            'number' => $number,
            'number_code' => self::format($code, $number),
        ];
    }

    public static function format(string $code, int $number): string
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($code))) ?: 'COBRO';

        return $prefix.'-'.str_pad((string) $number, 9, '0', STR_PAD_LEFT);
    }
}
