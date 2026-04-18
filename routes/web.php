<?php

use Platform\Locations\Livewire\Dashboard;
use Platform\Locations\Livewire\Manage;
use Platform\Locations\Livewire\Occupancy;

Route::get('/', Dashboard::class)->name('locations.dashboard');
Route::get('/locations', Manage::class)->name('locations.manage');
Route::get('/auslastung', Occupancy::class)->name('locations.occupancy');
