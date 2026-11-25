<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Database\Seeders\AreaPostulacionSeeder;
use Database\Seeders\CarreraSeeder;
use Database\Seeders\ExamenDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CienciasBasicasPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AreaPostulacionSeeder::class);
        $this->seed(CarreraSeeder::class);
        $this->seed(ExamenDemoSeeder::class);
    }

    private function area(string $codigo): int
    {
        return DB::table('areas_postulacion')->where('codigo', $codigo)->value('id');
    }

    private function usuario(array $datos = []): Usuario
    {
        return Usuario::create(array_merge([
            'nombres' => 'Diego Andrés', 'apellidos' => 'Camayo Ruiz', 'usuario' => uniqid('alumno'),
            'password' => 'ClaveCNI2026!', 'rol' => 'estudiante', 'activo' => true,
        ], $datos));
    }

    private function alumnoB(): Usuario
    {
        return $this->usuario(['area_postulacion_id' => $this->area('B')]);
    }

    public function test_solo_abre_para_estudiantes_del_area_b_y_respeta_el_flujo_principal(): void
    {
        $this->get('/alumno/ciencias-basicas')->assertRedirect(route('login'));
        $this->actingAs($this->usuario(['rol' => 'docente']), 'web')->get('/alumno/ciencias-basicas')->assertForbidden();

        // Sin área o de otra área: vuelve al flujo del panel principal sin cambiar el perfil.
        $sinArea = $this->usuario();
        $this->actingAs($sinArea, 'web')->get('/alumno/ciencias-basicas')->assertRedirect(route('alumno.elegir-area'));
        $ingenieria = $this->usuario(['area_postulacion_id' => $this->area('C')]);
        $this->actingAs($ingenieria, 'web')->get('/alumno/ciencias-basicas')->assertRedirect(route('alumno.panel'));
        $this->assertNull($sinArea->fresh()->area_postulacion_id);
        $this->assertSame($this->area('C'), $ingenieria->fresh()->area_postulacion_id);

        // Cuenta antigua con carrera del área B y sin área explícita.
        $matematica = DB::table('carreras')->where('nombre', 'Matemática')->value('id');
        $this->actingAs($this->usuario(['carrera_id' => $matematica]), 'web')->get('/alumno/ciencias-basicas')->assertRedirect(route('alumno.panel'));

        config(['cni.alumno_areas_enabled' => false]);
        $this->actingAs($this->alumnoB(), 'web')->get('/alumno/ciencias-basicas')->assertNotFound();
    }

    public function test_panel_muestra_el_diseno_sin_datos_inventados_y_no_reemplaza_el_principal(): void
    {
        $alumno = $this->alumnoB();
        $this->actingAs($alumno, 'web')->get('/alumno/ciencias-basicas')
            ->assertRedirect(route('alumno.panel'));
        $this->get(route('alumno.panel'))->assertOk()
            // El área B (UNI) usa su panel propio de tres días.
            ->assertViewIs('alumno.uni.panel')
            ->assertSee('alumno/panel-uni.css')->assertSee('Examen UNI')
            ->assertSee('Diego Andrés')
            ->assertDontSee('1240 preguntas');
    }
}
