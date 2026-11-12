<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Database\Seeders\AreaPostulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistorialPanelAlumnoTest extends TestCase
{
    use RefreshDatabase;

    public function test_historial_filtra_ordena_y_mantiene_los_intentos_del_alumno(): void
    {
        config(['cni.alumno_areas_enabled' => true]);
        $this->seed(AreaPostulacionSeeder::class);
        $area = DB::table('areas_postulacion')->where('codigo', 'D')->value('id');
        $alumno = Usuario::create([
            'nombres' => 'Alumno', 'apellidos' => 'Historial', 'usuario' => 'historial',
            'password' => 'ClaveCNI2026!', 'activo' => true, 'rol' => 'estudiante',
            'area_postulacion_id' => $area,
        ]);
        $otro = Usuario::create([
            'nombres' => 'Otro', 'apellidos' => 'Alumno', 'usuario' => 'otro_historial',
            'password' => 'ClaveCNI2026!', 'activo' => true, 'rol' => 'estudiante',
            'area_postulacion_id' => $area,
        ]);
        $simulacro = DB::table('simulacros')->insertGetId([
            'nombre' => 'Prueba historial', 'tiempo_limite' => 60,
            'total_preguntas' => 10, 'cantidad_alternativas' => 4,
            'puntaje_correcta' => 2, 'puntaje_incorrecta' => -1, 'puntaje_blanco' => 0,
            'fecha_inicio' => now()->subWeek(), 'fecha_fin' => now()->addWeek(),
            'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // El historial solo muestra exámenes de la universidad del panel.
        DB::table('area_postulacion_simulacro')->insert(['simulacro_id' => $simulacro, 'area_postulacion_id' => $area]);
        $crear = function (int $usuario, ?float $puntaje) use ($simulacro) {
            return DB::table('intentos')->insertGetId([
                'usuario_id' => $usuario, 'simulacro_id' => $simulacro,
                'tipo' => 'simulacro', 'estado' => $puntaje === null ? 'en_curso' : 'finalizado',
                'fecha_inicio' => now(), 'fecha_fin' => $puntaje === null ? null : now(),
                'total_preguntas' => 10, 'puntaje_total' => $puntaje,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        $calificado = $crear($alumno->id, 12);
        $enCurso = $crear($alumno->id, null);
        $crear($otro->id, 20);

        $this->actingAs($alumno, 'web')
            ->get(route('alumno.panel', ['estado' => 'calificado', 'orden' => 'puntaje']))
            ->assertOk()
            ->assertViewHas('intentos', fn ($filas) =>
                $filas->total() === 1 && $filas->first()->id === $calificado)
            ->assertSee('Historial de simulacros');

        $this->get(route('alumno.panel', ['buscar' => '#'.$enCurso, 'estado' => 'en_curso']))
            ->assertOk()
            ->assertViewHas('intentos', fn ($filas) =>
                $filas->total() === 1 && $filas->first()->id === $enCurso);

        for ($i = 0; $i < 11; $i++) {
            $crear($alumno->id, $i);
        }
        $this->get(route('alumno.panel', ['buscar' => 'Prueba historial', 'orden' => 'puntaje']))
            ->assertOk()
            ->assertViewHas('intentos', fn ($filas) =>
                $filas->total() === 13 && $filas->count() === 10
                && $filas->first()->id === $calificado
                && str_contains($filas->nextPageUrl(), '#historial'));
    }
}
