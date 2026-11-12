<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Alumno\ExamenIndependienteService;
use App\Services\IntentoService;
use App\Services\ResultadoService;
use App\Support\PerfilExamenUnmsm;
use Database\Seeders\AreaPostulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Modalidad San Marcos (área de postulación E): áreas A, C y E, 100 preguntas, 90 minutos, 2 000 puntos.
 * Las cantidades de conocimientos son datos de PRUEBA, no la distribución oficial.
 */
class ExamenUnmsmTest extends TestCase
{
    use RefreshDatabase;

    private const CONOCIMIENTOS_PRUEBA = [
        'A' => ['Biología' => 20, 'Química' => 15, 'Física' => 10, 'Lenguaje' => 10, 'Aritmética' => 15],
        'C' => ['Física' => 20, 'Álgebra' => 20, 'Geometría' => 15, 'Química' => 15],
        'E' => ['Lenguaje' => 20, 'Historia del Perú' => 20, 'Literatura' => 15, 'Filosofía' => 15],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cni.alumno_areas_enabled' => true]);
        $this->seed(AreaPostulacionSeeder::class);
    }

    private function configurar(): void
    {
        config(['unmsm.conocimientos' => self::CONOCIMIENTOS_PRUEBA]);
    }

    private function alumno(?string $areaUnmsm = 'A', string $usuario = 'sanmarquino', string $modalidad = 'E'): Usuario
    {
        $alumno = Usuario::create([
            'nombres' => 'Ana', 'apellidos' => 'Quispe', 'usuario' => $usuario,
            'password' => 'ClaveCNI2026!', 'activo' => true, 'rol' => 'estudiante',
            'area_postulacion_id' => DB::table('areas_postulacion')->where('codigo', $modalidad)->value('id'),
        ]);
        if ($areaUnmsm !== null) {
            DB::table('usuarios')->where('id', $alumno->id)->update(['area_unmsm' => $areaUnmsm]);
        }

        return $alumno->refresh();
    }

    /** Banco suficiente: rúbrica 20/15/10/5/0 en actitudinales; clave A en las ordinarias. */
    private function banco(int $porAsignatura = 25): void
    {
        $asignaturas = ['Actitudinal', 'Razonamiento Verbal', 'Habilidad Matemática', 'Biología', 'Química', 'Física',
            'Lenguaje', 'Aritmética', 'Álgebra', 'Geometría', 'Historia del Perú', 'Literatura', 'Filosofía'];
        foreach ($asignaturas as $nombre) {
            $area = DB::table('areas')->insertGetId(['nombre' => $nombre, 'bloque' => 'conocimientos', 'activo' => true]);
            $tema = DB::table('temas')->insertGetId(['area_id' => $area, 'nombre' => $nombre, 'activo' => true]);
            for ($i = 0; $i < $porAsignatura; $i++) {
                $pregunta = DB::table('preguntas')->insertGetId([
                    'tema_id' => $tema, 'enunciado' => $nombre.' pregunta '.$i,
                    'nivel' => 'intermedio', 'solucion' => 'Explicación '.$i, 'activo' => true,
                ]);
                foreach (['A' => 20, 'B' => 15, 'C' => 10, 'D' => 5, 'E' => 0] as $letra => $rubrica) {
                    DB::table('alternativas')->insert([
                        'pregunta_id' => $pregunta, 'letra' => $letra, 'texto' => 'Alternativa '.$letra,
                        'es_correcta' => $nombre !== 'Actitudinal' && $letra === 'A',
                        'puntaje_actitudinal' => $nombre === 'Actitudinal' ? $rubrica : null,
                    ]);
                }
            }
        }
    }

    private function iniciar(Usuario $alumno): object
    {
        return app(ExamenIndependienteService::class)->iniciarUnmsm($alumno);
    }

    private function preguntasConAsignatura(int $intentoId): \Illuminate\Support\Collection
    {
        return app(IntentoService::class)->preguntas($intentoId);
    }

    private function letra(int $preguntaId, string $letra): int
    {
        return (int) DB::table('alternativas')->where('pregunta_id', $preguntaId)->where('letra', $letra)->value('id');
    }

    public function test_cada_area_a_c_e_arma_cien_preguntas_con_su_distribucion_y_noventa_minutos(): void
    {
        $this->configurar();
        $this->banco();
        foreach (['A', 'C', 'E'] as $area) {
            $alumno = $this->alumno($area, 'alumno_'.$area);
            $this->actingAs($alumno, 'web')->post(route('examen.unmsm'))->assertRedirect();
            $intento = DB::table('intentos')->where('usuario_id', $alumno->id)->first();
            $this->assertNotNull($intento);
            $this->assertSame(100, (int) $intento->total_preguntas);
            $this->assertSame(5400, (int) Carbon::parse($intento->fecha_inicio)->diffInSeconds(Carbon::parse($intento->fecha_limite)));

            $preguntas = $this->preguntasConAsignatura($intento->id);
            $this->assertCount(100, $preguntas->pluck('id')->unique());
            foreach (PerfilExamenUnmsm::cuotas($area) as $asignatura => $cantidad) {
                $this->assertCount($cantidad, $preguntas->where('area', $asignatura), "Área {$area}: {$asignatura}");
            }
            // Orden de la plantilla: actitudinal, habilidades y luego conocimientos.
            $this->assertSame(array_fill(0, 10, 'Actitudinal'), $preguntas->take(10)->pluck('area')->all());

            $simulacro = DB::table('simulacros')->find($intento->simulacro_id);
            $this->assertSame($area, PerfilExamenUnmsm::areaDeNombre($simulacro->nombre));
            $this->assertEquals(2000, (float) $simulacro->puntaje_maximo);
            $this->assertEquals(-1.125, (float) $simulacro->puntaje_incorrecta);
            $html = $this->get(route('examen', $intento->id))->assertOk()
                ->assertSee('Universidad Nacional Mayor de San Marcos')
                ->assertSee('Área '.$area)
                ->assertSee('Marcar para revisar')
                ->assertSee('examen-unmsm.css')->getContent();
            // Livewire debe montar el componente en la raíz; si no, los clics dejan de funcionar.
            $this->assertMatchesRegularExpression('/<div wire:snapshot="[^"]*"[^>]*class="examen-distribucion"/', $html);
        }
        // Una plantilla independiente por área.
        $this->assertSame(3, DB::table('simulacros')->where('nombre', 'like', 'Examen San Marcos%')->count());
    }

    public function test_sin_distribucion_oficial_no_crea_intentos_y_avisa_en_el_panel(): void
    {
        config(['unmsm.conocimientos' => ['A' => [], 'C' => [], 'E' => []]]);
        $this->banco();
        $alumno = $this->alumno('A');
        $this->actingAs($alumno, 'web')->post(route('examen.unmsm'))
            ->assertRedirect(route('alumno.panel'))->assertSessionHasErrors('preguntas');
        $this->assertDatabaseCount('intentos', 0);
        $this->get(route('alumno.panel'))->assertOk()
            ->assertSee('falta configurar la distribución oficial');
    }

    public function test_banco_insuficiente_muestra_disponibilidad_y_no_crea_examen_incompleto(): void
    {
        $this->configurar();
        $this->banco(12); // Alcanza para las fijas (10), no para Biología (20).
        $alumno = $this->alumno('A');
        $this->actingAs($alumno, 'web')->get(route('alumno.panel'))->assertOk()
            ->assertSee('El banco de preguntas todavía no alcanza')
            ->assertSee('Biología: 12 de 20');
        $this->post(route('examen.unmsm'))->assertRedirect(route('alumno.panel'))->assertSessionHasErrors('preguntas');
        $this->assertDatabaseCount('intentos', 0);
        $this->assertDatabaseCount('respuestas_estudiante', 0);
    }

    public function test_reanuda_sin_reiniciar_el_tiempo_y_permite_volver_a_rendir(): void
    {
        $this->configurar();
        $this->banco();
        $alumno = $this->alumno('C');
        $primero = $this->iniciar($alumno);
        $orden = $this->preguntasConAsignatura($primero->id)->pluck('id')->all();
        $this->travel(5)->minutes();
        $reanudado = $this->iniciar($alumno);
        $this->assertSame($primero->id, $reanudado->id);
        $this->assertSame($primero->fecha_limite, $reanudado->fecha_limite);
        $this->assertSame($orden, $this->preguntasConAsignatura($reanudado->id)->pluck('id')->all());

        // Si la configuración cambia con el examen abierto, el alumno igual puede continuarlo.
        config(['unmsm.conocimientos.C' => []]);
        $this->actingAs($alumno, 'web')->post(route('examen.unmsm'))
            ->assertRedirect(route('examen', $primero->id));
        $this->configurar();

        app(IntentoService::class)->finalizar($primero->id, $alumno->id);
        $nuevo = $this->iniciar($alumno);
        $this->assertNotSame($primero->id, $nuevo->id);
        $this->assertSame(2, DB::table('intentos')->where('usuario_id', $alumno->id)->count());
    }

    public function test_califica_ordinarias_con_penalidad_y_actitudinales_con_rubrica(): void
    {
        $this->configurar();
        $this->banco();
        $alumno = $this->alumno('A');
        $intento = $this->iniciar($alumno);
        $servicio = app(IntentoService::class);
        $preguntas = $this->preguntasConAsignatura($intento->id)->values();

        $ordinarias = 0;
        foreach ($preguntas as $pregunta) {
            if ($pregunta->area === 'Actitudinal') {
                // A = 20 puntos en la rúbrica de prueba.
                $servicio->guardarRespuesta($intento->id, $alumno->id, ['pregunta_id' => $pregunta->id, 'alternativa_id' => $this->letra($pregunta->id, 'A')]);
                continue;
            }
            $ordinarias++;
            if ($ordinarias <= 50) {
                $letra = 'A';
            } elseif ($ordinarias <= 60) {
                $letra = 'B';
            } else {
                continue; // 30 en blanco
            }
            $servicio->guardarRespuesta($intento->id, $alumno->id, ['pregunta_id' => $pregunta->id, 'alternativa_id' => $this->letra($pregunta->id, $letra)]);
        }
        $cerrado = $servicio->finalizar($intento->id, $alumno->id);

        // (50 × 20) − (10 × 1.125) + (10 × 20) = 1188.75
        $this->assertEquals(1188.75, (float) $cerrado->puntaje_total);
        $this->assertSame(50, (int) $cerrado->correctas);
        $this->assertSame(10, (int) $cerrado->incorrectas);
        $this->assertSame(30, (int) $cerrado->en_blanco);

        $actitudinalId = DB::table('areas')->where('nombre', 'Actitudinal')->value('id');
        $fila = DB::table('resultados_area')->where('intento_id', $intento->id)->where('area_id', $actitudinalId)->first();
        $this->assertEquals(200, (float) $fila->puntaje);
        $this->assertEquals(100, (float) $fila->porcentaje);
        // Las actitudinales nunca se marcan como correctas o incorrectas.
        $this->assertSame(0, DB::table('respuestas_estudiante as re')->join('preguntas as p', 'p.id', '=', 're.pregunta_id')
            ->join('temas as t', 't.id', '=', 'p.tema_id')->where('t.area_id', $actitudinalId)
            ->where('re.intento_id', $intento->id)->whereIn('re.estado_respuesta', ['correcta', 'incorrecta'])->count());

        // Calificar de nuevo no cambia nada.
        app(ResultadoService::class)->calificar($intento->id);
        $this->assertEquals(1188.75, (float) DB::table('intentos')->where('id', $intento->id)->value('puntaje_total'));

        $this->actingAs($alumno, 'web')->get(route('alumno.resultados', ['intento' => $intento->id]))->assertOk()
            ->assertViewIs('alumno.unmsm.resultados')
            ->assertSee('Resultados de tu examen')
            ->assertSee('1,188.750')
            ->assertSee('Puntaje actitudinal')
            ->assertSee('200.000')
            ->assertSee('−11.250', false)
            ->assertSee('Retroalimentación')
            ->assertSee('Explicación')
            ->assertSee('no significa ingreso');
    }

    public function test_conserva_puntajes_negativos(): void
    {
        $this->configurar();
        $this->banco();
        $alumno = $this->alumno('E');
        $intento = $this->iniciar($alumno);
        $servicio = app(IntentoService::class);
        $ordinarias = $this->preguntasConAsignatura($intento->id)->reject(fn ($p) => $p->area === 'Actitudinal')->take(20);
        foreach ($ordinarias as $pregunta) {
            $servicio->guardarRespuesta($intento->id, $alumno->id, ['pregunta_id' => $pregunta->id, 'alternativa_id' => $this->letra($pregunta->id, 'B')]);
        }
        $this->assertEquals(-22.5, (float) $servicio->finalizar($intento->id, $alumno->id)->puntaje_total);
    }

    public function test_al_vencer_el_tiempo_se_envia_solo_bloquea_respuestas_y_califica_una_vez(): void
    {
        $this->configurar();
        $this->banco();
        $alumno = $this->alumno('A');
        $intento = $this->iniciar($alumno);
        $pregunta = $this->preguntasConAsignatura($intento->id)->get(20);
        $servicio = app(IntentoService::class);
        $servicio->guardarRespuesta($intento->id, $alumno->id, ['pregunta_id' => $pregunta->id, 'alternativa_id' => $this->letra($pregunta->id, 'A')]);

        $this->travel(91)->minutes();
        $cerrado = $servicio->obtenerPropio($intento->id, $alumno->id);
        $this->assertSame('enviado_auto', $cerrado->estado);
        $this->assertEquals(20, (float) $cerrado->puntaje_total);
        $this->assertEquals(Carbon::parse($intento->fecha_limite), Carbon::parse($cerrado->fecha_fin));

        try {
            $servicio->guardarRespuesta($intento->id, $alumno->id, ['pregunta_id' => $pregunta->id, 'alternativa_id' => $this->letra($pregunta->id, 'B')]);
            $this->fail('Debe rechazar respuestas después del cierre.');
        } catch (ValidationException) {
        }
        $this->assertSame(1, DB::table('resultados_area')->where('intento_id', $intento->id)
            ->where('area_id', DB::table('areas')->where('nombre', 'Biología')->value('id'))->count());
        $this->actingAs($alumno, 'web')->get(route('examen', $intento->id))
            ->assertRedirect(route('alumno.resultados', ['intento' => $intento->id]));
    }

    public function test_el_seeder_genera_el_banco_completo_con_imagenes_y_cada_area_arma_su_examen(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->seed(\Database\Seeders\CatalogoBancoCniSeeder::class);
        $this->seed(\Database\Seeders\BancoUnmsmSeeder::class);
        $total = DB::table('preguntas')->where('codigo', 'like', 'SM-%')->count();
        $this->seed(\Database\Seeders\BancoUnmsmSeeder::class); // idempotente
        $this->assertSame($total, DB::table('preguntas')->where('codigo', 'like', 'SM-%')->count());
        $this->assertGreaterThanOrEqual(230, $total);

        // Imágenes en storage/app/public/unmsm y referenciadas como storage/unmsm/*.svg.
        $conImagen = DB::table('preguntas')->where('codigo', 'like', 'SM-%')->whereNotNull('imagen')->pluck('imagen');
        $this->assertGreaterThanOrEqual(40, $conImagen->count());
        foreach ($conImagen as $ruta) {
            $this->assertStringStartsWith('storage/unmsm/', $ruta);
            \Illuminate\Support\Facades\Storage::disk('public')->assertExists(substr($ruta, strlen('storage/')));
        }

        // Cinco alternativas; una clave en las ordinarias y rúbrica completa en las actitudinales.
        $actitudinal = DB::table('areas')->where('nombre', 'Actitudinal')->value('id');
        foreach (DB::table('preguntas as p')->join('temas as t', 't.id', '=', 'p.tema_id')
            ->where('p.codigo', 'like', 'SM-%')->get(['p.id', 't.area_id']) as $p) {
            $alternativas = DB::table('alternativas')->where('pregunta_id', $p->id)->get();
            $this->assertCount(5, $alternativas);
            if ((int) $p->area_id === (int) $actitudinal) {
                $this->assertSame(0, $alternativas->where('es_correcta', true)->count());
                $this->assertEqualsCanonicalizing([0, 5, 10, 15, 20], $alternativas->pluck('puntaje_actitudinal')->map(fn ($v) => (int) $v)->all());
            } else {
                $this->assertSame(1, $alternativas->filter(fn ($a) => (bool) $a->es_correcta)->count());
            }
        }
        // La clave no queda siempre en la misma letra.
        $letras = DB::table('alternativas as a')->join('preguntas as p', 'p.id', '=', 'a.pregunta_id')
            ->where('p.codigo', 'like', 'SM-%')->where('a.es_correcta', true)->distinct()->pluck('a.letra');
        $this->assertCount(5, $letras);

        // Con la distribución de config/unmsm.php, cada área arma su examen de 100 preguntas.
        foreach (['A', 'C', 'E'] as $area) {
            $intento = $this->iniciar($this->alumno($area, 'banco_'.$area));
            $this->assertSame(100, (int) $intento->total_preguntas);
        }
    }

    public function test_el_examen_uni_no_recibe_estilos_ni_extras_de_san_marcos(): void
    {
        $this->banco();
        $area = DB::table('areas')->insertGetId(['nombre' => 'Trigonometría', 'bloque' => 'conocimientos', 'activo' => true]);
        $tema = DB::table('temas')->insertGetId(['area_id' => $area, 'nombre' => 'Trigonometría', 'activo' => true]);
        for ($i = 0; $i < 12; $i++) {
            $p = DB::table('preguntas')->insertGetId(['tema_id' => $tema, 'enunciado' => 'TR '.$i, 'nivel' => 'intermedio', 'solucion' => 'S', 'activo' => true]);
            foreach (['A', 'B', 'C', 'D', 'E'] as $letra) {
                DB::table('alternativas')->insert(['pregunta_id' => $p, 'letra' => $letra, 'texto' => $letra, 'es_correcta' => $letra === 'A']);
            }
        }
        $uni = $this->alumno(null, 'uni', 'B');
        $this->actingAs($uni, 'web')->post(route('examen.uni.dia', ['dia' => 2]))->assertRedirect();
        $intento = DB::table('intentos')->where('usuario_id', $uni->id)->first();
        $html = $this->get(route('examen', $intento->id))->assertOk()
            ->assertDontSee('examen-unmsm.css')
            ->assertDontSee('Marcar para revisar')
            ->assertDontSee('data-modalidad="unmsm"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/<div wire:snapshot="[^"]*"[^>]*class="examen-distribucion"/', $html);
        $this->assertSame(180, (int) DB::table('simulacros')->where('id', $intento->simulacro_id)->value('tiempo_limite'));
    }

    public function test_el_area_san_marcos_se_elige_una_sola_vez_y_solo_en_la_modalidad(): void
    {
        $alumno = $this->alumno(null);
        $this->actingAs($alumno, 'web')->post(route('alumno.unmsm.area'), ['area_unmsm' => 'B'])
            ->assertSessionHasErrors('area_unmsm');
        $this->post(route('alumno.unmsm.area'), ['area_unmsm' => 'C'])->assertRedirect(route('alumno.panel'));
        $this->assertSame('C', DB::table('usuarios')->where('id', $alumno->id)->value('area_unmsm'));
        $this->post(route('alumno.unmsm.area'), ['area_unmsm' => 'A'])->assertForbidden();
        $this->assertSame('C', DB::table('usuarios')->where('id', $alumno->id)->value('area_unmsm'));

        $uni = $this->alumno(null, 'alumno_uni', 'B');
        $this->actingAs($uni, 'web')->post(route('alumno.unmsm.area'), ['area_unmsm' => 'A'])->assertForbidden();
    }

    public function test_control_de_acceso_entre_uni_y_san_marcos(): void
    {
        $this->configurar();
        $this->banco();
        $sinArea = $this->alumno(null, 'sin_area');
        $this->actingAs($sinArea, 'web')->post(route('examen.unmsm'))
            ->assertRedirect(route('alumno.panel'))->assertSessionHasErrors('area_unmsm');

        $sanMarcos = $this->alumno('A', 'sm');
        $this->actingAs($sanMarcos, 'web')->post(route('examen.uni.dia', ['dia' => 1]))->assertForbidden();

        $uni = $this->alumno(null, 'uni', 'B');
        $this->actingAs($uni, 'web')->post(route('examen.unmsm'))->assertForbidden();
        $this->get(route('alumno.panel'))->assertOk()->assertViewIs('alumno.uni.panel')
            ->assertDontSee('Preparación San Marcos');
        $this->assertDatabaseCount('intentos', 0);
    }

    public function test_panel_tiene_cuatro_opciones_y_las_tres_areas_con_datos_reales(): void
    {
        $this->configurar();
        $this->banco();
        $alumno = $this->alumno('C');
        $this->actingAs($alumno, 'web');
        $html = $this->get(route('alumno.panel'))->assertOk()->assertViewIs('alumno.unmsm.panel')->getContent();

        $this->assertSame(1, preg_match('/<nav class="cni-alumno-menu"[^>]*>(.*?)<\/nav>/s', $html, $menu));
        $this->assertSame(4, preg_match_all('/<a\b/', $menu[1]));
        foreach (['#inicio' => 'Inicio', '#intentos' => 'Mis intentos', '#historial' => 'Historial', '#perfil' => 'Mi perfil'] as $href => $texto) {
            $this->assertMatchesRegularExpression('/href="'.$href.'"[^>]*>.*?'.$texto.'/s', $menu[1]);
        }
        $this->assertStringContainsString('Simulador CNI — Preparación San Marcos', $html);
        $this->assertStringContainsString('panel-unmsm.css', $html);
        foreach (PerfilExamenUnmsm::AREAS as $codigo => $nombre) {
            $this->assertStringContainsString('id="sm-area-'.$codigo.'"', $html);
        }
        $this->assertStringContainsString('Iniciar examen', $html);
        $this->assertSame(1, substr_count($html, 'action="'.route('examen.unmsm').'"'));
        $this->assertStringContainsString('Aún no tienes intentos en San Marcos.', $html);

        // Tras un intento calificado aparecen los datos en Inicio, Mis intentos e Historial.
        $intento = $this->iniciar($alumno);
        $p = $this->preguntasConAsignatura($intento->id)->get(30);
        app(IntentoService::class)->guardarRespuesta($intento->id, $alumno->id, ['pregunta_id' => $p->id, 'alternativa_id' => $this->letra($p->id, 'A')]);
        app(IntentoService::class)->finalizar($intento->id, $alumno->id);

        $this->get(route('alumno.panel'))->assertOk()
            ->assertSee('Volver a rendir')
            ->assertSee('20.000 / 2,000')
            ->assertSee('Evolución del rendimiento')
            ->assertSee('Estadísticas por asignatura')
            ->assertSee(route('alumno.resultados', ['intento' => $intento->id]));
    }

    public function test_los_intentos_anteriores_del_area_e_se_conservan_aparte(): void
    {
        $this->configurar();
        $alumno = $this->alumno('A');
        $simulacro = DB::table('simulacros')->insertGetId([
            'nombre' => 'Examen independiente CNI · Área E · abc', 'tiempo_limite' => 60, 'total_preguntas' => 1,
            'cantidad_alternativas' => 5, 'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDay(), 'activo' => true,
        ]);
        DB::table('intentos')->insert([
            'usuario_id' => $alumno->id, 'simulacro_id' => $simulacro, 'tipo' => 'simulacro',
            'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->subMinutes(30), 'estado' => 'finalizado',
            'total_preguntas' => 1, 'puntaje_total' => 20,
        ]);
        $this->actingAs($alumno, 'web')->get(route('alumno.panel'))->assertOk()
            ->assertSee('Otros simulacros anteriores')
            ->assertSee('Examen independiente CNI · Área E · abc')
            ->assertSee('Aún no tienes intentos en San Marcos.');
    }
}
