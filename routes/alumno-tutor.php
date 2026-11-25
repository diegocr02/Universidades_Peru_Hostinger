<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\FeatureAlumnoAreas;
use App\Livewire\Alumno\Tutor\TutorChat;
use Illuminate\Support\Facades\Route;

// Tutor CNI: solo estudiantes con sesión activa. Se desactiva con TUTOR_ENABLED=false.
Route::get('/alumno/tutor', TutorChat::class)
    ->middleware(['auth:web', EnsureActiveUser::class, FeatureAlumnoAreas::class])
    ->name('alumno.tutor');
