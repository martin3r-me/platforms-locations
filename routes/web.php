<?php

use Platform\Locations\Livewire\Dashboard;
use Platform\Locations\Livewire\Manage;
use Platform\Locations\Livewire\Occupancy;
use Platform\Locations\Livewire\Show;

Route::get('/', Dashboard::class)->name('locations.dashboard');
Route::get('/locations', Manage::class)->name('locations.manage');
Route::get('/locations/{location}', Show::class)->name('locations.show');
Route::get('/auslastung', Occupancy::class)->name('locations.occupancy');
