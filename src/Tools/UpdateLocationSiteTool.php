<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationSite;

class UpdateLocationSiteTool implements ToolContract, ToolMetadataContract
{
    /** @var array<int,string> */
    protected const UPDATABLE_FIELDS = [
        'name', 'description',
        'street', 'street_number', 'postal_code', 'city', 'state', 'country', 'country_code',
        'latitude', 'longitude', 'timezone', 'is_international',
        'phone', 'email', 'website', 'notes', 'done', 'sort_order',
    ];

    public function getName(): string
    {
        return 'locations.sites.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /locations/sites/{id} - Aktualisiert eine LocationSite. Identifikation per site_id ODER uuid. Aktualisierbare Felder: name, description (langer Marketing-/Historie-Text fuer Booklet-Einleitung), street, street_number, postal_code, city, state, country, country_code, latitude, longitude, timezone, is_international, phone, email, website, notes, done, sort_order. Nur uebergebene Felder werden geschrieben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'site_id'          => ['type' => 'integer', 'description' => 'ID der Site. Alternative zu uuid.'],
                'uuid'             => ['type' => 'string',  'description' => 'UUID der Site. Alternative zu site_id.'],
                'name'             => ['type' => 'string'],
                'description'      => ['type' => 'string', 'description' => 'Langer Marketing-/Historie-Text, erscheint im Booklet als Einleitungs-Sektion fuer jede zugeordnete Location.'],
                'street'           => ['type' => 'string'],
                'street_number'    => ['type' => 'string'],
                'postal_code'      => ['type' => 'string'],
                'city'             => ['type' => 'string'],
                'state'            => ['type' => 'string'],
                'country'          => ['type' => 'string'],
                'country_code'     => ['type' => 'string'],
                'latitude'         => ['type' => 'number'],
                'longitude'        => ['type' => 'number'],
                'timezone'         => ['type' => 'string'],
                'is_international' => ['type' => 'boolean'],
                'phone'            => ['type' => 'string'],
                'email'            => ['type' => 'string'],
                'website'          => ['type' => 'string'],
                'notes'            => ['type' => 'string'],
                'done'             => ['type' => 'boolean'],
                'sort_order'       => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = LocationSite::query();
            if (!empty($arguments['site_id'])) {
                $query->where('id', (int) $arguments['site_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', (string) $arguments['uuid']);
            } else {
                return ToolResult::error('INVALID_ARGUMENT', 'Entweder site_id oder uuid muss angegeben werden.');
            }

            $site = $query->first();
            if (!$site) {
                return ToolResult::error('SITE_NOT_FOUND', 'Die angegebene Site wurde nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $site->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Site.');
            }

            $update = [];
            foreach (self::UPDATABLE_FIELDS as $field) {
                if (array_key_exists($field, $arguments)) {
                    $update[$field] = $arguments[$field];
                }
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine aktualisierbaren Felder uebergeben.');
            }

            $site->update($update);
            $site->refresh();

            $known = array_merge(self::UPDATABLE_FIELDS, ['site_id', 'uuid']);
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            return ToolResult::success([
                'id'             => $site->id,
                'uuid'           => $site->uuid,
                'name'           => $site->name,
                'description'    => $site->description,
                'updated_fields' => array_keys($update),
                'ignored_fields' => $ignored,
                'message'        => "Site '{$site->name}' aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Site: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['locations', 'sites', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'low',
        ];
    }
}
