<?php

namespace Platform\Locations\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Locations\Models\Location;

/**
 * Hilfs-Trait fuer Sub-Entity-Tools (Pricing/Seating/Addons), die
 * eine Eltern-Location ueber location_id ODER location_uuid aufloesen.
 *
 * Nutzung:
 *   [$loc, $err] = $this->resolveLocation($arguments, $context);
 *   if ($err) return $err;
 */
trait ResolvesLocation
{
    /**
     * @return array{0: ?Location, 1: ?ToolResult}
     */
    protected function resolveLocation(array $arguments, ToolContext $context): array
    {
        if (!$context->user) {
            return [null, ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.')];
        }

        $query = Location::query();
        if (!empty($arguments['location_id'])) {
            $query->where('id', (int) $arguments['location_id']);
        } elseif (!empty($arguments['location_uuid'])) {
            $query->where('uuid', (string) $arguments['location_uuid']);
        } else {
            return [null, ToolResult::error('VALIDATION_ERROR', 'location_id oder location_uuid ist erforderlich.')];
        }

        $location = $query->first();
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
