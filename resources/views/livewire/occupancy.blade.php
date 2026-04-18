@php
    $months = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
    $periods = ['week' => 'Diese Woche', 'month' => 'Dieser Monat', 'year' => 'Dieses Jahr', 'all' => 'Nächste 3 Monate'];

    $statusColor = [
        'Vertrag'    => ['bg' => '#dcfce7', 'color' => '#065f46', 'border' => '#86efac'],
        'Definitiv'  => ['bg' => '#f0fdf4', 'color' => '#15803d', 'border' => '#bbf7d0'],
        '1. Option'  => ['bg' => '#fefce8', 'color' => '#854d0e', 'border' => '#fde68a'],
        '2. Option'  => ['bg' => '#fff7ed', 'color' => '#c2410c', 'border' => '#fed7aa'],
        '3. Option'  => ['bg' => '#faf5ff', 'color' => '#7c3aed', 'border' => '#ddd6fe'],
        'Abgesagt'   => ['bg' => '#fef2f2', 'color' => '#b91c1c', 'border' => '#fecaca'],
    ];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Auslastung" icon="heroicon-o-chart-bar" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">

            {{-- ===== Filter: Zeitraum ===== --}}
            <div class="flex flex-wrap gap-2">
                @foreach($periods as $key => $label)
                    <button
                        type="button"
                        wire:click="setPeriod('{{ $key }}')"
                        class="px-3 py-1.5 rounded-md text-[0.72rem] font-semibold border transition-colors
                               {{ $period === $key
                                    ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]'
                                    : 'bg-white text-[var(--ui-muted)] border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)]' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- ===== Filter: Gruppe ===== --}}
            @if(!empty($roomGroups))
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[0.6rem] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mr-1">Gruppe:</span>
                    <button
                        type="button"
                        wire:click="setGroup('')"
                        class="px-3 py-1 rounded-md text-[0.65rem] font-semibold border transition-colors
                               {{ empty($activeGroup)
                                    ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]'
                                    : 'bg-white text-[var(--ui-muted)] border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)]' }}"
                    >
                        Alle
                    </button>
                    @foreach($roomGroups as $grp)
                        <button
                            type="button"
                            wire:click="setGroup('{{ $grp }}')"
                            class="px-3 py-1 rounded-md text-[0.65rem] font-semibold border transition-colors
                                   {{ $activeGroup === $grp
                                        ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]'
                                        : 'bg-white text-[var(--ui-muted)] border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)]' }}"
                        >
                            {{ $grp }}
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- ===== Leerer State (keine Locations) ===== --}}
            @if($locations->isEmpty())
                <x-ui-panel>
                    <div class="p-12 text-center">
                        @svg('heroicon-o-building-office', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                        <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Keine Locations vorhanden</p>
                        <p class="text-xs text-[var(--ui-muted)]">
                            Lege zuerst Locations im Bereich
                            <a href="{{ route('locations.manage') }}" wire:navigate class="text-[var(--ui-primary)] font-semibold">Locations verwalten</a>
                            an.
                        </p>
                    </div>
                </x-ui-panel>
            @else

                {{-- ===== Legende ===== --}}
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[0.6rem] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Status:</span>
                    @foreach($statusColor as $label => $sc)
                        <span class="text-[0.62rem] font-bold px-2 py-0.5 rounded-full"
                              style="background:{{ $sc['bg'] }}; color:{{ $sc['color'] }}; border:1px solid {{ $sc['border'] }};">
                            {{ $label }}
                        </span>
                    @endforeach
                </div>

                {{-- ===== Auslastungs-Tabelle ===== --}}
                @if(empty($byDate))
                    <x-ui-panel>
                        <div class="p-12 text-center">
                            @svg('heroicon-o-calendar', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                            <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Noch keine Buchungen</p>
                            <p class="text-xs text-[var(--ui-muted)]">
                                Buchungen werden automatisch angezeigt, sobald das Events-Modul verbunden ist.
                            </p>
                        </div>
                    </x-ui-panel>
                @else
                    <div class="bg-white border border-[var(--ui-border)] rounded-lg overflow-x-auto">
                        <table class="w-full border-collapse" style="min-width: {{ 110 + count($roomNames) * 130 }}px;">
                            <thead>
                                <tr class="bg-[var(--ui-muted-5)] border-b-2 border-[var(--ui-border)]">
                                    <th class="px-3 py-2.5 text-left text-[0.62rem] font-semibold uppercase tracking-wider text-[var(--ui-muted)] sticky left-0 bg-[var(--ui-muted-5)] z-10">Datum</th>
                                    @foreach($roomNames as $rn)
                                        @php $vr = $locations->firstWhere('kuerzel', $rn); @endphp
                                        <th class="px-2 py-2.5 text-left text-[0.62rem] font-semibold uppercase tracking-wider text-[var(--ui-muted)] font-mono" title="{{ $vr->name ?? $rn }}">
                                            {{ $rn }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @php $today = now()->toDateString(); @endphp
                                @foreach($byDate as $date => $rooms)
                                    @php
                                        $dt = \Carbon\Carbon::parse($date);
                                        $isToday = $date === $today;
                                        $isPast  = $date < $today;
                                    @endphp
                                    <tr class="border-b border-[var(--ui-border)]/50 {{ $isPast ? 'opacity-60' : '' }} {{ $isToday ? 'bg-blue-50' : '' }}">
                                        <td class="px-3 py-2.5 align-top sticky left-0 {{ $isToday ? 'bg-blue-50' : 'bg-white' }} z-[1]">
                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-xs font-bold font-mono {{ $isToday ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)]' }}">{{ $dt->format('d') }}.</span>
                                                <span class="text-[0.6rem] text-[var(--ui-muted)]">{{ $months[$dt->month - 1] }} {{ $dt->year }}</span>
                                            </div>
                                        </td>
                                        @foreach($roomNames as $rn)
                                            <td class="px-2 py-2 align-top">
                                                @if(isset($rooms[$rn]))
                                                    <div class="flex flex-col gap-1">
                                                        @foreach($rooms[$rn] as $booking)
                                                            @php $sc = $statusColor[$booking->optionsrang ?? ''] ?? ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0']; @endphp
                                                            <div class="rounded-md px-2 py-1.5"
                                                                 style="background:{{ $sc['bg'] }}; border:1px solid {{ $sc['border'] }};">
                                                                <p class="text-[0.68rem] font-bold text-[var(--ui-secondary)] truncate">{{ $booking->title ?? '—' }}</p>
                                                                @if(!empty($booking->optionsrang))
                                                                    <span class="text-[0.58rem] font-bold" style="color:{{ $sc['color'] }};">{{ $booking->optionsrang }}</span>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="h-8 rounded-md"
                                                         style="background:repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(0,0,0,0.015) 4px, rgba(0,0,0,0.015) 8px);"></div>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- ===== Monatsauslastung ===== --}}
                @if(!empty($monthlyStats))
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <p class="text-sm font-bold text-[var(--ui-secondary)]">Monatsauslastung</p>
                            <span class="text-[0.6rem] text-[var(--ui-muted)]">gebuchte Tage / Tage im Monat</span>
                        </div>
                        <x-ui-panel>
                            {{-- Platzhalter – wird befüllt wenn Buchungsdaten vorhanden --}}
                            <div class="p-6 text-center text-xs text-[var(--ui-muted)]">
                                Monatsauslastung wird berechnet sobald Buchungen vorliegen.
                            </div>
                        </x-ui-panel>
                    </div>
                @endif
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
