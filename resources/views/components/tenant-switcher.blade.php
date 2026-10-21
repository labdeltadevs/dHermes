@props([
    'memberships' => collect(),
    'currentTenantId' => null,
])

@if ($memberships->count() > 1)
    <div class="mt-1 border-t border-black/5 pt-1">
        <p class="px-3 pb-1 pt-2 text-[11px] font-bold uppercase tracking-[0.12em] text-black/60">Empresa</p>

        <div class="space-y-1">
            @foreach ($memberships as $membership)
                @php
                    $isCurrent = $membership->tenant_id === $currentTenantId;
                @endphp

                <form method="POST" action="{{ route('tenant.switch') }}">
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ $membership->tenant_id }}">

                    <button type="submit"
                        class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm transition focus:outline-none focus:ring-4 focus:ring-brand-500/15 {{ $isCurrent ? 'bg-brand-50 font-semibold text-brand-800' : 'font-medium text-black/70 hover:bg-black/[0.04] hover:text-ink' }}">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg {{ $isCurrent ? 'bg-brand-500 text-ink' : 'bg-ink text-brand-500' }}">
                            {{ mb_strtoupper(mb_substr($membership->tenant?->code ?? '?', 0, 2)) }}
                        </span>
                        <span class="min-w-0 flex-1 truncate">{{ $membership->tenant?->name }}</span>
                        @if ($isCurrent)
                            <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7" />
                            </svg>
                        @endif
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endif
