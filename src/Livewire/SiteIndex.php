<?php

namespace Platform\Locations\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Platform\Locations\Models\LocationSite;

class SiteIndex extends Component
{
    public bool $showModal = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:65000')]
    public ?string $description = null;

    #[Validate('nullable|string|max:255')]
    public ?string $street = null;

    #[Validate('nullable|string|max:50')]
    public ?string $street_number = null;

    #[Validate('nullable|string|max:20')]
    public ?string $postal_code = null;

    #[Validate('nullable|string|max:255')]
    public ?string $city = null;

    #[Validate('nullable|string|max:255')]
    public ?string $country = null;

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
        $this->reset(['name', 'description', 'street', 'street_number', 'postal_code', 'city', 'country']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $data = $this->validate();

        $user = Auth::user();
        $team = $user->currentTeam;

        $site = DB::transaction(function () use ($data, $team, $user) {
            $data['team_id']    = $team->id;
            $data['user_id']    = $user->id;
            $data['sort_order'] = (LocationSite::where('team_id', $team->id)->max('sort_order') ?? 0) + 1;
            return LocationSite::create($data);
        });

        $this->showModal = false;
        $this->resetForm();

        $this->redirect(route('locations.sites.show', $site->uuid), navigate: true);
    }

    public function delete(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        LocationSite::where('team_id', $team->id)->where('uuid', $uuid)->firstOrFail()->delete();
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $sites = LocationSite::where('team_id', $team->id)
            ->withCount('locations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('locations::livewire.site-index', [
            'sites' => $sites,
        ])->layout('platform::layouts.app');
    }
}
