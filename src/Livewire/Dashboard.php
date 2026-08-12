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

        $locations = Location::where('team_id', $team->id)
            ->with('site:id,uuid,name')
            ->get();

        $totalLocations     = $locations->count();
        $uniqueSites        = $locations->pluck('site_id')->filter()->unique()->count();
        $totalCapacity      = (int) $locations->sum('pax_max');
        $multiUseLocations  = $locations->where('mehrfachbelegung', true)->count();

        // Site-Breakdown (Kachel-Bereich unter den Kennzahlen)
        $siteBreakdown = $locations
            ->groupBy(fn ($l) => $l->site?->name ?: '– Ohne Site –')
            ->map(fn ($group, $name) => [
                'name'     => $name,
                'count'    => $group->count(),
                'capacity' => (int) $group->sum('pax_max'),
                // null bei "Ohne Site" -> Karte wird dann nicht verlinkt
                'uuid'     => $group->first()->site?->uuid,
            ])
            ->sortByDesc('capacity')
            ->values();

        // Top-Locations nach Kapazität
        $topLocations = $locations
            ->filter(fn ($l) => $l->pax_max > 0)
            ->sortByDesc('pax_max')
            ->take(6)
            ->map(fn ($l) => [
                'uuid'     => $l->uuid,
                'name'     => $l->name,
                'kuerzel'  => $l->kuerzel,
                'pax_max'  => $l->pax_max,
                'site'     => $l->site?->name,
            ])
            ->values();

        return view('locations::livewire.dashboard', [
            'currentDate'        => now()->format('d.m.Y'),
            'currentDay'         => now()->format('l'),
            'totalLocations'     => $totalLocations,
            'uniqueSites'        => $uniqueSites,
            'totalCapacity'      => $totalCapacity,
            'multiUseLocations'  => $multiUseLocations,
            'siteBreakdown'      => $siteBreakdown,
            'topLocations'       => $topLocations,
        ])->layout('platform::layouts.app');
    }
}
