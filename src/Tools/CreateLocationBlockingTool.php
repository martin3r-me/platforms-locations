<?php

namespace Platform\Locations\Tools;

use Illuminate\Support\Carbon;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationBlocking;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

class CreateLocationBlockingTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;

    public function getName(): string
    {
        return 'locations.blockings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /locations/{location_id}/blockings - Legt eine Sperrzeit fuer eine Location an (tagesgenau, inkl. Grenzen). Gesperrte Tage gelten im Verfuegbarkeits-Check als nicht buchbar. Identifikation: location_id, location_uuid, location_kuerzel oder location_ref.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge(self::locationRefSchemaFields(), [
                'start_date' => ['type' => 'string', 'description' => 'Erster gesperrter Tag (YYYY-MM-DD). Pflicht.'],
                'end_date'   => ['type' => 'string', 'description' => 'Letzter gesperrter Tag (YYYY-MM-DD, inklusive). Optional — Default: start_date (Eintages-Sperre).'],
                'reason'     => ['type' => 'string', 'description' => 'Optionaler Grund (z. B. "Renovierung", "Eigenveranstaltung"). Max. 255 Zeichen.'],
            ]),
            'required' => ['start_date'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) {
                return $err;
            }

            try {
                $start = Carbon::parse((string) ($arguments['start_date'] ?? ''))->toDateString();
                $end = !empty($arguments['end_date'])
                    ? Carbon::parse((string) $arguments['end_date'])->toDateString()
                    : $start;
            } catch (\Throwable $e) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungueltiges Datum — erwartet wird YYYY-MM-DD.');
            }

            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }

            $reason = isset($arguments['reason']) && trim((string) $arguments['reason']) !== ''
                ? mb_substr(trim((string) $arguments['reason']), 0, 255)
                : null;

            $blocking = LocationBlocking::create([
                'user_id'     => $context->user->id,
                'team_id'     => $location->team_id,
                'location_id' => $location->id,
                'start_date'  => $start,
                'end_date'    => $end,
                'reason'      => $reason,
            ]);

            return ToolResult::success([
                'blocking' => [
                    'id'          => $blocking->id,
                    'uuid'        => $blocking->uuid,
                    'location_id' => $blocking->location_id,
                    'start_date'  => $start,
                    'end_date'    => $end,
                    'reason'      => $blocking->reason,
                ],
                'aliases_applied' => $this->resolvedLocationAliases(),
                'message'         => "Sperrzeit fuer '{$location->name}' angelegt ({$start} bis {$end})"
                    . ($reason ? ": {$reason}" : '.'),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der Sperrzeit: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['locations', 'blockings', 'availability', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
