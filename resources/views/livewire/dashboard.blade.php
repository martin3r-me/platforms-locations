<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="Dashboard" icon="heroicon-o-home" />
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

    {{-- Linke Sidebar (Schnellzugriff) --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                {{-- Quick Actions --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('locations.manage')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-building-office', 'w-4 h-4')
                                Locations verwalten
                            </span>
                        </x-ui-button>
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('locations.occupancy')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-chart-bar', 'w-4 h-4')
                                Auslastung
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                {{-- Quick Stats --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Schnellstatistiken</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Locations</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $totalLocations }}</div>
                        </div>
                        <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="text-xs text-[var(--ui-muted)]">Gesamt-Kapazität</div>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $totalCapacity }} PAX</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Rechte Sidebar (Aktivitäten) --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Dashboard geladen</div>
                        <div class="text-[var(--ui-muted)]">vor 1 Minute</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
