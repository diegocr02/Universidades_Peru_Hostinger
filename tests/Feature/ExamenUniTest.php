<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\IntentoService;
use App\Support\PerfilExamenCni;
use Database\Seeders\AreaPostulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamenUniTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cni.alumno_areas_enabled' => true]);
        $this->seed(AreaPostulacionSeeder::class);
    }

    private function alumno(string $codigo, string $usuario = 'alumno'): Usuario
    {
        return Usuario::create([
            'nombres' => $usuario, 'apellidos' => 'CNI', 'usuario' => $usuario,
            'password' => 'ClaveCNI2026!', 'activo' => true, 'rol' => 'estudiante',
            'area_postulacion_id' => DB::table('areas_postulacion')->where('codigo', $codigo)->value('id'),
        ]);
    }

    /** Banco suficiente para cualquiera de los tres días UNI. */
    private function banco(): void
    {
        foreach (PerfilExamenCni::CURSOS as $prefijo => [$nombre, $bloque]) {
            $area = DB::table('areas')->insertGetId(['nombre' => $nombre, 'bloque' => $bloque, 'activo' => true]);
            $tema = DB::table('temas')->insertGetId(['area_id' => $area, 'nombre' => $nombre, 'activo' => true]);
            $cantidad = max(array_map(fn ($d) => $d['cuotas'][$prefijo] ?? 0, PerfilExamenCni::UNI_DIAS)) + 3;
            for ($i = 0; $i < $cantidad; $i++) {
                $pregunta = DB::table('preguntas')->insertGetId([
                    'tema_id' => $tema, 'enunciado' => $nombre.' pregunta '.$i,
                    'nivel' => 'intermedio', 'solucion' => 'Solución', 'activo' => true,
                ]);
                foreach (['A', 'B', 'C', 'D', 'E'] as $letra) {
                    DB::table('alternativas')->insert([
                        'pregunta_id' => $pregunta, 'letra' => $letra,
                        'texto' => 'Alternativa '.$letra, 'es_correcta' => $letra === 'A',
                    ]);
                }
            }
        }
    }

    public function test_el_panel_del_area_b_muestra_los_tres_dias(): void
    {
        $this->actingAs($this->alumno('B'), 'web')
            ->get(route('alumno.panel'))
            ->assertOk()
            ->assertViewIs('alumno.uni.panel')
            ->assertSee('Día 1')->assertSee('Día 2')->assertSee('Día 3')
            ->assertSee('Aptitud Académica y Humanidades')
            ->assertSee(route('examen.uni.dia', ['dia' => 3]), false);
    }

    public function test_otras_areas_conservan_su_panel(): void
    {
        $this->actingAs($this->alumno('C'), 'web')
            ->get(route('alumno.panel'))
            ->assertOk()
            ->assertViewIs('alumno.panel');
    }

    public function test_cada_dia_crea_su_examen_con_cuotas_y_180_minutos(): void
    {
        $this->banco();
        $alumno = $this->alumno('B');
        foreach (PerfilExamenCni::UNI_DIAS as $dia => $config) {
            $this->actingAs($alumno, 'web')
                ->post(route('examen.uni.dia', ['dia' => $dia]))
                ->assertRedirect();
            $intento = DB::table('intentos as i')->join('simulacros as s', 's.id', '=', 'i.simulacro_id')
                ->where('i.usuario_id', $alumno->id)
                ->where('s.nombre', 'like', PerfilExamenCni::prefijoDiaUni($dia).'%')
                ->select('i.*')->first();
            $this->assertNotNull($intento);
            $this->assertSame(array_sum($config['cuotas']), (int) $intento->total_preguntas);
            $this->assertEquals(
                Carbon::parse($intento->fecha_inicio)->addMinutes(180),
                Carbon::parse($intento->fecha_limite)
            );
            $porCurso = app(IntentoService::class)->preguntas($intento->id)->countBy('area')->all();
            foreach ($config['cuotas'] as $prefijo => $cantidad) {
                $this->assertSame($cantidad, $porCurso[PerfilExamenCni::CURSOS[$prefijo][0]] ?? 0, "Día $dia · $prefijo");
            }
        }
    }

    public function test_volver_a_entrar_reanuda_el_intento_abierto(): void
    {
        $this->banco();
        $alumno = $this->alumno('B');
        $this->actingAs($alumno, 'web')->post(route('examen.uni.dia', ['dia' => 2]));
        $this->actingAs($alumno, 'web')->post(route('examen.uni.dia', ['dia' => 2]));
        $this->assertSame(1, DB::table('intentos')->where('usuario_id', $alumno->id)->count());

        $this->actingAs($alumno, 'web')->get(route('alumno.panel'))->assertSee('Continuar');
    }

    public function test_alumnos_de_otras_areas_no_pueden_rendir_dias_uni(): void
    {
        $this->banco();
        $this->actingAs($this->alumno('A'), 'web')
            ->post(route('examen.uni.dia', ['dia' => 1]))
            ->assertForbidden();
    }

    public function test_dia_inexistente_no_tiene_ruta(): void
    {
        $this->actingAs($this->alumno('B'), 'web')
            ->post('/alumno/examen/uni/4')
            ->assertNotFound();
    }

    public function test_el_banco_uni_alcanza_para_los_tres_dias_y_no_se_duplica(): void
    {
        $this->seed(\Database\Seeders\BancoUniSeeder::class);
        $this->seed(\Database\Seeders\BancoUniSeeder::class);
        $total = collect(PerfilExamenCni::UNI_DIAS)->sum(fn ($d) => array_sum($d['cuotas']));
        $this->assertSame($total, DB::table('preguntas')->where('codigo', 'like', 'UNI%')->count());
        // Cada pregunta tiene exactamente una clave y la clave no se concentra en una sola letra.
        $claves = DB::table('alternativas')->where('es_correcta', true)->pluck('letra');
        $this->assertCount($total, $claves);
        $this->assertCount(5, $claves->unique());

        $alumno = $this->alumno('B');
        foreach (array_keys(PerfilExamenCni::UNI_DIAS) as $dia) {
            $this->actingAs($alumno, 'web')->post(route('examen.uni.dia', ['dia' => $dia]))
                ->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame(3, DB::table('intentos')->where('usuario_id', $alumno->id)->count());
    }

    public function test_al_terminar_el_alumno_revisa_sus_aciertos_y_errores_con_la_solucion(): void
    {
        $this->seed(\Database\Seeders\BancoUniSeeder::class);
        $alumno = $this->alumno('B');
        $this->actingAs($alumno, 'web')->post(route('examen.uni.dia', ['dia' => 3]));
        $intento = DB::table('intentos')->where('usuario_id', $alumno->id)->first();
        $servicio = app(IntentoService::class);
        [$primera, $segunda] = $servicio->preguntas($intento->id)->take(2)->values()->all();
        $clave = fn ($p, bool $correcta) => DB::table('alternativas')
            ->where('pregunta_id', $p->id)->where('es_correcta', $correcta)->value('id');
        $servicio->guardarRespuesta($intento->id, $alumno->id, ['pregunta_id' => $primera->id, 'alternativa_id' => $clave($primera, true)]);
        $servicio->guardarRespuesta($intento->id, $alumno->id, ['pregunta_id' => $segunda->id, 'alternativa_id' => $clave($segunda, false)]);

        // Antes de terminar no se revelan claves ni soluciones.
        $this->get(route('alumno.resultados', $intento->id))->assertDontSee('Revisión de preguntas');

        $servicio->finalizar($intento->id, $alumno->id);
        $solucion = DB::table('preguntas')->where('id', $segunda->id)->value('solucion');
        $this->get(route('alumno.resultados', $intento->id))->assertOk()
            ->assertSee('Revisión de preguntas y respuestas')
            ->assertSee('✓ Tu respuesta · correcta')
            ->assertSee('✕ Tu respuesta')
            ->assertSee('— Sin responder')
            ->assertSee($solucion)
            ->assertSee('Todas (40)')->assertSee('Incorrectas (1)')->assertSee('Correctas (1)')->assertSee('En blanco (38)');

        // Otro alumno no puede ver la revisión ajena.
        $this->actingAs($this->alumno('B', 'otro'), 'web')
            ->get(route('alumno.resultados', $intento->id))->assertNotFound();
    }

    public function test_banco_incompleto_vuelve_al_panel_con_el_motivo(): void
    {
        $alumno = $this->alumno('B');
        $this->actingAs($alumno, 'web')
            ->from(route('alumno.panel'))
            ->post(route('examen.uni.dia', ['dia' => 3]))
            ->assertRedirect(route('alumno.panel'))
            ->assertSessionHasErrors('preguntas');
        $this->assertSame(0, DB::table('intentos')->where('usuario_id', $alumno->id)->count());
    }
}
