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
                [
                    'label' => 'Test',
                    'route' => 'locations.test',
                    'icon'  => 'heroicon-o-beaker',
                ],
            ],
        ],
    ],
];
