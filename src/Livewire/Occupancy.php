<?php

namespace Platform\Locations\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Locations\Models\Location;
use Platform\Locations\Models\LocationBlocking;
use Platform\Locations\Models\LocationSite;
use Platform\Locations\Services\AvailabilityService;

class Occupancy extends Component
{
    #[Url(as: 'period', except: 'month')]
    public string $period = 'month';

    /** Site-UUID, nach der gefiltert werden soll. Leerstring = alle Sites. */
    #[Url(as: 'site', except: '')]
    public string $activeSite = '';

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
            ->with('site:id,uuid,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Liste aller Sites mit mindestens einer Location im Team — bildet die
        // Filter-Optionen oben in der View.
        $sites = LocationSite::where('team_id', $team->id)
            ->whereHas('locations', fn ($q) => $q->where('team_id', $team->id))
            ->orderBy('name')
            ->get(['uuid', 'name']);

        $filtered = $this->activeSite
            ? $locations->filter(fn ($l) => $l->site && $l->site->uuid === $this->activeSite)->values()
            : $locations;

        $roomNames = $filtered->pluck('kuerzel')->toArray() ?: $locations->pluck('kuerzel')->toArray();

        [$periodStart, $periodEnd] = $this->periodRange();

        $byDate = $this->loadBookingsByDate($team->id, $locations, $periodStart, $periodEnd);
        $this->mergeBlockingsIntoByDate($byDate, $locations, $periodStart, $periodEnd);

        $utilization = $this->buildUtilization($byDate, $filtered, $periodStart, $periodEnd);

        return view('locations::livewire.occupancy', [
            'locations'   => $locations,
            'venueRooms'  => $locations,
            'sites'       => $sites,
            'roomNames'   => $roomNames,
            'periodStart' => $periodStart,
            'periodEnd'   => $periodEnd,
            'periodLabel' => $this->periodLabel(),
            'byDate'      => $byDate,
            'utilization' => $utilization,
        ])->layout('platform::layouts.app');
    }

    /**
     * Sperrzeiten in das byDate-Shape einmischen — sie erscheinen damit als
     * "Gesperrt"-Eintraege im Belegungskalender, analog zu Buchungen.
     *
     * @param array<string, array<string, array<int, object>>> $byDate
     * @param \Illuminate\Support\Collection<int, Location> $locations
     */
    protected function mergeBlockingsIntoByDate(array &$byDate, $locations, string $periodStart, string $periodEnd): void
    {
        $locKuerzelById = $locations->pluck('kuerzel', 'id');

        $blockings = LocationBlocking::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->overlapping($periodStart, $periodEnd)
            ->get();

        foreach ($blockings as $blocking) {
            $kuerzel = $locKuerzelById->get($blocking->location_id);
            if (!$kuerzel) {
                continue;
            }

            // Sperre auf die Tage innerhalb des Zeitraums begrenzen.
            $cursor = max($blocking->start_date->toDateString(), $periodStart);
            $last   = min($blocking->end_date->toDateString(), $periodEnd);

            $day = \Carbon\Carbon::parse($cursor);
            while ($day->toDateString() <= $last) {
                $byDate[$day->toDateString()][$kuerzel][] = (object) [
                    'title'       => $blocking->reason ?: 'Gesperrt',
                    'optionsrang' => 'Gesperrt',
                ];
                $day->addDay();
            }
        }

        ksort($byDate);
    }

    /**
     * Auslastungs-Kennzahlen pro Location fuer den Zeitraum.
     *
     * Ein Tag zaehlt als "belegt", wenn mind. eine Buchung mit hartem
     * Optionsrang (Definitiv/Vertrag) anliegt; als "Option", wenn nur
     * Optionen anliegen; Sperrtage zaehlen separat. Quote = belegte Tage /
     * Tage im Zeitraum.
     *
     * @param array<string, array<string, array<int, object>>> $byDate
     * @param \Illuminate\Support\Collection<int, Location> $locations
     * @return array<int, array{kuerzel: string, name: string, total_days: int, belegt: int, optionen: int, gesperrt: int, quote: float}>
     */
    protected function buildUtilization(array $byDate, $locations, string $periodStart, string $periodEnd): array
    {
        $totalDays = \Carbon\Carbon::parse($periodStart)->diffInDays(\Carbon\Carbon::parse($periodEnd)) + 1;

        return $locations->map(function (Location $location) use ($byDate, $totalDays) {
            $belegt = 0;
            $optionen = 0;
            $gesperrt = 0;

            foreach ($byDate as $rooms) {
                $entries = $rooms[$location->kuerzel] ?? null;
                if (!$entries) {
                    continue;
                }

                $ranks = array_filter(
                    array_map(fn ($e) => (string) ($e->optionsrang ?? ''), $entries),
                    fn ($r) => !in_array($r, AvailabilityService::IGNORED_RANKS, true)
                );

                if (in_array('Gesperrt', $ranks, true)) {
                    $gesperrt++;
                } elseif (array_intersect($ranks, AvailabilityService::HARD_RANKS) !== []) {
                    $belegt++;
                } elseif ($ranks !== []) {
                    $optionen++;
                }
            }

            return [
                'kuerzel'    => (string) $location->kuerzel,
                'name'       => (string) $location->name,
                'total_days' => $totalDays,
                'belegt'     => $belegt,
                'optionen'   => $optionen,
                'gesperrt'   => $gesperrt,
                'quote'      => $totalDays > 0 ? round($belegt / $totalDays * 100, 1) : 0.0,
            ];
        })->values()->all();
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
