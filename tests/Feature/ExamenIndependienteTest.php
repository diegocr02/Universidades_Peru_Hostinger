<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Alumno\ExamenIndependienteService;
use App\Services\IntentoService;
use App\Support\PerfilExamenCni;
use Database\Seeders\AreaPostulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamenIndependienteTest extends TestCase
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

    private function banco(): void
    {
        foreach (PerfilExamenCni::CURSOS as $prefijo => [$nombre, $bloque]) {
            $area = DB::table('areas')->insertGetId([
                'nombre' => $nombre, 'bloque' => $bloque, 'activo' => true,
            ]);
            $tema = DB::table('temas')->insertGetId([
                'area_id' => $area, 'nombre' => $nombre, 'activo' => true,
            ]);
            $cantidad = max(array_map(fn ($d) => $d[$prefijo] ?? 0, PerfilExamenCni::DISTRIBUCION)) + 5;
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

    public function test_cada_area_genera_cien_preguntas_con_sus_cuotas_y_el_orden_acordado(): void
    {
        $this->banco();
        foreach (PerfilExamenCni::DISTRIBUCION as $codigo => $cuotas) {
            $alumno = $this->alumno($codigo, 'alumno_'.$codigo);
            $this->actingAs($alumno, 'web')
                ->post(route('examen.independiente'), ['area_id' => 999])
                ->assertRedirect();
            $intento = DB::table('intentos')->where('usuario_id', $alumno->id)->first();
            $this->assertNotNull($intento);
            $this->assertSame(100, (int) $intento->total_preguntas);
            $this->assertEquals(
                \Illuminate\Support\Carbon::parse($intento->fecha_inicio)->addMinutes(60),
                \Illuminate\Support\Carbon::parse($intento->fecha_limite)
            );
            $preguntas = app(IntentoService::class)->preguntas($intento->id);
            $this->assertCount(100, $preguntas->pluck('id')->unique());
            $posiciones = $preguntas->map(fn ($p) => PerfilExamenCni::posicion($p->area))->all();
            $ordenadas = $posiciones;
            sort($ordenadas);
            $this->assertSame($ordenadas, $posiciones);
            foreach ($cuotas as $prefijo => $cantidad) {
                $this->assertCount($cantidad, $preguntas->where('area', PerfilExamenCni::CURSOS[$prefijo][0]));
            }
            $this->assertDatabaseHas('area_postulacion_simulacro', [
                'simulacro_id' => $intento->simulacro_id, 'area_postulacion_id' => $alumno->area_postulacion_id,
            ]);
            $this->get(route('examen', $intento->id))->assertOk();
        }
        $this->assertDatabaseCount('simulacros', 5);
    }

    public function test_reanuda_sin_resortear_ni_reiniciar_el_tiempo_y_luego_permite_otro_intento(): void
    {
        $this->banco();
        $alumno = $this->alumno('C');
        $servicio = app(ExamenIndependienteService::class);
        $primero = $servicio->iniciar($alumno);
        $preguntas = app(IntentoService::class)->preguntas($primero->id);
        $this->travel(2)->minutes();
        $reanudado = $servicio->iniciar($alumno);
        $this->assertSame($primero->id, $reanudado->id);
        $this->assertSame($primero->fecha_limite, $reanudado->fecha_limite);
        $this->assertSame($preguntas->pluck('id')->all(), app(IntentoService::class)->preguntas($reanudado->id)->pluck('id')->all());
        app(IntentoService::class)->finalizar($primero->id, $alumno->id);
        $nuevo = $servicio->iniciar($alumno);
        $this->assertNotSame($primero->id, $nuevo->id);
        $this->assertNotSame($preguntas->pluck('id')->all(), app(IntentoService::class)->preguntas($nuevo->id)->pluck('id')->all());
        $this->assertDatabaseCount('simulacros', 1);

        $otro = $this->alumno('C', 'otro');
        $ajeno = $servicio->iniciar($otro);
        $this->assertNotSame($nuevo->id, $ajeno->id);
        $this->actingAs($otro, 'web')->get(route('examen', $nuevo->id))->assertNotFound();
    }

    public function test_si_el_banco_es_insuficiente_no_deja_un_intento_incompleto(): void
    {
        $this->banco();
        $alumno = $this->alumno('C');
        DB::table('preguntas')->whereIn('tema_id', DB::table('temas')
            ->whereIn('area_id', DB::table('areas')->where('nombre', 'Física')->select('id'))->select('id'))
            ->update(['activo' => false]);
        $this->actingAs($alumno, 'web')->from(route('alumno.panel'))
            ->post(route('examen.independiente'))
            ->assertRedirect(route('examen.vacio'));
        $this->assertDatabaseCount('intentos', 0);
        $this->assertDatabaseCount('respuestas_estudiante', 0);
        $this->assertDatabaseCount('simulacros', 0);
    }

    public function test_sin_banco_muestra_error_y_solo_estudiantes_activos_pueden_iniciar(): void
    {
        $this->post(route('examen.independiente'))->assertRedirect(route('login'));
        $alumno = $this->alumno('C');
        $this->actingAs($alumno, 'web')->from(route('alumno.panel'))
            ->post(route('examen.independiente'))->assertRedirect(route('examen.vacio'));
        $this->assertDatabaseCount('intentos', 0);
        $alumno->update(['rol' => 'docente']);
        $this->actingAs($alumno->fresh(), 'web')->post(route('examen.independiente'))->assertForbidden();
    }

    public function test_el_boton_del_panel_inicia_directamente_sin_selector_de_simulacro_o_carrera(): void
    {
        $this->actingAs($this->alumno('D'), 'web')->get(route('alumno.panel'))
            ->assertOk()->assertSee(route('examen.independiente'), false)
            ->assertSee('Dar examen')->assertDontSee('Elegir simulacro')
            ->assertDontSee('Carrera de interés')->assertDontSee('0 disponibles');
    }
    public function test_el_banco_vacio_abre_la_misma_pantalla_sin_crear_intentos_y_luego_el_excel_permite_un_examen_real(): void
    {
        $alumno = $this->alumno('C');
        $this->actingAs($alumno, 'web')->post(route('examen.independiente'))
            ->assertRedirect(route('examen.vacio'));
        $this->get(route('examen.vacio'))->assertOk()
            ->assertSee('Simulador de examen')->assertSee('Aún no hay preguntas disponibles.')
            ->assertSee('Ver preguntas')->assertSee('Colegio Nacional de Imperial')
            ->assertSee('01:00:00')->assertDontSee('Intento 00');
        $this->assertDatabaseCount('intentos', 0);
        $this->assertDatabaseCount('respuestas_estudiante', 0);

        // La carga del banco hace que el mismo botón inicie el examen sin otro cambio de configuración.
        $this->banco();
        $respuesta = $this->post(route('examen.independiente'));
        $intento = DB::table('intentos')->where('usuario_id', $alumno->id)->first();
        $respuesta->assertRedirect(route('examen', $intento->id));
        $this->get(route('examen', $intento->id))->assertOk()
            ->assertDontSee('Aún no hay preguntas disponibles.');
        $this->assertDatabaseCount('intentos', 1);
        $this->assertDatabaseCount('respuestas_estudiante', 100);
    }

    public function test_las_acciones_de_la_pantalla_sin_banco_no_guardan_ni_finalizan(): void
    {
        $componente = new \App\Livewire\Examen();
        $componente->sinBanco = true;
        $componente->responder(999);
        $componente->finalizar();
        $componente->tiempoAgotado();
        $componente->ir(99);
        $this->assertSame(0, $componente->actual);
        $this->assertDatabaseCount('intentos', 0);
        $this->assertDatabaseCount('respuestas_estudiante', 0);
    }

    public function test_la_pantalla_vacia_tambien_exige_una_sesion_de_estudiante(): void
    {
        $this->get(route('examen.vacio'))->assertRedirect(route('login'));
        $docente = $this->alumno('C');
        $docente->update(['rol' => 'docente']);
        $this->actingAs($docente, 'web')->get(route('examen.vacio'))->assertForbidden();
    }

    public function test_el_examen_independiente_se_cierra_al_cumplir_una_hora(): void
    {
        $this->banco();
        $alumno = $this->alumno('C');
        $primero = app(ExamenIndependienteService::class)->iniciar($alumno);
        $this->travel(59)->minutes();
        $this->assertSame('en_curso', app(IntentoService::class)->obtenerPropio($primero->id, $alumno->id)->estado);
        $this->travel(1)->minutes();
        $cerrado = app(IntentoService::class)->obtenerPropio($primero->id, $alumno->id);
        $this->assertSame('enviado_auto', $cerrado->estado);
        $this->assertNotNull($cerrado->puntaje_total);
    }

}
