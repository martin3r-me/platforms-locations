<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dashboard" icon="heroicon-o-home" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Locations', 'icon' => 'map-pin'],
        ]">
            <x-ui-button variant="primary" size="sm" :href="route('locations.manage')" wire:navigate>
                @svg('heroicon-o-building-office', 'w-4 h-4')
                <span>Verwalten</span>
            </x-ui-button>
            <x-ui-button variant="ghost" size="sm" :href="route('locations.occupancy')" wire:navigate>
                @svg('heroicon-o-chart-bar', 'w-4 h-4')
                <span>Auslastung</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    {{-- Hauptinhalt --}}
    <x-ui-page-container>
        <div class="space-y-6">

            {{-- Kopfzeile: kompakt statt grosser Hero-Flaeche --}}
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)] flex items-center justify-center flex-shrink-0">
                    @svg('heroicon-o-map-pin', 'w-5 h-5 text-[var(--ui-secondary)]')
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold text-[var(--ui-secondary)] m-0 leading-tight">Locations</h1>
                    <p class="text-xs text-[var(--ui-muted)] m-0">Verwalte Locations, Räume und deren Auslastung.</p>
                </div>
            </div>

            {{-- Kennzahlen: Icon links im festen Quadrat, Zahl kann das Icon nicht mehr ueberlaufen --}}
            @php
                $tiles = [
                    ['label' => 'Locations',        'value' => $totalLocations,    'hint' => 'gesamt',          'icon' => 'heroicon-o-map-pin'],
                    ['label' => 'Sites',            'value' => $uniqueSites,       'hint' => 'unterschiedlich', 'icon' => 'heroicon-o-building-library'],
                    ['label' => 'Kapazität',        'value' => $totalCapacity,     'hint' => 'PAX gesamt',      'icon' => 'heroicon-o-users'],
                    ['label' => 'Mehrfachbelegung', 'value' => $multiUseLocations, 'hint' => 'Locations',       'icon' => 'heroicon-o-arrow-path'],
                ];
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach($tiles as $tile)
                    <div class="bg-white border border-[var(--ui-border)] rounded-xl p-4 flex items-start gap-3">
                        <div class="w-9 h-9 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 flex items-center justify-center flex-shrink-0">
                            <x-dynamic-component :component="$tile['icon']" class="w-4 h-4 text-[var(--ui-secondary)]" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] truncate">{{ $tile['label'] }}</div>
                            <div class="text-2xl font-bold text-[var(--ui-secondary)] leading-tight tabular-nums">{{ number_format($tile['value'], 0, ',', '.') }}</div>
                            <div class="text-[0.62rem] text-[var(--ui-muted)] truncate">{{ $tile['hint'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Uebersicht (vormals linke Sidebar): Sites-Verteilung + groesste Locations --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Sites nach Kapazitaet --}}
                <div class="lg:col-span-2 bg-white border border-[var(--ui-border)] rounded-xl p-4">
                    <div class="flex items-baseline justify-between gap-2 mb-3">
                        <h2 class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] m-0">Sites nach Kapazität</h2>
                        <a href="{{ route('locations.sites') }}" wire:navigate
                           class="text-[0.62rem] text-[var(--ui-muted)] no-underline hover:text-[var(--ui-secondary)]">Alle Sites →</a>
                    </div>

                    @if($siteBreakdown->isEmpty())
                        <p class="text-[0.7rem] text-[var(--ui-muted)] m-0">Noch keine Sites gepflegt.</p>
                    @else
                        @php $maxSiteCapacity = (int) $siteBreakdown->max('capacity'); @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($siteBreakdown as $group)
                                @php
                                    $share = $maxSiteCapacity > 0 ? round($group['capacity'] / $maxSiteCapacity * 100) : 0;
                                    $url   = $group['uuid'] ? route('locations.sites.show', $group['uuid']) : null;
                                    // Gruppe "Ohne Site" hat kein Ziel -> dann als div rendern statt als Link
                                    $tag   = $url ? 'a' : 'div';
                                @endphp
                                <{{ $tag }}
                                    @if($url) href="{{ $url }}" wire:navigate @endif
                                    class="block no-underline rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] px-3 py-2 {{ $url ? 'hover:border-[var(--ui-border)] hover:shadow-sm transition' : '' }}">
                                    <div class="flex items-baseline justify-between gap-2 mb-1.5">
                                        <span class="text-xs font-semibold text-[var(--ui-secondary)] truncate">{{ $group['name'] }}</span>
                                        <span class="text-[0.6rem] font-mono text-[var(--ui-muted)] flex-shrink-0 tabular-nums">
                                            {{ $group['count'] }}× · {{ number_format($group['capacity'], 0, ',', '.') }} PAX
                                        </span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-[var(--ui-muted)]/10 overflow-hidden">
                                        <div class="h-full rounded-full bg-[var(--ui-primary)] transition-all" style="width: {{ $share }}%"></div>
                                    </div>
                                </{{ $tag }}>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Groesste Locations --}}
                <div class="bg-white border border-[var(--ui-border)] rounded-xl p-4">
                    <div class="flex items-baseline justify-between gap-2 mb-3">
                        <h2 class="text-[0.65rem] font-bold uppercase tracking-wider text-[var(--ui-muted)] m-0">Größte Locations</h2>
                        <a href="{{ route('locations.manage') }}" wire:navigate
                           class="text-[0.62rem] text-[var(--ui-muted)] no-underline hover:text-[var(--ui-secondary)]">Alle →</a>
                    </div>

                    @if($topLocations->isEmpty())
                        <p class="text-[0.7rem] text-[var(--ui-muted)] m-0">Noch keine Kapazitäten gepflegt.</p>
                    @else
                        <div>
                            @foreach($topLocations as $loc)
                                <a href="{{ route('locations.show', $loc['uuid']) }}" wire:navigate
                                   class="flex items-center gap-2 py-1.5 border-b border-[var(--ui-border)]/40 last:border-0 no-underline hover:bg-slate-50 rounded px-1 -mx-1">
                                    <span class="text-[0.6rem] font-mono font-bold px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/60 uppercase flex-shrink-0">{{ $loc['kuerzel'] }}</span>
                                    <span class="text-xs text-[var(--ui-secondary)] truncate flex-1">{{ $loc['name'] }}</span>
                                    <span class="text-[0.62rem] font-mono font-semibold text-[var(--ui-muted)] flex-shrink-0 tabular-nums">{{ number_format($loc['pax_max'], 0, ',', '.') }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </x-ui-page-container>

    {{-- Rechte Sidebar (Aktivitäten) --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4">
                <p class="text-xs text-[var(--ui-muted)]">Noch keine Aktivitäten.</p>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
