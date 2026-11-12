<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Alumno\CalificacionUniService;
use App\Services\Alumno\PanelUniService;
use App\Services\IntentoService;
use App\Services\ResultadoService;
use App\Support\PerfilExamenCni;
use Database\Seeders\AreaPostulacionSeeder;
use Database\Seeders\BancoUniSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Escala referencial UNI: 3 pruebas de 600 puntos, acumulado sobre 1800 y nota sobre 20. */
class CalificacionUniTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cni.alumno_areas_enabled' => true]);
        $this->seed(AreaPostulacionSeeder::class);
        $this->seed(BancoUniSeeder::class);
    }

    private function alumno(string $usuario = 'alumno'): Usuario
    {
        return Usuario::create([
            'nombres' => $usuario, 'apellidos' => 'UNI', 'usuario' => $usuario,
            'password' => 'ClaveCNI2026!', 'activo' => true, 'rol' => 'estudiante',
            'area_postulacion_id' => DB::table('areas_postulacion')->where('codigo', 'B')->value('id'),
        ]);
    }

    /** Rinde un día: las primeras $correctas bien, las siguientes $incorrectas mal, el resto en blanco. */
    private function rendir(Usuario $alumno, int $dia, int $correctas, int $incorrectas, bool $terminar = true): int
    {
        $this->actingAs($alumno, 'web')->post(route('examen.uni.dia', ['dia' => $dia]))->assertRedirect();
        $intento = (int) DB::table('intentos')->where('usuario_id', $alumno->id)
            ->where('estado', 'en_curso')->orderByDesc('id')->value('id');
        $servicio = app(IntentoService::class);
        foreach ($servicio->preguntas($intento)->values() as $i => $pregunta) {
            if ($i >= $correctas + $incorrectas) {
                break;
            }
            $alternativa = DB::table('alternativas')->where('pregunta_id', $pregunta->id)
                ->where('es_correcta', $i < $correctas)->value('id');
            $servicio->guardarRespuesta($intento, $alumno->id, ['pregunta_id' => $pregunta->id, 'alternativa_id' => $alternativa]);
        }
        if ($terminar) {
            $servicio->finalizar($intento, $alumno->id);
        }

        return $intento;
    }

    private function puntaje(int $intento): float
    {
        return (float) DB::table('intentos')->where('id', $intento)->value('puntaje_total');
    }

    private function resumen(Usuario $alumno): array
    {
        return app(PanelUniService::class)->obtener($alumno);
    }

    public function test_cada_dia_perfecto_vale_600_y_los_tres_suman_1800(): void
    {
        $alumno = $this->alumno();
        foreach (PerfilExamenCni::UNI_DIAS as $dia => $config) {
            $total = array_sum($config['cuotas']);
            $this->assertSame(600.0, $this->puntaje($this->rendir($alumno, $dia, $total, 0)), "Día {$dia}");
        }
        $r = $this->resumen($alumno)['resumen'];
        $this->assertTrue($r['completo']);
        $this->assertSame(1800.0, $r['puntaje']);
        $this->assertSame(20.0, (float) $r['nota_vigesimal']);
        $this->assertSame(180, $r['correctas']);
    }

    public function test_todo_en_blanco_vale_cero_y_no_cuenta_como_incorrecta(): void
    {
        $alumno = $this->alumno();
        $intento = $this->rendir($alumno, 1, 0, 0);
        $fila = DB::table('intentos')->where('id', $intento)->first();
        $this->assertSame(0.0, (float) $fila->puntaje_total);
        $this->assertSame(0, (int) $fila->incorrectas);
        $this->assertSame(100, (int) $fila->en_blanco);
    }

    public function test_dia_2_todo_incorrecto_da_menos_120_sin_redondear_a_cero(): void
    {
        $alumno = $this->alumno();
        $intento = $this->rendir($alumno, 2, 0, 40);
        $this->assertSame(-120.0, $this->puntaje($intento));
        $dia = $this->resumen($alumno)['dias'][2]['calificacion'];
        $this->assertSame(-120.0, $dia['puntaje']);
        $this->assertSame(0.0, $dia['ganados']);
        $this->assertSame(120.0, $dia['descontados']);
        $this->actingAs($alumno, 'web')->get(route('alumno.resultados', $intento))->assertOk()
            ->assertSee('-120.00 / 600.00')
            ->assertSee('Tu puntaje incluye penalizaciones por respuestas incorrectas.');
    }

    public function test_caso_mixto_468_345_288_suma_1101_y_equivale_a_12_23(): void
    {
        $alumno = $this->alumno();
        $this->assertSame(468.0, $this->puntaje($this->rendir($alumno, 1, 80, 10)));
        $this->assertSame(345.0, $this->puntaje($this->rendir($alumno, 2, 25, 10)));
        $this->assertSame(288.0, $this->puntaje($this->rendir($alumno, 3, 22, 14)));

        $r = $this->resumen($alumno)['resumen'];
        $this->assertSame(1101.0, $r['puntaje']);
        $this->assertSame(12.23, $r['nota_vigesimal']);
        $this->assertSame(127, $r['correctas']);
        $this->assertSame(34, $r['incorrectas']);
        $this->assertSame(19, $r['en_blanco']);
        $this->assertSame(161, $r['respondidas']);
        $this->assertSame($r['preguntas'], $r['correctas'] + $r['incorrectas'] + $r['en_blanco']);

        $this->actingAs($alumno, 'web')->get(route('alumno.panel'))->assertOk()
            ->assertSee('1.101,00')->assertSee('12,23')->assertDontSee('Resultado parcial')
            ->assertDontSee('Ingresó');
    }

    public function test_la_nota_referencial_redondea_y_usa_cero_como_minimo_visual(): void
    {
        $servicio = app(CalificacionUniService::class);
        $dia = fn (float $puntaje) => ['puntaje' => $puntaje, 'correctas' => 0, 'incorrectas' => 0, 'en_blanco' => 0, 'total_preguntas' => 0];
        $this->assertSame(12.23, $servicio->acumulado([1 => $dia(468), 2 => $dia(345), 3 => $dia(288)])['nota_vigesimal']);

        $negativo = $servicio->acumulado([1 => $dia(-120), 2 => $dia(-120), 3 => $dia(-120)]);
        $this->assertSame(-360.0, $negativo['puntaje']);
        $this->assertSame(0.0, (float) $negativo['nota_vigesimal']);
        // Decimales de la penalización del día 1 sin error de punto flotante.
        $this->assertSame(-12.0, $servicio->puntajeDia(0, 10, 90, ['correcta' => 6, 'incorrecta' => 1.2], 600)['puntaje']);
    }

    public function test_los_intentos_de_alumnos_diferentes_no_se_mezclan(): void
    {
        $ana = $this->alumno('ana');
        $luis = $this->alumno('luis');
        $this->rendir($ana, 1, 100, 0);
        $this->rendir($luis, 1, 80, 10);

        $this->assertSame(600.0, $this->resumen($ana)['dias'][1]['calificacion']['puntaje']);
        $this->assertSame(468.0, $this->resumen($luis)['dias'][1]['calificacion']['puntaje']);
        $this->assertCount(1, $this->resumen($luis)['historial']);
    }

    public function test_se_usa_el_ultimo_intento_calificado_y_se_conserva_el_historial(): void
    {
        $alumno = $this->alumno();
        $this->rendir($alumno, 2, 25, 10);
        $this->travel(1)->minutes();
        $ultimo = $this->rendir($alumno, 2, 40, 0);

        $panel = $this->resumen($alumno);
        $this->assertSame($ultimo, $panel['dias'][2]['ultimo']->id);
        $this->assertSame(600.0, $panel['resumen']['puntaje']);
        $this->assertCount(2, $panel['historial']);
    }

    public function test_un_intento_sin_terminar_no_cuenta_y_el_resultado_es_parcial(): void
    {
        $alumno = $this->alumno();
        $this->rendir($alumno, 1, 80, 10);
        $this->rendir($alumno, 2, 40, 0, terminar: false);

        $panel = $this->resumen($alumno);
        $this->assertFalse($panel['resumen']['completo']);
        $this->assertSame(1, $panel['resumen']['dias_rendidos']);
        $this->assertSame(468.0, $panel['resumen']['puntaje']);
        $this->assertNull($panel['resumen']['nota_vigesimal']);
        $this->assertNull($panel['dias'][2]['calificacion']);
        $this->actingAs($alumno, 'web')->get(route('alumno.panel'))->assertSee('Resultado parcial');
    }

    public function test_el_resultado_del_dia_coincide_con_el_resumen_y_lo_guardado(): void
    {
        $alumno = $this->alumno();
        $intento = $this->rendir($alumno, 2, 25, 10);
        $dia = $this->resumen($alumno)['dias'][2]['calificacion'];

        $this->assertSame($this->puntaje($intento), $dia['puntaje']);
        $this->assertSame(375.0, $dia['ganados']);
        $this->assertSame(30.0, $dia['descontados']);
        $this->assertSame(345.0, (float) DB::table('resultados_area')->where('intento_id', $intento)->sum('puntaje'));
        $this->actingAs($alumno, 'web')->get(route('alumno.resultados', $intento))->assertOk()
            ->assertSee('DÍA 2 — MATEMÁTICA')->assertSee('+375.00')->assertSee('−30.00')->assertSee('345.00 / 600.00');
    }

    public function test_recalificar_no_suma_ni_descuenta_dos_veces(): void
    {
        $alumno = $this->alumno();
        $intento = $this->rendir($alumno, 3, 22, 14);
        $areas = DB::table('resultados_area')->where('intento_id', $intento)->count();

        app(ResultadoService::class)->calificar($intento);
        app(ResultadoService::class)->calificar($intento);

        $this->assertSame(288.0, $this->puntaje($intento));
        $this->assertSame($areas, DB::table('resultados_area')->where('intento_id', $intento)->count());
        $this->assertSame(288.0, (float) DB::table('respuestas_estudiante')->where('intento_id', $intento)->sum('puntaje_obtenido'));
    }

    public function test_intentos_con_la_escala_anterior_no_se_recalifican_ni_se_mezclan(): void
    {
        $alumno = $this->alumno();
        // Plantilla antigua del día 1 (+20 / −1.125) con un intento ya calificado.
        $antigua = DB::table('simulacros')->insertGetId([
            'nombre' => PerfilExamenCni::prefijoDiaUni(1).'antigua000', 'tiempo_limite' => 180,
            'total_preguntas' => 100, 'cantidad_alternativas' => 5,
            'puntaje_correcta' => 20, 'puntaje_incorrecta' => -1.125, 'puntaje_blanco' => 0,
            'fecha_inicio' => '2000-01-01 00:00:00', 'fecha_fin' => '2099-12-31 23:59:59', 'activo' => true,
        ]);
        DB::table('intentos')->insert([
            'usuario_id' => $alumno->id, 'tipo' => 'simulacro', 'simulacro_id' => $antigua,
            'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->subDay(), 'estado' => 'finalizado',
            'total_preguntas' => 100, 'correctas' => 90, 'incorrectas' => 10, 'en_blanco' => 0,
            'doble_marca' => 0, 'puntaje_total' => 1788.75,
        ]);

        $panel = $this->resumen($alumno);
        $this->assertNull($panel['dias'][1]['calificacion']);
        $this->assertSame(0, $panel['resumen']['dias_rendidos']);
        $this->assertCount(1, $panel['historial']);
        $this->assertSame(1788.75, (float) DB::table('intentos')->where('simulacro_id', $antigua)->value('puntaje_total'));
    }
}
