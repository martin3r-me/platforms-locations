<?php

namespace Platform\Locations\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Platform\Locations\Models\Location;
use Platform\Locations\Models\LocationBlocking;

/**
 * Verfuegbarkeits-Logik fuer Locations — tagesgenau.
 *
 * Datenquellen:
 *   1. locations_blockings (eigene Sperrzeiten, immer hart)
 *   2. events_bookings (Cross-Modul-Bridge, nur lesend; greift nur wenn das
 *      Events-Modul installiert ist — analog Livewire\Occupancy)
 *
 * Konfliktregeln (Optionsrang-Matrix):
 *   - Sperrzeit                  -> Tag ist GESPERRT, egal was sonst anliegt
 *   - "Definitiv"/"Vertrag"      -> Tag ist BELEGT — ausser die Location
 *                                   erlaubt Mehrfachbelegung, dann nur Hinweis
 *   - "1./2./3. Option" etc.     -> Tag hat OPTIONEN (parallel buchbar, das
 *                                   ist das Optionsgeschaeft) — Status bleibt
 *                                   verfuegbar, Konflikte werden gelistet
 *   - "Abgesagt"                 -> wird ignoriert
 */
class AvailabilityService
{
    public const STATUS_FREI     = 'frei';
    public const STATUS_OPTIONEN = 'optionen';
    public const STATUS_BELEGT   = 'belegt';
    public const STATUS_GESPERRT = 'gesperrt';

    /** Optionsraenge, die einen Tag hart belegen. */
    public const HARD_RANKS = ['Definitiv', 'Vertrag'];

    /** Optionsraenge, die ignoriert werden. */
    public const IGNORED_RANKS = ['Abgesagt'];

    /**
     * Prueft eine einzelne Location fuer einen Zeitraum (inkl. Grenzen).
     *
     * @return array{
     *   status: string,
     *   available: bool,
     *   days: array<string, array{status: string, blockings: array<int, array<string, mixed>>, bookings: array<int, array<string, mixed>>}>,
     * }
     * status = schlechtester Tages-Status im Zeitraum
     * available = kein Tag ist 'belegt' oder 'gesperrt'
     *
     * $ignoreEventId: Buchungen dieses Events ausklammern — fuer Checks aus
     * dem Events-Modul heraus, damit ein Event nicht mit sich selbst kollidiert.
     */
    public function check(Location $location, string $from, ?string $to = null, ?int $ignoreEventId = null): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        $blockings = $location->blockings()->overlapping($from, $to)->get();
        $bookings  = $this->bookingsByLocation([$location->id], (int) $location->team_id, $from, $to, $ignoreEventId)
            ->get($location->id, collect());

        $days = [];
        foreach ($this->datesBetween($from, $to) as $date) {
            $days[$date] = $this->evaluateDay($location, $date, $blockings, $bookings);
        }

        $status = $this->worstStatus(array_column($days, 'status'));

        return [
            'status'    => $status,
            'available' => !in_array($status, [self::STATUS_BELEGT, self::STATUS_GESPERRT], true),
            'days'      => $days,
        ];
    }

    /**
     * Sucht alle verfuegbaren Locations eines Teams fuer einen Zeitraum.
     * Optional gefiltert nach PAX (gegen pax_max) und Site.
     *
     * Liefert ALLE Locations mit ihrem Status zurueck (auch belegte) — der
     * Aufrufer (UI/AI) entscheidet, was er anzeigt. `available` markiert die
     * tatsaechlich buchbaren.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAvailable(int $teamId, string $from, ?string $to = null, ?int $pax = null, ?int $siteId = null): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        $locations = Location::query()
            ->where('team_id', $teamId)
            ->when($siteId !== null, fn ($q) => $q->where('site_id', $siteId))
            ->with(['site:id,name', 'seatingOptions'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($locations->isEmpty()) {
            return [];
        }

        $blockingsByLocation = LocationBlocking::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->overlapping($from, $to)
            ->get()
            ->groupBy('location_id');

        $bookingsByLocation = $this->bookingsByLocation($locations->pluck('id')->all(), $teamId, $from, $to);

        $dates = $this->datesBetween($from, $to);

        return $locations->map(function (Location $location) use ($dates, $blockingsByLocation, $bookingsByLocation, $pax) {
            $blockings = $blockingsByLocation->get($location->id, collect());
            $bookings  = $bookingsByLocation->get($location->id, collect());

            $dayStatuses = [];
            $conflicts = [];
            foreach ($dates as $date) {
                $day = $this->evaluateDay($location, $date, $blockings, $bookings);
                $dayStatuses[] = $day['status'];
                if ($day['status'] !== self::STATUS_FREI) {
                    $conflicts[$date] = $day;
                }
            }

            $status = $this->worstStatus($dayStatuses);

            $paxFits = null;
            if ($pax !== null) {
                $paxFits = $location->pax_max === null || $pax <= (int) $location->pax_max;
            }

            $seatings = $location->seatingOptions
                ->when($pax !== null, fn ($c) => $c->filter(
                    fn ($s) => (int) $s->pax_max_ca === 0 || (int) $s->pax_max_ca >= $pax
                ))
                ->map(fn ($s) => ['label' => $s->label, 'pax_max_ca' => (int) $s->pax_max_ca])
                ->values()
                ->all();

            return [
                'id'               => $location->id,
                'uuid'             => $location->uuid,
                'name'             => $location->name,
                'kuerzel'          => $location->kuerzel,
                'site'             => $location->site?->name,
                'pax_min'          => $location->pax_min,
                'pax_max'          => $location->pax_max,
                'mehrfachbelegung' => (bool) $location->mehrfachbelegung,
                'status'           => $status,
                'available'        => !in_array($status, [self::STATUS_BELEGT, self::STATUS_GESPERRT], true),
                'pax_fits'         => $paxFits,
                'seating_options'  => $seatings,
                'conflicts'        => $conflicts,
            ];
        })->all();
    }

    /**
     * Status eines einzelnen Tages fuer eine Location.
     *
     * @param Collection<int, LocationBlocking> $blockings  Sperren der Location im Gesamtzeitraum
     * @param Collection<int, object>           $bookings   Events-Bookings der Location im Gesamtzeitraum
     * @return array{status: string, blockings: array<int, array<string, mixed>>, bookings: array<int, array<string, mixed>>}
     */
    protected function evaluateDay(Location $location, string $date, Collection $blockings, Collection $bookings): array
    {
        $dayBlockings = $blockings
            ->filter(fn (LocationBlocking $b) => $b->coversDate($date))
            ->map(fn (LocationBlocking $b) => [
                'id'         => $b->id,
                'uuid'       => $b->uuid,
                'start_date' => $b->start_date?->toDateString(),
                'end_date'   => $b->end_date?->toDateString(),
                'reason'     => $b->reason,
            ])
            ->values();

        $dayBookings = $bookings
            ->filter(fn ($b) => $this->bookingDate($b) === $date)
            ->map(fn ($b) => [
                'event'       => $b->event?->name ?: $b->event?->event_number,
                'optionsrang' => (string) ($b->optionsrang ?? ''),
                'start_time'  => $b->start_time,
                'end_time'    => $b->end_time,
            ])
            ->values();

        if ($dayBlockings->isNotEmpty()) {
            $status = self::STATUS_GESPERRT;
        } else {
            $hasHard = $dayBookings->contains(
                fn ($b) => in_array($b['optionsrang'], self::HARD_RANKS, true)
            );
            if ($hasHard && !$location->mehrfachbelegung) {
                $status = self::STATUS_BELEGT;
            } elseif ($dayBookings->isNotEmpty()) {
                $status = self::STATUS_OPTIONEN;
            } else {
                $status = self::STATUS_FREI;
            }
        }

        return [
            'status'    => $status,
            'blockings' => $dayBlockings->all(),
            'bookings'  => $dayBookings->all(),
        ];
    }

    /**
     * Events-Bookings im Zeitraum, gruppiert nach location_id. Leer, wenn das
     * Events-Modul nicht installiert ist oder der Lookup fehlschlaegt.
     *
     * @param array<int, int> $locationIds
     * @return Collection<int, Collection<int, object>>
     */
    protected function bookingsByLocation(array $locationIds, int $teamId, string $from, string $to, ?int $ignoreEventId = null): Collection
    {
        $bookingClass = '\\Platform\\Events\\Models\\Booking';
        if (!class_exists($bookingClass) || $locationIds === []) {
            return collect();
        }

        try {
            return $bookingClass::query()
                ->where('team_id', $teamId)
                ->whereIn('location_id', $locationIds)
                ->whereBetween('datum', [$from, $to])
                ->whereNotIn('optionsrang', self::IGNORED_RANKS)
                ->when($ignoreEventId !== null, fn ($q) => $q->where('event_id', '!=', $ignoreEventId))
                ->with('event:id,name,event_number')
                ->get()
                ->groupBy('location_id');
        } catch (\Throwable $e) {
            \Log::warning('[Locations\\Availability] Buchungs-Lookup fehlgeschlagen', [
                'error'   => $e->getMessage(),
                'team_id' => $teamId,
            ]);
            return collect();
        }
    }

    /**
     * @return array{0: string, 1: string} [from, to] als YYYY-MM-DD, from <= to
     */
    protected function normalizeRange(string $from, ?string $to): array
    {
        $fromDate = Carbon::parse($from)->toDateString();
        $toDate   = $to !== null && $to !== '' ? Carbon::parse($to)->toDateString() : $fromDate;

        if ($toDate < $fromDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    /**
     * @return array<int, string> alle Tage von $from bis $to (inklusive)
     */
    protected function datesBetween(string $from, string $to): array
    {
        $dates = [];
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);
        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }
        return $dates;
    }

    protected function worstStatus(array $statuses): string
    {
        $order = [self::STATUS_GESPERRT, self::STATUS_BELEGT, self::STATUS_OPTIONEN, self::STATUS_FREI];
        foreach ($order as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }
        return self::STATUS_FREI;
    }

    /**
     * Datum eines Events-Bookings als YYYY-MM-DD (datum kann String oder Date sein).
     */
    protected function bookingDate(object $booking): string
    {
        $datum = $booking->datum ?? null;
        if ($datum instanceof \DateTimeInterface) {
            return $datum->format('Y-m-d');
        }
        return substr((string) $datum, 0, 10);
    }
}
