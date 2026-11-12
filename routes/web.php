<?php

use App\Http\Controllers\SessionController;
use App\Http\Controllers\Admin\BancoPreguntasController;
use App\Http\Controllers\Admin\VaciarBancoPreguntasController;
use App\Http\Controllers\Admin\BancoGestionController;
use App\Http\Controllers\Admin\CatalogoController;
use App\Http\Controllers\Admin\SimulacrosController;
use App\Http\Middleware\SoloAdministrador;
use App\Http\Controllers\ExamenController;
use App\Http\Controllers\Alumno\OrientacionController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RedirigirPanelEstudiante;
use App\Http\Middleware\FeatureAlumnoAreas;
use App\Livewire\Panel;
use App\Livewire\Examen;
use App\Livewire\Docente\PanelDocente;
use App\Livewire\Docente\CalificacionesSalon;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('inicio');

Route::middleware('guest:web')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [SessionController::class, 'store'])
        ->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth:web')->name('logout');

Route::get('/panel', Panel::class)
    ->middleware(['auth:web', EnsureActiveUser::class, RedirigirPanelEstudiante::class])->name('panel');

Route::middleware(['auth:web', EnsureActiveUser::class, FeatureAlumnoAreas::class])
    ->prefix('alumno')->name('alumno.')->group(function () {
        Route::get('/cambiar-universidad', [OrientacionController::class, 'cambiarUniversidad'])->name('universidad.cambiar');
        Route::post('/cambiar-universidad', [OrientacionController::class, 'guardarUniversidad'])->name('universidad.guardar');
        Route::get('/elegir-area', [OrientacionController::class, 'elegirArea'])->name('elegir-area');
        Route::post('/elegir-area', [OrientacionController::class, 'guardarArea'])->name('area.guardar');
        Route::get('/panel', [OrientacionController::class, 'panel'])->name('panel');
        Route::post('/carrera', [OrientacionController::class, 'guardarCarrera'])->name('carrera.guardar');
        Route::post('/unmsm/area', [OrientacionController::class, 'guardarAreaUnmsm'])->name('unmsm.area');
        Route::get('/tema/{tema}', [OrientacionController::class, 'tema'])
            ->whereNumber('tema')->name('tema');
        Route::get('/resultados/{intento}', [OrientacionController::class, 'resultados'])
            ->whereNumber('intento')->name('resultados');
    });

// Portales de área independientes; los grupos internos ya aplican guard web y permisos.
require __DIR__.'/alumno-ciencias-basicas.php';
require __DIR__.'/alumno-humanidades.php';

// Herramientas internas de administración; nunca disponibles para estudiantes/docentes.
Route::middleware(['auth:web', EnsureActiveUser::class, SoloAdministrador::class])
    ->prefix('administracion')->name('admin.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.subtemas.index'))->name('inicio');
        Route::get('/alumnos', [\App\Http\Controllers\Admin\AlumnosImportacionController::class, 'index'])->name('alumnos.index');
        Route::get('/alumnos/plantilla', [\App\Http\Controllers\Admin\AlumnosImportacionController::class, 'plantilla'])->name('alumnos.plantilla');
        Route::post('/alumnos/validar', [\App\Http\Controllers\Admin\AlumnosImportacionController::class, 'validar'])->middleware('throttle:6,1')->name('alumnos.validar');
        Route::post('/alumnos/importar', [\App\Http\Controllers\Admin\AlumnosImportacionController::class, 'cargar'])->middleware('throttle:6,1')->name('alumnos.cargar');
        Route::get('/subtemas', [BancoGestionController::class, 'subtemas'])->name('subtemas.index');
        Route::get('/importar-excel', [BancoGestionController::class, 'importar'])->name('importar.index');
        Route::get('/importar-excel/plantilla', [BancoGestionController::class, 'plantilla'])->name('importar.plantilla');
        Route::post('/importar-excel/validar', [BancoGestionController::class, 'validarExcel'])->middleware('throttle:6,1')->name('importar.validar');
        Route::post('/importar-excel', [BancoGestionController::class, 'cargar'])->middleware('throttle:6,1')->name('importar.cargar');
        Route::post('/catalogo/asignaturas', [CatalogoController::class, 'asignatura'])->name('asignatura.guardar');
        Route::post('/catalogo/temas', [CatalogoController::class, 'tema'])->name('tema.guardar');
        Route::post('/catalogo/carreras', [CatalogoController::class, 'carrera'])->name('carrera.guardar');
        Route::get('/preguntas', [BancoPreguntasController::class, 'index'])->name('preguntas.index');
        Route::delete('/preguntas/todas', VaciarBancoPreguntasController::class)->middleware('throttle:3,10')->name('preguntas.destroy-all');
        Route::get('/preguntas/{pregunta}', [BancoPreguntasController::class, 'show'])->whereNumber('pregunta')->name('preguntas.show');
        Route::delete('/preguntas/{pregunta}', [BancoPreguntasController::class, 'destroy'])->whereNumber('pregunta')->name('preguntas.destroy');
        Route::get('/simulacros', [SimulacrosController::class, 'index'])->name('simulacros.index');
        Route::get('/simulacros/nuevo', [SimulacrosController::class, 'create'])->name('simulacros.create');
        Route::post('/simulacros', [SimulacrosController::class, 'store'])->name('simulacros.store');
    });

// Pantalla de examen del alumno; reanuda intentos propios y valida tiempo en el servidor.
Route::middleware(['auth:web', EnsureActiveUser::class, FeatureAlumnoAreas::class])->group(function () {
    Route::get('/alumno/examen/vista-previa', Examen::class)->name('examen.vacio');
    Route::post('/alumno/examen/iniciar', [ExamenController::class, 'independiente'])
        ->name('examen.independiente');
    Route::post('/alumno/examen/uni/{dia}', [ExamenController::class, 'diaUni'])
        ->whereIn('dia', ['1', '2', '3'])->name('examen.uni.dia');
    Route::post('/alumno/examen/unmsm', [ExamenController::class, 'unmsm'])->name('examen.unmsm');
    Route::post('/simulacros/{simulacro}/iniciar', [ExamenController::class, 'iniciar'])
        ->whereNumber('simulacro')->name('examen.iniciar');
    Route::get('/intentos/{intento}/examen', Examen::class)
        ->whereNumber('intento')->name('examen');
});

// Compatibilidad con los módulos existentes: JSON con sesión y CSRF.
Route::prefix('api')->group(base_path('routes/api.php'));

Route::get('/docente', PanelDocente::class)
    ->middleware(['auth:web', EnsureActiveUser::class])->name('docente.panel');

Route::get('/docente/calificaciones', CalificacionesSalon::class)
    ->middleware(['auth:web', EnsureActiveUser::class])->name('docente.calificaciones');

// Exportación de calificaciones limitada a la sección del docente autenticado.
Route::get('/docente/exportar', \App\Http\Controllers\Docente\ExportarCalificacionesController::class)
    ->middleware(['auth:web', EnsureActiveUser::class])
    ->name('docente.exportar');

// Vistas previas de diseño sin autenticación: exclusivas del entorno local.
if (app()->environment('local')) {
    // Vista previa visual sin usuario ni MariaDB. No procesa elecciones reales.
    Route::get('/preview-areas', function () {
        $areas = collect([
            ['id' => 1, 'codigo' => 'A', 'nombre' => 'Ciencias de la Salud'],
            ['id' => 2, 'codigo' => 'B', 'nombre' => 'Ciencias Básicas'],
            ['id' => 3, 'codigo' => 'C', 'nombre' => 'Ingenierías'],
            ['id' => 4, 'codigo' => 'D', 'nombre' => 'Ciencias Económicas y de la Gestión'],
            ['id' => 5, 'codigo' => 'E', 'nombre' => 'Humanidades y Ciencias Jurídicas y Sociales'],
        ])->map(fn (array $area) => (object) $area);

        return view('alumno.elegir-area', compact('areas'));
    })->name('alumno.preview-areas');

    Route::view('/docente-preview', 'livewire.docente.panel-docente');
    Route::redirect('/preview-docente', '/docente-preview');

    Route::get('/docente/calificaciones-preview', function () {
        return view('livewire.docente.calificaciones-salon', [
            'seccion' => null,
            'totalEstudiantes' => 0,
            'totalEvaluaciones' => 0,
            'promedioSeccion' => null,
            'alumnos' => collect(),
        ]);
    });
}

// Panel administrativo de respaldo cifrado (desactivado por defecto).
require __DIR__.'/contingencia.php';
