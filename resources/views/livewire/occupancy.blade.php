@php
    $months = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];

    $periodOptions = [
        ['value' => 'week',  'label' => 'Woche',       'icon' => 'heroicon-o-calendar'],
        ['value' => 'month', 'label' => 'Monat',       'icon' => 'heroicon-o-calendar-days'],
        ['value' => 'year',  'label' => 'Jahr',        'icon' => 'heroicon-o-calendar-days'],
        ['value' => 'all',   'label' => '3 Monate',    'icon' => 'heroicon-o-arrow-trending-up'],
    ];

    $siteOptions = array_merge(
        [['value' => '', 'label' => 'Alle']],
        $sites->map(fn ($s) => ['value' => $s->uuid, 'label' => $s->name])->toArray()
    );
    $activeSiteName = $activeSite
        ? optional($sites->firstWhere('uuid', $activeSite))->name
        : null;

    $statusColor = [
        'Vertrag'    => ['bg' => '#dcfce7', 'color' => '#065f46', 'border' => '#86efac'],
        'Definitiv'  => ['bg' => '#f0fdf4', 'color' => '#15803d', 'border' => '#bbf7d0'],
        '1. Option'  => ['bg' => '#fefce8', 'color' => '#854d0e', 'border' => '#fde68a'],
        '2. Option'  => ['bg' => '#fff7ed', 'color' => '#c2410c', 'border' => '#fed7aa'],
        '3. Option'  => ['bg' => '#faf5ff', 'color' => '#7c3aed', 'border' => '#ddd6fe'],
        'Abgesagt'   => ['bg' => '#fef2f2', 'color' => '#b91c1c', 'border' => '#fecaca'],
        'Gesperrt'   => ['bg' => '#f1f5f9', 'color' => '#334155', 'border' => '#cbd5e1'],
    ];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Auslastung" icon="heroicon-o-chart-bar" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Locations', 'href' => route('locations.dashboard'), 'icon' => 'map-pin'],
            ['label' => 'Auslastung'],
        ]">
            <x-slot name="left">
                <x-ui-button variant="ghost" size="sm" :href="route('locations.manage')" wire:navigate>
                    @svg('heroicon-o-building-office', 'w-4 h-4')
                    <span>Verwalten</span>
                </x-ui-button>
            </x-slot>
            <span class="text-[0.68rem] font-mono text-[var(--ui-muted)]">
                {{ \Carbon\Carbon::parse($periodStart)->format('d.m.Y') }}
                –
                {{ \Carbon\Carbon::parse($periodEnd)->format('d.m.Y') }}
            </span>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">

            {{-- ===== Header ===== --}}
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">Raum-Auslastung</h1>
                    <p class="text-xs text-[var(--ui-muted)] mt-1">
                        Übersicht der Buchungen für {{ $periodLabel }}
                        @if($activeSiteName)
                            · Site <span class="font-semibold text-[var(--ui-secondary)]">{{ $activeSiteName }}</span>
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

                    {{-- Site --}}
                    @if($sites->isNotEmpty())
                        <div class="flex items-center gap-3 flex-wrap border-t border-[var(--ui-border)]/40 pt-4">
                            <span class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-20 flex-shrink-0">
                                Site
                            </span>
                            <x-ui-segmented-toggle
                                model="activeSite"
                                :current="$activeSite"
                                :options="$siteOptions"
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

                    {{-- ===== Auslastung pro Location ===== --}}
                    @if(!empty($utilization))
                        <x-ui-panel title="Auslastung pro Location" subtitle="Belegte Tage im gewählten Zeitraum ({{ $periodLabel }})">
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse">
                                    <thead>
                                        <tr class="bg-[var(--ui-muted-5)] border-b-2 border-[var(--ui-border)]">
                                            <th class="px-3 py-2.5 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Location</th>
                                            <th class="px-3 py-2.5 text-right text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Belegt</th>
                                            <th class="px-3 py-2.5 text-right text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Optionen</th>
                                            <th class="px-3 py-2.5 text-right text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Gesperrt</th>
                                            <th class="px-3 py-2.5 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] w-48">Quote</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($utilization as $u)
                                            <tr class="border-b border-[var(--ui-border)]/50" wire:key="util-{{ $u['kuerzel'] }}">
                                                <td class="px-3 py-2">
                                                    <span class="text-xs font-mono font-bold text-[var(--ui-secondary)]">{{ $u['kuerzel'] }}</span>
                                                    <span class="text-[0.62rem] text-[var(--ui-muted)] ml-1.5">{{ $u['name'] }}</span>
                                                </td>
                                                <td class="px-3 py-2 text-right text-xs font-mono font-bold text-[var(--ui-secondary)]">{{ $u['belegt'] }}<span class="text-[var(--ui-muted)] font-normal">/{{ $u['total_days'] }}</span></td>
                                                <td class="px-3 py-2 text-right text-xs font-mono text-[var(--ui-muted)]">{{ $u['optionen'] }}</td>
                                                <td class="px-3 py-2 text-right text-xs font-mono text-[var(--ui-muted)]">{{ $u['gesperrt'] }}</td>
                                                <td class="px-3 py-2">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex-1 h-2 rounded-full bg-[var(--ui-muted-5)] overflow-hidden">
                                                            <div class="h-full rounded-full bg-[var(--ui-primary)]" style="width: {{ min(100, $u['quote']) }}%;"></div>
                                                        </div>
                                                        <span class="text-[0.62rem] font-mono font-bold text-[var(--ui-secondary)] w-12 text-right">{{ number_format($u['quote'], 1, ',', '.') }}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-[0.62rem] text-[var(--ui-muted)] px-3 py-2">Quote = Tage mit Definitiv-/Vertrags-Buchung geteilt durch Tage im Zeitraum. Options- und Sperrtage zählen nicht in die Quote.</p>
                        </x-ui-panel>
                    @endif
                @endif
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
