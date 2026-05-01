<?php

use Platform\Locations\Livewire\Dashboard;
use Platform\Locations\Livewire\Manage;
use Platform\Locations\Livewire\Occupancy;
use Platform\Locations\Livewire\Show;
use Platform\Locations\Livewire\SiteIndex;
use Platform\Locations\Livewire\SiteShow;

Route::get('/', Dashboard::class)->name('locations.dashboard');
Route::get('/locations', Manage::class)->name('locations.manage');
Route::get('/locations/{location}', Show::class)->name('locations.show');
Route::get('/auslastung', Occupancy::class)->name('locations.occupancy');
Route::get('/sites', SiteIndex::class)->name('locations.sites');
Route::get('/sites/{site}', SiteShow::class)->name('locations.sites.show');
