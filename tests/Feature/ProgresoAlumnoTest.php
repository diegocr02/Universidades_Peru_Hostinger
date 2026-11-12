<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Alumno\ProgresoAlumnoService;
use Database\Seeders\AreaPostulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgresoAlumnoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cni.alumno_areas_enabled' => true]);
        $this->seed(AreaPostulacionSeeder::class);
    }

    private function estudiante(string $nombre): Usuario
    {
        return Usuario::create([
            'nombres' => $nombre, 'apellidos' => 'CNI', 'usuario' => $nombre,
            'password' => 'ClaveCNI2026!', 'activo' => true, 'rol' => 'estudiante',
            'area_postulacion_id' => DB::table('areas_postulacion')->where('codigo', 'C')->value('id'),
        ]);
    }

    private function simulacro(string $nombre, int $puntosPorAcierto): int
    {
        $id = DB::table('simulacros')->insertGetId([
            'nombre' => $nombre, 'tiempo_limite' => 60,
            'total_preguntas' => 10, 'cantidad_alternativas' => 4,
            'puntaje_correcta' => $puntosPorAcierto, 'puntaje_incorrecta' => -1,
            'puntaje_blanco' => 0,
            'fecha_inicio' => now()->subWeek(), 'fecha_fin' => now()->addWeek(),
            'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // El progreso solo cuenta exámenes de la universidad del alumno (área C).
        DB::table('area_postulacion_simulacro')->insert([
            'simulacro_id' => $id,
            'area_postulacion_id' => DB::table('areas_postulacion')->where('codigo', 'C')->value('id'),
        ]);

        return $id;
    }

    private function intento(int $usuarioId, int $simulacroId, ?float $puntaje, int $dias): void
    {
        DB::table('intentos')->insert([
            'usuario_id' => $usuarioId, 'simulacro_id' => $simulacroId,
            'tipo' => 'simulacro',
            'estado' => $puntaje === null ? 'en_curso' : 'finalizado',
            'fecha_inicio' => now()->subDays($dias),
            'fecha_fin' => $puntaje === null ? null : now()->subDays($dias)->addHour(),
            'total_preguntas' => 10, 'puntaje_total' => $puntaje,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_muestra_solo_intentos_calificados_del_alumno_y_normaliza_escalas_diferentes(): void
    {
        $alumno = $this->estudiante('DiegoPrueba');
        $otro = $this->estudiante('OtroPrueba');
        $s1 = $this->simulacro('Examen de 20 puntos', 2);
        $s2 = $this->simulacro('Examen de 40 puntos', 4);

        $this->intento($alumno->id, $s1, 10, 3); // 50%
        $this->intento($alumno->id, $s2, 30, 2); // 75%
        $this->intento($alumno->id, $s2, null, 1); // En curso: NO cuenta
        $this->intento($otro->id, $s2, 40, 1); // Otro alumno: NO cuenta

        $progreso = app(ProgresoAlumnoService::class)->obtener($alumno);
        $this->assertSame(2, $progreso['cantidad']);
        $this->assertFalse($progreso['truncado']);
        $this->assertSame([50.0, 75.0], array_column($progreso['puntos'], 'porcentaje'));
        $this->assertSame(25.0, $progreso['diferencia']);
        $this->assertSame(30.0, $progreso['ultimo']['puntaje']);
        $this->assertSame(40.0, $progreso['ultimo']['maximo']);
        $this->assertCount(2, $progreso['puntos']);
        $this->assertStringContainsString('L ', $progreso['trayectoria']);

        $this->actingAs($alumno, 'web')->get(route('alumno.panel'))
            ->assertOk()
            ->assertSee('Mi progreso')
            ->assertSee('Evolución de tu puntaje')
            ->assertSee('30,00 de 40,00 puntos')
            ->assertDontSee('Tu último intento')
            ->assertDontSee('Simulacros disponibles completados')
            ->assertSee('Examen de 40 puntos')
            ->assertDontSee('Nivel Básico');
    }

    public function test_sin_intentos_muestra_estado_vacio_sin_puntajes_inventados(): void
    {
        $alumno = $this->estudiante('SinIntentos');
        $progreso = app(ProgresoAlumnoService::class)->obtener($alumno);

        $this->assertSame(0, $progreso['cantidad']);
        $this->assertNull($progreso['ultimo']);
        $this->assertSame([], $progreso['puntos']);

        $this->actingAs($alumno, 'web')
            ->get(route('alumno.panel'))->assertOk()
            ->assertSee('Completa tu primer simulacro para ver tu progreso.')
            ->assertDontSee('8% avance');
    }

    public function test_no_usa_una_nota_historica_cuando_no_existe_resultado_calificado(): void
    {
        $alumno = $this->estudiante('SinCalificacion');
        $s1 = $this->simulacro('Pendiente', 3);
        $this->intento($alumno->id, $s1, null, 1);

        $progreso = app(ProgresoAlumnoService::class)->obtener($alumno);
        $this->assertNull($progreso['ultimo']);
        $this->assertSame(0, $progreso['cantidad']);
    }
}
