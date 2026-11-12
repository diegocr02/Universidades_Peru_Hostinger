<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\IntentoService;
use Database\Seeders\ExamenDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Integridad de respuestas (escenarios ATAM de integridad de datos):
 *  - una alternativa de otra pregunta no se puede guardar;
 *  - un intento finalizado ya no acepta respuestas ni cambia su calificación.
 * Las reglas están en IntentoService::guardarRespuesta(); estas pruebas no las modifican.
 */
class IntegridadRespuestasTest extends TestCase
{
    use RefreshDatabase;

    private IntentoService $servicio;

    private Usuario $alumno;

    private int $simulacro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        $this->seed(ExamenDemoSeeder::class);
        $this->alumno = Usuario::create([
            'nombres' => 'Alumno', 'apellidos' => 'Integridad', 'usuario' => 'integridad',
            'password' => 'PruebaExamen123!', 'rol' => 'estudiante', 'activo' => true,
        ]);
        $this->simulacro = DB::table('simulacros')->where('nombre', ExamenDemoSeeder::SIMULACRO)->value('id');
        $this->servicio = app(IntentoService::class);
    }

    private function iniciar(): object
    {
        return $this->servicio->iniciar($this->alumno->id, ['tipo' => 'simulacro', 'simulacro_id' => $this->simulacro]);
    }

    private function alternativaGuardada(int $intento, int $pregunta): ?int
    {
        $valor = DB::table('respuestas_estudiante')->where('intento_id', $intento)
            ->where('pregunta_id', $pregunta)->value('alternativa_id');

        return $valor === null ? null : (int) $valor;
    }

    public function test_servicio_rechaza_alternativa_de_otra_pregunta_sin_guardar_nada(): void
    {
        $intento = $this->iniciar();
        $preguntas = $this->servicio->preguntas($intento->id);
        $pregunta = $preguntas->get(0);
        $ajena = $preguntas->get(1)->alternativas->first()->id;

        try {
            $this->servicio->guardarRespuesta($intento->id, $this->alumno->id, [
                'pregunta_id' => $pregunta->id, 'alternativa_id' => $ajena,
            ]);
            $this->fail('Se aceptó una alternativa que pertenece a otra pregunta.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('alternativa_id', $e->errors());
        }

        $this->assertNull($this->alternativaGuardada($intento->id, $pregunta->id));
    }

    public function test_api_rechaza_alternativa_de_otra_pregunta_con_422(): void
    {
        $intento = $this->iniciar();
        $preguntas = $this->servicio->preguntas($intento->id);
        $pregunta = $preguntas->get(0);
        $ajena = $preguntas->get(1)->alternativas->first()->id;

        $this->actingAs($this->alumno, 'web')
            ->postJson('/api/intentos/'.$intento->id.'/respuestas', [
                'pregunta_id' => $pregunta->id, 'alternativa_id' => $ajena,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('alternativa_id');

        $this->assertNull($this->alternativaGuardada($intento->id, $pregunta->id));
    }

    public function test_intento_finalizado_no_acepta_respuestas_ni_cambia_su_calificacion(): void
    {
        $intento = $this->iniciar();
        $pregunta = $this->servicio->preguntas($intento->id)->first();
        [$primera, $segunda] = $pregunta->alternativas->take(2)->pluck('id')->all();

        $this->servicio->guardarRespuesta($intento->id, $this->alumno->id, [
            'pregunta_id' => $pregunta->id, 'alternativa_id' => $primera,
        ]);
        $this->servicio->finalizar($intento->id, $this->alumno->id);
        $cerrado = DB::table('intentos')->where('id', $intento->id)->first();
        $this->assertSame('finalizado', $cerrado->estado);
        $this->assertNotNull($cerrado->puntaje_total, 'El examen finalizado debe quedar calificado.');

        try {
            $this->servicio->guardarRespuesta($intento->id, $this->alumno->id, [
                'pregunta_id' => $pregunta->id, 'alternativa_id' => $segunda,
            ]);
            $this->fail('Un intento finalizado aceptó una respuesta nueva.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('intento', $e->errors());
        }

        // Ni la respuesta ni la calificación cambian; finalizar de nuevo no recalifica.
        $this->assertSame((int) $primera, $this->alternativaGuardada($intento->id, $pregunta->id));
        $this->servicio->finalizar($intento->id, $this->alumno->id);
        $despues = DB::table('intentos')->where('id', $intento->id)->first();
        $this->assertSame($cerrado->puntaje_total, $despues->puntaje_total);
        $this->assertSame($cerrado->fecha_fin, $despues->fecha_fin);
    }

    public function test_api_rechaza_responder_un_intento_finalizado_con_422(): void
    {
        $intento = $this->iniciar();
        $pregunta = $this->servicio->preguntas($intento->id)->first();
        $this->actingAs($this->alumno, 'web')
            ->postJson('/api/intentos/'.$intento->id.'/finalizar')->assertOk();

        $this->postJson('/api/intentos/'.$intento->id.'/respuestas', [
            'pregunta_id' => $pregunta->id, 'alternativa_id' => $pregunta->alternativas->first()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('intento');

        $this->assertNull($this->alternativaGuardada($intento->id, $pregunta->id));
    }
}
