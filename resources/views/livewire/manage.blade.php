<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Locations verwalten" icon="heroicon-o-building-office" />
    </x-slot>

    {{-- Leaflet (OpenStreetMap) --}}
    @once
        <link rel="stylesheet"
              href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
              crossorigin="anonymous" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin="anonymous" defer></script>
    @endonce

    <x-ui-page-container>
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Locations', 'route' => 'locations.dashboard'],
            ['label' => 'Verwalten'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                <span class="flex items-center gap-2">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Neue Location
                </span>
            </x-ui-button>
        </x-ui-page-actionbar>

        <div class="space-y-6 pt-4">
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
                                                <x-ui-button variant="secondary-outline" size="sm" wire:click="openEdit('{{ $location->uuid }}')">
                                                    Bearbeiten
                                                </x-ui-button>
                                                <x-ui-button
                                                    variant="danger-outline"
                                                    size="sm"
                                                    wire:click="delete('{{ $location->uuid }}')"
                                                    wire:confirm="Location „{{ $location->name }}“ wirklich löschen?"
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

        {{-- ===== Modal: Create / Edit ===== --}}
        <x-ui-modal wire:model="showModal" size="lg" :hideFooter="true">
            <x-slot name="header">
                {{ $editingId ? 'Location bearbeiten' : 'Neue Location' }}
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
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">PAX Max</label>
                        <input wire:model="pax_max" type="number" min="0" placeholder="0"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('pax_max') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- ===== Adresse mit Autocomplete + Karte ===== --}}
                <div
                    x-data="locationMap({
                        initialLat: @js($latitude),
                        initialLng: @js($longitude),
                    })"
                    x-init="boot()"
                    class="space-y-2"
                >
                    <div class="flex items-center justify-between">
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)]">Adresse</label>
                        @if($latitude !== null && $longitude !== null)
                            <button type="button" wire:click="clearCoordinates"
                                    class="text-[0.62rem] text-[var(--ui-muted)] hover:text-red-600 flex items-center gap-1">
                                @svg('heroicon-o-x-mark', 'w-3 h-3')
                                Position entfernen
                            </button>
                        @endif
                    </div>

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
                            <ul class="absolute left-0 right-0 top-full mt-1 bg-white border border-[var(--ui-border)] rounded-md shadow-lg max-h-60 overflow-y-auto z-50">
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

                    <div wire:ignore>
                        <div x-ref="mapContainer"
                             class="w-full h-56 rounded-md border border-[var(--ui-border)] bg-[var(--ui-muted-5)] overflow-hidden"></div>
                    </div>

                    <div class="flex items-center justify-between text-[0.6rem]">
                        @if($latitude !== null && $longitude !== null)
                            <span class="font-mono text-[var(--ui-muted)]">
                                {{ number_format($latitude, 6) }}, {{ number_format($longitude, 6) }}
                            </span>
                        @else
                            <span class="text-[var(--ui-muted)]">Tipp: Adresse eingeben und einen Vorschlag auswählen, um die Karte zu setzen.</span>
                        @endif
                        <span class="text-[var(--ui-muted)]">Karte: © OpenStreetMap</span>
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

                <div class="flex justify-end gap-2 pt-4 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeModal">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">
                        <span wire:loading.remove wire:target="save">
                            {{ $editingId ? 'Änderungen speichern' : 'Location anlegen' }}
                        </span>
                        <span wire:loading wire:target="save">Speichern …</span>
                    </x-ui-button>
                </div>
            </form>
        </x-ui-modal>
    </x-ui-page-container>

    @once
        @push('scripts')
        @endpush
        <script>
            window.locationMap = function (config) {
                return {
                    config,
                    map: null,
                    marker: null,
                    async boot() {
                        await this.waitForLeaflet();

                        // Livewire-Event: wenn selectSuggestion() feuert, Marker aktualisieren
                        window.Livewire.on('locations:map-update', (payload) => {
                            const data = Array.isArray(payload) ? payload[0] : payload;
                            this.updateMarker(data?.lat ?? null, data?.lng ?? null);
                        });

                        // Map initialisieren, sobald das Modal sichtbar ist
                        this.$watch('$wire.showModal', (open) => {
                            if (open) {
                                this.$nextTick(() => {
                                    if (!this.map) {
                                        this.initMap();
                                    } else {
                                        this.map.invalidateSize();
                                        this.updateMarker(this.$wire.latitude, this.$wire.longitude);
                                    }
                                });
                            } else if (!open && this.map) {
                                this.map.remove();
                                this.map = null;
                                this.marker = null;
                            }
                        });

                        if (this.$wire.showModal && !this.map) {
                            this.$nextTick(() => this.initMap());
                        }
                    },
                    waitForLeaflet() {
                        if (window.L) return Promise.resolve();
                        return new Promise((resolve) => {
                            const iv = setInterval(() => {
                                if (window.L) { clearInterval(iv); resolve(); }
                            }, 50);
                        });
                    },
                    initMap() {
                        const lat = this.$wire.latitude ?? this.config.initialLat;
                        const lng = this.$wire.longitude ?? this.config.initialLng;
                        const hasCoords = lat !== null && lng !== null;

                        this.map = L.map(this.$refs.mapContainer, {
                            center: hasCoords ? [lat, lng] : [51.1657, 10.4515],
                            zoom: hasCoords ? 15 : 6,
                            scrollWheelZoom: false,
                        });

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
                        }).addTo(this.map);

                        if (hasCoords) {
                            this.marker = L.marker([lat, lng]).addTo(this.map);
                        }
                    },
                    updateMarker(lat, lng) {
                        if (!this.map) return;
                        if (lat !== null && lat !== undefined && lng !== null && lng !== undefined) {
                            if (this.marker) {
                                this.marker.setLatLng([lat, lng]);
                            } else {
                                this.marker = L.marker([lat, lng]).addTo(this.map);
                            }
                            this.map.setView([lat, lng], 15);
                        } else if (this.marker) {
                            this.marker.remove();
                            this.marker = null;
                        }
                    },
                };
            };
        </script>
    @endonce
</x-ui-page>
