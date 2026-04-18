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
  - Felder: `name`, `kuerzel`, `gruppe`, `pax_min`, `pax_max`, `mehrfachbelegung`, `adresse`, `latitude`, `longitude`, `sort_order`
  - SoftDeletes
  - Helper: `hasCoordinates(): bool`

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

Konventionen:
- Team-Scope: immer `team_id` aus Argumenten oder `$context->team->id`, Zugriff via `$context->user->teams()` prüfen
- Identifikation: `location_id` ODER `uuid` bei Get/Update/Delete
- Return: flaches Location-Payload inkl. `id`, `uuid`, `team_id`
- Error-Codes: `AUTH_ERROR`, `ACCESS_DENIED`, `VALIDATION_ERROR`, `LOCATION_NOT_FOUND`, `MISSING_TEAM`, `EXECUTION_ERROR`
