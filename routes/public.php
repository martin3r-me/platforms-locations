<?php

use Illuminate\Support\Facades\Route;
use Platform\Locations\Http\Controllers\BookletController;

/*
|--------------------------------------------------------------------------
| Public Token-Routes (Locations)
|--------------------------------------------------------------------------
|
| Token-basierte oeffentliche Routen — kein Auth. Kunden-Ansicht des
| Location-Booklets als HTML und PDF.
|
*/

Route::middleware('web')->prefix('locations')->group(function () {
    Route::get('/booklet/{token}', [BookletController::class, 'publicShow'])
        ->name('locations.booklet.public.show');

    Route::get('/booklet/{token}/pdf', [BookletController::class, 'publicPdf'])
        ->name('locations.booklet.public.pdf');
});
