@php
    $months = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];

    $periodOptions = [
        ['value' => 'week',  'label' => 'Woche',       'icon' => 'heroicon-o-calendar'],
        ['value' => 'month', 'label' => 'Monat',       'icon' => 'heroicon-o-calendar-days'],
        ['value' => 'year',  'label' => 'Jahr',        'icon' => 'heroicon-o-calendar-days'],
        ['value' => 'all',   'label' => '3 Monate',    'icon' => 'heroicon-o-arrow-trending-up'],
    ];

    $groupOptions = array_merge(
        [['value' => '', 'label' => 'Alle']],
        array_map(fn($g) => ['value' => $g, 'label' => $g], $roomGroups)
    );

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
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Locations', 'route' => 'locations.dashboard'],
            ['label' => 'Auslastung'],
        ]">
            <span class="text-[0.68rem] font-mono text-[var(--ui-muted)]">
                {{ \Carbon\Carbon::parse($periodStart)->format('d.m.Y') }}
                –
                {{ \Carbon\Carbon::parse($periodEnd)->format('d.m.Y') }}
            </span>
        </x-ui-page-actionbar>

        <div class="space-y-6 pt-4">

            {{-- ===== Header ===== --}}
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">Raum-Auslastung</h1>
                    <p class="text-xs text-[var(--ui-muted)] mt-1">
                        Übersicht der Buchungen für {{ $periodLabel }}
                        @if($activeGroup)
                            · Gruppe <span class="font-semibold text-[var(--ui-secondary)]">{{ $activeGroup }}</span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- ===== Filter-Panel ===== --}}
            <x-ui-panel>
                <div class="flex flex-col gap-4">
                    {{-- Zeitraum --}}
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-20 flex-shrink-0">
                            Zeitraum
                        </span>
                        <x-ui-segmented-toggle
                            model="period"
                            :current="$period"
                            :options="$periodOptions"
                            size="sm"
                            activeVariant="secondary"
                        />
                    </div>

                    {{-- Gruppe --}}
                    @if(!empty($roomGroups))
                        <div class="flex items-center gap-3 flex-wrap border-t border-[var(--ui-border)]/40 pt-4">
                            <span class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-20 flex-shrink-0">
                                Gruppe
                            </span>
                            <x-ui-segmented-toggle
                                model="activeGroup"
                                :current="$activeGroup"
                                :options="$groupOptions"
                                size="sm"
                                activeVariant="secondary"
                            />
                        </div>
                    @endif
                </div>
            </x-ui-panel>

            {{-- ===== Leerer State: keine Locations ===== --}}
            @if($locations->isEmpty())
                <x-ui-panel>
                    <div class="p-12 text-center">
                        @svg('heroicon-o-building-office', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                        <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Keine Locations vorhanden</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">
                            Lege zuerst Locations an, damit eine Auslastung angezeigt werden kann.
                        </p>
                        <x-ui-button variant="primary" size="sm" :href="route('locations.manage')" wire:navigate>
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Locations verwalten
                            </span>
                        </x-ui-button>
                    </div>
                </x-ui-panel>
            @else

                {{-- ===== Auslastung: Leerstate (keine Buchungen) ===== --}}
                @if(empty($byDate))
                    <x-ui-panel title="Belegungskalender" subtitle="Buchungen pro Tag und Raum">
                        <div class="p-12 text-center">
                            @svg('heroicon-o-calendar', 'w-12 h-12 text-[var(--ui-muted)] mx-auto mb-3')
                            <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Noch keine Buchungen</p>
                            <p class="text-xs text-[var(--ui-muted)] max-w-md mx-auto">
                                Buchungen erscheinen hier automatisch, sobald das Events-Modul verbunden ist und
                                Raum-Reservierungen im gewählten Zeitraum existieren.
                            </p>
                            <div class="mt-5 flex items-center justify-center gap-1.5 flex-wrap">
                                @foreach($roomNames as $rn)
                                    <span class="text-[0.62rem] font-mono font-bold px-2 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/60">{{ $rn }}</span>
                                @endforeach
                            </div>
                        </div>
                    </x-ui-panel>
                @else
                    {{-- ===== Legende (nur wenn Buchungen existieren) ===== --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Status:</span>
                        @foreach($statusColor as $label => $sc)
                            <span class="text-[0.62rem] font-bold px-2 py-0.5 rounded-full"
                                  style="background:{{ $sc['bg'] }}; color:{{ $sc['color'] }}; border:1px solid {{ $sc['border'] }};">
                                {{ $label }}
                            </span>
                        @endforeach
                    </div>

                    {{-- ===== Auslastungs-Tabelle ===== --}}
                    <x-ui-panel>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse" style="min-width: {{ 110 + count($roomNames) * 130 }}px;">
                                <thead>
                                    <tr class="bg-[var(--ui-muted-5)] border-b-2 border-[var(--ui-border)]">
                                        <th class="px-3 py-2.5 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] sticky left-0 bg-[var(--ui-muted-5)] z-10">Datum</th>
                                        @foreach($roomNames as $rn)
                                            @php $vr = $locations->firstWhere('kuerzel', $rn); @endphp
                                            <th class="px-2 py-2.5 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] font-mono" title="{{ $vr->name ?? $rn }}">
                                                {{ $rn }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $today = now()->toDateString(); @endphp
                                    @foreach($byDate as $date => $rooms)
                                        @php
                                            $dt      = \Carbon\Carbon::parse($date);
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
                    </x-ui-panel>

                    {{-- ===== Monatsauslastung ===== --}}
                    @if(!empty($monthlyStats))
                        <x-ui-panel title="Monatsauslastung" subtitle="Gebuchte Tage pro Monat">
                            <div class="p-6 text-center text-xs text-[var(--ui-muted)]">
                                Monatsauslastung wird berechnet sobald Buchungen vorliegen.
                            </div>
                        </x-ui-panel>
                    @endif
                @endif
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
