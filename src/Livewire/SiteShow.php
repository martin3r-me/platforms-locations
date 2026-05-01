<?php

namespace Platform\Locations\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Platform\Locations\Models\LocationSite;
use Platform\Locations\Services\GeocodingService;

class SiteShow extends Component
{
    public LocationSite $site;
    public string $currentUuid = '';

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
    public ?string $state = null;

    #[Validate('nullable|string|max:255')]
    public ?string $country = null;

    #[Validate('nullable|string|max:2')]
    public ?string $country_code = null;

    #[Validate('nullable|numeric|between:-90,90')]
    public ?float $latitude = null;

    #[Validate('nullable|numeric|between:-180,180')]
    public ?float $longitude = null;

    #[Validate('nullable|string|max:100')]
    public ?string $timezone = null;

    public bool $is_international = false;

    #[Validate('nullable|string|max:50')]
    public ?string $phone = null;

    #[Validate('nullable|email|max:255')]
    public ?string $email = null;

    #[Validate('nullable|string|max:255')]
    public ?string $website = null;

    #[Validate('nullable|string|max:65000')]
    public ?string $notes = null;

    /** @var array<int,array<string,mixed>> */
    public array $addressSuggestions = [];

    /** @var array<int, array> */
    public array $activityItems = [];

    public function mount(string $site): void
    {
        $team = Auth::user()->currentTeam;
        $this->site = LocationSite::where('team_id', $team->id)
            ->where('uuid', $site)
            ->firstOrFail();

        $this->currentUuid = $this->site->uuid;
        $this->loadForm();
    }

    protected function loadForm(): void
    {
        $s = $this->site;

        $this->name             = $s->name;
        $this->description      = $s->description;
        $this->street           = $s->street;
        $this->street_number    = $s->street_number;
        $this->postal_code      = $s->postal_code;
        $this->city             = $s->city;
        $this->state            = $s->state;
        $this->country          = $s->country;
        $this->country_code     = $s->country_code;
        $this->latitude         = $s->latitude !== null ? (float) $s->latitude : null;
        $this->longitude        = $s->longitude !== null ? (float) $s->longitude : null;
        $this->timezone         = $s->timezone;
        $this->is_international = (bool) $s->is_international;
        $this->phone            = $s->phone;
        $this->email            = $s->email;
        $this->website          = $s->website;
        $this->notes            = $s->notes;
        $this->addressSuggestions = [];

        $this->loadActivityItems();
        $this->resetErrorBag();
    }

    public function updatedStreet(): void
    {
        $this->searchAddress();
    }

    public function updatedCity(): void
    {
        $this->searchAddress();
    }

    protected function searchAddress(): void
    {
        $query = trim(($this->street ?? '') . ' ' . ($this->street_number ?? '') . ', ' . ($this->postal_code ?? '') . ' ' . ($this->city ?? ''));
        if (mb_strlen($query) < 3) {
            $this->addressSuggestions = [];
            return;
        }
        $this->addressSuggestions = app(GeocodingService::class)->searchSuggestions($query);
    }

    public function selectSuggestion(int $index): void
    {
        $s = $this->addressSuggestions[$index] ?? null;
        if (!$s) {
            return;
        }

        $this->latitude  = $s['lat'];
        $this->longitude = $s['lon'];
        $this->addressSuggestions = [];
    }

    public function clearCoordinates(): void
    {
        $this->latitude = null;
        $this->longitude = null;
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['is_international'] = (bool) $this->is_international;

        DB::transaction(function () use ($data) {
            $this->site->update($data);
        });

        $this->site->refresh();
        $this->loadForm();
    }

    public function delete(): void
    {
        $this->site->delete();
        $this->redirect(route('locations.sites'), navigate: true);
    }

    protected function loadActivityItems(): void
    {
        $this->activityItems = [];

        try {
            $activities = $this->site->activities()
                ->with('user')
                ->latest()
                ->take(30)
                ->get();

            $this->activityItems = $activities->map(function ($activity) {
                return [
                    'id'         => $activity->id,
                    'type'       => $activity->activity_type,
                    'name'       => $activity->name,
                    'message'    => $activity->message,
                    'user_name'  => $activity->user?->name ?? 'System',
                    'properties' => $activity->properties ?? [],
                    'created_at' => $activity->created_at?->diffForHumans(),
                    'created_at_full' => $activity->created_at?->format('d.m.Y H:i'),
                ];
            })->all();
        } catch (\Throwable $e) {
            // ActivityLog ggf. nicht verfuegbar
        }
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $allSites = LocationSite::where('team_id', $team->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['uuid', 'name']);

        $locations = $this->site->locations()->get(['uuid', 'name', 'kuerzel']);

        return view('locations::livewire.site-show', [
            'allSites'  => $allSites,
            'locations' => $locations,
        ])->layout('platform::layouts.app');
    }
}
