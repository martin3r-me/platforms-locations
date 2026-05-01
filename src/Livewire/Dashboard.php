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

        // Gruppen-Breakdown für Sidebar
        $groupBreakdown = $locations
            ->groupBy(fn ($l) => $l->gruppe ?: '– Ohne Gruppe –')
            ->map(fn ($group, $name) => [
                'name'     => $name,
                'count'    => $group->count(),
                'capacity' => (int) $group->sum('pax_max'),
            ])
            ->sortByDesc('capacity')
            ->values();

        // Top-Locations nach Kapazität
        $topLocations = $locations
            ->filter(fn ($l) => $l->pax_max > 0)
            ->sortByDesc('pax_max')
            ->take(5)
            ->map(fn ($l) => [
                'name'     => $l->name,
                'kuerzel'  => $l->kuerzel,
                'pax_max'  => $l->pax_max,
                'gruppe'   => $l->gruppe,
            ])
            ->values();

        return view('locations::livewire.dashboard', [
            'currentDate'        => now()->format('d.m.Y'),
            'currentDay'         => now()->format('l'),
            'totalLocations'     => $totalLocations,
            'uniqueGroups'       => $uniqueGroups,
            'totalCapacity'      => $totalCapacity,
            'multiUseLocations'  => $multiUseLocations,
            'groupBreakdown'     => $groupBreakdown,
            'topLocations'       => $topLocations,
        ])->layout('platform::layouts.app');
    }
}
