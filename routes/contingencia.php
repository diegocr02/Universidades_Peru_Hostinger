<?php

use App\Http\Controllers\Admin\ContingenciaController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\SoloContingencia;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web', EnsureActiveUser::class, SoloContingencia::class])
    ->prefix('administracion/contingencia')->name('admin.contingencia.')->group(function () {
        Route::get('/', [ContingenciaController::class, 'index'])->name('index');
        Route::post('/crear', [ContingenciaController::class, 'crear'])->middleware('throttle:2,10')->name('crear');
        Route::post('/{id}/enviar', [ContingenciaController::class, 'enviar'])->middleware('throttle:6,10')->name('enviar');
        Route::post('/{id}/verificar', [ContingenciaController::class, 'verificar'])->middleware('throttle:6,10')->name('verificar');
        Route::get('/{id}/descargar', [ContingenciaController::class, 'descargar'])->middleware('throttle:6,10')->name('descargar');
    });