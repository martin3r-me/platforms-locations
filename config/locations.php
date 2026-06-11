<?php

/**
 * Locations Module Configuration
 *
 * @see Platform\Core\PlatformCore::registerModule() für Details zur Modul-Registrierung
 */

return [
    'routing' => [
        'mode' => env('LOCATIONS_MODE', 'path'),
        'prefix' => 'locations',
    ],

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Geocoding (Nominatim / OpenStreetMap)
    |--------------------------------------------------------------------------
    |
    | Nominatim verlangt einen aussagekräftigen User-Agent. Standard ist
    | "Platform Locations Module / <app.name>". Nutze LOCATIONS_NOMINATIM_USER_AGENT
    | um das zu überschreiben. Nominatim-Nutzungs-Richtlinien erlauben max.
    | 1 Request pro Sekunde – die Eingabe ist entsprechend debounced.
    |
    */
    'geocoding' => [
        'nominatim_url' => env('LOCATIONS_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent'    => env('LOCATIONS_NOMINATIM_USER_AGENT'),
        'language'      => env('LOCATIONS_NOMINATIM_LANG', 'de'),
        'countrycodes'  => env('LOCATIONS_NOMINATIM_COUNTRY', 'de,at,ch,lu,nl,be,fr,it'),
        'limit'         => 6,
        // Suchergebnisse werden gecacht, um die Nominatim-Policy
        // (max. 1 Request/Sekunde) auch bei Live-Eingabe einzuhalten.
        'cache_seconds' => (int) env('LOCATIONS_NOMINATIM_CACHE', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kunden-Booklet (Browsershot / Headless Chrome)
    |--------------------------------------------------------------------------
    |
    | chromium_path: expliziter Chrome/Chromium-Binary-Pfad (sonst Auto-Discover).
    | pdf_cache_seconds: wie lange ein gerendertes PDF gecacht wird. Der
    | Cache-Key enthält updated_at der Location — Stammdaten-Änderungen
    | invalidieren sofort, der TTL begrenzt nur die Rest-Stale-Zeit
    | (z. B. bei Site-Bild-Änderungen).
    |
    */
    'booklet' => [
        'chromium_path'     => env('CHROMIUM_PATH'),
        'no_sandbox'        => (bool) env('BROWSERSHOT_NO_SANDBOX', true),
        'pdf_cache_seconds' => (int) env('LOCATIONS_BOOKLET_PDF_CACHE', 900),
    ],

    'navigation' => [
        'route' => 'locations.dashboard',
        'icon'  => 'heroicon-o-map-pin',
        'order' => 100,
    ],

    'sidebar' => [
        [
            'group' => 'Allgemein',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'locations.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
            ],
        ],
        [
            'group' => 'Stammdaten',
            'items' => [
                [
                    'label' => 'Locations',
                    'route' => 'locations.manage',
                    'icon'  => 'heroicon-o-building-office',
                ],
                [
                    'label' => 'Sites',
                    'route' => 'locations.sites',
                    'icon'  => 'heroicon-o-building-library',
                ],
            ],
        ],
        [
            'group' => 'Auswertung',
            'items' => [
                [
                    'label' => 'Auslastung',
                    'route' => 'locations.occupancy',
                    'icon'  => 'heroicon-o-chart-bar',
                ],
            ],
        ],
    ],
];
