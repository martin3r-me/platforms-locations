<?php

namespace Platform\Locations\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Platform\Locations\Models\Location;

class Manage extends Component
{
    public ?string $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:20')]
    public string $kuerzel = '';

    #[Validate('nullable|string|max:255')]
    public ?string $gruppe = null;

    #[Validate('nullable|integer|min:0|max:65535')]
    public ?int $pax_min = null;

    #[Validate('nullable|integer|min:0|max:65535')]
    public ?int $pax_max = null;

    public bool $mehrfachbelegung = false;

    #[Validate('nullable|string|max:255')]
    public ?string $adresse = null;

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'kuerzel', 'gruppe', 'pax_min', 'pax_max', 'mehrfachbelegung', 'adresse']);
        $this->resetErrorBag();
    }

    public function edit(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        $location = Location::where('team_id', $team->id)->where('uuid', $uuid)->firstOrFail();

        $this->editingId = $location->uuid;
        $this->name = $location->name;
        $this->kuerzel = $location->kuerzel;
        $this->gruppe = $location->gruppe;
        $this->pax_min = $location->pax_min;
        $this->pax_max = $location->pax_max;
        $this->mehrfachbelegung = (bool) $location->mehrfachbelegung;
        $this->adresse = $location->adresse;

        $this->dispatch('locations:edit-open');
    }

    public function save(): void
    {
        $data = $this->validate();

        $user = Auth::user();
        $team = $user->currentTeam;

        if ($this->editingId) {
            $location = Location::where('team_id', $team->id)->where('uuid', $this->editingId)->firstOrFail();
            $location->update($data);
        } else {
            $data['team_id'] = $team->id;
            $data['user_id'] = $user->id;
            $data['sort_order'] = (Location::where('team_id', $team->id)->max('sort_order') ?? 0) + 1;
            Location::create($data);
        }

        $this->resetForm();
        $this->dispatch('locations:saved');
    }

    public function delete(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        Location::where('team_id', $team->id)->where('uuid', $uuid)->firstOrFail()->delete();
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $locations = Location::where('team_id', $team->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('locations::livewire.manage', [
            'locations' => $locations,
        ])->layout('platform::layouts.app');
    }
}
