# Locations Module - LLM Guide

## Overview
- **Namespace**: `Platform\Locations`
- **Module Key**: `locations`
- **Service Provider**: `LocationsServiceProvider`
- **Config**: `config/locations.php`
- **Views**: `locations::livewire.*`
- **Livewire Prefix**: `locations.*`

## Architecture
- **ServiceProvider** registriert Config, Modul, Routes, Views, Livewire, Migrations
- **Models** in `src/Models/`
- **Livewire Components** in `src/Livewire/`
- **Views** in `resources/views/livewire/`
- **Routes** in `routes/web.php`
- **Migrations** in `database/migrations/`

## Models
- `Platform\Locations\Models\Location` (Tabelle `locations_locations`)
  - UUID + `team_id` + `user_id`
  - Stamm-Felder: `name`, `kuerzel`, `gruppe`, `pax_min`, `pax_max` (= max inkl. Personal), `mehrfachbelegung`, `adresse`, `latitude`, `longitude`, `sort_order`, `groesse_qm`, `hallennummer`, `barrierefrei`, `besonderheit`, `anlaesse` (json)
  - SoftDeletes
  - Helper: `hasCoordinates(): bool`
  - Grundriss-API: `hasFloorPlan()`, `floorPlanPath()`, `floorPlanUrl()`, `floorPlanContents()`, `floorPlanMimeType()`, `floorPlanIsPdf()`, `floorPlanIsImage()`, `floorPlanDisk()`, `floorPlanDirectory()`, `floorPlanFileName()`, `floorPlanExtension()`
  - Pricing-API: `pricings()`, `seatingOptions()`, `addons()`, `pricingForDayType($label)`, `pricingTable()`, `activeAddons()`
- `Platform\Locations\Models\LocationSeatingOption` (Tabelle `locations_seating_options`) — Bestuhlungs-Hinweis (`label`, `pax_max_ca`)
- `Platform\Locations\Models\LocationPricing` (Tabelle `locations_pricings`) — Mietpreis pro Tag-Typ-Volltext (`day_type_label`, `price_net`, optional `label`)
- `Platform\Locations\Models\LocationAddon` (Tabelle `locations_addons`) — Optionaler Posten (`label`, `price_net`, `unit` ∈ `pro_tag`/`pro_va_tag`/`einmalig`/`pro_stueck`, `is_active`)

## Livewire Components & Routes
- `Dashboard` → `locations.dashboard` → `/`
- `Manage`    → `locations.manage`    → `/locations` (Stammdaten CRUD)
- `Occupancy` → `locations.occupancy` → `/auslastung` (Auslastungsübersicht)
- `Sidebar`   → Komponente für Modul-Sidebar

## Important Patterns
- Team-based data: `$user->currentTeam`
- UUIDs für alle Models (via `UuidV7::generate()` in `booted()`)
- Layout: `->layout('platform::layouts.app')`
- Views: `locations::livewire.viewname`
- UI-Komponenten: `x-ui-page`, `x-ui-panel`, `x-ui-button`, `x-ui-dashboard-tile`, `x-ui-page-navbar`, `x-ui-page-container`, `x-ui-page-sidebar`

## Geocoding (OpenStreetMap / Nominatim)

Das Adressfeld in `Manage` nutzt Nominatim für Autocomplete und speichert Koordinaten. Konfiguration in `config/locations.php` unter `geocoding.*`:

- `LOCATIONS_NOMINATIM_URL` (Default `https://nominatim.openstreetmap.org`)
- `LOCATIONS_NOMINATIM_USER_AGENT` (Pflicht laut Policy – Default enthält `config('app.name')`)
- `LOCATIONS_NOMINATIM_LANG` (Default `de`)
- `LOCATIONS_NOMINATIM_COUNTRY` (Komma-Liste, Default: DACH + BeNeLux + FR + IT)

Karte rendert im Modal über Leaflet 1.9.4 per CDN. Marker aktualisiert sich live via Livewire-Event `locations:map-update` (wird von `selectSuggestion`/`clearCoordinates` dispatched). DOM unter der Karte ist durch `wire:ignore` gegen Livewire-DOM-Patching geschützt.

## Pricing & Bestuhlung & Add-ons

Drei Sub-Tabellen pro Location, alle in der Manage-UI inline editierbar (nur im Edit-Modus):

- **`locations_seating_options`** — Bestuhlungs-Hinweise als ca.-PAX-Werte (Mischformen werden bewusst nicht abgebildet, daher reine Information).
- **`locations_pricings`** — Mietpreise pro Tag-Typ. `day_type_label` ist ein Volltext-String und matcht 1:1 die Tages-Typen aus den Events-Settings (`SettingsService::dayTypes()` — Defaults: `Veranstaltungstag`, `Aufbautag`, `Abbautag`, `Rüsttag`). Kein FK, kein Slug — bewusst lose gekoppelt, damit Settings und Stammdaten unabhängig pflegbar bleiben.
- **`locations_addons`** — Optionale Zusatz-Posten (z.B. Heizung). `unit` steuert die Default-Menge beim Einbuchen ins Events-Modul: `pro_tag` = Anzahl aller Tage, `pro_va_tag` = Anzahl Tage mit dem ersten Eintrag der Settings-`dayTypes()`-Liste (Default „Veranstaltungstag"), `einmalig` und `pro_stueck` = 1 (mit User-Override).

### Konsum durch Events-Modul

Das Events-Modul (`Platform\Events\Services\LocationPricingApplicator`) zieht die Pricings/Addons über die öffentliche Model-API der Location:

```php
$location->pricings();              // HasMany
$location->seatingOptions();        // HasMany
$location->addons();                // HasMany
$location->pricingForDayType($s);   // ?LocationPricing
$location->pricingTable();          // array
$location->activeAddons();          // Collection
```

Das Locations-Modul kennt das Events-Modul nicht. Die Audit-Tabelle für die Anwendung liegt in Events (`events_location_pricing_applications`).

## Asset-Kategorien (Multi-Datei, S3, ohne DB)

Pro Location lassen sich neben dem Grundriss vier weitere Asset-Kategorien hinterlegen, alle ohne DB-Eintrag direkt im Storage. Service: `Platform\Locations\Services\LocationAssetService`.

| Key | Label | Multi | Endungen | Max |
|---|---|---|---|---|
| `buffet` | Buffetstationen | ja | pdf, png, jpg, webp | 20 MB |
| `seating_plans` | Bestuhlungsplaene | ja | pdf, png, jpg, webp | 20 MB |
| `photos_with_seating` | Fotos mit Bestuhlung | ja | png, jpg, webp | 15 MB |
| `photos_empty` | Fotos der leeren Location | png, jpg, webp | 15 MB |

Pfadschema: `locations/{uuid}/{slug}/{token}.{ext}` (Slug aus `LocationAssetService::categories()[$key]['slug']`). Disk-Wahl identisch zum Grundriss (S3 wenn konfiguriert, sonst default).

**Public Model API auf `Location`:**

```php
$loc->assetCategories();      // statisch: Konfig aller Kategorien
$loc->assetFiles($category);  // Collection von [path, filename, size, mime, url, is_image, is_pdf, extension]
$loc->buffetFiles();
$loc->seatingPlanFiles();
$loc->photosWithSeating();
$loc->photosEmpty();
```

**Wichtig:** Der **Grundriss** laeuft weiterhin separat ueber `floorPlan*()` und den eigenen Pfad `locations/grundrisse/{uuid}/grundriss.{ext}` — bewusst nicht migriert, damit bestehende Konsumenten (Events `PdfFloorPlanMerger`, Quote-PDF) unveraendert weiterlaufen.

## Grundriss-Upload (S3, ohne DB)

Locations können einen Grundriss (PDF/PNG/JPG/WEBP, max. 20 MB) zugeordnet bekommen. Die Datei liegt ausschließlich im Storage – **kein** DB-Eintrag, kein ContextFile.

- Disk-Wahl: `s3` falls `filesystems.disks.s3.bucket` konfiguriert, sonst `filesystems.default`
- Pfadschema: `locations/grundrisse/{location_uuid}/grundriss.{ext}`
- Überschreiben: Beim Upload wird das Verzeichnis geleert und neu befüllt (deckt Extension-Wechsel ab)
- Erkennung: `Storage::disk(...)->files($dir)` – ein File pro Location
- View-URL: `temporaryUrl()` (S3 presigned, 15 min) oder `url()` als Fallback
- UI: Upload-Sektion nur im Edit-Modus im Manage-Modal (benötigt gespeicherte UUID)
- Soft-Delete: Beim Soft-Delete der Location bleibt der Grundriss im Bucket erhalten (Restore-Fähigkeit)

## Occupancy (Auslastung)
Die Auslastungs-View ist bewusst entkoppelt. Buchungsdaten werden später vom Events-Modul geliefert. Solange keine Buchungen vorliegen, zeigt die View einen leeren State. Der Platzhalter erwartet pro Eintrag: `title`, `optionsrang` (Status) – analog zum alten `Room`-Model.

## AI-Tools (ToolRegistry)

Tools werden in `LocationsServiceProvider::registerTools()` an `Platform\Core\Tools\ToolRegistry` übergeben. Implementierungen liegen in `src/Tools/`.

| Tool-Name                   | Klasse                   | Zweck                                |
| --------------------------- | ------------------------ | ------------------------------------ |
| `locations.locations.GET`   | `ListLocationsTool`      | Listet Locations des aktuellen Teams |
| `locations.location.GET`    | `GetLocationTool`        | Details zu einer Location            |
| `locations.locations.POST`  | `CreateLocationTool`     | Location anlegen                     |
| `locations.locations.PATCH` | `UpdateLocationTool`     | Location aktualisieren               |
| `locations.locations.DELETE`| `DeleteLocationTool`     | Location löschen (Soft Delete)       |
| `locations.locations.bulk.POST`   | `BulkCreateLocationsTool` | Mehrere Locations anlegen (atomic)   |
| `locations.locations.bulk.PATCH`  | `BulkUpdateLocationsTool` | Mehrere Locations aktualisieren      |
| `locations.locations.bulk.DELETE` | `BulkDeleteLocationsTool` | Mehrere Locations löschen            |
| `locations.pricings.GET/POST/PATCH/DELETE` | je `*LocationPricingTool` / `ListLocationPricingsTool` | Mietpreise pro Tag-Typ |
| `locations.seating-options.GET/POST/PATCH/DELETE` | je `*LocationSeatingOptionTool` / `ListLocationSeatingOptionsTool` | Bestuhlungs-Hinweise |
| `locations.addons.GET/POST/PATCH/DELETE` | je `*LocationAddonTool` / `ListLocationAddonsTool` | Optionale Add-ons |
| `locations.asset-categories.GET` | `GetLocationAssetCategoriesTool` | Discovery: erlaubte Asset-Kategorien |
| `locations.assets.GET` | `ListLocationAssetsTool` | Liste der Dateien einer Asset-Kategorie (oder aller) |
| `locations.assets.DELETE` | `DeleteLocationAssetTool` | Asset-Datei per Filename loeschen (Upload bleibt UI-only) |

Sub-Entity-Tools nutzen das `Tools\Concerns\ResolvesLocation`-Trait und akzeptieren als Eltern-Selektor `location_id` ODER `location_uuid`. `Create/UpdateLocationTool` wurden um die neuen Stamm-Felder (`groesse_qm`, `hallennummer`, `barrierefrei`, `besonderheit`, `anlaesse`) erweitert.

Konventionen:
- Team-Scope: immer `team_id` aus Argumenten oder `$context->team->id`, Zugriff via `$context->user->teams()` prüfen
- Identifikation: `location_id` ODER `uuid` bei Get/Update/Delete
- Return: flaches Location-Payload inkl. `id`, `uuid`, `team_id`
- Error-Codes: `AUTH_ERROR`, `ACCESS_DENIED`, `VALIDATION_ERROR`, `LOCATION_NOT_FOUND`, `MISSING_TEAM`, `EXECUTION_ERROR`
