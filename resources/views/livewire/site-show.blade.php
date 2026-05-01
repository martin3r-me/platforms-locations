<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$site->name" icon="heroicon-o-building-library" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Locations', 'href' => route('locations.dashboard'), 'icon' => 'map-pin'],
            ['label' => 'Sites', 'href' => route('locations.sites')],
            ['label' => $site->name],
        ]">
            <x-slot name="left">
                <x-ui-button variant="ghost" size="sm" :href="route('locations.sites')" wire:navigate>
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
                wire:confirm="Site „{{ $site->name }}" wirklich löschen?"
            >
                @svg('heroicon-o-trash', 'w-4 h-4')
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Sites" width="w-72" :defaultOpen="true">
            <div class="p-3 space-y-1">
                @foreach($allSites as $s)
                    <a href="{{ route('locations.sites.show', $s->uuid) }}" wire:navigate
                       class="flex items-center gap-2 px-3 py-2 rounded-md text-xs transition-colors
                           {{ $currentUuid === $s->uuid
                               ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold'
                               : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                        @svg('heroicon-o-building-library', 'w-3.5 h-3.5 flex-shrink-0')
                        <span class="truncate">{{ $s->name }}</span>
                    </a>
                @endforeach
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
                                    <span class="text-[var(--ui-muted)]">{{ $act['created_at'] }}</span>
                                </div>
                                @if($act['message'])
                                    <p class="text-[var(--ui-secondary)] mt-0.5">{{ $act['message'] }}</p>
                                @else
                                    <p class="text-[var(--ui-muted)] mt-0.5">
                                        @switch($act['name'])
                                            @case('created') Site erstellt @break
                                            @case('updated') Site aktualisiert @break
                                            @case('deleted') Site gelöscht @break
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
            <x-ui-panel title="Stammdaten" subtitle="Name und Beschreibung des Standorts">
                <div class="space-y-4 p-1">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Name *</label>
                        <input wire:model="name" type="text" placeholder="z.B. Messegelände Köln"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @error('name') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Beschreibung</label>
                        <textarea wire:model="description" rows="3" placeholder="Optionale Beschreibung des Standorts"
                                  class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input wire:model="is_international" type="checkbox"
                                   class="w-4 h-4 accent-[var(--ui-primary)] cursor-pointer">
                            <span class="text-xs font-medium text-[var(--ui-secondary)]">Internationaler Standort</span>
                        </label>
                    </div>
                </div>
            </x-ui-panel>

            {{-- ===== Panel: Adresse ===== --}}
            <x-ui-panel title="Adresse" subtitle="Standort-Adresse und GPS-Koordinaten">
                <div class="space-y-4 p-1">
                    <div class="grid grid-cols-[1fr_80px] gap-3">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Straße</label>
                            <input wire:model.live.debounce.500ms="street" type="text" placeholder="z.B. Messeplatz"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Nr.</label>
                            <input wire:model="street_number" type="text" placeholder="1"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                    </div>

                    <div class="grid grid-cols-[100px_1fr] gap-3">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">PLZ</label>
                            <input wire:model="postal_code" type="text" placeholder="50679"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Ort</label>
                            <input wire:model.live.debounce.500ms="city" type="text" placeholder="Köln"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                    </div>

                    @if(!empty($addressSuggestions))
                        <ul class="bg-white border border-[var(--ui-border)] rounded-md shadow-xl max-h-60 overflow-y-auto">
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

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Bundesland</label>
                            <input wire:model="state" type="text" placeholder="NRW"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Land</label>
                            <input wire:model="country" type="text" placeholder="Deutschland"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                    </div>

                    <div class="grid grid-cols-[80px_1fr] gap-3">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Ländercode</label>
                            <input wire:model="country_code" type="text" maxlength="2" placeholder="DE"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs font-mono uppercase focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Zeitzone</label>
                            <input wire:model="timezone" type="text" placeholder="Europe/Berlin"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)]">GPS-Koordinaten</label>
                        @if($latitude !== null && $longitude !== null)
                            <button type="button" wire:click="clearCoordinates"
                                    class="text-[0.62rem] text-[var(--ui-muted)] hover:text-red-600 flex items-center gap-1">
                                @svg('heroicon-o-x-mark', 'w-3 h-3')
                                Position entfernen
                            </button>
                        @endif
                    </div>
                    @if($latitude !== null && $longitude !== null)
                        <span class="font-mono text-[0.6rem] text-[var(--ui-muted)]">
                            {{ number_format($latitude, 6) }}, {{ number_format($longitude, 6) }}
                        </span>
                    @else
                        <span class="text-[0.6rem] text-[var(--ui-muted)]">Tipp: Adresse eingeben, Vorschlag wählen.</span>
                    @endif
                </div>
            </x-ui-panel>

            {{-- ===== Panel: Kontakt ===== --}}
            <x-ui-panel title="Kontakt" subtitle="Telefon, E-Mail, Website">
                <div class="space-y-4 p-1">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Telefon</label>
                            <input wire:model="phone" type="text" placeholder="+49 221 12345"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        </div>
                        <div>
                            <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">E-Mail</label>
                            <input wire:model="email" type="email" placeholder="info@example.com"
                                   class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                            @error('email') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Website</label>
                        <input wire:model="website" type="text" placeholder="https://www.example.com"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                </div>
            </x-ui-panel>

            {{-- ===== Panel: Notizen ===== --}}
            <x-ui-panel title="Notizen" subtitle="Interne Notizen zum Standort">
                <div class="p-1">
                    <textarea wire:model="notes" rows="4" placeholder="Interne Notizen..."
                              class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                </div>
            </x-ui-panel>

            {{-- ===== Panel: Zugehörige Locations ===== --}}
            <x-ui-panel title="Zugehörige Locations" subtitle="Locations an diesem Standort">
                <div class="p-1">
                    @if($locations->isEmpty())
                        <p class="text-[0.62rem] text-[var(--ui-muted)]">Noch keine Locations diesem Site zugeordnet.</p>
                    @else
                        <div class="space-y-1">
                            @foreach($locations as $loc)
                                <a href="{{ route('locations.show', $loc->uuid) }}" wire:navigate
                                   class="flex items-center gap-2 px-3 py-2 rounded-md text-xs text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors">
                                    <span class="font-mono font-bold text-[0.6rem] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 uppercase">{{ $loc->kuerzel }}</span>
                                    <span class="truncate">{{ $loc->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-ui-panel>

        </form>
    </x-ui-page-container>
</x-ui-page>
