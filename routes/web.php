<?php

use App\Http\Controllers\SessionApiController;
use App\Http\Controllers\TryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('try.index');
});

Route::get('/try', [TryController::class, 'index'])->name('try.index');
Route::post('/try', [TryController::class, 'start'])->name('try.start');

Route::get('/session/{lease}', [TryController::class, 'session'])
    ->whereUuid('lease')
    ->name('try.session');
