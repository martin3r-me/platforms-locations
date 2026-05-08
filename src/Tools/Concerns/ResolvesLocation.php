<?php

namespace Platform\Locations\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Locations\Models\Location;

/**
 * Hilfs-Trait fuer Sub-Entity-Tools (Pricing/Seating/Addons), die
 * eine Eltern-Location ueber location_id, location_uuid, location_kuerzel
 * oder generisches location_ref aufloesen.
 *
 * Konflikt-Strategie: werden mehrere Identifikationsfelder gesendet
 * (z.B. location_id und location_kuerzel), MUESSEN sie auf dieselbe
 * Location zeigen — sonst VALIDATION_ERROR. Kein Silent-Pick.
 *
 * Nutzung:
 *   [$loc, $err] = $this->resolveLocation($arguments, $context);
 *   if ($err) return $err;
 *   $aliases = $this->resolvedLocationAliases();  // fuer aliases_applied
 */
trait ResolvesLocation
{
    /** @var array<int,string> */
    private array $resolvedLocationAliases = [];

    /**
     * Schema-Fragmente fuer die vier akzeptierten Location-Identifikatoren.
     * Tools koennen das per Spread in $schema['properties'] mergen.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function locationRefSchemaFields(): array
    {
        return [
            'location_id' => [
                'type'        => 'integer',
                'description' => 'Location-ID. Alternative zu location_uuid / location_kuerzel / location_ref.',
            ],
            'location_uuid' => [
                'type'        => 'string',
                'description' => 'Location-UUID. Alternative zu location_id / location_kuerzel / location_ref.',
            ],
            'location_kuerzel' => [
                'type'        => 'string',
                'description' => 'Location-Kuerzel (per Team eindeutig, TRIM+UPPER). Benoetigt team_id-Kontext. Alternative zu location_id / location_uuid / location_ref.',
            ],
            'location_ref' => [
                'description' => 'Generischer Resolver: numerisch -> ID, UUID-Format -> uuid, sonst -> kuerzel. Bei Mehrfach-Identifikatoren muessen alle auf dieselbe Location zeigen, sonst VALIDATION_ERROR.',
            ],
        ];
    }

    /**
     * Felder, die als bekannt gelten (fuer ignored_fields-Diff).
     *
     * @return array<int,string>
     */
    public static function locationRefKnownFields(): array
    {
        return ['location_id', 'location_uuid', 'location_kuerzel', 'location_ref'];
    }

    /**
     * Liefert die zuletzt durch resolveLocation() angewandten Aliases
     * (z.B. ["location_kuerzel:'KSH'→location_id:16"]). Leeres Array, wenn ueber
     * location_id aufgeloest wurde oder kein Resolve stattfand.
     *
     * @return array<int,string>
     */
    protected function resolvedLocationAliases(): array
    {
        return $this->resolvedLocationAliases;
    }

    /**
     * @return array{0: ?Location, 1: ?ToolResult}
     */
    protected function resolveLocation(array $arguments, ToolContext $context): array
    {
        $this->resolvedLocationAliases = [];

        if (!$context->user) {
            return [null, ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.')];
        }

        $teamCtxId = $arguments['team_id'] ?? $context->team?->id;
        $teamCtxId = $teamCtxId ? (int) $teamCtxId : null;

        // Pro Identifikations-Feld eine Location aufloesen, dann auf Konsistenz pruefen.
        // Reihenfolge der Aliases-Reportage entspricht der Eingabereihenfolge.
        $candidates = [];

        if (!empty($arguments['location_id'])) {
            $loc = Location::query()->where('id', (int) $arguments['location_id'])->first();
            $candidates[] = ['field' => 'location_id', 'input' => (int) $arguments['location_id'], 'location' => $loc, 'matched_by' => 'id'];
        }

        if (!empty($arguments['location_uuid'])) {
            $loc = Location::query()->where('uuid', (string) $arguments['location_uuid'])->first();
            $candidates[] = ['field' => 'location_uuid', 'input' => (string) $arguments['location_uuid'], 'location' => $loc, 'matched_by' => 'uuid'];
        }

        if (!empty($arguments['location_kuerzel'])) {
            if ($teamCtxId === null) {
                return [null, ToolResult::error(
                    'MISSING_TEAM',
                    'Bei location_kuerzel ist ein team_id-Kontext erforderlich (kuerzel ist nur per Team eindeutig).'
                )];
            }
            $loc = Location::resolveByKuerzel((string) $arguments['location_kuerzel'], $teamCtxId);
            $candidates[] = ['field' => 'location_kuerzel', 'input' => (string) $arguments['location_kuerzel'], 'location' => $loc, 'matched_by' => 'kuerzel'];
        }

        if (!empty($arguments['location_ref'])) {
            $ref = $arguments['location_ref'];
            $needsTeamFor = (is_string($ref) && !preg_match('/^\d+$/', $ref) && !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $ref));
            if ($needsTeamFor && $teamCtxId === null) {
                return [null, ToolResult::error(
                    'MISSING_TEAM',
                    'location_ref als Kuerzel benoetigt einen team_id-Kontext (kuerzel ist nur per Team eindeutig).'
                )];
            }
            $resolved = Location::resolveRef($ref, $teamCtxId);
            $candidates[] = [
                'field'      => 'location_ref',
                'input'      => is_int($ref) ? $ref : (string) $ref,
                'location'   => $resolved['location'],
                'matched_by' => $resolved['matched_by'],
            ];
        }

        if ($candidates === []) {
            return [null, ToolResult::error(
                'VALIDATION_ERROR',
                'location_id, location_uuid, location_kuerzel oder location_ref ist erforderlich.'
            )];
        }

        // Mind. ein Feld muss aufgeloest worden sein.
        $resolvedIds = collect($candidates)
            ->filter(fn ($c) => $c['location'] !== null)
            ->map(fn ($c) => $c['location']->id)
            ->unique()
            ->values()
            ->all();

        if ($resolvedIds === []) {
            $detail = collect($candidates)
                ->map(fn ($c) => "{$c['field']}='{$c['input']}'")
                ->implode(', ');
            $msg = "Die angegebene Location wurde nicht gefunden ({$detail}).";

            // Komfort: bei Kuerzel-aehnlichem Input die bekannten Kuerzel des Teams anhaengen.
            $hasKuerzelLikeInput = false;
            foreach ($candidates as $c) {
                if ($c['field'] === 'location_kuerzel') {
                    $hasKuerzelLikeInput = true;
                    break;
                }
                if ($c['field'] === 'location_ref') {
                    $v = $c['input'];
                    if (is_string($v)
                        && !preg_match('/^\d+$/', $v)
                        && !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $v)
                    ) {
                        $hasKuerzelLikeInput = true;
                        break;
                    }
                }
            }
            if ($hasKuerzelLikeInput && $teamCtxId !== null) {
                $known = Location::knownKuerzel($teamCtxId);
                if ($known !== []) {
                    $msg .= "\nBekannte Kuerzel: " . implode(', ', $known);
                }
            }

            return [null, ToolResult::error('LOCATION_NOT_FOUND', $msg)];
        }

        // Konflikt: mehrere Felder zeigen auf unterschiedliche Locations.
        if (count($resolvedIds) > 1) {
            $detail = collect($candidates)
                ->map(function ($c) {
                    $id = $c['location']?->id ?? 'null';
                    return "{$c['field']}={$c['input']}->id={$id}";
                })
                ->implode(', ');
            return [null, ToolResult::error(
                'VALIDATION_ERROR',
                "Konflikt: mehrere Location-Identifikatoren wurden gesendet, die auf unterschiedliche Locations zeigen ({$detail}). Bitte nur einen Identifikator senden oder konsistente Werte."
            )];
        }

        // Konflikt: ein Feld konnte nicht aufgeloest werden, andere schon — ebenfalls inkonsistent.
        $unresolvedFields = collect($candidates)
            ->filter(fn ($c) => $c['location'] === null)
            ->map(fn ($c) => "{$c['field']}={$c['input']}")
            ->all();
        if ($unresolvedFields !== []) {
            return [null, ToolResult::error(
                'VALIDATION_ERROR',
                'Konflikt: Identifikatoren ' . implode(', ', $unresolvedFields) .
                ' liefen ins Leere, andere wurden aber aufgeloest. Bitte konsistente Werte oder nur einen Identifikator senden.'
            )];
        }

        $location = $candidates[0]['location'];

        $hasAccess = $context->user->teams()->where('teams.id', $location->team_id)->exists();
        if (!$hasAccess) {
            return [null, ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Location.')];
        }

        // Aliases reporten — nur Felder, die ein Mapping noetig hatten.
        foreach ($candidates as $c) {
            if (in_array($c['field'], ['location_kuerzel'], true)) {
                $this->resolvedLocationAliases[] = "{$c['field']}:'{$c['input']}'→location_id:{$location->id}";
            } elseif ($c['field'] === 'location_ref' && $c['matched_by'] !== 'id') {
                $this->resolvedLocationAliases[] = "location_ref:'{$c['input']}'→location_id:{$location->id}";
            } elseif ($c['field'] === 'location_uuid') {
                $this->resolvedLocationAliases[] = "location_uuid:'{$c['input']}'→location_id:{$location->id}";
            }
        }

        return [$location, null];
    }
}
