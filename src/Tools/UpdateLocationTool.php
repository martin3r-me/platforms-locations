<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\Location;

/**
 * Aktualisiert eine Location. Nur übergebene Felder werden geändert.
 */
class UpdateLocationTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.locations.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /locations/{id} - Aktualisiert eine Location. REST-Parameter: location_id (integer) ODER uuid (string) - mindestens einer. Übrige Felder optional: name, kuerzel, gruppe, pax_min, pax_max, mehrfachbelegung, adresse, sort_order. Nur übergebene Werte werden geändert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Location. Alternative zu uuid.',
                ],
                'uuid' => [
                    'type' => 'string',
                    'description' => 'UUID der Location. Alternative zu location_id.',
                ],
                'name' => ['type' => 'string', 'description' => 'Optional: Neuer Name.'],
                'kuerzel' => ['type' => 'string', 'description' => 'Optional: Neues Kürzel (max. 20).'],
                'gruppe' => ['type' => 'string', 'description' => 'Optional: Neue Gruppe.'],
                'pax_min' => ['type' => 'integer', 'description' => 'Optional: Neue Mindestbelegung.'],
                'pax_max' => ['type' => 'integer', 'description' => 'Optional: Neue Kapazität.'],
                'mehrfachbelegung' => ['type' => 'boolean', 'description' => 'Optional: Mehrfachbelegung erlaubt ja/nein.'],
                'adresse' => ['type' => 'string', 'description' => 'Optional: Neue Adresse.'],
                'sort_order' => ['type' => 'integer', 'description' => 'Optional: Sortierreihenfolge.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = Location::query();
            if (!empty($arguments['location_id'])) {
                $query->where('id', (int) $arguments['location_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'location_id oder uuid ist erforderlich.');
            }

            $location = $query->first();
            if (!$location) {
                return ToolResult::error('LOCATION_NOT_FOUND', 'Die angegebene Location wurde nicht gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', $location->team_id)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Location.');
            }

            $update = [];
            foreach (['name', 'gruppe', 'adresse'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $update[$field] = $arguments[$field];
                }
            }
            if (array_key_exists('kuerzel', $arguments)) {
                $update['kuerzel'] = mb_substr((string) $arguments['kuerzel'], 0, 20);
            }
            foreach (['pax_min', 'pax_max', 'sort_order'] as $field) {
                if (array_key_exists($field, $arguments) && $arguments[$field] !== null) {
                    $update[$field] = (int) $arguments[$field];
                } elseif (array_key_exists($field, $arguments)) {
                    $update[$field] = null;
                }
            }
            if (array_key_exists('mehrfachbelegung', $arguments)) {
                $update['mehrfachbelegung'] = (bool) $arguments['mehrfachbelegung'];
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $location->update($update);

            return ToolResult::success([
                'id'               => $location->id,
                'uuid'             => $location->uuid,
                'name'             => $location->name,
                'kuerzel'          => $location->kuerzel,
                'gruppe'           => $location->gruppe,
                'pax_min'          => $location->pax_min,
                'pax_max'          => $location->pax_max,
                'mehrfachbelegung' => (bool) $location->mehrfachbelegung,
                'adresse'          => $location->adresse,
                'sort_order'       => $location->sort_order,
                'team_id'          => $location->team_id,
                'message'          => "Location '{$location->name}' erfolgreich aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Location: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['locations', 'location', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
