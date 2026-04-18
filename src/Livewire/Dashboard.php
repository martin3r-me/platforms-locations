<?php

namespace Platform\Locations\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Locations\Models\Location;

class Dashboard extends Component
{
    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => 'Platform\\Locations\\Models\\Location',
            'modelId' => null,
            'subject' => 'Locations Dashboard',
            'description' => 'Übersicht des Locations-Moduls',
            'url' => route('locations.dashboard'),
            'source' => 'locations.dashboard',
            'recipients' => [],
            'meta' => [
                'view_type' => 'dashboard',
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $locations = Location::where('team_id', $team->id)->get();

        $totalLocations     = $locations->count();
        $uniqueGroups       = $locations->pluck('gruppe')->filter()->unique()->count();
        $totalCapacity      = (int) $locations->sum('pax_max');
        $multiUseLocations  = $locations->where('mehrfachbelegung', true)->count();

        return view('locations::livewire.dashboard', [
            'currentDate'        => now()->format('d.m.Y'),
            'currentDay'         => now()->format('l'),
            'totalLocations'     => $totalLocations,
            'uniqueGroups'       => $uniqueGroups,
            'totalCapacity'      => $totalCapacity,
            'multiUseLocations'  => $multiUseLocations,
        ])->layout('platform::layouts.app');
    }
}
