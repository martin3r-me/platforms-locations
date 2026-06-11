<?php

namespace Platform\Locations\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Delete: mehrere Locations in einem Call löschen (Soft Delete).
 *
 * REST-Idee: DELETE /locations/bulk
 */
class BulkDeleteLocationsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.locations.bulk.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /locations/bulk - Löscht mehrere Locations (Soft Delete). Body: {"location_ids":[1,2]} ODER {"uuids":["..."]}. Mind. eines der beiden Felder ist erforderlich. atomic=true (default): alles oder nichts.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, wird alles in einer DB-Transaktion ausgeführt. Standard: true.',
                ],
                'location_ids' => [
                    'type' => 'array',
                    'description' => 'IDs der zu löschenden Locations. Alternative/ergänzend zu uuids.',
                    'items' => ['type' => 'integer'],
                ],
                'uuids' => [
                    'type' => 'array',
                    'description' => 'UUIDs der zu löschenden Locations. Alternative/ergänzend zu location_ids.',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $targets = [];
            foreach (($arguments['location_ids'] ?? []) as $id) {
                $targets[] = ['location_id' => (int) $id];
            }
            foreach (($arguments['uuids'] ?? []) as $uuid) {
                $targets[] = ['uuid' => (string) $uuid];
            }

            if (empty($targets)) {
                return ToolResult::error('INVALID_ARGUMENT', 'location_ids oder uuids ist erforderlich.');
            }

            $atomic = (bool) ($arguments['atomic'] ?? true);

            // Batch-Vorbereitung: alle referenzierten Locations + Team-IDs des
            // Users in 2 Queries laden, statt pro Target Lookup + Team-Check
            // (vorher ~3 Queries pro Location). Geloescht wird weiterhin pro
            // Model via delete(), damit SoftDelete-Events/ActivityLog feuern.
            $ids   = array_values(array_filter(array_column($targets, 'location_id')));
            $uuids = array_values(array_filter(array_column($targets, 'uuid')));

            if (empty($ids) && empty($uuids)) {
                // Ohne diesen Guard wuerde die Query unten ungefiltert laufen.
                return ToolResult::error('INVALID_ARGUMENT', 'Keine gültigen location_ids oder uuids übergeben.');
            }

            $found = \Platform\Locations\Models\Location::query()
                ->where(function ($q) use ($ids, $uuids) {
                    if (!empty($ids)) {
                        $q->whereIn('id', $ids);
                    }
                    if (!empty($uuids)) {
                        empty($ids) ? $q->whereIn('uuid', $uuids) : $q->orWhereIn('uuid', $uuids);
                    }
                })
                ->get();

            $byId   = $found->keyBy('id');
            $byUuid = $found->keyBy(fn ($l) => mb_strtolower((string) $l->uuid));

            $userTeamIds = $context->user->teams()->pluck('teams.id')->all();

            $run = function () use ($targets, $byId, $byUuid, $userTeamIds, $atomic) {
                $results   = [];
                $okCount   = 0;
                $failCount = 0;

                $fail = function (int $idx, string $code, string $message) use (&$results, &$failCount, $atomic) {
                    $failCount++;
                    $results[] = [
                        'index' => $idx,
                        'ok'    => false,
                        'error' => ['code' => $code, 'message' => $message],
                    ];

                    if ($atomic) {
                        throw new \RuntimeException(json_encode([
                            'code'          => 'BULK_VALIDATION_ERROR',
                            'message'       => "Löschen an Index {$idx}: {$message}",
                            'failed_index'  => $idx,
                            'error_code'    => $code,
                            'error_message' => $message,
                            'results'       => $results,
                        ], JSON_UNESCAPED_UNICODE));
                    }
                };

                foreach ($targets as $idx => $payload) {
                    $location = isset($payload['location_id'])
                        ? $byId->get((int) $payload['location_id'])
                        : $byUuid->get(mb_strtolower((string) $payload['uuid']));

                    if (!$location) {
                        $fail($idx, 'LOCATION_NOT_FOUND', 'Die angegebene Location wurde nicht gefunden.');
                        continue;
                    }

                    if (!in_array((int) $location->team_id, $userTeamIds, true)) {
                        $fail($idx, 'ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Location.');
                        continue;
                    }

                    $location->delete();

                    $okCount++;
                    $results[] = [
                        'index' => $idx,
                        'ok'    => true,
                        'data'  => [
                            'id'      => $location->id,
                            'uuid'    => $location->uuid,
                            'name'    => $location->name,
                            'message' => "Location '{$location->name}' erfolgreich gelöscht.",
                        ],
                    ];
                }

                return [
                    'results' => $results,
                    'summary' => [
                        'requested' => count($targets),
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Delete der Locations: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'bulk',
            'tags'          => ['locations', 'location', 'bulk', 'batch', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'high',
            'idempotent'    => true,
            'side_effects'  => ['deletes'],
        ];
    }
}
