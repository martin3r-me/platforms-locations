<?php

namespace Platform\Locations\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Locations\Models\Location;

class Occupancy extends Component
{
    #[Url(as: 'period', except: 'month')]
    public string $period = 'month';

    #[Url(as: 'gruppe', except: '')]
    public string $activeGroup = '';

    public function setPeriod(string $period): void
    {
        if (!in_array($period, ['week', 'month', 'year', 'all'], true)) {
            $period = 'month';
        }
        $this->period = $period;
    }

    public function setGroup(string $group): void
    {
        $this->activeGroup = $group;
    }

    protected function periodRange(): array
    {
        return match ($this->period) {
            'week'  => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'year'  => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'all'   => [now()->subMonths(1)->startOfMonth()->toDateString(), now()->addMonths(3)->endOfMonth()->toDateString()],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $locations = Location::where('team_id', $team->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $roomGroups = $locations->pluck('gruppe')->filter()->unique()->values()->toArray();

        $filtered = $this->activeGroup
            ? $locations->where('gruppe', $this->activeGroup)->values()
            : $locations;

        $roomNames = $filtered->pluck('kuerzel')->toArray() ?: $locations->pluck('kuerzel')->toArray();

        [$periodStart, $periodEnd] = $this->periodRange();

        // Platzhalter: Buchungen kommen später aus dem Events-Modul.
        $byDate = [];
        $monthlyStats = [];
        $yearlyStats = [];

        return view('locations::livewire.occupancy', [
            'locations'    => $locations,
            'venueRooms'   => $locations,
            'roomGroups'   => $roomGroups,
            'roomNames'    => $roomNames,
            'periodStart'  => $periodStart,
            'periodEnd'    => $periodEnd,
            'byDate'       => $byDate,
            'monthlyStats' => $monthlyStats,
            'yearlyStats'  => $yearlyStats,
        ])->layout('platform::layouts.app');
    }
}
