@props(['items' => []])

@if (count($items) > 0)
    <nav aria-label="Ruta de navegación">
        <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs font-medium text-muted">
            @foreach ($items as $item)
                <li class="flex items-center gap-1.5">
                    @if (! $loop->first)
                        <svg class="size-3 shrink-0 text-muted/50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 6 6 6-6 6" />
                        </svg>
                    @endif

                    @isset($item['url'])
                        <a href="{{ $item['url'] }}" wire:navigate
                            class="rounded transition hover:text-brand-700">
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span @class([
                            'text-ink-soft' => $loop->last,
                        ]) @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                    @endisset
                </li>
            @endforeach
        </ol>
    </nav>
@endif
