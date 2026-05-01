<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Sites verwalten" icon="heroicon-o-building-library" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Locations', 'href' => route('locations.dashboard'), 'icon' => 'map-pin'],
            ['label' => 'Sites'],
        ]">
            <x-slot name="left">
                <x-ui-button variant="ghost" size="sm" :href="route('locations.manage')" wire:navigate>
                    @svg('heroicon-o-building-office', 'w-4 h-4')
                    <span>Locations</span>
                </x-ui-button>
            </x-slot>
            <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Site</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            <x-ui-panel title="Sites" subtitle="Standorte als Eltern-Container fuer Locations">
                @if($sites->isEmpty())
                    <div class="p-12 text-center">
                        <div class="mb-4">
                            @svg('heroicon-o-building-library', 'w-12 h-12 text-[var(--ui-muted)] mx-auto')
                        </div>
                        <p class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Noch keine Sites angelegt</p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Sites gruppieren Locations nach Standort.</p>
                        <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Neuer Site
                            </span>
                        </x-ui-button>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                                    <th class="px-4 py-3 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Name</th>
                                    <th class="px-4 py-3 text-left text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Ort</th>
                                    <th class="px-4 py-3 text-right text-[0.62rem] font-bold uppercase tracking-wider text-[var(--ui-muted)]">Locations</th>
                                    <th class="px-4 py-3 w-24"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sites as $site)
                                    <tr class="border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]/60 transition-colors">
                                        <td class="px-4 py-3">
                                            <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $site->name }}</span>
                                            @if($site->description)
                                                <p class="text-[0.62rem] text-[var(--ui-muted)] line-clamp-1 mt-0.5">{{ $site->description }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-xs text-[var(--ui-muted)]">{{ $site->full_address ?: '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="text-xs font-mono text-[var(--ui-muted)]">{{ $site->locations_count }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <x-ui-button variant="secondary-outline" size="sm" :href="route('locations.sites.show', $site->uuid)" wire:navigate>
                                                    Bearbeiten
                                                </x-ui-button>
                                                <x-ui-button
                                                    variant="danger-outline"
                                                    size="sm"
                                                    wire:click="delete('{{ $site->uuid }}')"
                                                    wire:confirm="Site „{{ $site->name }}" wirklich löschen?"
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

        {{-- ===== Modal: Create Site ===== --}}
        <x-ui-modal wire:model="showModal" size="lg" :hideFooter="true">
            <x-slot name="header">Neuer Site</x-slot>

            <form wire:submit.prevent="save" class="space-y-4">
                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Name *</label>
                    <input wire:model="name" type="text" placeholder="z.B. Messegelände Köln"
                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    @error('name') <p class="mt-1 text-[0.62rem] text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Beschreibung</label>
                    <textarea wire:model="description" rows="2" placeholder="Optionale Beschreibung"
                              class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30"></textarea>
                </div>

                <div class="grid grid-cols-[1fr_80px] gap-3">
                    <div>
                        <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Straße</label>
                        <input wire:model="street" type="text" placeholder="z.B. Messeplatz"
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
                        <input wire:model="city" type="text" placeholder="Köln"
                               class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                    </div>
                </div>

                <div>
                    <label class="text-[0.65rem] font-semibold text-[var(--ui-muted)] block mb-1">Land</label>
                    <input wire:model="country" type="text" placeholder="Deutschland"
                           class="w-full border border-[var(--ui-border)] rounded-md px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                </div>

                <p class="text-[0.62rem] text-[var(--ui-muted)] border-t border-[var(--ui-border)] pt-3">
                    Weitere Details (GPS, Kontakt, Notizen) können nach dem Anlegen auf der Detail-Seite gepflegt werden.
                </p>

                <div class="flex justify-end gap-2 pt-2 border-t border-[var(--ui-border)]">
                    <x-ui-button type="button" variant="secondary-outline" size="sm" wire:click="closeModal">
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button type="submit" variant="primary" size="sm">
                        <span wire:loading.remove wire:target="save">Site anlegen</span>
                        <span wire:loading wire:target="save">Speichern …</span>
                    </x-ui-button>
                </div>
            </form>
        </x-ui-modal>
    </x-ui-page-container>
</x-ui-page>
