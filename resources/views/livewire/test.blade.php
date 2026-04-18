<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="Test" icon="heroicon-o-beaker" />
    </x-slot>

    {{-- Hauptinhalt --}}
    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">Test-Seite</h1>
                    <p class="text-[var(--ui-muted)] mt-1">Diese Seite dient zum Testen und als Beispiel</p>
                </div>
            </div>

            {{-- Test Panel --}}
            <x-ui-panel title="UI-Komponenten Test" subtitle="Verschiedene UI-Komponenten zum Testen">
                <div class="space-y-6">
                    {{-- Buttons --}}
                    <div>
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Buttons</h3>
                        <div class="flex flex-wrap gap-2">
                            <x-ui-button variant="primary" size="sm">Primary</x-ui-button>
                            <x-ui-button variant="secondary" size="sm">Secondary</x-ui-button>
                            <x-ui-button variant="success" size="sm">Success</x-ui-button>
                            <x-ui-button variant="danger" size="sm">Danger</x-ui-button>
                            <x-ui-button variant="warning" size="sm">Warning</x-ui-button>
                        </div>
                    </div>

                    {{-- Form Inputs --}}
                    <div>
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Form Inputs</h3>
                        <x-ui-form-grid :cols="2" :gap="4">
                            <x-ui-input-text
                                name="test_text"
                                label="Text Input"
                                wire:model="testValue"
                                placeholder="Test Text..."
                            />
                            <x-ui-input-text
                                name="test_number"
                                label="Number Input"
                                type="number"
                                wire:model="testNumber"
                                placeholder="42"
                            />
                        </x-ui-form-grid>
                    </div>

                    {{-- Test Action --}}
                    <div>
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Test Action</h3>
                        <x-ui-button variant="primary" wire:click="testAction">
                            Test-Aktion ausführen
                        </x-ui-button>
                    </div>
                </div>
            </x-ui-panel>
        </div>
    </x-ui-page-container>

    {{-- Linke Sidebar --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Navigation</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary-outline" size="sm" :href="route('locations.dashboard')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-home', 'w-4 h-4')
                                Dashboard
                            </span>
                        </x-ui-button>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Rechte Sidebar --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="font-medium text-[var(--ui-secondary)] truncate">Test-Seite geladen</div>
                        <div class="text-[var(--ui-muted)]">vor 1 Minute</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
