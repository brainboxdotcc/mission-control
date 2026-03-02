<?php

use App\Http\Controllers\SessionApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'throttle:session-touch'])->group(function (): void {
    Route::post('/session/touch', [SessionApiController::class, 'touch'])->name('api.session.touch');
});

Route::middleware(['web', 'throttle:session-release'])->group(function (): void {
    Route::post('/session/release', [SessionApiController::class, 'release'])->name('api.session.release');
});
