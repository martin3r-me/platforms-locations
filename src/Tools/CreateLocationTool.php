<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\Location;

/**
 * Erstellt eine neue Location (Raum/Standort).
 *
 * WICHTIG: Wenn der Nutzer nicht alle Pflichtfelder angibt, frage nach:
 * - name (erforderlich)
 * - kuerzel (erforderlich, max. 20 Zeichen)
 */
class CreateLocationTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.locations.POST';
    }

    public function getDescription(): string
    {
        return 'POST /locations - Erstellt eine neue Location. REST-Parameter: name (required, string), kuerzel (required, string, max 20), team_id (optional, integer) - sonst aktuelles Team, gruppe (optional, string), pax_min (optional, integer), pax_max (optional, integer), mehrfachbelegung (optional, boolean, default false), adresse (optional, string).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name der Location (ERFORDERLICH), z.B. "Großer Saal".',
                ],
                'kuerzel' => [
                    'type' => 'string',
                    'description' => 'Kürzel (ERFORDERLICH, max. 20 Zeichen), z.B. "GOH".',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Wenn nicht angegeben, wird das aktuelle Team aus dem Kontext verwendet.',
                ],
                'gruppe' => [
                    'type' => 'string',
                    'description' => 'Optional: Gruppierung (z.B. Gebäude).',
                ],
                'pax_min' => [
                    'type' => 'integer',
                    'description' => 'Optional: Minimale Personenzahl.',
                ],
                'pax_max' => [
                    'type' => 'integer',
                    'description' => 'Optional: Maximale Personenzahl / Kapazität.',
                ],
                'mehrfachbelegung' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Mehrfachbelegung an einem Tag erlaubt. Default false.',
                ],
                'adresse' => [
                    'type' => 'string',
                    'description' => 'Optional: Adresse (Straße, PLZ Ort).',
                ],
            ],
            'required' => ['name', 'kuerzel'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }
            if (empty($arguments['name'])) {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }
            if (empty($arguments['kuerzel'])) {
                return ToolResult::error('VALIDATION_ERROR', 'kuerzel ist erforderlich.');
            }

            $teamId = $arguments['team_id'] ?? null;
            if ($teamId === 0 || $teamId === '0') {
                $teamId = null;
            }
            if ($teamId === null) {
                $teamId = $context->team?->id;
            }
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', "Du hast keinen Zugriff auf Team-ID {$teamId}.");
            }

            $maxSort = Location::where('team_id', $teamId)->max('sort_order') ?? 0;

            $location = Location::create([
                'team_id'          => $teamId,
                'user_id'          => $context->user->id,
                'name'             => $arguments['name'],
                'kuerzel'          => mb_substr($arguments['kuerzel'], 0, 20),
                'gruppe'           => $arguments['gruppe'] ?? null,
                'pax_min'          => isset($arguments['pax_min']) ? (int) $arguments['pax_min'] : null,
                'pax_max'          => isset($arguments['pax_max']) ? (int) $arguments['pax_max'] : null,
                'mehrfachbelegung' => (bool) ($arguments['mehrfachbelegung'] ?? false),
                'adresse'          => $arguments['adresse'] ?? null,
                'sort_order'       => $maxSort + 1,
            ]);

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
                'message'          => "Location '{$location->name}' erfolgreich erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Location: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['locations', 'location', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
