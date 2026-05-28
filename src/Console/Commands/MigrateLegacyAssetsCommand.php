<?php

namespace Platform\Locations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Platform\Locations\Models\Location;
use Platform\Locations\Services\LocationAssetService;
use Symfony\Component\Uid\UuidV7;

/**
 * Migriert die alten Legacy-S3-Flat-Files (locations/{uuid}/{cat-slug}/*) in
 * das ContextFile-System. Die S3-Datei wird NICHT verschoben — wir legen
 * lediglich passende `context_files`- und `context_file_references`-Eintraege
 * an, die den bestehenden Pfad referenzieren. Dadurch tauchen die Bilder
 * danach im Manage-UI mit Stern-Toggle auf und koennen im Booklet als
 * Highlight markiert werden.
 *
 * Idempotent: ueberspringt Files, die bereits ueber Pfad+Context referenziert
 * sind.
 */
class MigrateLegacyAssetsCommand extends Command
{
    protected $signature = 'locations:migrate-legacy-assets
                            {--location-uuid= : Nur eine bestimmte Location migrieren}
                            {--dry-run : Nur listen, keine DB-Eintraege schreiben}';

    protected $description = 'Migriert Legacy-S3-Asset-Files zu ContextFile-References (S3-Dateien bleiben unangetastet).';

    public function handle(LocationAssetService $service): int
    {
        $query = Location::query();
        if ($uuid = $this->option('location-uuid')) {
            $query->where('uuid', $uuid);
        }
        $locations = $query->orderBy('name')->get();

        if ($locations->isEmpty()) {
            $this->warn('Keine Location gefunden.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? '[DRY-RUN] ' : '') . 'Migriere Legacy-Assets fuer ' . $locations->count() . ' Location(s)…');

        $stats = ['migrated' => 0, 'skipped' => 0, 'errors' => 0];
        $diskName = $service->disk();
        $categories = array_keys(LocationAssetService::categories());

        foreach ($locations as $location) {
            $hasFiles = false;
            foreach ($categories as $category) {
                $files = $service->listFiles($location, $category);
                if ($files->isEmpty()) continue;

                if (!$hasFiles) {
                    $this->line('');
                    $this->line('<comment>' . $location->name . '</> (' . $location->kuerzel . ', id=' . $location->id . ')');
                    $hasFiles = true;
                }

                foreach ($files as $file) {
                    try {
                        $result = $this->migrateOne($location, $category, $file, $diskName, $dryRun);
                        $stats[$result]++;
                        $tag = match ($result) {
                            'migrated' => $dryRun ? '<fg=cyan>[dry]</>' : '<fg=green>[ok]</>',
                            'skipped'  => '<fg=yellow>[skip]</>',
                            default    => '<fg=red>[err]</>',
                        };
                        $this->line('  ' . $tag . ' ' . $category . ' / ' . $file['filename']);
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        $this->error('  [err] ' . $category . ' / ' . $file['filename'] . ' — ' . $e->getMessage());
                    }
                }
            }
        }

        $this->line('');
        $this->info(sprintf(
            ($dryRun ? '[DRY-RUN] ' : '') . 'Fertig — migriert: %d | uebersprungen: %d | Fehler: %d',
            $stats['migrated'], $stats['skipped'], $stats['errors']
        ));

        if ($dryRun) {
            $this->comment('Tipp: ohne --dry-run erneut ausfuehren, um die Eintraege wirklich zu schreiben.');
        }

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param array{path:string,filename:string,size:int,mime:string,extension:string,is_image:bool,is_pdf:bool} $file
     */
    protected function migrateOne(Location $location, string $category, array $file, string $diskName, bool $dryRun): string
    {
        $exists = DB::table('context_files')
            ->where('context_type', Location::class)
            ->where('context_id', $location->id)
            ->where('disk', $diskName)
            ->where('path', $file['path'])
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        if ($dryRun) {
            return 'migrated';
        }

        DB::transaction(function () use ($location, $category, $file, $diskName) {
            $token = $this->generateUniqueToken();

            $contextFileId = DB::table('context_files')->insertGetId([
                'token'         => $token,
                'team_id'       => $location->team_id,
                'user_id'       => $location->user_id,
                'context_type'  => Location::class,
                'context_id'    => $location->id,
                'disk'          => $diskName,
                'path'          => $file['path'],
                'file_name'     => $file['filename'],
                'original_name' => $file['filename'],
                'mime_type'     => $file['mime'] ?: 'application/octet-stream',
                'file_size'     => (int) ($file['size'] ?? 0),
                'meta'          => json_encode([
                    'migrated_from' => 'legacy_s3',
                    'migrated_at'   => now()->toIso8601String(),
                    'category'      => $category,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('context_file_references')->insert([
                'uuid'            => (string) UuidV7::generate(),
                'context_file_id' => $contextFileId,
                'reference_type'  => Location::class,
                'reference_id'    => $location->id,
                'order'           => 0,
                'meta'            => json_encode(['category' => $category]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        });

        return 'migrated';
    }

    protected function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (DB::table('context_files')->where('token', $token)->exists());
        return $token;
    }
}
