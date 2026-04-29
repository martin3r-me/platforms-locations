<?php

namespace Platform\Locations\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Create: mehrere Locations in einem Call anlegen.
 *
 * REST-Idee: POST /locations/bulk
 */
class BulkCreateLocationsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.locations.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /locations/bulk - Body MUSS {locations:[{name,kuerzel,...}], team_id?, defaults?} enthalten. Erstellt mehrere Locations. team_id kann top-level, in defaults oder pro Item gesetzt werden. atomic=true (default): alles oder nichts.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden alle Creates in einer DB-Transaktion ausgeführt. Standard: true.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID für alle Locations (wird als Default verwendet, überschreibbar pro Item oder via defaults).',
                ],
                'defaults' => [
                    'type' => 'object',
                    'description' => 'Optional: Default-Werte, die auf jedes Item angewendet werden (können pro Item überschrieben werden).',
                    'properties' => [
                        'team_id' => ['type' => 'integer'],
                        'gruppe' => ['type' => 'string'],
                        'mehrfachbelegung' => ['type' => 'boolean'],
                        'adresse' => ['type' => 'string'],
                        'barrierefrei' => ['type' => 'boolean'],
                    ],
                ],
                'locations' => [
                    'type' => 'array',
                    'description' => 'Liste von Locations. Pflichtfelder pro Item: name, kuerzel. Akzeptiert die volle Felder-Palette von CreateLocationTool.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'kuerzel' => ['type' => 'string'],
                            'team_id' => ['type' => 'integer'],
                            'gruppe' => ['type' => 'string'],
                            'pax_min' => ['type' => 'integer'],
                            'pax_max' => ['type' => 'integer', 'description' => 'Max. Kapazität (inkl. Personal).'],
                            'mehrfachbelegung' => ['type' => 'boolean'],
                            'adresse' => ['type' => 'string'],
                            'groesse_qm' => ['type' => 'number', 'description' => 'Größe in Quadratmetern.'],
                            'hallennummer' => ['type' => 'string', 'description' => 'Hallennummer / interne Kennung (max 30).'],
                            'barrierefrei' => ['type' => 'boolean'],
                            'besonderheit' => ['type' => 'string', 'description' => 'Kurze Hervorhebung (1-2 Sätze).'],
                            'beschreibung' => ['type' => 'string', 'description' => 'Langer Marketing-/Historie-/Kundeninfo-Text.'],
                            'anlaesse' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['name', 'kuerzel'],
                    ],
                ],
            ],
            'required' => ['locations'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $locations = $arguments['locations'] ?? null;
            if (!is_array($locations) || empty($locations)) {
                return ToolResult::error('INVALID_ARGUMENT', 'locations muss ein nicht-leeres Array sein.');
            }

            $defaults = $arguments['defaults'] ?? [];
            if (!is_array($defaults)) {
                $defaults = [];
            }

            if (isset($arguments['team_id']) && !array_key_exists('team_id', $defaults)) {
                $defaults['team_id'] = $arguments['team_id'];
            }

            $atomic = (bool) ($arguments['atomic'] ?? true);
            $singleTool = new CreateLocationTool();

            $run = function () use ($locations, $defaults, $singleTool, $context, $atomic) {
                $results   = [];
                $okCount   = 0;
                $failCount = 0;

                foreach ($locations as $idx => $item) {
                    if (!is_array($item)) {
                        $failCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok'    => false,
                            'error' => ['code' => 'INVALID_ITEM', 'message' => 'Location-Item muss ein Objekt sein.'],
                        ];

                        if ($atomic) {
                            throw new \RuntimeException(json_encode([
                                'code'         => 'BULK_VALIDATION_ERROR',
                                'message'      => "Location an Index {$idx}: Item muss ein Objekt sein.",
                                'failed_index' => $idx,
                                'results'      => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                        continue;
                    }

                    $payload = $defaults;
                    foreach ($item as $k => $v) {
                        $payload[$k] = $v;
                    }

                    $res = $singleTool->execute($payload, $context);
                    if ($res->success) {
                        $okCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok'    => true,
                            'data'  => $res->data,
                        ];
                    } else {
                        $failCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok'    => false,
                            'error' => [
                                'code'    => $res->errorCode,
                                'message' => $res->error,
                            ],
                        ];

                        if ($atomic) {
                            $name = $item['name'] ?? '(kein Name)';
                            throw new \RuntimeException(json_encode([
                                'code'          => 'BULK_VALIDATION_ERROR',
                                'message'       => "Location an Index {$idx} ('{$name}'): {$res->error}",
                                'failed_index'  => $idx,
                                'error_code'    => $res->errorCode,
                                'error_message' => $res->error,
                                'results'       => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }

                return [
                    'results' => $results,
                    'summary' => [
                        'requested' => count($locations),
                        'ok'        => $okCount,
                        'failed'    => $failCount,
                    ],
                ];
            };

            if ($atomic) {
                try {
                    $payload = DB::transaction(fn() => $run());
                } catch (\RuntimeException $e) {
                    $errorData = json_decode($e->getMessage(), true);
                    if (is_array($errorData) && isset($errorData['code'])) {
                        return ToolResult::error($errorData['code'], $errorData['message']);
                    }
                    throw $e;
                }
            } else {
                $payload = $run();
            }

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Create der Locations: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'bulk',
            'tags'          => ['locations', 'location', 'bulk', 'batch', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'medium',
            'idempotent'    => false,
        ];
    }
}
