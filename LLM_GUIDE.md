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
  - Felder: `name`, `kuerzel`, `gruppe`, `pax_min`, `pax_max`, `mehrfachbelegung`, `adresse`, `sort_order`
  - SoftDeletes

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

## Occupancy (Auslastung)
Die Auslastungs-View ist bewusst entkoppelt. Buchungsdaten werden später vom Events-Modul geliefert. Solange keine Buchungen vorliegen, zeigt die View einen leeren State. Der Platzhalter erwartet pro Eintrag: `title`, `optionsrang` (Status) – analog zum alten `Room`-Model.
