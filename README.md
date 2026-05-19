# platforms-locations

Location- und Raum-Stammdaten inklusive Auslastungs-Übersicht für die Platform.

## Dokumentation

Die vollständige Dokumentation wird **nicht in diesem Repo** gepflegt, sondern im **office.bhgdigital** Dev-Connector:

- Package: `platforms-locations` (Package-ID `9`)
- Pages: Übersicht, Architektur, Setup, API-Referenz, Datenmodell, Testing, Deployment, Changelog, Contributing, Troubleshooting

Aufruf:

- Office-UI → Dev → Packages → `platforms-locations`
- MCP: `dev.docs.overview(package_id=9)` im `office.bhgdigital`-Connector

Lokale `LLM_GUIDE.md` / `README.md`-Inhalte gelten als veraltet; Single Source of Truth sind die Connector-Pages.

## Installation

```bash
composer require martin3r/platforms-locations
```

Der Service-Provider `Platform\Locations\LocationsServiceProvider` wird automatisch via `extra.laravel.providers` in `composer.json` registriert.
