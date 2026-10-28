<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Integration\IntegrationDiagnostics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dhermes:integration-check {--tenant= : Codigo o ID de empresa} {--json : Emite el resultado en JSON}')]
#[Description('Valida la calidad de los datos importados desde SAP Business One')]
class CheckIntegrationQuality extends Command
{
    public function handle(IntegrationDiagnostics $diagnostics): int
    {
        $tenantId = null;
        $raw = $this->option('tenant');
        $tenantOption = is_string($raw) ? $raw : '';

        if ($tenantOption !== '') {
            $tenant = Tenant::query()
                ->where('id', $tenantOption)
                ->orWhere('code', $tenantOption)
                ->first();

            if ($tenant === null) {
                $this->error('No se encontro la empresa indicada.');

                return self::FAILURE;
            }

            $tenantId = $tenant->id;
        }

        $checks = $diagnostics->run($tenantId);
        $lastSyncedAt = $diagnostics->lastSyncedAt($tenantId);
        $errorCount = collect($checks)
            ->where('severity', 'error')
            ->sum('count');
        $warningCount = collect($checks)
            ->where('severity', 'warning')
            ->sum('count');

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'tenant_id' => $tenantId,
                'checks' => $checks,
                'last_synced_at' => $lastSyncedAt,
                'error_count' => $errorCount,
                'warning_count' => $warningCount,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->table(
            ['Estado', 'Chequeo', 'Cantidad', 'Detalle'],
            array_map(fn (array $check): array => [
                strtoupper($check['severity']),
                $check['label'],
                number_format($check['count']),
                $check['description'],
            ], $checks),
        );

        $this->newLine();
        $this->line('Ultima sincronizacion por tabla:');

        foreach ($lastSyncedAt as $table => $syncedAt) {
            $this->line(sprintf('  %-24s %s', $table, $syncedAt ?? 'sin datos'));
        }

        $this->newLine();

        if ($errorCount > 0) {
            $this->error(sprintf('Se encontraron %d registros con errores de integracion.', $errorCount));

            return self::FAILURE;
        }

        $this->info(sprintf('Sin errores de integracion. Advertencias: %d.', $warningCount));

        return self::SUCCESS;
    }
}
