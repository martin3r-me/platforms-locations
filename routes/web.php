<?php

use Platform\Locations\Livewire\Dashboard;
use Platform\Locations\Livewire\Test;
use Platform\Locations\Livewire\Sidebar;

Route::get('/', Dashboard::class)->name('locations.dashboard');
Route::get('/test', Test::class)->name('locations.test');
