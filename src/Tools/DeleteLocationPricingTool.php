<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationPricing;

class DeleteLocationPricingTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.pricings.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /locations/pricings/{id} - Loescht einen Mietpreis (Soft Delete). Identifikation: pricing_id ODER uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pricing_id' => ['type' => 'integer'],
                'uuid'       => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User.');
            }

            $query = LocationPricing::query();
            if (!empty($arguments['pricing_id'])) {
                $query->where('id', (int) $arguments['pricing_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', (string) $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'pricing_id oder uuid noetig.');
            }

            $pricing = $query->with('location')->first();
            if (!$pricing) {
                return ToolResult::error('PRICING_NOT_FOUND', 'Nicht gefunden.');
            }

            $teamId = $pricing->location?->team_id;
            $hasAccess = $teamId && $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            $pricing->delete();

            return ToolResult::success([
                'id'      => $pricing->id,
                'uuid'    => $pricing->uuid,
                'message' => "Pricing '{$pricing->day_type_label}' geloescht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['locations', 'pricings', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
            'side_effects' => ['deletes'],
        ];
    }
}
