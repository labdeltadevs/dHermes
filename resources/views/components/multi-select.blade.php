@props([
    'id',
    'label',
    'property',
    'options' => [],
    'selected' => [],
    'placeholder' => 'Todos',
])

@php
    $selectedValues = array_values(is_array($selected) ? $selected : (array) $selected);
    $optionLabels = collect($options)->mapWithKeys(fn (array $option): array => [$option['value'] => $option['label']]);

    $summary = match (count($selectedValues)) {
        0 => $placeholder,
        1 => (string) ($optionLabels[$selectedValues[0]] ?? $selectedValues[0]),
        default => count($selectedValues).' seleccionados',
    };
@endphp

<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
    <label for="{{ $id }}" class="label">{{ $label }}</label>

    <button type="button" id="{{ $id }}" x-on:click="open = ! open" :aria-expanded="open" aria-haspopup="true"
        class="input flex cursor-pointer items-center justify-between gap-2 text-left">
        <span class="truncate {{ $selectedValues === [] ? 'text-muted' : '' }}">{{ $summary }}</span>
        <svg class="size-3.5 shrink-0 text-muted transition" :class="open ? 'rotate-180' : ''" fill="none"
            stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.left
        class="absolute left-0 top-full z-40 mt-2 w-full min-w-56 origin-top-left rounded-2xl border border-line bg-surface p-2 shadow-pop">
        <button type="button" wire:click="$set('{{ $property }}', [])"
            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-medium text-ink-soft transition hover:bg-brand-50">
            {{ $placeholder }}
        </button>

        <div class="my-1 h-px bg-line"></div>

        <div class="max-h-64 space-y-0.5 overflow-y-auto">
            @foreach ($options as $option)
                <label wire:key="{{ $id }}-{{ $option['value'] }}"
                    class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-ink-soft transition hover:bg-brand-50">
                    <input type="checkbox" value="{{ $option['value'] }}" wire:model.live="{{ $property }}"
                        class="size-4 shrink-0 rounded border-line text-brand-700 focus:ring-brand-500">
                    <span class="truncate">{{ $option['label'] }}</span>
                </label>
            @endforeach
        </div>
    </div>
</div>
