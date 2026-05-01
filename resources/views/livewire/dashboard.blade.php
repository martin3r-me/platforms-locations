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
            {{-- Welcome Section --}}
            <x-ui-panel title="Willkommen im Locations Modul" subtitle="Verwalte deine Standorte">
                <div class="p-6 text-center">
                    <div class="mb-4">
                        @svg('heroicon-o-map-pin', 'w-16 h-16 text-[var(--ui-primary)] mx-auto')
                    </div>
                    <h2 class="text-xl font-semibold text-[var(--ui-secondary)] mb-2">
                        Locations
                    </h2>
                    <p class="text-[var(--ui-muted)]">
                        Verwalte Locations, Räume und deren Auslastung.
                    </p>
                </div>
            </x-ui-panel>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <x-ui-dashboard-tile
                    title="Locations"
                    :count="$totalLocations"
                    subtitle="Gesamt"
                    icon="map-pin"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Gruppen"
                    :count="$uniqueGroups"
                    subtitle="unterschiedlich"
                    icon="squares-2x2"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Kapazität"
                    :count="$totalCapacity"
                    subtitle="PAX gesamt"
                    icon="users"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Mehrfachbelegung"
                    :count="$multiUseLocations"
                    subtitle="Locations"
                    icon="arrow-path"
                    variant="secondary"
                    size="lg"
                />
            </div>
        </div>
    </x-ui-page-container>

    {{-- Linke Sidebar --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-6">
                {{-- Gruppen-Breakdown --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Gruppen</h3>
                    @if($groupBreakdown->isEmpty())
                        <p class="text-xs text-[var(--ui-muted)]">Keine Gruppen gepflegt.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($groupBreakdown as $group)
                                <div class="p-2.5 rounded-md border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-semibold text-[var(--ui-secondary)] truncate">{{ $group['name'] }}</span>
                                        <span class="text-[0.62rem] font-mono text-[var(--ui-muted)] flex-shrink-0 ml-2">{{ $group['count'] }}x</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1 h-1.5 bg-[var(--ui-muted)]/10 rounded-full overflow-hidden mr-3">
                                            <div class="h-full bg-[var(--ui-primary)] rounded-full transition-all"
                                                 style="width: {{ $totalCapacity > 0 ? round($group['capacity'] / $totalCapacity * 100) : 0 }}%"></div>
                                        </div>
                                        <span class="text-[0.62rem] font-mono font-semibold text-[var(--ui-secondary)] flex-shrink-0">{{ $group['capacity'] }} PAX</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Top Locations nach Kapazität --}}
                @if($topLocations->isNotEmpty())
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Größte Locations</h3>
                        <div class="space-y-1.5">
                            @foreach($topLocations as $loc)
                                <div class="flex items-center gap-2 p-2 rounded-md border border-[var(--ui-border)]/40 bg-white">
                                    <span class="text-[0.62rem] font-mono font-bold px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/60 uppercase flex-shrink-0">{{ $loc['kuerzel'] }}</span>
                                    <span class="text-xs text-[var(--ui-secondary)] truncate flex-1">{{ $loc['name'] }}</span>
                                    <span class="text-[0.62rem] font-mono font-semibold text-[var(--ui-muted)] flex-shrink-0">{{ $loc['pax_max'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Rechte Sidebar (Aktivitäten) --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4">
                <p class="text-xs text-[var(--ui-muted)]">Noch keine Aktivitäten.</p>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
