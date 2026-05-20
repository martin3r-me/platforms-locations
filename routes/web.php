<?php

use Platform\Locations\Http\Controllers\BookletController;
use Platform\Locations\Livewire\Dashboard;
use Platform\Locations\Livewire\Manage;
use Platform\Locations\Livewire\Occupancy;
use Platform\Locations\Livewire\Show;
use Platform\Locations\Livewire\SiteIndex;
use Platform\Locations\Livewire\SiteShow;

Route::get('/', Dashboard::class)->name('locations.dashboard');
Route::get('/manage', Manage::class)->name('locations.manage');
Route::get('/auslastung', Occupancy::class)->name('locations.occupancy');
Route::get('/sites', SiteIndex::class)->name('locations.sites');
Route::get('/sites/{site}', SiteShow::class)->name('locations.sites.show');

// Auth-Download des Bookletts (User aus Show-View). Muss VOR der Catch-all-Show-Route stehen.
Route::get('/{location}/booklet.pdf', [BookletController::class, 'authDownload'])
    ->name('locations.booklet.download');

Route::get('/{location}', Show::class)->name('locations.show');
