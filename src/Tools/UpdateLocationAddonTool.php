<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationAddon;

class UpdateLocationAddonTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.addons.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /locations/addons/{id} - Aktualisiert ein Add-on. Identifikation: addon_id ODER uuid. Felder: label, price_net, unit, article_number (Events-Artikelstamm-Verknuepfung), is_active, sort_order (alle optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'addon_id'       => ['type' => 'integer'],
                'uuid'           => ['type' => 'string'],
                'label'          => ['type' => 'string'],
                'price_net'      => ['type' => 'number'],
                'unit'           => ['type' => 'string', 'enum' => LocationAddon::UNITS],
                'article_number' => ['type' => 'string', 'description' => 'Artikelnummer aus dem Events-Stamm. Leerstring oder null entfernt die Verknuepfung.'],
                'is_active'      => ['type' => 'boolean'],
                'sort_order'     => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) return ToolResult::error('AUTH_ERROR', 'Kein User.');

            $query = LocationAddon::query();
            if (!empty($arguments['addon_id'])) {
                $query->where('id', (int) $arguments['addon_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', (string) $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'addon_id oder uuid noetig.');
            }

            $row = $query->with('location')->first();
            if (!$row) return ToolResult::error('ADDON_NOT_FOUND', 'Nicht gefunden.');

            $teamId = $row->location?->team_id;
            $hasAccess = $teamId && $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$hasAccess) return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');

            $update = [];
            if (array_key_exists('label', $arguments)) {
                $update['label'] = trim((string) $arguments['label']);
            }
            if (array_key_exists('price_net', $arguments) && $arguments['price_net'] !== null && $arguments['price_net'] !== '') {
                $update['price_net'] = (float) $arguments['price_net'];
            }
            if (array_key_exists('unit', $arguments)) {
                $unit = (string) $arguments['unit'];
                if (!in_array($unit, LocationAddon::UNITS, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'unit ungueltig.');
                }
                $update['unit'] = $unit;
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }
            if (array_key_exists('article_number', $arguments)) {
                $update['article_number'] = $arguments['article_number'] !== '' && $arguments['article_number'] !== null
                    ? mb_substr((string) $arguments['article_number'], 0, 30)
                    : null;
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int) $arguments['sort_order'];
            }
            if (empty($update)) return ToolResult::error('VALIDATION_ERROR', 'Keine Felder.');

            $row->update($update);

            return ToolResult::success([
                'id' => $row->id, 'uuid' => $row->uuid, 'location_id' => $row->location_id,
                'label' => $row->label, 'price_net' => (float) $row->price_net,
                'unit' => $row->unit, 'unit_label' => $row->unitLabel(),
                'article_number' => $row->article_number,
                'is_active' => (bool) $row->is_active, 'sort_order' => (int) $row->sort_order,
                'message' => 'Add-on aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category'=>'action','tags'=>['locations','addons','update'],'read_only'=>false,'requires_auth'=>true,'requires_team'=>false,'risk_level'=>'write','idempotent'=>true,'side_effects'=>['updates']];
    }
}
