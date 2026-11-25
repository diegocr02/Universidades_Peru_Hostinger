<?php

namespace Tests\Feature;

use App\Livewire\Alumno\Tutor\TutorChat;
use App\Models\TutorConversacion;
use App\Models\TutorMensaje;
use App\Models\Usuario;
use App\Services\Alumno\ContextoAlumnoService;
use App\Services\Tutor\TutorService;
use App\Services\Tutor\TutorTools;
use Database\Seeders\AreaPostulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tutor CNI: acceso por universidad, aislamiento entre alumnos, regla del examen
 * activo, práctica calificada en el servidor e IA simulada (sin llamadas reales).
 */
class TutorCniTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $alumno;

    private int $asignatura;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cni.alumno_areas_enabled' => true,
            'tutor.enabled' => true,
            'tutor.provider' => 'openai',
            'tutor.openai.api_key' => null,
            'tutor.openai.base_url' => 'https://ia.test/v1',
        ]);
        Http::preventStrayRequests();
        $this->seed(AreaPostulacionSeeder::class);
        $this->alumno = $this->crearAlumno('ana', 'B');
        $this->asignatura = DB::table('areas')->insertGetId(['nombre' => 'Álgebra', 'bloque' => 'conocimientos', 'activo' => true]);
    }

    // ───────────── Acceso y contexto ─────────────

    public function test_cada_panel_muestra_el_tutor_y_el_tutor_se_adapta_a_la_universidad(): void
    {
        foreach (['A' => 'UNDC · Área A', 'B' => 'UNI · Área B', 'C' => 'Villarreal · Área C', 'D' => '· Área D', 'E' => 'San Marcos · Área E'] as $codigo => $etiqueta) {
            $this->alumno->forceFill(['area_postulacion_id' => $this->area($codigo)])->save();
            $alumno = $this->alumno->fresh();

            $this->actingAs($alumno, 'web')->get(route('alumno.panel'))->assertOk()
                ->assertSee(route('alumno.tutor'), false)->assertSee('Tutor IA');
            $this->actingAs($alumno, 'web')->get(route('alumno.tutor'))->assertOk()
                ->assertSee('¡Hola! Soy tu tutor CNI')->assertSee('¿En qué te ayudo hoy?')
                ->assertSee($etiqueta)->assertSee('data-area="'.$codigo.'"', false)
                ->assertSee('Prueba preguntarme')->assertSee('gatito-cni.webp', false);
        }
    }

    public function test_solo_estudiantes_con_sesion_y_modulo_activo(): void
    {
        $this->get(route('alumno.tutor'))->assertRedirect(route('login'));

        $docente = Usuario::create(['nombres' => 'Doc', 'apellidos' => 'Ente', 'usuario' => 'doc', 'password' => 'ClaveCNI2026!',
            'activo' => true, 'rol' => 'docente']);
        $this->actingAs($docente, 'web')->get(route('alumno.tutor'))->assertForbidden();

        config(['tutor.enabled' => false]);
        $this->actingAs($this->alumno, 'web')->get(route('alumno.tutor'))->assertNotFound();
        $this->actingAs($this->alumno, 'web')->get(route('alumno.panel'))->assertDontSee(route('alumno.tutor'), false);
    }

    public function test_al_cambiar_de_universidad_empieza_una_conversacion_nueva(): void
    {
        $uni = $this->chat()->call('iniciar', 'resultados')->get('conversacionId');
        $this->alumno->forceFill(['area_postulacion_id' => $this->area('C')])->save();
        $unfv = Livewire::actingAs($this->alumno->fresh(), 'web')->test(TutorChat::class)->get('conversacionId');

        $this->assertNotSame($uni, $unfv);
        $this->assertSame('C', TutorConversacion::find($unfv)->area_codigo);
    }

    // ───────────── Resultados y aislamiento ─────────────

    public function test_resultados_con_datos_reales_y_asignaturas_a_reforzar(): void
    {
        $intento = $this->crearIntentoUni($this->alumno, correctas: 1, incorrectas: 3);

        $this->chat()->call('iniciar', 'resultados')->assertSet('error', null);

        $respuesta = $this->ultimoDelTutor();
        $this->assertStringContainsString('Examen UNI · Día 1', $respuesta->contenido);
        $this->assertStringContainsString('Correctas: **1**', $respuesta->contenido);
        $this->assertStringContainsString('Te conviene reforzar:** Álgebra', $respuesta->contenido);
        $this->assertContains((string) $intento, collect($respuesta->datos['opciones'])->pluck('valor')->all());
    }

    public function test_alumno_sin_intentos_recibe_una_guia_clara(): void
    {
        $this->chat()->call('iniciar', 'resultados');
        $this->assertStringContainsString('Aún no tienes exámenes finalizados', $this->ultimoDelTutor()->contenido);

        $this->chat()->call('iniciar', 'progreso');
        $this->assertStringContainsString('Aún no tienes exámenes finalizados', $this->ultimoDelTutor()->contenido);
    }

    public function test_no_se_pueden_ver_intentos_ni_preguntas_de_otro_alumno(): void
    {
        $otro = $this->crearAlumno('beto', 'B');
        $intentoAjeno = $this->crearIntentoUni($otro, correctas: 2, incorrectas: 2);
        $preguntaAjena = (int) DB::table('respuestas_estudiante')->where('intento_id', $intentoAjeno)->value('pregunta_id');

        $this->chat()->call('elegir', 'intento_resultados', (string) $intentoAjeno)->assertSet('error', 'Ese intento no está disponible.');
        $this->chat()->call('elegir', 'intento_explicar', (string) $intentoAjeno)->assertSet('error', 'Ese intento no está disponible.');
        $this->chat()->call('elegir', 'pregunta_explicar', (string) $preguntaAjena)->assertSet('error', 'Esa pregunta no está disponible.');

        // Herramientas de la IA: tampoco exponen datos ajenos.
        $tools = app(TutorTools::class);
        $this->assertArrayHasKey('error', $tools->ejecutar('buscar_explicacion', ['pregunta_id' => $preguntaAjena], $this->alumno, 'B', []));
        $this->assertArrayHasKey('error', $tools->ejecutar('consultar_mis_resultados', ['intento_id' => $intentoAjeno], $this->alumno, 'B', []));
        $this->assertSame([], $tools->ejecutar('consultar_mis_errores', ['intento_id' => $intentoAjeno], $this->alumno, 'B', [])['preguntas']);
        $this->assertArrayHasKey('error', $tools->ejecutar('ejecutar_sql', ['q' => 'select * from usuarios'], $this->alumno, 'B', []));
    }

    public function test_la_conversacion_de_otro_alumno_no_se_puede_abrir(): void
    {
        $otro = $this->crearAlumno('beto', 'B');
        $ajena = TutorConversacion::create(['usuario_id' => $otro->id, 'area_codigo' => 'B', 'contexto' => []]);
        $ajena->mensajes()->create(['rol' => 'alumno', 'tipo' => 'texto', 'contenido' => 'Mensaje privado de Beto']);

        $propia = app(TutorService::class)->conversacion($this->alumno,
            app(ContextoAlumnoService::class)->contexto($this->alumno), $ajena->id);

        $this->assertNotSame($ajena->id, $propia->id);
        $this->assertSame($this->alumno->id, $propia->usuario_id);
        $this->chat()->assertDontSee('Mensaje privado de Beto');
    }

    public function test_explicar_un_error_muestra_la_solucion_revisada(): void
    {
        $intento = $this->crearIntentoUni($this->alumno, correctas: 1, incorrectas: 1);
        $pregunta = (int) DB::table('respuestas_estudiante')->where('intento_id', $intento)->where('estado_respuesta', 'incorrecta')->value('pregunta_id');

        $this->chat()->call('elegir', 'intento_explicar', (string) $intento)->assertSet('error', null);
        $this->assertContains((string) $pregunta, collect($this->ultimoDelTutor()->datos['opciones'])->pluck('valor')->all());

        $this->chat()->call('elegir', 'pregunta_explicar', (string) $pregunta)->assertSet('error', null)
            ->assertSee('Solución revisada')->assertSee('Solución de la pregunta');
        $this->assertSame('A', $this->ultimoDelTutor()->datos['clave']);
    }

    // ───────────── Examen activo ─────────────

    public function test_durante_un_examen_activo_el_tutor_no_responde_nada(): void
    {
        $intento = $this->crearIntentoUni($this->alumno, correctas: 1, incorrectas: 1);
        $pregunta = (int) DB::table('respuestas_estudiante')->where('intento_id', $intento)->value('pregunta_id');
        $activo = $this->crearIntentoEnCurso($this->alumno, $pregunta);
        config(['tutor.provider' => 'demo']);

        $chat = $this->chat()->assertSee('Tienes un examen en curso')->assertSee(route('examen', ['intento' => $activo]), false);
        foreach ([['iniciar', 'resultados'], ['iniciar', 'practicar'], ['elegir', 'pregunta_explicar', (string) $pregunta],
            ['ayuda', 'pista'], ['responder', 'A']] as $llamada) {
            $chat->call(...$llamada)->assertSet('error', fn ($e) => str_contains((string) $e, 'examen en curso'));
        }
        $chat->set('mensaje', 'Resuelve: '.DB::table('preguntas')->where('id', $pregunta)->value('enunciado'))
            ->call('enviar')->assertSet('error', fn ($e) => str_contains((string) $e, 'examen en curso'));

        $this->assertSame(0, TutorMensaje::count(), 'No debe guardarse ni responderse nada durante el examen.');

        // Al vencer el tiempo, el tutor vuelve a estar disponible.
        DB::table('intentos')->where('id', $activo)->update(['fecha_limite' => now()->subMinute()]);
        $this->chat()->call('iniciar', 'resultados')->assertSet('error', null);
    }

    // ───────────── Práctica ─────────────

    public function test_practica_completa_calificada_en_el_servidor(): void
    {
        foreach (range(1, 3) as $n) {
            $this->crearPregunta("Pregunta de práctica {$n}", 'basico');
        }

        $chat = $this->chat()->call('iniciar', 'practicar');
        $this->assertContains('Álgebra', collect($this->ultimoDelTutor()->datos['opciones'])->pluck('valor')->all());

        $chat->call('elegir', 'asignatura', 'Álgebra')->call('elegir', 'nivel', 'todos')->call('elegir', 'cantidad', '3')
            ->assertSet('error', null);
        $pregunta = $this->ultimoDelTutor();
        $this->assertSame('pregunta', $pregunta->tipo);
        $this->assertTrue($pregunta->datos['pendiente']);
        $this->assertArrayNotHasKey('clave', $pregunta->datos, 'La pregunta pendiente no debe enviar la clave al navegador.');
        $this->assertArrayNotHasKey('solucion', $pregunta->datos);

        // Pista sin IA: descarta una incorrecta, nunca la correcta.
        $chat->call('ayuda', 'pista')->assertSet('error', null);
        $this->assertMatchesRegularExpression('/descarta la alternativa \*\*[BCD]\*\*/', $this->ultimoDelTutor()->contenido);

        // La alternativa descartada tampoco se acepta si se envía manipulando la petición.
        $descartada = TutorConversacion::first()->contexto['practica']['descartadas'][0];
        $chat->call('responder', $descartada)->assertSet('error', 'Esa alternativa ya fue descartada con una pista.');

        // «Explícalo más fácil» no revela nada mientras la pregunta está pendiente.
        $chat->call('ayuda', 'facil');
        $this->assertStringContainsString('Primero intenta responder', $this->ultimoDelTutor()->contenido);

        $chat->call('responder', 'C')->assertSet('error', null);
        $feedback = $this->ultimoDelTutor();
        $this->assertStringContainsString('No es correcta', $feedback->contenido);
        $this->assertSame('A', $feedback->datos['clave']);
        $this->assertSame('incorrecta', $feedback->datos['estado']);

        // No se puede responder dos veces la misma pregunta.
        $chat->call('responder', 'A')->assertSet('error', 'No hay una pregunta pendiente de respuesta.');

        $chat->call('elegir', 'siguiente', '1')->call('responder', 'A')->call('elegir', 'siguiente', '1')->call('responder', 'A');
        $this->assertStringContainsString('Terminaste la práctica: **2 de 3**', $this->ultimoDelTutor()->contenido);
    }

    public function test_asignatura_sin_preguntas_y_valores_manipulados(): void
    {
        $this->chat()->call('iniciar', 'practicar');
        $this->assertStringContainsString('Todavía no hay preguntas de práctica', $this->ultimoDelTutor()->contenido);

        $this->chat()->call('elegir', 'asignatura', 'Física')->assertSet('error', 'Esa asignatura no tiene preguntas de práctica.');
        $this->chat()->call('elegir', 'cantidad', '999')->assertSet('error', 'Esa opción ya no está disponible.');
        $this->chat()->call('iniciar', 'borrar_todo')->assertSet('error', 'Esa opción ya no está disponible.');
        $this->chat()->call('elegir', 'accion_inventada', 'x')->assertSet('error', 'Esa opción ya no está disponible.');
    }

    public function test_hazme_una_pregunta_pide_tema_si_no_hay_uno(): void
    {
        $this->crearPregunta('Pregunta suelta', 'basico');
        $this->chat()->call('ayuda', 'pregunta');
        $this->assertStringContainsString('¿De qué asignatura quieres la pregunta?', $this->ultimoDelTutor()->contenido);
    }

    // ───────────── IA (respuestas simuladas) ─────────────

    public function test_sin_proveedor_configurado_lo_informa_y_no_llama_a_nadie(): void
    {
        $this->chat()->assertSee('IA sin configurar')->set('mensaje', '¿Qué es una función?')->call('enviar')->assertSet('error', null);

        $this->assertStringContainsString('todavía no está configurado', $this->ultimoDelTutor()->contenido);
        Http::assertNothingSent();
    }

    public function test_pregunta_libre_con_herramienta_y_sin_datos_personales(): void
    {
        config(['tutor.openai.api_key' => 'clave-de-prueba']);
        $this->crearIntentoUni($this->alumno, correctas: 3, incorrectas: 1);
        Http::fake(['ia.test/*' => Http::sequence()
            ->push(['choices' => [['finish_reason' => 'tool_calls', 'message' => ['content' => null, 'tool_calls' => [
                ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'consultar_mis_resultados', 'arguments' => '{}']],
            ]]]], 'usage' => ['total_tokens' => 120]])
            ->push(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'Obtuviste **3 correctas**. ¡Sigue así!']]], 'usage' => ['total_tokens' => 80]]),
        ]);

        $this->chat()->set('mensaje', '¿Cómo me fue en mi último examen?')->call('enviar')
            ->assertSet('error', null)->assertSet('mensaje', '')->assertSee('Generado con IA');

        $respuesta = $this->ultimoDelTutor();
        $this->assertSame('ia', $respuesta->origen);
        $this->assertSame(200, $respuesta->tokens);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            $cuerpo = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return $request->hasHeader('Authorization', 'Bearer clave-de-prueba')
                && ! str_contains($cuerpo, 'Alumno Prueba') && ! str_contains($cuerpo, '"ana"')
                && $request['max_completion_tokens'] === 600;
        });
        // El resultado de la herramienta (datos reales) llegó a la segunda llamada.
        Http::assertSent(fn (Request $r) => str_contains(json_encode($r->data()), 'tool_call_id'));
    }

    public function test_limite_de_herramientas_por_mensaje(): void
    {
        config(['tutor.openai.api_key' => 'clave', 'tutor.herramientas_por_turno' => 1]);
        $pedido = ['choices' => [['finish_reason' => 'tool_calls', 'message' => ['content' => null, 'tool_calls' => [
            ['id' => 'c', 'type' => 'function', 'function' => ['name' => 'consultar_mi_progreso', 'arguments' => '{}']],
        ]]]], 'usage' => ['total_tokens' => 10]];
        Http::fake(['ia.test/*' => Http::sequence()->push($pedido)
            ->push(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'Listo.']]], 'usage' => ['total_tokens' => 5]])]);

        $this->chat()->set('mensaje', 'Mi progreso')->call('enviar')->assertSet('error', null);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => ($r['tool_choice'] ?? null) === 'none');
    }

    public function test_fallo_del_servicio_permite_reintentar_sin_duplicar(): void
    {
        config(['tutor.openai.api_key' => 'clave']);
        Http::fake(['ia.test/*' => Http::sequence()
            ->push(['error' => ['type' => 'server_error']], 500)
            ->push(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'Ahora sí: una ecuación es una igualdad.']]], 'usage' => ['total_tokens' => 30]]),
        ]);

        $chat = $this->chat()->set('mensaje', '¿Qué es una ecuación?')->call('enviar')
            ->assertSet('puedeReintentar', true)->assertSet('mensaje', '¿Qué es una ecuación?')
            ->assertSee('Tu última pregunta quedó sin respuesta');

        // Un segundo envío no duplica la pregunta pendiente.
        $chat->call('enviar')->assertSet('error', fn ($e) => str_contains((string) $e, 'sin respuesta'));

        $chat->call('reintentar')->assertSet('error', null)->assertSee('Ahora sí');
        $this->assertSame(1, TutorMensaje::where('rol', 'alumno')->count());
        $this->assertSame(1, TutorMensaje::where('rol', 'tutor')->count());
    }

    public function test_tiempo_de_espera_permite_reintentar(): void
    {
        config(['tutor.openai.api_key' => 'clave']);
        Http::fake(['ia.test/*' => fn () => throw new ConnectionException('timeout')]);
        $this->chat()->set('mensaje', 'Hola')->call('enviar')
            ->assertSet('error', fn ($e) => str_contains((string) $e, 'tardó demasiado'))->assertSet('puedeReintentar', true);
    }

    public function test_clave_invalida_no_ofrece_reintentar(): void
    {
        config(['tutor.openai.api_key' => 'clave']);
        Http::fake(['ia.test/*' => Http::response(['error' => ['code' => 'invalid_api_key']], 401)]);
        $this->chat()->set('mensaje', 'Hola')->call('enviar')
            ->assertSet('error', fn ($e) => str_contains((string) $e, 'no es válida'))->assertSet('puedeReintentar', false);
    }

    public function test_limite_diario_de_consultas_con_ia(): void
    {
        config(['tutor.openai.api_key' => 'clave', 'tutor.consultas_ia_por_dia' => 1]);
        Http::fake(['ia.test/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'Respuesta.']]], 'usage' => ['total_tokens' => 9]])]);

        $this->chat()->set('mensaje', 'Primera')->call('enviar')->assertSet('error', null);
        $this->chat()->set('mensaje', 'Segunda')->call('enviar')
            ->assertSet('error', fn ($e) => str_contains((string) $e, 'límite de consultas'));
        Http::assertSentCount(1);
    }

    public function test_mensaje_vacio_o_demasiado_largo(): void
    {
        $this->chat()->set('mensaje', '   ')->call('enviar')->assertSet('error', 'Escribe tu pregunta antes de enviarla.');
        $this->chat()->set('mensaje', str_repeat('a', 601))->call('enviar')->assertSet('error', fn ($e) => str_contains((string) $e, 'muy larga'));
        $this->assertSame(0, TutorMensaje::count());
    }

    public function test_modo_demo_se_rotula_y_markdown_es_seguro(): void
    {
        config(['tutor.provider' => 'demo']);
        $this->chat()->set('mensaje', '<script>alert(1)</script>')->call('enviar')
            ->assertSee('Modo demostración')->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame('demo', $this->ultimoDelTutor()->origen);
    }

    public function test_limpieza_borra_solo_conversaciones_antiguas(): void
    {
        $vieja = TutorConversacion::create(['usuario_id' => $this->alumno->id, 'area_codigo' => 'B', 'contexto' => []]);
        $vieja->mensajes()->create(['rol' => 'alumno', 'tipo' => 'texto', 'contenido' => 'Antigua']);
        TutorConversacion::query()->whereKey($vieja->id)->update(['updated_at' => now()->subDays(200)]);
        $reciente = TutorConversacion::create(['usuario_id' => $this->alumno->id, 'area_codigo' => 'B', 'contexto' => []]);

        $this->artisan('tutor:limpiar', ['--dias' => 120])->assertSuccessful();

        $this->assertNull(TutorConversacion::find($vieja->id));
        $this->assertSame(0, TutorMensaje::where('conversacion_id', $vieja->id)->count(), 'Los mensajes se borran en cascada.');
        $this->assertNotNull(TutorConversacion::find($reciente->id));
    }

    // ───────────── Ayudantes ─────────────

    private function chat()
    {
        return Livewire::actingAs($this->alumno->fresh(), 'web')->test(TutorChat::class);
    }

    private function ultimoDelTutor(): TutorMensaje
    {
        return TutorMensaje::where('rol', 'tutor')->latest('id')->firstOrFail();
    }

    private function area(string $codigo): int
    {
        return (int) DB::table('areas_postulacion')->where('codigo', $codigo)->value('id');
    }

    private function crearAlumno(string $usuario, string $codigo): Usuario
    {
        return Usuario::create(['nombres' => 'Alumno', 'apellidos' => 'Prueba', 'usuario' => $usuario,
            'password' => 'ClaveCNI2026!', 'activo' => true, 'rol' => 'estudiante', 'area_postulacion_id' => $this->area($codigo)]);
    }

    private function crearPregunta(string $enunciado, string $nivel = 'basico'): int
    {
        $tema = DB::table('temas')->where('area_id', $this->asignatura)->value('id')
            ?? DB::table('temas')->insertGetId(['area_id' => $this->asignatura, 'nombre' => 'Ecuaciones', 'activo' => true]);
        $id = DB::table('preguntas')->insertGetId(['tema_id' => $tema, 'enunciado' => $enunciado, 'nivel' => $nivel,
            'solucion' => 'Solución de la pregunta: despeja x.', 'activo' => true]);
        foreach (['A' => true, 'B' => false, 'C' => false, 'D' => false] as $letra => $correcta) {
            DB::table('alternativas')->insert(['pregunta_id' => $id, 'letra' => $letra, 'texto' => "Opción {$letra}", 'es_correcta' => $correcta]);
        }

        return $id;
    }

    /** Intento UNI finalizado y calificado: «correctas» eligen A (clave) e «incorrectas» eligen B. */
    private function crearIntentoUni(Usuario $alumno, int $correctas, int $incorrectas): int
    {
        $total = $correctas + $incorrectas;
        $simulacro = DB::table('simulacros')->insertGetId([
            'nombre' => 'Examen UNI · Día 1 · abc123', 'tiempo_limite' => 180, 'total_preguntas' => $total,
            'puntaje_correcta' => 20, 'puntaje_incorrecta' => -1.125, 'puntaje_blanco' => 0,
            'fecha_inicio' => '2000-01-01 00:00:00', 'fecha_fin' => '2099-12-31 23:59:59', 'activo' => true,
        ]);
        DB::table('area_postulacion_simulacro')->insert(['simulacro_id' => $simulacro, 'area_postulacion_id' => $this->area('B')]);
        $intento = DB::table('intentos')->insertGetId([
            'usuario_id' => $alumno->id, 'simulacro_id' => $simulacro, 'tipo' => 'simulacro', 'estado' => 'finalizado',
            'fecha_inicio' => now()->subHour(), 'fecha_fin' => now(), 'total_preguntas' => $total,
            'correctas' => $correctas, 'incorrectas' => $incorrectas, 'en_blanco' => 0,
            'puntaje_total' => $correctas * 20 - $incorrectas * 1.125,
        ]);
        foreach (range(1, $total) as $orden) {
            $pregunta = $this->crearPregunta("Pregunta {$orden} del intento {$intento}", 'intermedio');
            $correcta = $orden <= $correctas;
            DB::table('respuestas_estudiante')->insert([
                'intento_id' => $intento, 'pregunta_id' => $pregunta, 'orden' => $orden,
                'alternativa_id' => DB::table('alternativas')->where('pregunta_id', $pregunta)->where('letra', $correcta ? 'A' : 'B')->value('id'),
                'estado_respuesta' => $correcta ? 'correcta' : 'incorrecta', 'puntaje_obtenido' => $correcta ? 20 : -1.125,
            ]);
        }
        DB::table('resultados_area')->insert([
            'intento_id' => $intento, 'area_id' => $this->asignatura, 'total_preguntas' => $total, 'aciertos' => $correctas,
            'incorrectas' => $incorrectas, 'en_blanco' => 0, 'puntaje' => $correctas * 20 - $incorrectas * 1.125,
            'porcentaje' => round($correctas / $total * 100, 2),
        ]);

        return $intento;
    }

    private function crearIntentoEnCurso(Usuario $alumno, int $pregunta): int
    {
        $simulacro = (int) DB::table('simulacros')->value('id');
        $intento = DB::table('intentos')->insertGetId([
            'usuario_id' => $alumno->id, 'simulacro_id' => $simulacro, 'tipo' => 'simulacro', 'estado' => 'en_curso',
            'fecha_inicio' => now(), 'fecha_limite' => now()->addHour(), 'total_preguntas' => 1,
        ]);
        DB::table('respuestas_estudiante')->insert(['intento_id' => $intento, 'pregunta_id' => $pregunta, 'orden' => 1]);

        return $intento;
    }
}
