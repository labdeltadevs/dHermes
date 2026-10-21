<?php

use App\Models\Tenant;
use App\Support\Integration\IntegrationDiagnostics;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

new class extends Component
{
    public string $tenantCode;

    public function mount(?string $tenantCode = null): void
    {
        $this->tenantCode = $tenantCode ?? app(Tenant::class)
            ->where('id', Auth::user()->tenant_id ?? '')
            ->value('code') ?? '';
    }

    #[Computed]
    public function tenant(): ?Tenant
    {
        return Tenant::query()
            ->where('code', $this->tenantCode)
            ->first();
    }

    #[Computed]
    public function checks(): array
    {
        if ($this->tenant === null) {
            return [];
        }

        return app(IntegrationDiagnostics::class)->run($this->tenant->id);
    }

    #[Computed]
    public function lastSyncedAt(): array
    {
        if ($this->tenant === null) {
            return [];
        }

        return app(IntegrationDiagnostics::class)->lastSyncedAt($this->tenant->id);
    }
}
?>
<section class="max-w-6xl mx-auto px-4 py-6 space-y-6">
    <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-[#ff671f]">Calidad de integracion</h1>
            <p class="text-sm text-neutral-500">Diagnostico del contrato RCT2 y consistencia SAP Business One.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.integration.check', ['tenant' => $tenantCode]) }}" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 text-white px-4 py-2 text-sm font-medium hover:opacity-90">
                Ejecutar comando
            </a>
        </div>
    </header>

    @if ($tenant === null)
        <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
            No se encontro la empresa {{ $tenantCode }}.
        </div>
    @else
        <div class="rounded-xl border bg-white dark:bg-neutral-950 px-4 py-4">
            <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <dt class="text-neutral-500">Empresa</dt>
                    <dd class="font-medium">{{ $tenant->name }} ({{ $tenant->code }})</dd>
                </div>
                <div>
                    <dt class="text-neutral-500">Ultima sincronizacion</dt>
                    <dd class="font-medium">
                        @foreach($lastSyncedAt as $table => $ts)
                            <div class="flex justify-between gap-4">
                                <span class="capitalize">{{ $table }}</span>
                                <span class="text-neutral-600">{{ $ts ?? 'sin datos' }}</span>
                            </div>
                        @endforeach
                    </dd>
                </div>
            </dl>
        </div>

        @foreach ($checks as $check)
            <div class="rounded-xl border bg-white dark:bg-neutral-950 p-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="font-medium text-neutral-900 dark:text-neutral-100">{{ $check['label'] }}</h2>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ $check['description'] }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
                        @if($check['severity'] === 'error') bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300
                        @elseif($check['severity'] === 'warning') bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300
                        @else bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 @endif
                    ">
                        {{ strtoupper($check['severity'] ?? 'info') }} &bull; {{ number_format($check['count']) }}
                    </span>
                </div>
            </div>
        @endforeach
    @endif
</section>
