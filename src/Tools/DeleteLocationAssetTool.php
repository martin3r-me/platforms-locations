<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Locations\Services\LocationAssetService;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

class DeleteLocationAssetTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;

    public function getName(): string
    {
        return 'locations.assets.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /locations/{location_id}/assets/{category}/{filename} - Loescht eine konkrete Datei einer Asset-Kategorie aus dem Storage (kein DB-Eintrag, daher hart geloescht). Identifikation: location_id ODER location_uuid + category + filename (genauer Dateiname inkl. Endung, ohne Pfad).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id'   => ['type' => 'integer'],
                'location_uuid' => ['type' => 'string'],
                'category'      => [
                    'type' => 'string',
                    'enum' => array_keys(LocationAssetService::categories()),
                ],
                'filename'      => [
                    'type' => 'string',
                    'description' => 'Genauer Dateiname inkl. Endung. Aus locations.assets.GET uebernehmen.',
                ],
            ],
            'required' => ['category', 'filename'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) return $err;

            $cat = (string) ($arguments['category'] ?? '');
            $filename = (string) ($arguments['filename'] ?? '');
            if ($cat === '' || !LocationAssetService::isValidCategory($cat)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungueltige oder fehlende Kategorie.');
            }
            if ($filename === '') {
                return ToolResult::error('VALIDATION_ERROR', 'filename ist erforderlich.');
            }
            // Pfad-Traversal-Guard (entspricht Service-Logik)
            if (basename($filename) !== $filename) {
                return ToolResult::error('VALIDATION_ERROR', 'filename darf keinen Pfad enthalten.');
            }

            $service = app(LocationAssetService::class);
            $ok = $service->delete($location, $cat, $filename);

            if (!$ok) {
                return ToolResult::error('ASSET_NOT_FOUND', "Datei '{$filename}' in Kategorie '{$cat}' nicht gefunden oder konnte nicht geloescht werden.");
            }

            return ToolResult::success([
                'location_id' => $location->id,
                'category'    => $cat,
                'filename'    => $filename,
                'message'     => "Datei '{$filename}' aus '{$cat}' geloescht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['locations', 'assets', 'delete', 's3'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
            'side_effects' => ['deletes'],
        ];
    }
}
