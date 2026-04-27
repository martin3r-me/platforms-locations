<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationPricing;

class UpdateLocationPricingTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.pricings.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /locations/pricings/{id} - Aktualisiert einen Mietpreis-Eintrag. Identifikation: pricing_id ODER uuid. Felder: day_type_label, price_net, label, article_number (Events-Artikelstamm-Verknuepfung), sort_order (alle optional, nur uebergebene werden geaendert).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pricing_id'     => ['type' => 'integer'],
                'uuid'           => ['type' => 'string'],
                'day_type_label' => ['type' => 'string'],
                'price_net'      => ['type' => 'number'],
                'label'          => ['type' => 'string'],
                'article_number' => ['type' => 'string', 'description' => 'Artikelnummer aus dem Events-Stamm. Leerstring oder null entfernt die Verknuepfung.'],
                'sort_order'     => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $query = LocationPricing::query();
            if (!empty($arguments['pricing_id'])) {
                $query->where('id', (int) $arguments['pricing_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', (string) $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'pricing_id oder uuid ist erforderlich.');
            }

            /** @var LocationPricing|null $pricing */
            $pricing = $query->with('location')->first();
            if (!$pricing) {
                return ToolResult::error('PRICING_NOT_FOUND', 'Pricing nicht gefunden.');
            }

            $teamId = $pricing->location?->team_id;
            $hasAccess = $teamId && $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diese Location.');
            }

            $update = [];
            if (array_key_exists('day_type_label', $arguments)) {
                $update['day_type_label'] = trim((string) $arguments['day_type_label']);
            }
            if (array_key_exists('price_net', $arguments) && $arguments['price_net'] !== null && $arguments['price_net'] !== '') {
                $update['price_net'] = (float) $arguments['price_net'];
            }
            if (array_key_exists('label', $arguments)) {
                $update['label'] = $arguments['label'] !== '' ? (string) $arguments['label'] : null;
            }
            if (array_key_exists('article_number', $arguments)) {
                $update['article_number'] = $arguments['article_number'] !== '' && $arguments['article_number'] !== null
                    ? mb_substr((string) $arguments['article_number'], 0, 30)
                    : null;
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int) $arguments['sort_order'];
            }
            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren.');
            }

            $pricing->update($update);

            return ToolResult::success([
                'id'             => $pricing->id,
                'uuid'           => $pricing->uuid,
                'location_id'    => $pricing->location_id,
                'day_type_label' => $pricing->day_type_label,
                'price_net'      => (float) $pricing->price_net,
                'label'          => $pricing->label,
                'article_number' => $pricing->article_number,
                'sort_order'     => (int) $pricing->sort_order,
                'message'        => 'Pricing aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['locations', 'pricings', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
            'side_effects' => ['updates'],
        ];
    }
}
