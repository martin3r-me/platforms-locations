<?php

namespace Platform\Locations\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Platform\Locations\Models\Location;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Rendert das Kunden-Booklet einer Location ueber Browsershot (Headless
 * Chrome). Magazin-Layout mit Hero, Photo-Spread, Eckdaten, Bestuhlungen,
 * Anlaessen und Adress-Block.
 *
 * Voraussetzung am Host: Chromium oder Google Chrome installiert.
 * Konfiguration via Env:
 *   CHROMIUM_PATH=/usr/bin/chromium       (optional, sonst Auto-Discover)
 *   BROWSERSHOT_NO_SANDBOX=1              (Default 1, fuer Linux-Container)
 */
class BookletPdfService
{
    /**
     * Wieviele Foto-Slots bekommt der Spread maximal.
     * Erstes Bild ist Hero (separat), die restlichen fuellen das Magazin.
     */
    public const MAX_SPREAD_PHOTOS = 8;

    /**
     * Rendert das Magazin-HTML einer Location (ohne Browsershot-Step,
     * fuer Public-HTML-View nutzbar).
     */
    public function renderHtml(Location $location): string
    {
        $assets  = $this->collectAssets($location);
        $options = $location->bookletOptions();

        // Site-Daten (optional, nur wenn Location einer Site zugeordnet ist
        // und show_site aktiv). Beschreibung + bis zu 3 Bilder kommen als
        // Einleitungs-Seite ins Booklet.
        $site = $options['show_site'] ? $location->site : null;
        $siteImages = [];
        if ($site) {
            $siteImages = collect($site->siteImageReferences())
                ->pluck('url')
                ->filter()
                ->take(3)
                ->values()
                ->all();
        }

        return view('locations::booklet.magazine', [
            'location'   => $location,
            'options'    => $options,
            'site'       => $site,
            'siteImages' => $siteImages,
            'hero'       => $options['show_photos']      ? $assets['hero']      : null,
            'spread'     => $options['show_photos']      ? $assets['spread']    : [],
            'floorPlan'  => $options['show_grundriss']   ? $assets['floorPlan'] : null,
            'seatings'   => $options['show_bestuhlungen']
                ? $location->seatingOptions()->orderBy('sort_order')->orderBy('label')->get()
                : collect(),
            'pricings'   => $options['show_mietpreise']
                ? $location->pricings()->orderBy('sort_order')->orderBy('day_type_label')->get()
                : collect(),
            'addons'     => $options['show_addons']
                ? $location->activeAddons()
                : collect(),
            'anlaesse'   => $options['show_anlaesse'] && is_array($location->anlaesse)
                ? array_values(array_filter($location->anlaesse))
                : [],
        ])->render();
    }

    /**
     * Erzeugt das PDF-Binary fuer eine Location.
     */
    public function renderPdf(Location $location): string
    {
        $html = $this->renderHtml($location);

        $shot = Browsershot::html($html)
            ->format('A4')
            ->showBackground()
            ->waitUntilNetworkIdle()
            ->margins(0, 0, 0, 0)
            ->timeout(60);

        if ($path = env('CHROMIUM_PATH')) {
            $shot->setChromePath($path);
        }
        if ((bool) env('BROWSERSHOT_NO_SANDBOX', true)) {
            $shot->noSandbox();
        }

        return $shot->pdf();
    }

    /**
     * HTTP-Response (inline) — fuer Public-View und Auth-Preview.
     */
    public function inlineResponse(Location $location): SymfonyResponse
    {
        return response($this->renderPdf($location), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->filename($location) . '"',
        ]);
    }

    /**
     * HTTP-Response (download) — fuer den Manage-Download-Button.
     */
    public function downloadResponse(Location $location): SymfonyResponse
    {
        return response($this->renderPdf($location), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->filename($location) . '"',
        ]);
    }

    protected function filename(Location $location): string
    {
        $base = Str::slug($location->name ?: $location->kuerzel ?: 'location');
        return $base . '-booklet.pdf';
    }

    /**
     * Sammelt verfuegbare Bilder einer Location aus beiden Speichersystemen:
     *
     *   1. ContextFile-References (modern, via `getOrderedFileReferences()`)
     *   2. Legacy S3-Flat-Files (via `assetFiles($cat)`)
     *
     * Sortierung pro Quelle nach den Kategorien `photos_empty` → `photos_with_seating`
     *   → `buffet` → `seating_plans`. Erstes Bild wird Hero, die weiteren
     * fuellen den Photo-Spread (max 8 Slots, siehe MAX_SPREAD_PHOTOS).
     *
     * Falls ueberhaupt keine Asset-Bilder vorhanden sind, faellt das Booklet
     * **bewusst nicht** auf den Grundriss zurueck — ein Grundriss als Cover-
     * Hero wirkt nicht magazinhaft. Der Grundriss bekommt im Template eine
     * eigene Seite (siehe `renderHtml`).
     *
     * Browsershot fetched die URLs beim Rendern. PDFs werden uebersprungen —
     * das Booklet ist Bild-fokussiert.
     *
     * @return array{hero: ?string, spread: array<int,string>, floorPlan: ?string}
     */
    protected function collectAssets(Location $location): array
    {
        $urls = [];
        $categories = ['photos_empty', 'photos_with_seating', 'buffet', 'seating_plans'];

        // 1) ContextFile-References (neue Quelle, gepflegt via Manage-UI).
        //    Highlights (meta.highlight === true) kommen IMMER zuerst und
        //    landen damit garantiert in Cover-Hero + Photo-Spread. Alles
        //    weitere wird nach Kategorie aufgefuellt — bisheriges Verhalten.
        try {
            $highlights = [];
            $byCategory = [];
            foreach ($location->getOrderedFileReferences() as $ref) {
                if (!($ref->contextFile?->isImage() ?? false)) {
                    continue;
                }
                $url = $ref->url ?? null;
                if (!is_string($url) || $url === '') {
                    continue;
                }
                if ((bool) ($ref->meta['highlight'] ?? false)) {
                    $highlights[] = $url;
                    continue;
                }
                $cat = $ref->meta['category'] ?? 'uncategorized';
                $byCategory[$cat][] = $url;
            }

            foreach ($highlights as $url) {
                $urls[] = $url;
            }
            foreach ($categories as $cat) {
                foreach ($byCategory[$cat] ?? [] as $url) {
                    $urls[] = $url;
                }
            }
        } catch (\Throwable $e) {
            // ContextFile-System ggf. nicht verfuegbar
        }

        // 2) Legacy S3-Flat-Files (alte Quelle, Read-Only-Fallback)
        foreach ($categories as $cat) {
            try {
                $files = $location->assetFiles($cat);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($files as $f) {
                if (!($f['is_image'] ?? false)) {
                    continue;
                }
                $url = $f['url'] ?? null;
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
        }

        // URL-Duplikate eliminieren (falls Datei in beiden Systemen referenziert)
        $urls = array_values(array_unique($urls));

        $hero   = $urls[0] ?? null;
        $spread = array_slice($urls, 1, self::MAX_SPREAD_PHOTOS);

        // Grundriss bekommt eine eigene Seite — nicht als Hero-Fallback.
        $floorPlan = ($location->floorPlanIsImage() ? $location->floorPlanUrl(60) : null) ?: null;

        return ['hero' => $hero, 'spread' => $spread, 'floorPlan' => $floorPlan];
    }
}
