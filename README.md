# Locations Module

Location- und Raum-Stammdaten inkl. Auslastungsübersicht für die Platform.

## Features

- **Locations-Stammdaten** – Name, Kürzel, Gruppe, PAX Min/Max, Mehrfachbelegung, Adresse
- **CRUD** via Livewire 3 + Alpine.js (Modal-basiert)
- **Auslastungs-View** – Filter nach Zeitraum (Woche/Monat/Jahr/3 Monate) und Gruppe
- **Dashboard** – Kennzahlen zu Locations, Gruppen, Kapazität, Mehrfachbelegung
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
| `deleted_at`      | —              | SoftDeletes                                  |

## Konventionen

- **Layout**: `->layout('platform::layouts.app')`
- **Views**: `view('locations::livewire.<name>')`
- **Livewire-Alias-Prefix**: `locations.*` (automatisch via ServiceProvider)
- **Team-Zugriff**: immer `$user->currentTeam->id` verwenden, Queries mit `->where('team_id', ...)` scopen
- **UI-Komponenten**: `x-ui-page`, `x-ui-panel`, `x-ui-button`, `x-ui-dashboard-tile`, `x-ui-page-navbar`, `x-ui-page-container`, `x-ui-page-sidebar`

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
