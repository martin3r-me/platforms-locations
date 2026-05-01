<div>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Locations
    </div>

    {{-- Abschnitt: Allgemein --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('locations.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Stammdaten --}}
    <x-ui-sidebar-list label="Stammdaten">
        <x-ui-sidebar-item :href="route('locations.manage')">
            @svg('heroicon-o-building-office', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Locations</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('locations.sites')">
            @svg('heroicon-o-building-library', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Sites</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Abschnitt: Auswertung --}}
    <x-ui-sidebar-list label="Auswertung">
        <x-ui-sidebar-item :href="route('locations.occupancy')">
            @svg('heroicon-o-chart-bar', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Auslastung</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('locations.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Dashboard">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('locations.manage') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Locations">
                @svg('heroicon-o-building-office', 'w-5 h-5')
            </a>
            <a href="{{ route('locations.sites') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Sites">
                @svg('heroicon-o-building-library', 'w-5 h-5')
            </a>
            <a href="{{ route('locations.occupancy') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Auslastung">
                @svg('heroicon-o-chart-bar', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
