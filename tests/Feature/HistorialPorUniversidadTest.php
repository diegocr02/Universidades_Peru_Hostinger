<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Alumno\PanelUnfvService;
use App\Services\Alumno\PanelUniService;
use App\Services\Alumno\PanelUnmsmService;
use App\Services\Alumno\ProgresoAlumnoService;
use Database\Seeders\AreaPostulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Cada panel muestra solo los intentos de su universidad, aunque el alumno haya cambiado de universidad. */
class HistorialPorUniversidadTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $alumno;

    /** @var array<string, int> universidad => id del intento */
    private array $intentos = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cni.alumno_areas_enabled' => true]);
        $this->seed(AreaPostulacionSeeder::class);
        $this->alumno = Usuario::create([
            'nombres' => 'Alumno', 'apellidos' => 'Viajero', 'usuario' => 'viajero',
            'password' => 'ClaveCNI2026!', 'activo' => true, 'rol' => 'estudiante',
            'area_postulacion_id' => $this->area('B'),
        ]);

        $examenes = [
            'UNDC' => ['UNDC - Admisión Ordinaria Tipo A (preparación)', ['A', 'B', 'C', 'D', 'E']],
            'UNI' => ['Examen UNI · Día 1 · abc123', ['B']],
            'UNFV' => ['Práctica general UNFV · abc · def', ['C']],
            'SAN_MARCOS' => ['Examen San Marcos · Área A · abc123', ['E']],
            'AREA_D' => ['Simulacro general CNI · Área D', ['D']],
        ];
        foreach ($examenes as $clave => [$nombre, $areas]) {
            $simulacro = DB::table('simulacros')->insertGetId([
                'nombre' => $nombre, 'tiempo_limite' => 60, 'total_preguntas' => 10,
                'puntaje_correcta' => 20, 'puntaje_incorrecta' => -1.125, 'puntaje_blanco' => 0,
                'fecha_inicio' => '2000-01-01 00:00:00', 'fecha_fin' => '2099-12-31 23:59:59', 'activo' => true,
            ]);
            foreach ($areas as $codigo) {
                DB::table('area_postulacion_simulacro')->insert([
                    'simulacro_id' => $simulacro, 'area_postulacion_id' => $this->area($codigo),
                ]);
            }
            $this->intentos[$clave] = DB::table('intentos')->insertGetId([
                'usuario_id' => $this->alumno->id, 'simulacro_id' => $simulacro, 'tipo' => 'simulacro',
                'estado' => 'finalizado', 'fecha_inicio' => now()->subHour(), 'fecha_fin' => now(),
                'total_preguntas' => 10, 'correctas' => 5, 'incorrectas' => 0, 'en_blanco' => 5,
                'puntaje_total' => 100,
            ]);
        }
    }

    private function area(string $codigo): int
    {
        return (int) DB::table('areas_postulacion')->where('codigo', $codigo)->value('id');
    }

    public function test_cada_universidad_ve_solo_sus_intentos(): void
    {
        $ids = fn ($coleccion) => collect($coleccion)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame([$this->intentos['UNI']], $ids(app(PanelUniService::class)->obtener($this->alumno)['historial']));
        $this->assertSame([$this->intentos['SAN_MARCOS']], $ids(app(PanelUnmsmService::class)->obtener($this->alumno)['historial']));
        $this->assertSame([$this->intentos['UNFV']], $ids(app(PanelUnfvService::class)->obtener($this->alumno)['historial']));

        $progreso = app(ProgresoAlumnoService::class);
        foreach (['A' => 'UNDC', 'B' => 'UNI', 'C' => 'UNFV', 'E' => 'SAN_MARCOS', 'D' => 'AREA_D'] as $codigo => $clave) {
            $this->assertSame([$this->intentos[$clave]], collect($progreso->obtener($this->alumno, $codigo)['puntos'])->pluck('id')->all(),
                "La gráfica de progreso del área {$codigo} mezcla universidades.");
        }
    }

    public function test_paneles_undc_y_general_no_mezclan_el_historial(): void
    {
        foreach (['A' => 'UNDC', 'D' => 'AREA_D'] as $codigo => $clave) {
            $this->alumno->forceFill(['area_postulacion_id' => $this->area($codigo)])->save();
            $intentos = $this->actingAs($this->alumno->fresh(), 'web')->get(route('alumno.panel'))
                ->assertOk()->viewData('intentos');
            $this->assertSame([$this->intentos[$clave]], $intentos->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all(),
                "El historial del área {$codigo} mezcla universidades.");
        }
    }

    public function test_al_cambiar_de_universidad_el_panel_nuevo_no_muestra_intentos_anteriores(): void
    {
        $this->alumno->forceFill(['area_postulacion_id' => $this->area('C')])->save();
        $html = $this->actingAs($this->alumno->fresh(), 'web')->get(route('alumno.panel'))->assertOk()->getContent();

        $this->assertStringContainsString('#'.str_pad((string) $this->intentos['UNFV'], 3, '0', STR_PAD_LEFT), $html);
        foreach (['UNDC', 'UNI', 'SAN_MARCOS', 'AREA_D'] as $clave) {
            $this->assertStringNotContainsString(route('alumno.resultados', ['intento' => $this->intentos[$clave]]), $html);
        }
    }
}
