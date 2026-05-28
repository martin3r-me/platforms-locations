<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationSite;

class CreateLocationSiteTool implements ToolContract, ToolMetadataContract
{
    /** @var array<int,string> */
    protected const KNOWN_FIELDS = [
        'name', 'description', 'team_id',
        'street', 'street_number', 'postal_code', 'city', 'state', 'country', 'country_code',
        'latitude', 'longitude', 'timezone', 'is_international',
        'phone', 'email', 'website', 'notes', 'sort_order',
    ];

    public function getName(): string
    {
        return 'locations.sites.POST';
    }

    public function getDescription(): string
    {
        return 'POST /locations/sites - Legt eine neue LocationSite an (Eltern-Container fuer Locations). ERFORDERLICH: name. OPTIONAL: description (langer Marketing-/Historie-Text, erscheint im Booklet als Einleitung), street/street_number/postal_code/city/state/country/country_code (Adresse), latitude/longitude (WGS84), timezone, is_international, phone, email, website, notes (interne Notizen), sort_order, team_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name'             => ['type' => 'string', 'description' => 'Name der Site (ERFORDERLICH), z.B. "Areal Boehler".'],
                'description'      => ['type' => 'string', 'description' => 'Optional: Langer Marketing-/Historie-/Lage-Text. Erscheint im Kunden-Booklet jeder zugeordneten Location als Einleitungs-Sektion.'],
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-ID, sonst aktuelles Team.'],
                'street'           => ['type' => 'string'],
                'street_number'    => ['type' => 'string'],
                'postal_code'      => ['type' => 'string'],
                'city'             => ['type' => 'string'],
                'state'            => ['type' => 'string'],
                'country'          => ['type' => 'string'],
                'country_code'     => ['type' => 'string', 'description' => 'ISO 3166-1 alpha-2.'],
                'latitude'         => ['type' => 'number'],
                'longitude'        => ['type' => 'number'],
                'timezone'         => ['type' => 'string'],
                'is_international' => ['type' => 'boolean'],
                'phone'            => ['type' => 'string'],
                'email'            => ['type' => 'string'],
                'website'          => ['type' => 'string'],
                'notes'            => ['type' => 'string'],
                'sort_order'       => ['type' => 'integer'],
            ],
            'required' => ['name'],
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

            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', "Du hast keinen Zugriff auf Team-ID {$teamId}.");
            }

            $payload = ['team_id' => $teamId, 'user_id' => $context->user->id];
            foreach (self::KNOWN_FIELDS as $field) {
                if ($field === 'team_id') continue;
                if (array_key_exists($field, $arguments)) {
                    $payload[$field] = $arguments[$field];
                }
            }

            $site = LocationSite::create($payload);

            $ignored = array_values(array_diff(array_keys($arguments), self::KNOWN_FIELDS));

            return ToolResult::success([
                'id'             => $site->id,
                'uuid'           => $site->uuid,
                'name'           => $site->name,
                'description'    => $site->description,
                'city'           => $site->city,
                'country_code'   => $site->country_code,
                'ignored_fields' => $ignored,
                'message'        => "Site '{$site->name}' erfolgreich angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der Site: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['locations', 'sites', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'low',
        ];
    }
}
