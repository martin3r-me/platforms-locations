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
        return 'POST /locations - Erstellt eine neue Location. REST-Parameter: name (required), kuerzel (required, max 20), team_id (optional), gruppe (optional), pax_min (optional), pax_max (optional, gemeint als max inkl. Personal), mehrfachbelegung (optional, default false), adresse (optional), groesse_qm (optional, decimal), hallennummer (optional), barrierefrei (optional, boolean, default false), besonderheit (optional, string), anlaesse (optional, array of strings z.B. ["Hochzeit","Firmenfeier"]).';
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
                'groesse_qm' => [
                    'type' => 'number',
                    'description' => 'Optional: Größe der Location in Quadratmetern.',
                ],
                'hallennummer' => [
                    'type' => 'string',
                    'description' => 'Optional: Hallennummer / interne Kennung (max. 30 Zeichen).',
                ],
                'barrierefrei' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ist die Location barrierefrei zugänglich? Default false.',
                ],
                'besonderheit' => [
                    'type' => 'string',
                    'description' => 'Optional: Besonderheiten (Freitext, z.B. "3 verfahrbare Kronleuchter").',
                ],
                'anlaesse' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Liste von geeigneten Anlässen (z.B. ["Hochzeit","Firmenfeier","Tagung"]).',
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

            $anlaesse = null;
            if (array_key_exists('anlaesse', $arguments) && is_array($arguments['anlaesse'])) {
                $anlaesse = collect($arguments['anlaesse'])
                    ->map(fn ($v) => is_string($v) ? trim($v) : null)
                    ->filter(fn ($v) => $v !== null && $v !== '')
                    ->values()
                    ->all();
                if ($anlaesse === []) {
                    $anlaesse = null;
                }
            }

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
                'groesse_qm'       => isset($arguments['groesse_qm']) ? (float) $arguments['groesse_qm'] : null,
                'hallennummer'     => isset($arguments['hallennummer']) ? mb_substr((string) $arguments['hallennummer'], 0, 30) : null,
                'barrierefrei'     => (bool) ($arguments['barrierefrei'] ?? false),
                'besonderheit'     => $arguments['besonderheit'] ?? null,
                'anlaesse'         => $anlaesse,
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
                'groesse_qm'       => $location->groesse_qm !== null ? (float) $location->groesse_qm : null,
                'hallennummer'     => $location->hallennummer,
                'barrierefrei'     => (bool) $location->barrierefrei,
                'besonderheit'     => $location->besonderheit,
                'anlaesse'         => $location->anlaesse,
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
