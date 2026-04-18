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

    public function updatingPeriod($value): void
    {
        if (!in_array($value, ['week', 'month', 'year', 'all'], true)) {
            $this->period = 'month';
        }
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

    protected function periodLabel(): string
    {
        return match ($this->period) {
            'week'  => 'diese Woche',
            'year'  => 'dieses Jahr',
            'all'   => 'nächste 3 Monate',
            default => 'diesen Monat',
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
        $byDate       = [];
        $monthlyStats = [];
        $yearlyStats  = [];

        return view('locations::livewire.occupancy', [
            'locations'    => $locations,
            'venueRooms'   => $locations,
            'roomGroups'   => $roomGroups,
            'roomNames'    => $roomNames,
            'periodStart'  => $periodStart,
            'periodEnd'    => $periodEnd,
            'periodLabel'  => $this->periodLabel(),
            'byDate'       => $byDate,
            'monthlyStats' => $monthlyStats,
            'yearlyStats'  => $yearlyStats,
        ])->layout('platform::layouts.app');
    }
}
