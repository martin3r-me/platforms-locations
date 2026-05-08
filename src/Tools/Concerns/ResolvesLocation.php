<?php

namespace Platform\Locations\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Locations\Models\Location;

/**
 * Hilfs-Trait fuer Sub-Entity-Tools (Pricing/Seating/Addons), die
 * eine Eltern-Location ueber location_id, location_uuid ODER
 * location_kuerzel aufloesen.
 *
 * Nutzung:
 *   [$loc, $err] = $this->resolveLocation($arguments, $context);
 *   if ($err) return $err;
 *   $aliases = $this->resolvedLocationAliases();
 */
trait ResolvesLocation
{
    /** @var array<int,string> */
    private array $resolvedLocationAliases = [];

    /**
     * Liefert die zuletzt durch resolveLocation() angewandten Aliases
     * (z.B. ["location_kuerzel→location_id:16"]). Leeres Array, wenn ueber
     * id/uuid aufgeloest wurde.
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

        $location = null;

        if (!empty($arguments['location_id'])) {
            $location = Location::query()->where('id', (int) $arguments['location_id'])->first();
        } elseif (!empty($arguments['location_uuid'])) {
            $location = Location::query()->where('uuid', (string) $arguments['location_uuid'])->first();
        } elseif (!empty($arguments['location_kuerzel'])) {
            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return [null, ToolResult::error(
                    'MISSING_TEAM',
                    'Bei location_kuerzel ist ein team_id-Kontext erforderlich (kuerzel ist nur per Team eindeutig).'
                )];
            }
            $location = Location::resolveByKuerzel((string) $arguments['location_kuerzel'], (int) $teamId);
            if ($location) {
                $this->resolvedLocationAliases[] = "location_kuerzel→location_id:{$location->id}";
            }
        } else {
            return [null, ToolResult::error(
                'VALIDATION_ERROR',
                'location_id, location_uuid oder location_kuerzel ist erforderlich.'
            )];
        }

        if (!$location) {
            return [null, ToolResult::error('LOCATION_NOT_FOUND', 'Die angegebene Location wurde nicht gefunden.')];
        }

        $hasAccess = $context->user->teams()->where('teams.id', $location->team_id)->exists();
        if (!$hasAccess) {
            return [null, ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Location.')];
        }

        return [$location, null];
    }
}
