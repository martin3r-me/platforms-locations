<?php

namespace Platform\Locations\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Platform\Locations\Models\Location;

class Manage extends Component
{
    public bool $showModal = false;

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

    #[Validate('nullable|numeric|between:-90,90')]
    public ?float $latitude = null;

    #[Validate('nullable|numeric|between:-180,180')]
    public ?float $longitude = null;

    /** @var array<int,array<string,mixed>> */
    public array $addressSuggestions = [];

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset([
            'name', 'kuerzel', 'gruppe',
            'pax_min', 'pax_max', 'mehrfachbelegung',
            'adresse', 'latitude', 'longitude', 'addressSuggestions',
        ]);
        $this->resetErrorBag();
    }

    public function updatedAdresse(?string $value): void
    {
        $this->searchAddress($value);
    }

    public function searchAddress(?string $query): void
    {
        $this->addressSuggestions = app(\Platform\Locations\Services\GeocodingService::class)
            ->searchSuggestions((string) $query);
    }

    public function selectSuggestion(int $index): void
    {
        $s = $this->addressSuggestions[$index] ?? null;
        if (!$s) {
            return;
        }

        $this->adresse   = $s['display'];
        $this->latitude  = $s['lat'];
        $this->longitude = $s['lon'];
        $this->addressSuggestions = [];
    }

    public function save(): void
    {
        $data = $this->validate();

        $user = Auth::user();
        $team = $user->currentTeam;

        $data['mehrfachbelegung'] = (bool) $this->mehrfachbelegung;

        $location = DB::transaction(function () use ($data, $team, $user) {
            $data['team_id']    = $team->id;
            $data['user_id']    = $user->id;
            $data['sort_order'] = (Location::where('team_id', $team->id)->max('sort_order') ?? 0) + 1;
            return Location::create($data);
        });

        $this->showModal = false;
        $this->resetForm();

        $this->redirect(route('locations.show', $location->uuid), navigate: true);
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
            'locations'    => $locations,
            'allLocations' => $locations,
        ])->layout('platform::layouts.app');
    }
}
