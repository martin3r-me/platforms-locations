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

        $byDate       = $this->loadBookingsByDate($team->id, $locations, $periodStart, $periodEnd);
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

    /**
     * Cross-Modul-Bridge zum Events-Modul. Wenn `Platform\Events\Models\Booking`
     * verfuegbar ist, werden die Buchungen des Teams im Period geladen und auf
     * das Shape gemappt, das die Occupancy-View erwartet:
     *
     *   [date => [location_kuerzel => [(object){title, optionsrang}, ...]]]
     *
     * Wenn das Events-Modul nicht installiert ist, bleibt das Array leer und
     * die View zeigt ihren bestehenden Leerstate. Beide Module bleiben damit
     * unabhaengig lauffaehig — die Verbindung greift nur, wenn Events da ist.
     *
     * @param int $teamId
     * @param \Illuminate\Support\Collection<int, Location> $locations
     * @param string $periodStart  YYYY-MM-DD
     * @param string $periodEnd    YYYY-MM-DD
     * @return array<string, array<string, array<int, object>>>
     */
    protected function loadBookingsByDate(int $teamId, $locations, string $periodStart, string $periodEnd): array
    {
        $bookingClass = '\\Platform\\Events\\Models\\Booking';
        if (!class_exists($bookingClass)) {
            return [];
        }

        $byDate = [];
        try {
            $locKuerzelById = $locations->pluck('kuerzel', 'id');

            $bookings = $bookingClass::query()
                ->where('team_id', $teamId)
                ->whereNotNull('location_id')
                ->whereBetween('datum', [$periodStart, $periodEnd])
                ->with(['event:id,name,event_number'])
                ->orderBy('datum')
                ->get();

            foreach ($bookings as $b) {
                $kuerzel = $locKuerzelById->get($b->location_id);
                if (!$kuerzel) {
                    // Buchung verweist auf eine Location ausserhalb des aktuellen
                    // Teams oder eine geloeschte — defensiv ueberspringen.
                    continue;
                }

                $datum = $b->datum;
                $dateKey = $datum instanceof \DateTimeInterface
                    ? $datum->format('Y-m-d')
                    : substr((string) $datum, 0, 10);
                if ($dateKey === '') {
                    continue;
                }

                $event = $b->event ?? null;
                $title = $event?->name
                    ?: ($event?->event_number ?: ($b->raum ?: '—'));

                $byDate[$dateKey][$kuerzel][] = (object) [
                    'title'       => (string) $title,
                    'optionsrang' => (string) ($b->optionsrang ?? ''),
                ];
            }

            ksort($byDate);
        } catch (\Throwable $e) {
            \Log::warning('[Locations\\Occupancy] Buchungs-Lookup fehlgeschlagen', [
                'error'   => $e->getMessage(),
                'team_id' => $teamId,
            ]);
            return [];
        }

        return $byDate;
    }
}
