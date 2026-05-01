<x-ui-page>
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

    <x-slot name="navbar">
        <x-ui-page-navbar :title="$location->name" icon="heroicon-o-building-office" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Locations', 'href' => route('locations.dashboard'), 'icon' => 'map-pin'],
            ['label' => 'Verwalten', 'href' => route('locations.manage')],
            ['label' => $location->name],
        ]">
            <x-slot name="left">
                <x-ui-button variant="ghost" size="sm" :href="route('locations.manage')" wire:navigate>
                    @svg('heroicon-o-arrow-left', 'w-4 h-4')
                    <span>Zurück</span>
                </x-ui-button>
            </x-slot>
            <x-ui-button variant="primary" size="sm" wire:click="save">
                <span wire:loading.remove wire:target="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    Speichern
                </span>
                <span wire:loading wire:target="save">Speichern …</span>
            </x-ui-button>
            <x-ui-button
                variant="danger-outline"
                size="sm"
                wire:click="delete"
                wire:confirm="Location &bdquo;{{ $location->name }}&ldquo; wirklich löschen?"
            >
                @svg('heroicon-o-trash', 'w-4 h-4')
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Locations" width="w-72" :defaultOpen="true">
            <div class="p-3 space-y-1">
                @php
                    $siteGroups = collect($allLocations)->groupBy('site_id');
                    $hasSites = $allSites->isNotEmpty();
                @endphp

                @if($hasSites)
                    {{-- Locations nach Site gruppiert --}}
                    @foreach($allSites as $site)
                        @php $siteLocs = $siteGroups->get($site->id, collect()); @endphp
                        @if($siteLocs->isNotEmpty())
                            <div class="pt-2 first:pt-0">
                                <div class="px-3 py-1 text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">
                                    {{ $site->name }}
                                </div>
                                @foreach($siteLocs as $loc)
                                    <a href="{{ route('locations.show', $loc->uuid) }}" wire:navigate
                                       class="flex items-center gap-2 px-3 py-2 rounded-md text-xs transition-colors ml-2
                                           {{ $currentUuid === $loc->uuid
                                               ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold'
                                               : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                        <span class="font-mono font-bold text-[0.6rem] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 uppercase">{{ $loc->kuerzel }}</span>
                                        <span class="truncate">{{ $loc->name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @endforeach

                    {{-- Locations ohne Site --}}
                    @php $unsitedLocs = $siteGroups->get('', collect())->merge($siteGroups->get(null, collect())); @endphp
                    @if($unsitedLocs->isNotEmpty())
                        <div class="pt-2">
                            <div class="px-3 py-1 text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">
                                Ohne Site
                            </div>
                            @foreach($unsitedLocs as $loc)
                                <a href="{{ route('locations.show', $loc->uuid) }}" wire:navigate
                                   class="flex items-center gap-2 px-3 py-2 rounded-md text-xs transition-colors ml-2
                                       {{ $currentUuid === $loc->uuid
                                           ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold'
                                           : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                    <span class="font-mono font-bold text-[0.6rem] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 uppercase">{{ $loc->kuerzel }}</span>
                                    <span class="truncate">{{ $loc->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @else
                    {{-- Keine Sites: flache Liste wie bisher --}}
                    @foreach($allLocations as $loc)
                        <a href="{{ route('locations.show', $loc->uuid) }}" wire:navigate
                           class="flex items-center gap-2 px-3 py-2 rounded-md text-xs transition-colors
                               {{ $currentUuid === $loc->uuid
                                   ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold'
                                   : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                            <span class="font-mono font-bold text-[0.6rem] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 uppercase">{{ $loc->kuerzel }}</span>
                            <span class="truncate">{{ $loc->name }}</span>
                        </a>
                    @endforeach
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            @if(empty($activityItems))
                <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
            @else
                <div class="p-3 space-y-3">
                    @foreach($activityItems as $act)
                        <div class="flex items-start gap-2 text-[0.65rem]">
                            <div class="w-6 h-6 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)] flex items-center justify-center flex-shrink-0 mt-0.5">
                                @svg('heroicon-o-user', 'w-3 h-3 text-[var(--ui-muted)]')
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1">
                                    <span class="font-semibold text-[var(--ui-secondary)]">{{ $act['user_name'] }}</span>
                                    <span class="text-[var(--ui-muted)]" title="{{ $act['created_at_full'] ?? '' }}">{{ $act['created_at'] }}</span>
                                </div>
                                @if(!empty($act['message']))
                                    <p class="text-[var(--ui-secondary)] mt-0.5">{{ $act['message'] }}</p>
                                @else
                                    <p class="text-[var(--ui-muted)] mt-0.5">
                                        @switch($act['name'])
                                            @case('created') Location erstellt @break
                                            @case('updated')
                                                Location aktualisiert
                                                @if(!empty($act['properties']))
                                                    <span class="text-[0.58rem]">
                                                        ({{ implode(', ', array_keys($act['properties'])) }})
                                                    </span>
                                                @endif
                                                @break
                                            @case('deleted') Location gelöscht @break
                                            @default {{ $act['name'] }}
                                        @endswitch
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <form wire:submit.prevent="save" class="space-y-6">

            {{-- ===== Panel: Stammdaten ===== --}}
            <x-ui-panel title="Stammdaten" subtitle="Name, Kapazitäten und Grunddaten">
                <div class="space-y-4 p-1">
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

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Gruppe</label>
                            <input wire:model="gruppe" type="text" placeholder="z.B. Hauptgebäude"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Site (Standort)</label>
                            <select wire:model="site_id"
                                    class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                <option value="">— Kein Site —</option>
                                @foreach($allSites as $site)
                                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </div>
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

                    <div class="grid grid-cols-[1fr_140px_auto] gap-3 items-end">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Größe (qm)</label>
                            <input wire:model="groesse_qm" type="number" step="0.01" min="0" placeholder="z.B. 400"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @error('groesse_qm') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Hallennummer</label>
                            <input wire:model="hallennummer" type="text" maxlength="30" placeholder="z.B. 11"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer select-none pb-2">
                            <input wire:model="barrierefrei" type="checkbox"
                                   class="w-4 h-4 accent-[var(--ui-primary)] cursor-pointer">
                            <span class="text-xs font-medium text-[var(--ui-secondary)]">Barrierefrei</span>
                        </label>
                    </div>

                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Besonderheit</label>
                        <textarea wire:model="besonderheit" rows="2" placeholder="z.B. 3 verfahrbare Kronleuchter"
                                  class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                        <p class="mt-1 text-[0.62rem] text-[var(--ui-muted)]">Kurze, prägnante Hervorhebung (1–2 Sätze).</p>
                    </div>

                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Beschreibung</label>
                        <textarea wire:model="beschreibung" rows="6" placeholder="Längere Beschreibung der Location für Kunden — Geschichte, Charakter, Besonderheiten…"
                                  class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                        @error('beschreibung') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-[0.62rem] text-[var(--ui-muted)]">Marketing-Text / Historie / Kundeninfo. Längerer Fließtext, später für Angebote, Web-Auftritt etc. nutzbar.</p>
                    </div>

                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Anlässe</label>
                        <input wire:model="anlaesseInput" type="text" placeholder="z.B. Hochzeit, Firmenfeier, Tagung"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <p class="mt-1 text-[0.62rem] text-[var(--ui-muted)]">Komma-getrennt</p>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input wire:model="mehrfachbelegung" type="checkbox"
                                   class="w-4 h-4 accent-[var(--ui-primary)] cursor-pointer">
                            <span class="text-xs font-medium text-[var(--ui-secondary)]">Mehrfachbelegung erlaubt</span>
                        </label>
                        <p class="text-[0.62rem] text-[var(--ui-muted)] mt-1 ml-6">Raum kann an einem Tag mehrfach gebucht werden</p>
                    </div>
                </div>
            </x-ui-panel>

            {{-- ===== Panel: Adresse & Karte ===== --}}
            <x-ui-panel title="Adresse & Karte" subtitle="Standort und Geocoding">
                <div
                    x-data="locationMap({
                        initialLat: @js($latitude),
                        initialLng: @js($longitude),
                    })"
                    x-init="boot()"
                    class="space-y-2 p-1"
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

                    <div class="relative z-[1000]">
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

                    <div wire:ignore class="relative z-0">
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
            </x-ui-panel>

            {{-- ===== Panel: Bestuhlungsoptionen ===== --}}
            <x-ui-panel title="Bestuhlungsoptionen" subtitle="Kapazitäten je Bestuhlung (ca.-Werte)">
                <div class="space-y-2 p-1">
                    <div class="flex items-center justify-end">
                        <button type="button" wire:click="addSeatingRow"
                                class="text-[0.62rem] text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                            @svg('heroicon-o-plus', 'w-3 h-3')
                            Zeile hinzufügen
                        </button>
                    </div>
                    @if(empty($seatingRows))
                        <p class="text-[0.62rem] text-[var(--ui-muted)]">Noch keine Bestuhlungsoptionen gepflegt.</p>
                    @else
                        <div class="space-y-1.5">
                            @foreach($seatingRows as $i => $row)
                                <div class="grid grid-cols-[1fr_120px_auto] gap-2 items-center" wire:key="seating-{{ $i }}">
                                    <input wire:model="seatingRows.{{ $i }}.label" type="text" placeholder="z.B. Reihenbestuhlung"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    <input wire:model="seatingRows.{{ $i }}.pax_max_ca" type="number" min="0" placeholder="bis zu PAX"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    <button type="button" wire:click="removeSeatingRow({{ $i }})"
                                            class="text-[0.62rem] text-red-600 hover:bg-red-50 rounded p-1.5">
                                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <p class="text-[0.62rem] text-[var(--ui-muted)]">Reine ca.-Hinweise. Mischformen aus runden/eckigen Tischen sind nicht abgebildet.</p>
                </div>
            </x-ui-panel>

            {{-- ===== Panel: Mietpreise ===== --}}
            <x-ui-panel title="Mietpreise" subtitle="Netto-Preise pro Tag-Typ">
                <div class="space-y-2 p-1">
                    <div class="flex items-center justify-end">
                        <button type="button" wire:click="addPricingRow"
                                class="text-[0.62rem] text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                            @svg('heroicon-o-plus', 'w-3 h-3')
                            Zeile hinzufügen
                        </button>
                    </div>
                    @if(empty($pricingRows))
                        <p class="text-[0.62rem] text-[var(--ui-muted)]">Noch keine Mietpreise gepflegt.</p>
                    @else
                        <div class="space-y-1.5">
                            @foreach($pricingRows as $i => $row)
                                <div class="grid grid-cols-[1fr_120px_1fr_180px_auto] gap-2 items-center" wire:key="pricing-{{ $i }}">
                                    <input wire:model="pricingRows.{{ $i }}.day_type_label" type="text" placeholder="Tag-Typ (z.B. Veranstaltungstag)"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    <input wire:model="pricingRows.{{ $i }}.price_net" type="number" step="0.01" min="0" placeholder="Preis €"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    <input wire:model="pricingRows.{{ $i }}.label" type="text" placeholder="Optionales Label"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    <div class="flex items-center gap-1">
                                        <input wire:model="pricingRows.{{ $i }}.article_number" type="text" maxlength="30"
                                               placeholder="Artikelnr."
                                               title="Artikelnummer aus Events-Stamm. Suche per Lupe."
                                               class="flex-1 min-w-0 border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                        <button type="button" wire:click="openArticlePicker({{ $i }})"
                                                class="text-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/10 rounded p-1.5"
                                                title="Artikel suchen">
                                            @svg('heroicon-o-magnifying-glass', 'w-3.5 h-3.5')
                                        </button>
                                        @if(!empty($row['article_number']))
                                            <button type="button" wire:click="clearArticleNumber({{ $i }})"
                                                    class="text-[var(--ui-muted)] hover:bg-red-50 hover:text-red-600 rounded p-1"
                                                    title="Artikel-Verknuepfung entfernen">
                                                @svg('heroicon-o-x-mark', 'w-3 h-3')
                                            </button>
                                        @endif
                                    </div>
                                    <button type="button" wire:click="removePricingRow({{ $i }})"
                                            class="text-[0.62rem] text-red-600 hover:bg-red-50 rounded p-1.5">
                                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <p class="text-[0.62rem] text-[var(--ui-muted)]">Tag-Typ-Volltext muss mit den Tages-Typen aus den Events-Settings übereinstimmen (z.B. „Veranstaltungstag", „Aufbautag"). Optionale Artikelnummer verknüpft mit dem Events-Artikelstamm — bei der Einbuchung werden Gruppe, Name, MwSt, EK und Procurement-Type vom Artikel übernommen; der Preis bleibt aus dem Pricing-Eintrag.</p>
                </div>
            </x-ui-panel>

            {{-- ===== Panel: Add-ons ===== --}}
            <x-ui-panel title="Add-ons" subtitle="Optionale Zusatzleistungen">
                <div class="space-y-2 p-1">
                    <div class="flex items-center justify-end">
                        <button type="button" wire:click="addAddonRow"
                                class="text-[0.62rem] text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                            @svg('heroicon-o-plus', 'w-3 h-3')
                            Zeile hinzufügen
                        </button>
                    </div>
                    @if(empty($addonRows))
                        <p class="text-[0.62rem] text-[var(--ui-muted)]">Noch keine Add-ons gepflegt.</p>
                    @else
                        <div class="space-y-1.5">
                            @foreach($addonRows as $i => $row)
                                <div class="grid grid-cols-[1fr_110px_120px_180px_auto_auto] gap-2 items-center" wire:key="addon-{{ $i }}">
                                    <input wire:model="addonRows.{{ $i }}.label" type="text" placeholder="z.B. Heizung"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    <input wire:model="addonRows.{{ $i }}.price_net" type="number" step="0.01" min="0" placeholder="Preis €"
                                           class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                    <select wire:model="addonRows.{{ $i }}.unit"
                                            class="w-full border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                        <option value="pro_tag">pro Tag</option>
                                        <option value="pro_va_tag">pro VA-Tag</option>
                                        <option value="einmalig">einmalig</option>
                                        <option value="pro_stueck">pro Stück</option>
                                    </select>
                                    <div class="flex items-center gap-1">
                                        <input wire:model="addonRows.{{ $i }}.article_number" type="text" maxlength="30"
                                               placeholder="Artikelnr."
                                               title="Artikelnummer aus Events-Stamm. Suche per Lupe."
                                               class="flex-1 min-w-0 border border-[var(--ui-border)] rounded-md px-2 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                                        <button type="button" wire:click="openArticlePicker({{ $i }}, 'addon')"
                                                class="text-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/10 rounded p-1.5"
                                                title="Artikel suchen">
                                            @svg('heroicon-o-magnifying-glass', 'w-3.5 h-3.5')
                                        </button>
                                        @if(!empty($row['article_number']))
                                            <button type="button" wire:click="clearArticleNumber({{ $i }}, 'addon')"
                                                    class="text-[var(--ui-muted)] hover:bg-red-50 hover:text-red-600 rounded p-1"
                                                    title="Artikel-Verknuepfung entfernen">
                                                @svg('heroicon-o-x-mark', 'w-3 h-3')
                                            </button>
                                        @endif
                                    </div>
                                    <label class="flex items-center gap-1 text-[0.62rem] text-[var(--ui-secondary)] cursor-pointer select-none">
                                        <input wire:model="addonRows.{{ $i }}.is_active" type="checkbox"
                                               class="w-3.5 h-3.5 accent-[var(--ui-primary)]">
                                        aktiv
                                    </label>
                                    <button type="button" wire:click="removeAddonRow({{ $i }})"
                                            class="text-[0.62rem] text-red-600 hover:bg-red-50 rounded p-1.5">
                                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <p class="text-[0.62rem] text-[var(--ui-muted)]">Optionale Artikelnummer verknüpft mit dem Events-Artikelstamm — bei der Einbuchung werden Gruppe, Name, MwSt, EK und Procurement-Type vom Artikel übernommen; der Preis bleibt aus dem Add-on-Eintrag, die Menge aus der Einheit oder dem User-Override.</p>
                </div>
            </x-ui-panel>

            {{-- ===== Panel: Grundriss ===== --}}
            <x-ui-panel title="Grundriss" subtitle="Grundriss-Datei (S3)">
                <div class="space-y-2 p-1">
                    @if($grundrissFileName)
                        <div class="flex items-center justify-end">
                            <span class="text-[0.6rem] font-mono text-[var(--ui-muted)]">{{ $grundrissFileName }}</span>
                        </div>
                    @endif

                    @if($grundrissPath)
                        <div class="flex items-center gap-2 p-2 rounded-md border border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                            @svg('heroicon-o-document', 'w-4 h-4 text-[var(--ui-primary)] flex-shrink-0')
                            <span class="text-xs text-[var(--ui-secondary)] flex-1 line-clamp-1">Grundriss hinterlegt</span>
                            @if($this->grundrissUrl)
                                <a href="{{ $this->grundrissUrl }}" target="_blank" rel="noopener"
                                   class="text-[0.62rem] text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                                    @svg('heroicon-o-arrow-top-right-on-square', 'w-3 h-3')
                                    Anzeigen
                                </a>
                            @endif
                            <button type="button"
                                    wire:click="deleteGrundriss"
                                    wire:confirm="Grundriss wirklich entfernen?"
                                    class="text-[0.62rem] text-red-600 hover:underline flex items-center gap-1">
                                @svg('heroicon-o-trash', 'w-3 h-3')
                                Entfernen
                            </button>
                        </div>
                    @endif

                    <div>
                        <div x-data="{ over: false }"
                             @dragover.prevent.stop="over = true"
                             @dragleave.prevent.stop="over = false"
                             @drop.prevent.stop="
                                 over = false;
                                 if (!$event.dataTransfer.files.length) return;
                                 const dt = new DataTransfer();
                                 dt.items.add($event.dataTransfer.files[0]);
                                 $refs.input.files = dt.files;
                                 $refs.input.dispatchEvent(new Event('change', { bubbles: true }));
                             "
                             @click="$refs.input.click()"
                             :class="over ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5' : 'border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)]/50'"
                             class="border-2 border-dashed rounded-md p-3 text-center cursor-pointer transition-colors">
                            <input type="file"
                                   wire:model="grundriss"
                                   x-ref="input"
                                   accept=".pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/png,image/jpeg,image/webp"
                                   class="sr-only">
                            <div class="flex items-center gap-2 justify-center text-[0.65rem] text-[var(--ui-muted)]">
                                @svg('heroicon-o-cloud-arrow-up', 'w-4 h-4')
                                <span>Datei hierher ziehen oder klicken</span>
                            </div>
                        </div>
                        <div wire:loading wire:target="grundriss,updatedGrundriss" class="mt-1 flex items-center gap-1 text-[0.62rem] text-[var(--ui-muted)]">
                            @svg('heroicon-o-arrow-path', 'w-3 h-3 animate-spin')
                            Upload läuft …
                        </div>
                        @error('grundriss') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-[0.62rem] text-[var(--ui-muted)]">PDF, PNG, JPG oder WEBP (max. 20 MB). Wird im S3-Bucket abgelegt, nicht in der Datenbank.</p>
                    </div>
                </div>
            </x-ui-panel>

            {{-- ===== Panel: Weitere Assets (ContextFile-basiert) ===== --}}
            <x-ui-panel title="Weitere Assets" subtitle="Buffetstationen, Bestuhlungspläne, Fotos">
                @php
                    $assetSections = [
                        ['cat' => 'buffet',              'label' => 'Buffetstationen',          'accept' => '.pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/png,image/jpeg,image/webp', 'hint' => 'PDF, PNG, JPG oder WEBP, max. 20 MB pro Datei. Mehrfachauswahl möglich.'],
                        ['cat' => 'seating_plans',       'label' => 'Bestuhlungspläne',         'accept' => '.pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/png,image/jpeg,image/webp', 'hint' => 'PDF, PNG, JPG oder WEBP, max. 20 MB pro Datei.'],
                        ['cat' => 'photos_with_seating', 'label' => 'Fotos mit Bestuhlung',     'accept' => '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp',                       'hint' => 'PNG, JPG oder WEBP, max. 15 MB pro Foto.'],
                        ['cat' => 'photos_empty',        'label' => 'Fotos der leeren Location','accept' => '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp',                       'hint' => 'PNG, JPG oder WEBP, max. 15 MB pro Foto.'],
                    ];
                @endphp

                <div class="space-y-4 p-1">
                    @foreach($assetSections as $sec)
                        @php
                            $contextFiles = $fileReferences[$sec['cat']] ?? [];
                            $legacyFiles = $legacyAssetFiles[$sec['cat']] ?? [];
                            $hasContextFiles = !empty($contextFiles);
                            $hasLegacyFiles = !empty($legacyFiles);
                        @endphp
                        <div class="space-y-2 {{ !$loop->first ? 'pt-3 border-t border-[var(--ui-border)]' : '' }}">
                            <div class="flex items-center justify-between">
                                <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)]">{{ $sec['label'] }}</label>
                                <span class="text-[0.6rem] font-mono text-[var(--ui-muted)]">{{ count($contextFiles) + count($legacyFiles) }} hinterlegt</span>
                            </div>

                            {{-- ContextFile-basierte Dateien --}}
                            @if($hasContextFiles)
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                    @foreach($contextFiles as $f)
                                        <div class="border border-[var(--ui-border)] rounded-md overflow-hidden bg-[var(--ui-muted-5)]">
                                            @if(($f['is_image'] ?? false) && ($f['thumbnail'] ?? $f['url'] ?? null))
                                                <a href="{{ $f['url'] }}" target="_blank" rel="noopener" class="block bg-white">
                                                    <img src="{{ $f['thumbnail'] ?? $f['url'] }}" alt="{{ $f['title'] ?? '' }}"
                                                         class="w-full h-24 object-cover hover:opacity-90 transition-opacity">
                                                </a>
                                            @else
                                                <div class="flex items-center justify-center h-24 bg-white text-[var(--ui-muted)]">
                                                    @svg('heroicon-o-document', 'w-8 h-8')
                                                </div>
                                            @endif
                                            <div class="p-1.5 flex items-center gap-1 text-[0.6rem]">
                                                @if($f['url'] ?? null)
                                                    <a href="{{ $f['url'] }}" target="_blank" rel="noopener"
                                                       class="text-[var(--ui-primary)] hover:underline truncate flex-1"
                                                       title="{{ $f['title'] ?? '' }}">
                                                        {{ $f['title'] ?? 'Datei' }}
                                                    </a>
                                                @else
                                                    <span class="truncate flex-1 text-[var(--ui-muted)]">{{ $f['title'] ?? 'Datei' }}</span>
                                                @endif
                                                <button type="button"
                                                        wire:click="deleteFile({{ $f['id'] }})"
                                                        wire:confirm="Datei wirklich entfernen?"
                                                        class="text-red-600 hover:bg-red-50 rounded p-1">
                                                    @svg('heroicon-o-trash', 'w-3 h-3')
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Legacy S3-Dateien (read-only Fallback) --}}
                            @if($hasLegacyFiles)
                                @if($hasContextFiles)
                                    <div class="flex items-center gap-2 text-[0.58rem] text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                                        @svg('heroicon-o-exclamation-triangle', 'w-3 h-3 flex-shrink-0')
                                        <span>Legacy-Dateien (S3): werden nach Migration in das neue System entfernt.</span>
                                    </div>
                                @endif
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                    @foreach($legacyFiles as $f)
                                        <div class="border border-[var(--ui-border)] rounded-md overflow-hidden bg-[var(--ui-muted-5)] {{ $hasContextFiles ? 'opacity-60' : '' }}">
                                            @if($f['is_image'] && $f['url'])
                                                <a href="{{ $f['url'] }}" target="_blank" rel="noopener" class="block bg-white">
                                                    <img src="{{ $f['url'] }}" alt="{{ $f['filename'] }}"
                                                         class="w-full h-24 object-cover hover:opacity-90 transition-opacity">
                                                </a>
                                            @else
                                                <div class="flex items-center justify-center h-24 bg-white text-[var(--ui-muted)]">
                                                    @svg('heroicon-o-document', 'w-8 h-8')
                                                </div>
                                            @endif
                                            <div class="p-1.5 flex items-center gap-1 text-[0.6rem]">
                                                @if($f['url'])
                                                    <a href="{{ $f['url'] }}" target="_blank" rel="noopener"
                                                       class="text-[var(--ui-primary)] hover:underline truncate flex-1"
                                                       title="{{ $f['filename'] }}">
                                                        {{ $f['filename'] }}
                                                    </a>
                                                @else
                                                    <span class="truncate flex-1 text-[var(--ui-muted)]">{{ $f['filename'] }}</span>
                                                @endif
                                                <button type="button"
                                                        wire:click="deleteLegacyAssetFile('{{ $sec['cat'] }}', '{{ $f['filename'] }}')"
                                                        wire:confirm="Datei &bdquo;{{ $f['filename'] }}&ldquo; wirklich entfernen?"
                                                        class="text-red-600 hover:bg-red-50 rounded p-1">
                                                    @svg('heroicon-o-trash', 'w-3 h-3')
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Upload-Dropzone --}}
                            <div>
                                <div x-data="{ over: false }"
                                     @dragover.prevent.stop="over = true"
                                     @dragleave.prevent.stop="over = false"
                                     @drop.prevent.stop="
                                         over = false;
                                         if (!$event.dataTransfer.files.length) return;
                                         const dt = new DataTransfer();
                                         for (const f of $event.dataTransfer.files) dt.items.add(f);
                                         $refs.input.files = dt.files;
                                         $refs.input.dispatchEvent(new Event('change', { bubbles: true }));
                                     "
                                     @click="$refs.input.click()"
                                     :class="over ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5' : 'border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)]/50'"
                                     class="border-2 border-dashed rounded-md p-3 text-center cursor-pointer transition-colors">
                                    <input type="file"
                                           multiple
                                           wire:model="newUploadFiles"
                                           x-ref="input"
                                           accept="{{ $sec['accept'] }}"
                                           class="sr-only">
                                    <div class="flex items-center gap-2 justify-center text-[0.65rem] text-[var(--ui-muted)]">
                                        @svg('heroicon-o-cloud-arrow-up', 'w-4 h-4')
                                        <span>Dateien hierher ziehen oder klicken (Mehrfachauswahl)</span>
                                    </div>
                                </div>
                                @if(!empty($newUploadFiles))
                                    <div class="mt-2 flex items-center gap-2">
                                        <span class="text-[0.62rem] text-[var(--ui-muted)]">{{ count($newUploadFiles) }} Datei(en) ausgewählt</span>
                                        <button type="button" wire:click="uploadFiles('{{ $sec['cat'] }}')"
                                                class="text-[0.62rem] text-[var(--ui-primary)] font-semibold hover:underline flex items-center gap-1">
                                            @svg('heroicon-o-cloud-arrow-up', 'w-3 h-3')
                                            Hochladen
                                        </button>
                                    </div>
                                @endif
                                <div wire:loading wire:target="uploadFiles" class="mt-1 flex items-center gap-1 text-[0.62rem] text-[var(--ui-muted)]">
                                    @svg('heroicon-o-arrow-path', 'w-3 h-3 animate-spin')
                                    Upload läuft …
                                </div>
                                @error('newUploadFiles') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                                <p class="mt-1 text-[0.62rem] text-[var(--ui-muted)]">{{ $sec['hint'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui-panel>

        </form>

        {{-- ===== Article-Picker Modal ===== --}}
        <x-ui-modal wire:model="showArticlePickerModal" size="lg" :hideFooter="true">
            <x-slot name="header">Artikel auswählen</x-slot>

            <div class="space-y-3">
                <div class="relative">
                    <input
                        wire:model.live.debounce.300ms="articleSearchQuery"
                        type="text"
                        placeholder="Suchen nach Artikelnummer (Prefix) oder Name (enthält)…"
                        autocomplete="off"
                        class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 pl-9 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"
                    />
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--ui-muted)]">
                        @svg('heroicon-o-magnifying-glass', 'w-4 h-4')
                    </span>
                    <div wire:loading wire:target="articleSearchQuery" class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--ui-muted)]">
                        @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                    </div>
                </div>

                @error('articleSearchQuery')
                    <div class="p-2 rounded border border-amber-300 bg-amber-50 text-[0.62rem] text-amber-800">{{ $message }}</div>
                @enderror

                @if(empty($articleSearchResults))
                    <p class="text-[0.65rem] text-[var(--ui-muted)] italic text-center py-4">
                        Keine Artikel gefunden.
                    </p>
                @else
                    <div class="max-h-80 overflow-y-auto border border-[var(--ui-border)] rounded-md">
                        <table class="w-full text-xs">
                            <thead class="sticky top-0 bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]">
                                <tr>
                                    <th class="px-2 py-1.5 text-left text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Nr.</th>
                                    <th class="px-2 py-1.5 text-left text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Name</th>
                                    <th class="px-2 py-1.5 text-left text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Gruppe</th>
                                    <th class="px-2 py-1.5 text-right text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">VK</th>
                                    <th class="px-2 py-1.5 text-center text-[0.6rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">MwSt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($articleSearchResults as $a)
                                    <tr wire:click="pickArticle('{{ $a['article_number'] }}')"
                                        class="border-b border-[var(--ui-border)]/50 hover:bg-[var(--ui-primary)]/5 cursor-pointer last:border-b-0">
                                        <td class="px-2 py-1.5 font-mono text-[0.65rem] font-semibold text-[var(--ui-primary)]">{{ $a['article_number'] }}</td>
                                        <td class="px-2 py-1.5">
                                            <span class="text-[var(--ui-secondary)] line-clamp-1">{{ $a['name'] }}</span>
                                        </td>
                                        <td class="px-2 py-1.5 text-[var(--ui-muted)] text-[0.65rem]">{{ $a['group_name'] ?? '—' }}</td>
                                        <td class="px-2 py-1.5 text-right font-mono text-[0.65rem] text-[var(--ui-muted)]">
                                            {{ number_format((float) $a['vk'], 2, ',', '.') }} €
                                        </td>
                                        <td class="px-2 py-1.5 text-center text-[0.65rem] text-[var(--ui-muted)]">{{ $a['mwst'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-[0.6rem] text-[var(--ui-muted)] italic">
                        Beim Einbuchen werden Gruppe, Name, MwSt, EK und Procurement-Type vom Artikel übernommen. Der Preis bleibt aus dem Pricing-Eintrag.
                    </p>
                @endif

                <div class="flex justify-end pt-2 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeArticlePicker">
                        Abbrechen
                    </x-ui-button>
                </div>
            </div>
        </x-ui-modal>
    </x-ui-page-container>

    @once
        <script>
            window.locationMap = function (config) {
                return {
                    config,
                    map: null,
                    marker: null,
                    async boot() {
                        await this.waitForLeaflet();

                        window.Livewire.on('locations:map-update', (payload) => {
                            const data = Array.isArray(payload) ? payload[0] : payload;
                            this.updateMarker(data?.lat ?? null, data?.lng ?? null);
                        });

                        this.$nextTick(() => this.initMap());
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
