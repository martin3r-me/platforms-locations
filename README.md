# Locations Module

Location- und Raum-Stammdaten inkl. Auslastungsübersicht für die Platform.

## Features

- **Locations-Stammdaten** – Name, Kürzel, Gruppe, PAX Min/Max, Mehrfachbelegung, Adresse, Koordinaten
- **Adress-Autocomplete + Karte** – Nominatim (OpenStreetMap) mit Leaflet-Karte und Stecknadel
- **CRUD** via Livewire 3 + Alpine.js (Modal-basiert)
- **Auslastungs-View** – Filter nach Zeitraum (Woche/Monat/Jahr/3 Monate) und Gruppe
- **Dashboard** – Kennzahlen zu Locations, Gruppen, Kapazität, Mehrfachbelegung
- **AI-Tools** – List/Get/Create/Update/Delete über `Platform\Core\Tools\ToolRegistry`
- **Team-Scoping** – alle Daten an `currentTeam` gebunden
- **UUIDs** – `UuidV7` auf allen Models

## Installation

```bash
composer require martin3r/platforms-locations
php artisan migrate
```

Der Service-Provider `Platform\Locations\LocationsServiceProvider` wird automatisch via `extra.laravel.providers` in `composer.json` registriert.

## Struktur

```
modules/platforms-locations/
├── config/
│   └── locations.php               # Routing, Navigation, Sidebar
├── database/
│   └── migrations/                 # locations_locations Tabelle
├── resources/
│   └── views/
│       └── livewire/               # Blade Views (locations::livewire.*)
├── routes/
│   └── web.php                     # Module-Routes
└── src/
    ├── Livewire/                   # Livewire 3 Components
    │   ├── Dashboard.php
    │   ├── Manage.php              # CRUD Locations
    │   ├── Occupancy.php           # Auslastung
    │   └── Sidebar.php
    ├── Models/
    │   └── Location.php            # UUID + team_id + SoftDeletes
    ├── Tools/                      # AI-Tools (ToolRegistry)
    │   ├── ListLocationsTool.php
    │   ├── GetLocationTool.php
    │   ├── CreateLocationTool.php
    │   ├── UpdateLocationTool.php
    │   ├── DeleteLocationTool.php
    │   ├── BulkCreateLocationsTool.php
    │   ├── BulkUpdateLocationsTool.php
    │   └── BulkDeleteLocationsTool.php
    └── LocationsServiceProvider.php
```

## Routen

| Route                    | Name                   | Beschreibung           |
| ------------------------ | ---------------------- | ---------------------- |
| `GET /locations`         | `locations.dashboard`  | Dashboard mit Counts   |
| `GET /locations/locations`| `locations.manage`     | CRUD Stammdaten        |
| `GET /locations/auslastung`| `locations.occupancy`| Auslastungsübersicht   |

> Der URL-Prefix `locations` kommt aus `config/locations.php` (`routing.prefix`) und lässt sich via `LOCATIONS_MODE`-Env umschalten.

## Datenmodell

### `Platform\Locations\Models\Location`

Tabelle `locations_locations`.

| Feld              | Typ            | Hinweis                                      |
| ----------------- | -------------- | -------------------------------------------- |
| `id`              | bigint         | Primary Key                                  |
| `uuid`            | uuid (unique)  | `UuidV7` – wird im `booted()` gesetzt        |
| `user_id`         | fk users       | Ersteller, nullable                          |
| `team_id`         | fk teams       | Owning Team, nullable                        |
| `name`            | string         | Anzeigename                                  |
| `kuerzel`         | string(20)     | Kürzel für kompakte Darstellung              |
| `gruppe`          | string?        | Gruppierung (z.B. Gebäude)                   |
| `pax_min`         | usmallint?     | Minimale Belegung                            |
| `pax_max`         | usmallint?     | Maximale Kapazität                           |
| `mehrfachbelegung`| boolean        | Mehrere Buchungen pro Tag erlaubt            |
| `adresse`         | string?        | Freier Adress-Text                           |
| `sort_order`      | usmallint      | Sortierreihenfolge                           |
| `timestamps`      | —              | created_at / updated_at                      |
| `latitude`        | decimal(10,7)  | WGS84-Breitengrad (via Nominatim geocoded)   |
| `longitude`       | decimal(10,7)  | WGS84-Längengrad (via Nominatim geocoded)    |
| `deleted_at`      | —              | SoftDeletes                                  |

## Konventionen

- **Layout**: `->layout('platform::layouts.app')`
- **Views**: `view('locations::livewire.<name>')`
- **Livewire-Alias-Prefix**: `locations.*` (automatisch via ServiceProvider)
- **Team-Zugriff**: immer `$user->currentTeam->id` verwenden, Queries mit `->where('team_id', ...)` scopen
- **UI-Komponenten**: `x-ui-page`, `x-ui-panel`, `x-ui-button`, `x-ui-dashboard-tile`, `x-ui-page-navbar`, `x-ui-page-container`, `x-ui-page-sidebar`

## Geocoding & Karte

- Nominatim (OpenStreetMap) für Adress-Autocomplete – **kein API-Key**, kein Account
- Leaflet 1.9.4 per CDN für die Karten-Darstellung im Modal
- Koordinaten werden automatisch gesetzt, wenn der Nutzer einen Vorschlag wählt

`.env`-Optionen:

```dotenv
# Optional – Default ist "Platform-Locations/1.0 (<APP_NAME>)"
LOCATIONS_NOMINATIM_USER_AGENT="MyApp/1.0 (ops@example.com)"

# Optional – Default https://nominatim.openstreetmap.org
LOCATIONS_NOMINATIM_URL=

# Optional – Sprache der Ergebnisse (Default de)
LOCATIONS_NOMINATIM_LANG=de

# Optional – Länder-Einschränkung (Default de,at,ch,lu,nl,be,fr,it)
LOCATIONS_NOMINATIM_COUNTRY=de,at,ch
```

> **Nominatim-Usage-Policy:** Nominatim erwartet einen aussagekräftigen User-Agent und erlaubt max. 1 Request/Sekunde. Das Adressfeld ist mit 400 ms debounced; bei höherer Last eigenen Nominatim-Server oder anderen Provider (Mapbox, Google) nutzen.

## AI-Tools

Die Tools werden automatisch in `Platform\Core\Tools\ToolRegistry` registriert.

| Tool-Name                   | Zweck                                |
| --------------------------- | ------------------------------------ |
| `locations.locations.GET`   | Listet Locations des aktuellen Teams |
| `locations.location.GET`    | Details zu einer Location            |
| `locations.locations.POST`  | Location anlegen                     |
| `locations.locations.PATCH` | Location aktualisieren               |
| `locations.locations.DELETE`| Location löschen (Soft Delete)       |
| `locations.locations.bulk.POST`   | Mehrere Locations anlegen (atomic default)   |
| `locations.locations.bulk.PATCH`  | Mehrere Locations aktualisieren              |
| `locations.locations.bulk.DELETE` | Mehrere Locations löschen (Soft Delete)      |

Alle Schreib-Tools verlangen `team_id` (oder übernehmen das aktuelle Team aus dem `ToolContext`). Get/Update/Delete akzeptieren wahlweise `location_id` oder `uuid`.

## Auslastung & Events-Modul

Die Auslastungs-View (`Occupancy`) ist bewusst **vom Events-Modul entkoppelt**. Solange keine Buchungen vorliegen, zeigt sie einen Leerstate.

Die View erwartet pro Buchungseintrag folgendes Shape:

```php
[
    'title'       => string,   // Anzeigename der Buchung
    'optionsrang' => string,   // Status: Vertrag | Definitiv | 1. Option | ...
]
```

Buchungsdaten werden vom späteren Events-Modul geliefert.

## Herkunft

Abgeleitet aus dem internen `event-modul` (Model `VenueRoom` + `roomsOverview`). Beim Port auf Platform-Konventionen umgestellt:

- `VenueRoom` → `Location`
- Tabelle mit Modul-Prefix (`locations_locations`)
- UUID + team_id + SoftDeletes ergänzt
- Classic Controller/Blade + Alpine-Fetch → Livewire 3 + Alpine-Modals
- Event-Kopplung der Auslastung entfernt (eigenständig, später über Events-Modul angebunden)

## Lizenz

MIT
