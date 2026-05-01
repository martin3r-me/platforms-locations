<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Locations verwalten" icon="heroicon-o-building-office" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Locations', 'href' => route('locations.dashboard'), 'icon' => 'map-pin'],
            ['label' => 'Verwalten'],
        ]">
            <x-slot name="left">
                <x-ui-button variant="ghost" size="sm" :href="route('locations.occupancy')" wire:navigate>
                    @svg('heroicon-o-chart-bar', 'w-4 h-4')
                    <span>Auslastung</span>
                </x-ui-button>
            </x-slot>
            <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Location</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Locations" width="w-72" :defaultOpen="true">
            <div class="p-3 space-y-1">
                @foreach($allLocations as $loc)
                    <a href="{{ route('locations.show', $loc->uuid) }}" wire:navigate
                       class="flex items-center gap-2 px-3 py-2 rounded-md text-xs transition-colors
                           text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                        <span class="font-mono font-bold text-[0.6rem] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 uppercase">{{ $loc->kuerzel }}</span>
                        <span class="truncate">{{ $loc->name }}</span>
                    </a>
                @endforeach
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            <x-ui-panel title="Stammdaten" subtitle="Alle verfügbaren Locations">
                @if($locations->isEmpty())
                    <div class="p-12 text-center">
                        <div class="mb-4">
                            @svg('heroicon-o-building-office', 'w-12 h-12 text-[var(--ui-muted)] mx-auto')
                        </div>
                        <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Noch keine Locations angelegt</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Klicke auf „Neue Location", um zu starten.</p>
                        <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neue Location
                            </span>
                        </x-ui-button>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                    <th class="px-4 py-3 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Name</th>
                                    <th class="px-4 py-3 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Kürzel</th>
                                    <th class="px-4 py-3 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Gruppe</th>
                                    <th class="px-4 py-3 text-right text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">PAX Min</th>
                                    <th class="px-4 py-3 text-right text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">PAX Max</th>
                                    <th class="px-4 py-3 text-center text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Mehrfach</th>
                                    <th class="px-4 py-3 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Adresse</th>
                                    <th class="px-4 py-3 w-24"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($locations as $location)
                                    <tr class="border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]/60 transition-colors">
                                        <td class="px-4 py-3">
                                            <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $location->name }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/60 uppercase">{{ $location->kuerzel }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-xs text-[var(--ui-muted)]">{{ $location->gruppe ?: '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="text-xs font-mono text-[var(--ui-muted)]">{{ $location->pax_min ?: '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="text-xs font-mono text-[var(--ui-muted)]">{{ $location->pax_max ?: '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if($location->mehrfachbelegung)
                                                <span class="text-[0.62rem] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Ja</span>
                                            @else
                                                <span class="text-[0.62rem] text-[var(--ui-muted)]">Nein</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-1.5 max-w-xs">
                                                @if($location->hasCoordinates())
                                                    <span class="flex-shrink-0 text-[var(--ui-primary)]" title="Position hinterlegt ({{ number_format($location->latitude, 4) }}, {{ number_format($location->longitude, 4) }})">
                                                        @svg('heroicon-o-map-pin', 'w-3.5 h-3.5')
                                                    </span>
                                                @endif
                                                <span class="text-xs text-[var(--ui-muted)] line-clamp-1">{{ $location->adresse ?: '—' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <x-ui-button variant="secondary-outline" size="sm" :href="route('locations.show', $location->uuid)" wire:navigate>
                                                    Bearbeiten
                                                </x-ui-button>
                                                <x-ui-button
                                                    variant="danger-outline"
                                                    size="sm"
                                                    wire:click="delete('{{ $location->uuid }}')"
                                                    wire:confirm="Location „{{ $location->name }}" wirklich löschen?"
                                                >
                                                    @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                                </x-ui-button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui-panel>
        </div>

        {{-- ===== Modal: Create Only ===== --}}
        <x-ui-modal wire:model="showModal" size="lg" :hideFooter="true">
            <x-slot name="header">
                Neue Location
            </x-slot>

            <form wire:submit.prevent="save" class="space-y-4">
                <div class="grid grid-cols-[1fr_140px] gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Name *</label>
                        <input wire:model="name" type="text" placeholder="z.B. Großer Saal"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('name') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Kürzel *</label>
                        <input wire:model="kuerzel" type="text" placeholder="z.B. GOH" maxlength="20"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono uppercase focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('kuerzel') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Gruppe</label>
                    <input wire:model="gruppe" type="text" placeholder="z.B. Hauptgebäude"
                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">PAX Min</label>
                        <input wire:model="pax_min" type="number" min="0" placeholder="0"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('pax_min') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">PAX Max (inkl. Personal)</label>
                        <input wire:model="pax_max" type="number" min="0" placeholder="0"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('pax_max') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Adresse</label>
                    <div class="relative">
                        <input
                            wire:model.live.debounce.400ms="adresse"
                            type="text"
                            placeholder="Straße Nr., PLZ Ort"
                            autocomplete="off"
                            class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"
                        />
                        <div wire:loading wire:target="adresse,searchAddress"
                             class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--ui-muted)]">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                        </div>

                        @if(!empty($addressSuggestions))
                            <ul class="absolute left-0 right-0 top-full mt-1 bg-white border border-[var(--ui-border)] rounded-md shadow-xl max-h-60 overflow-y-auto z-[1100]">
                                @foreach($addressSuggestions as $i => $s)
                                    <li>
                                        <button type="button"
                                                wire:click.prevent="selectSuggestion({{ $i }})"
                                                class="w-full text-left px-3 py-2 text-xs hover:bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]/30 last:border-b-0">
                                            <div class="flex items-start gap-2">
                                                @svg('heroicon-o-map-pin', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0 mt-0.5')
                                                <span class="line-clamp-2">{{ $s['display'] }}</span>
                                            </div>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input wire:model="mehrfachbelegung" type="checkbox"
                               class="w-4 h-4 accent-[var(--ui-primary)] cursor-pointer">
                        <span class="text-xs font-medium text-[var(--ui-secondary)]">Mehrfachbelegung erlaubt</span>
                    </label>
                    <p class="text-[0.62rem] text-[var(--ui-muted)] mt-1 ml-6">Raum kann an einem Tag mehrfach gebucht werden</p>
                </div>

                <p class="text-[0.62rem] text-[var(--ui-muted)] border-t border-[var(--ui-border)] pt-3">
                    Weitere Details (Beschreibung, Bestuhlung, Preise, Assets) können nach dem Anlegen auf der Detail-Seite gepflegt werden.
                </p>

                <div class="flex justify-end gap-2 pt-2 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeModal">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">
                        <span wire:loading.remove wire:target="save">Location anlegen</span>
                        <span wire:loading wire:target="save">Speichern …</span>
                    </x-ui-button>
                </div>
            </form>
        </x-ui-modal>
    </x-ui-page-container>
</x-ui-page>
