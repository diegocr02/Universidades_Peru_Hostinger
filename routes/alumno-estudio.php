<?php

use App\Http\Controllers\Alumno\HistorialVisualController;
use App\Http\Controllers\Alumno\MetaEstudioController;
use App\Http\Controllers\Alumno\RepasoController;
use App\Http\Controllers\Alumno\ResultadoPdfController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\FeatureAlumnoAreas;
use App\Http\Middleware\SoloEstudiante;
use App\Livewire\Alumno\Practica\PracticaPorTema;
use Illuminate\Support\Facades\Route;

// Herramientas de estudio del alumno: práctica, repaso, historial visual, meta y PDF.
// Cada acción valida además la propiedad del intento, la práctica o la pregunta.
Route::middleware(['auth:web', EnsureActiveUser::class, FeatureAlumnoAreas::class, SoloEstudiante::class])
    ->prefix('alumno')->name('alumno.')->group(function () {
        Route::get('/practica', PracticaPorTema::class)->name('practica');
        Route::get('/progreso', HistorialVisualController::class)->name('progreso');
        Route::get('/guardadas', [RepasoController::class, 'guardadas'])->name('guardadas');

        Route::post('/preguntas/{pregunta}/guardar', [RepasoController::class, 'guardar'])
            ->whereNumber('pregunta')->middleware('throttle:40,1')->name('pregunta.guardar');
        Route::delete('/preguntas/{pregunta}/guardar', [RepasoController::class, 'quitar'])
            ->whereNumber('pregunta')->middleware('throttle:40,1')->name('pregunta.quitar');
        Route::post('/preguntas/{pregunta}/reportar', [RepasoController::class, 'reportar'])
            ->whereNumber('pregunta')->middleware('throttle:10,1')->name('pregunta.reportar');

        Route::post('/meta', MetaEstudioController::class)->middleware('throttle:10,1')->name('meta.guardar');
        Route::get('/resultados/{intento}/pdf', ResultadoPdfController::class)
            ->whereNumber('intento')->middleware('throttle:20,1')->name('resultados.pdf');
    });
