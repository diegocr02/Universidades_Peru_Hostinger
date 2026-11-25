<?php

namespace Tests\Feature;

use App\Livewire\Alumno\Practica\PracticaPorTema;
use App\Livewire\Examen;
use App\Models\Usuario;
use App\Services\Alumno\PracticaService;
use App\Services\Alumno\ProgresoComparableService;
use Database\Seeders\AreaPostulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Herramientas de estudio: práctica por temas, revisión, guardadas, reportes, historial
 * visual, meta semanal, PDF y estados del examen. Sin IA (el tutor tiene su propia prueba).
 */
class EstudioAlumnoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $alumno;

    private int $asignatura;

    private int $tema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cni.alumno_areas_enabled' => true, 'tutor.enabled' => true]);
        $this->seed(AreaPostulacionSeeder::class);
        $this->alumno = $this->crearAlumno('ana');
        $this->asignatura = DB::table('areas')->insertGetId(['nombre' => 'Álgebra', 'bloque' => 'conocimientos', 'activo' => true]);
        $this->tema = DB::table('temas')->insertGetId(['area_id' => $this->asignatura, 'nombre' => 'Ecuaciones', 'activo' => true]);
    }

    // ───────────── Práctica por temas ─────────────

    public function test_practica_se_califica_en_el_servidor_sin_tocar_intentos(): void
    {
        foreach (range(1, 4) as $n) {
            $this->crearPregunta("Práctica {$n}");
        }
        $chat = Livewire::actingAs($this->alumno, 'web')->test(PracticaPorTema::class)
            ->set('asignatura', 'Álgebra')->set('cantidad', 5)->call('comenzar')
            ->assertSet('error', null)
            ->assertSet('aviso', 'Solo hay 4 preguntas disponibles con esa selección; las practicarás todas.');

        $practicaId = $chat->get('practicaId');
        // La pregunta pendiente no expone la clave.
        $chat->assertDontSee('✓ Respuesta correcta');
        $chat->call('responder', 'B')->assertSee('No es correcta. La respuesta es A');

        $this->assertDatabaseHas('practicas', ['id' => $practicaId, 'incorrectas' => 1, 'correctas' => 0, 'origen' => 'tema']);
        $this->assertSame(0, DB::table('intentos')->count(), 'La práctica no debe crear intentos ni alterar estadísticas de simulacros.');

        foreach (range(2, 4) as $orden) {
            $chat->call('siguiente')->call('responder', 'A');
        }
        $chat->call('siguiente')->assertSee('Resumen de tu práctica')->assertSee('Repetir mis errores (1)');
        $this->assertNotNull(DB::table('practicas')->where('id', $practicaId)->value('finalizada_en'));

        $chat->call('repetirErrores');
        $this->assertSame(1, DB::table('practicas')->where('origen', 'repaso')->value('total_preguntas'));
    }

    public function test_responder_dos_veces_no_cuenta_doble(): void
    {
        $pregunta = $this->crearPregunta('Única');
        $servicio = app(PracticaService::class);
        $practica = $servicio->crear($this->alumno, 'B', 'Álgebra', null, null, 5);

        $primera = $servicio->responder($this->alumno, $practica->id, $pregunta, 'A');
        $segunda = $servicio->responder($this->alumno, $practica->id, $pregunta, 'B');

        $this->assertTrue($primera['nueva']);
        $this->assertFalse($segunda['nueva']);
        $this->assertSame('correcta', $segunda['estado'], 'Un reintento devuelve el resultado guardado.');
        $this->assertSame(1, (int) DB::table('practicas')->where('id', $practica->id)->value('correctas'));
        $this->assertSame(1, $servicio->respondidasEnSemana($this->alumno)['total']);
    }

    public function test_practica_ajena_no_se_puede_responder_ni_ver(): void
    {
        $pregunta = $this->crearPregunta('Ajena');
        $otro = $this->crearAlumno('beto');
        $servicio = app(PracticaService::class);
        $practica = $servicio->crear($otro, 'B', 'Álgebra', null, null, 5);

        $this->assertNull($servicio->propia($this->alumno, $practica->id));
        $this->expectException(ValidationException::class);
        $servicio->responder($this->alumno, $practica->id, $pregunta, 'A');
    }

    public function test_practica_bloqueada_durante_un_examen_activo(): void
    {
        $this->crearPregunta('Bloqueada');
        $this->crearIntento($this->alumno, 'en_curso');

        Livewire::actingAs($this->alumno, 'web')->test(PracticaPorTema::class)
            ->assertSee('Tienes un examen en curso')
            ->set('asignatura', 'Álgebra')->call('comenzar')
            ->assertSet('error', 'Tienes un examen en curso. Termínalo antes de practicar.')
            ->assertSet('practicaId', null);
    }

    public function test_seleccion_manipulada_se_rechaza(): void
    {
        $this->crearPregunta('Válida');
        Livewire::actingAs($this->alumno, 'web')->test(PracticaPorTema::class)
            ->set('asignatura', 'Física')->call('comenzar')->assertHasErrors('asignatura')
            ->set('asignatura', 'Álgebra')->set('cantidad', 999)->call('comenzar')->assertHasErrors('cantidad')
            ->set('cantidad', 5)->set('temaId', 99999)->call('comenzar')->assertHasErrors('temaId');
    }

    // ───────────── Revisión, guardadas y reportes ─────────────

    public function test_revision_muestra_tema_y_acciones_y_tutor_explica_por_enlace(): void
    {
        $intento = $this->crearIntento($this->alumno, 'finalizado');
        $pregunta = (int) DB::table('respuestas_estudiante')->where('intento_id', $intento)->value('pregunta_id');

        $this->actingAs($this->alumno, 'web')->get(route('alumno.resultados', $intento))->assertOk()
            ->assertSee('Ecuaciones')->assertSee('Guardar pregunta')->assertSee('Reportar pregunta')
            ->assertSee(route('alumno.resultados.pdf', $intento), false)
            ->assertSee(e(route('alumno.tutor', ['pregunta' => $pregunta, 'intento' => $intento])), false);

        // El enlace al tutor explica la pregunta una sola vez y limpia la URL.
        $this->actingAs($this->alumno, 'web')->get(route('alumno.tutor', ['pregunta' => $pregunta]))->assertRedirect(route('alumno.tutor'));
        $this->assertSame(1, DB::table('tutor_mensajes')->where('tipo', 'explicacion')->count());
    }

    public function test_guardar_sin_duplicados_y_solo_preguntas_visibles(): void
    {
        $intento = $this->crearIntento($this->alumno, 'finalizado');
        $visible = (int) DB::table('respuestas_estudiante')->where('intento_id', $intento)->value('pregunta_id');
        $noVista = $this->crearPregunta('Nunca vista');
        $enCurso = (int) DB::table('respuestas_estudiante')->where('intento_id', $this->crearIntento($this->alumno, 'en_curso'))->value('pregunta_id');

        $this->actingAs($this->alumno, 'web')->post(route('alumno.pregunta.guardar', $visible))->assertRedirect();
        $this->actingAs($this->alumno, 'web')->post(route('alumno.pregunta.guardar', $visible))->assertRedirect();
        $this->actingAs($this->alumno, 'web')->post(route('alumno.pregunta.guardar', $noVista))->assertSessionHas('repaso_error');
        $this->actingAs($this->alumno, 'web')->post(route('alumno.pregunta.guardar', $enCurso))->assertSessionHas('repaso_error');

        $this->assertSame(1, DB::table('preguntas_guardadas')->where('usuario_id', $this->alumno->id)->count());

        // Retirada del banco: se conserva en la lista con aviso.
        DB::table('preguntas')->where('id', $visible)->update(['activo' => false]);
        $this->actingAs($this->alumno, 'web')->get(route('alumno.guardadas'))->assertOk()
            ->assertSee('Retirada del banco')->assertSee('Explicación revisada');
        // Otro alumno no ve la lista ajena.
        $this->actingAs($this->crearAlumno('beto'), 'web')->get(route('alumno.guardadas'))->assertOk()->assertSee('Aún no tienes preguntas guardadas');
    }

    public function test_reportes_sin_duplicados_y_revision_administrativa(): void
    {
        $intento = $this->crearIntento($this->alumno, 'finalizado');
        $pregunta = (int) DB::table('respuestas_estudiante')->where('intento_id', $intento)->value('pregunta_id');
        $datos = ['motivo' => 'clave_incorrecta', 'comentario' => '<b>La clave</b> parece ser C', 'intento_id' => $intento];

        $this->actingAs($this->alumno, 'web')->post(route('alumno.pregunta.reportar', $pregunta), $datos)
            ->assertSessionHas('repaso_ok', 'Gracias. Tu reporte quedó registrado y un docente lo revisará.');
        $this->actingAs($this->alumno, 'web')->post(route('alumno.pregunta.reportar', $pregunta), $datos)
            ->assertSessionHas('repaso_ok', 'Ya reportaste esta pregunta; tu reporte sigue en revisión.');
        $this->actingAs($this->alumno, 'web')->post(route('alumno.pregunta.reportar', $pregunta), ['motivo' => 'inventado'])->assertSessionHasErrors('motivo');
        $this->actingAs($this->alumno, 'web')->post(route('alumno.pregunta.reportar', $this->crearPregunta('No vista')), $datos)->assertSessionHas('repaso_error');

        $reporte = DB::table('reportes_pregunta')->sole();
        $this->assertSame('La clave parece ser C', $reporte->comentario, 'El comentario se guarda sin HTML.');
        $this->assertSame($intento, (int) $reporte->intento_id);

        // Solo administración revisa; cambiar el estado no toca la pregunta ni su clave.
        $this->actingAs($this->alumno, 'web')->get(route('admin.reportes.index'))->assertForbidden();
        $admin = Usuario::create(['nombres' => 'Ad', 'apellidos' => 'Min', 'usuario' => 'admin_t', 'password' => 'ClaveCNI2026!', 'activo' => true, 'rol' => 'admin']);
        $claveAntes = DB::table('alternativas')->where('pregunta_id', $pregunta)->where('es_correcta', true)->value('letra');
        $this->actingAs($admin, 'web')->get(route('admin.reportes.index'))->assertOk()->assertSee('Posible clave incorrecta');
        $this->actingAs($admin, 'web')->patch(route('admin.reportes.actualizar', $reporte->id), ['estado' => 'resuelto'])->assertRedirect();
        $this->assertDatabaseHas('reportes_pregunta', ['id' => $reporte->id, 'estado' => 'resuelto', 'revisado_por' => $admin->id]);
        $this->assertSame($claveAntes, DB::table('alternativas')->where('pregunta_id', $pregunta)->where('es_correcta', true)->value('letra'));
    }

    // ───────────── Historial visual y meta ─────────────

    public function test_historial_solo_compara_escalas_equivalentes(): void
    {
        $this->crearIntento($this->alumno, 'finalizado', 'Examen UNI · Día 1 · aaa111', 20.0, 2, now()->subDays(3));
        $this->crearIntento($this->alumno, 'finalizado', 'Examen UNI · Día 1 · bbb222', 20.0, 2, now()->subDays(2));
        // Mismo examen base pero con otra escala: no debe mezclarse.
        $this->crearIntento($this->alumno, 'finalizado', 'Examen UNI · Día 1 · ccc333', 15.0, 2, now()->subDay());

        $grupos = app(ProgresoComparableService::class)->grupos($this->alumno, 'B');
        $this->assertCount(2, $grupos['grupos']);
        $this->assertFalse($grupos['comparables']);
        $this->assertSame([2, 1], array_column($grupos['grupos'], 'intentos'));

        $this->actingAs($this->alumno, 'web')->get(route('alumno.progreso'))->assertOk()
            ->assertSee('Examen UNI · Día 1')->assertSee('<svg class="progreso-grafico"', false)
            ->assertSee('Con dos o más verás tu tendencia');
        $this->actingAs($this->alumno, 'web')->get(route('alumno.progreso', ['desde' => '2026-02-01', 'hasta' => '2026-01-01']))
            ->assertSessionHasErrors('hasta');
    }

    public function test_meta_semanal_y_resumen_de_inicio(): void
    {
        $this->actingAs($this->alumno, 'web')->get(route('alumno.panel'))->assertOk()
            ->assertSee('Tu preparación')->assertSee('Hola, Alumno')->assertSee('Empezar a practicar')->assertSee('Meta de la semana');

        $this->actingAs($this->alumno, 'web')->post(route('alumno.meta.guardar'), ['preguntas_semana' => 2])->assertSessionHasErrors('preguntas_semana');
        $this->actingAs($this->alumno, 'web')->post(route('alumno.meta.guardar'), ['preguntas_semana' => 40])->assertRedirect();
        $this->actingAs($this->alumno, 'web')->post(route('alumno.meta.guardar'), ['preguntas_semana' => 30])->assertRedirect();
        $this->assertSame(30, (int) DB::table('metas_estudio')->where('usuario_id', $this->alumno->id)->sole()->preguntas_semana);

        // Con un examen pendiente, la acción principal es continuarlo (no empezar una práctica).
        $pendiente = $this->crearIntento($this->alumno, 'en_curso');
        $this->actingAs($this->alumno, 'web')->get(route('alumno.panel'))
            ->assertSee('Continuar examen pendiente')->assertSee(route('examen', ['intento' => $pendiente]), false)->assertSee('0</strong> de 30', false);
    }

    // ───────────── PDF ─────────────

    public function test_pdf_solo_del_propio_intento_finalizado(): void
    {
        $propio = $this->crearIntento($this->alumno, 'finalizado');
        $ajeno = $this->crearIntento($this->crearAlumno('beto'), 'finalizado');
        $abierto = $this->crearIntento($this->alumno, 'en_curso');

        $respuesta = $this->actingAs($this->alumno, 'web')->get(route('alumno.resultados.pdf', $propio))->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());

        $this->actingAs($this->alumno, 'web')->get(route('alumno.resultados.pdf', $ajeno))->assertNotFound();
        $this->actingAs($this->alumno, 'web')->get(route('alumno.resultados.pdf', $abierto))->assertNotFound();
        auth('web')->logout();
        $this->get(route('alumno.resultados.pdf', $propio))->assertRedirect(route('login'));
    }

    // ───────────── Examen: guardado con reintento ─────────────

    public function test_reintento_guarda_en_la_pregunta_original(): void
    {
        $intento = $this->crearIntento($this->alumno, 'en_curso');
        $primera = (int) DB::table('respuestas_estudiante')->where('intento_id', $intento)->where('orden', 1)->value('pregunta_id');
        $alternativa = (int) DB::table('alternativas')->where('pregunta_id', $primera)->where('letra', 'C')->value('id');

        // El alumno ya pasó a la pregunta 2 cuando llega el reintento de la 1.
        Livewire::actingAs($this->alumno, 'web')->test(Examen::class, ['intento' => $intento])
            ->call('siguiente')->call('responder', $alternativa, $primera);

        $this->assertSame($alternativa, (int) DB::table('respuestas_estudiante')->where('intento_id', $intento)->where('pregunta_id', $primera)->value('alternativa_id'));
        $this->assertNull(DB::table('respuestas_estudiante')->where('intento_id', $intento)->where('orden', 2)->value('alternativa_id'));
    }

    public function test_accesibilidad_disponible_para_alumnos(): void
    {
        $this->actingAs($this->alumno, 'web')->get(route('alumno.practica'))->assertOk()
            ->assertSee('Ajustes de accesibilidad')->assertSee('accesibilidad.css', false)->assertSee('Alto contraste');
    }

    // ───────────── Ayudantes ─────────────

    private function crearAlumno(string $usuario): Usuario
    {
        return Usuario::create(['nombres' => 'Alumno', 'apellidos' => 'Prueba', 'usuario' => $usuario, 'password' => 'ClaveCNI2026!',
            'activo' => true, 'rol' => 'estudiante', 'area_postulacion_id' => DB::table('areas_postulacion')->where('codigo', 'B')->value('id')]);
    }

    private function crearPregunta(string $enunciado): int
    {
        $id = DB::table('preguntas')->insertGetId(['tema_id' => $this->tema, 'enunciado' => $enunciado, 'nivel' => 'basico',
            'solucion' => 'Despeja x paso a paso.', 'activo' => true]);
        foreach (['A' => true, 'B' => false, 'C' => false, 'D' => false] as $letra => $correcta) {
            DB::table('alternativas')->insert(['pregunta_id' => $id, 'letra' => $letra, 'texto' => "Opción {$letra}", 'es_correcta' => $correcta]);
        }

        return $id;
    }

    /** Intento UNI con 2 preguntas (1 correcta + 1 incorrecta si está finalizado). */
    private function crearIntento(Usuario $alumno, string $estado, string $nombre = 'Examen UNI · Día 1 · abc123', float $correcta = 20.0, int $total = 2, $fecha = null): int
    {
        $simulacro = DB::table('simulacros')->insertGetId(['nombre' => $nombre, 'tiempo_limite' => 180, 'total_preguntas' => $total,
            'puntaje_correcta' => $correcta, 'puntaje_incorrecta' => -1.125, 'puntaje_blanco' => 0,
            'fecha_inicio' => '2000-01-01 00:00:00', 'fecha_fin' => '2099-12-31 23:59:59', 'activo' => true]);
        DB::table('area_postulacion_simulacro')->insert(['simulacro_id' => $simulacro, 'area_postulacion_id' => DB::table('areas_postulacion')->where('codigo', 'B')->value('id')]);
        $cerrado = $estado !== 'en_curso';
        $intento = DB::table('intentos')->insertGetId([
            'usuario_id' => $alumno->id, 'simulacro_id' => $simulacro, 'tipo' => 'simulacro', 'estado' => $estado,
            'fecha_inicio' => $fecha ?? now()->subHour(), 'fecha_limite' => $cerrado ? null : now()->addHour(), 'fecha_fin' => $cerrado ? now() : null,
            'total_preguntas' => $total, 'correctas' => $cerrado ? 1 : null, 'incorrectas' => $cerrado ? 1 : null, 'en_blanco' => $cerrado ? 0 : null,
            'puntaje_total' => $cerrado ? $correcta - 1.125 : null,
        ]);
        foreach (range(1, $total) as $orden) {
            $pregunta = $this->crearPregunta("Pregunta {$orden} del intento {$intento}");
            $acierta = $orden === 1;
            DB::table('respuestas_estudiante')->insert(['intento_id' => $intento, 'pregunta_id' => $pregunta, 'orden' => $orden,
                'alternativa_id' => $cerrado ? DB::table('alternativas')->where('pregunta_id', $pregunta)->where('letra', $acierta ? 'A' : 'B')->value('id') : null,
                'estado_respuesta' => $cerrado ? ($acierta ? 'correcta' : 'incorrecta') : null,
                'puntaje_obtenido' => $cerrado ? ($acierta ? $correcta : -1.125) : null]);
        }
        if ($cerrado) {
            DB::table('resultados_area')->insert(['intento_id' => $intento, 'area_id' => $this->asignatura, 'total_preguntas' => $total,
                'aciertos' => 1, 'incorrectas' => 1, 'en_blanco' => 0, 'puntaje' => $correcta - 1.125, 'porcentaje' => 50]);
        }

        return $intento;
    }
}
