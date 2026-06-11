<?php

namespace Platform\Locations\Tools;

use Illuminate\Support\Carbon;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Services\AvailabilityService;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

/**
 * Prueft die Verfuegbarkeit einer Location fuer ein Datum oder einen Zeitraum.
 * Beruecksichtigt Sperrzeiten (locations_blockings) und — falls das
 * Events-Modul installiert ist — Raumbuchungen (events_bookings).
 */
class CheckLocationAvailabilityTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;

    /** Maximale Zeitraum-Laenge in Tagen (Schutz vor Riesen-Ranges). */
    public const MAX_RANGE_DAYS = 366;

    public function getName(): string
    {
        return 'locations.availability.CHECK';
    }

    public function getDescription(): string
    {
        return 'GET /locations/{location_id}/availability - Prueft, ob eine Location an einem Datum oder in einem Zeitraum verfuegbar ist. Status pro Tag: frei | optionen (parallel buchbare Optionen vorhanden) | belegt (Definitiv/Vertrag) | gesperrt (Sperrzeit). Identifikation: location_id, location_uuid, location_kuerzel oder location_ref.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge(self::locationRefSchemaFields(), [
                'date' => [
                    'type'        => 'string',
                    'description' => 'Datum (YYYY-MM-DD). Pflicht. Bei Zeitraum: Startdatum.',
                ],
                'end_date' => [
                    'type'        => 'string',
                    'description' => 'Optionales Enddatum (YYYY-MM-DD, inklusive) fuer Zeitraum-Pruefung.',
                ],
            ]),
            'required' => ['date'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) {
                return $err;
            }

            $date = (string) ($arguments['date'] ?? '');
            $endDate = isset($arguments['end_date']) ? (string) $arguments['end_date'] : null;

            $rangeError = self::validateRange($date, $endDate);
            if ($rangeError) {
                return $rangeError;
            }

            $result = app(AvailabilityService::class)->check($location, $date, $endDate);

            $statusText = match ($result['status']) {
                AvailabilityService::STATUS_FREI     => 'frei',
                AvailabilityService::STATUS_OPTIONEN => 'frei (es liegen Optionen anderer Events an)',
                AvailabilityService::STATUS_BELEGT   => 'belegt (Definitiv/Vertrag)',
                AvailabilityService::STATUS_GESPERRT => 'gesperrt (Sperrzeit)',
                default => $result['status'],
            };

            return ToolResult::success([
                'location' => [
                    'id'               => $location->id,
                    'uuid'             => $location->uuid,
                    'name'             => $location->name,
                    'kuerzel'          => $location->kuerzel,
                    'pax_min'          => $location->pax_min,
                    'pax_max'          => $location->pax_max,
                    'mehrfachbelegung' => (bool) $location->mehrfachbelegung,
                ],
                'status'          => $result['status'],
                'available'       => $result['available'],
                'days'            => $result['days'],
                'aliases_applied' => $this->resolvedLocationAliases(),
                'message'         => "Location '{$location->name}' ist im angefragten Zeitraum {$statusText}.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Verfuegbarkeits-Check: ' . $e->getMessage());
        }
    }

    /**
     * Gemeinsame Datums-/Range-Validierung fuer die Availability-Tools.
     */
    public static function validateRange(string $date, ?string $endDate): ?ToolResult
    {
        try {
            $from = Carbon::parse($date);
            $to = $endDate !== null && $endDate !== '' ? Carbon::parse($endDate) : $from;
        } catch (\Throwable $e) {
            return ToolResult::error('VALIDATION_ERROR', 'Ungueltiges Datum — erwartet wird YYYY-MM-DD.');
        }

        if (abs($from->diffInDays($to)) > self::MAX_RANGE_DAYS) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                'Zeitraum zu gross — maximal ' . self::MAX_RANGE_DAYS . ' Tage pro Abfrage.'
            );
        }

        return null;
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['locations', 'availability', 'booking', 'check'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
            'side_effects'  => [],
        ];
    }
}
