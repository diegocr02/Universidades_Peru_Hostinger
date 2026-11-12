<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Alumno\ExamenIndependienteService;
use Database\Seeders\AreaPostulacionSeeder;
use Database\Seeders\BancoUniSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BancoUniJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_el_json_sin_cambiar_claves_ni_sobrescribir_preguntas_existentes(): void
    {
        $banco = json_decode(file_get_contents(database_path('data/uni_preguntas.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->seed(BancoUniSeeder::class);
        $this->assertDatabaseCount('preguntas', count($banco['preguntas']));
        $this->assertDatabaseCount('alternativas', count($banco['preguntas']) * 5);

        foreach ($banco['preguntas'] as $p) {
            $pregunta = DB::table('preguntas')->where('codigo', $p['codigo'])->first();
            $this->assertSame($p['enunciado'], $pregunta->enunciado);
            $this->assertSame($p['solucion'], $pregunta->solucion);
            foreach ($p['alternativas'] as $letra => $texto) {
                $alternativa = DB::table('alternativas')->where('pregunta_id', $pregunta->id)->where('letra', $letra)->first();
                $this->assertSame($texto, $alternativa->texto);
                $this->assertSame($p['correcta'] === $letra, (bool) $alternativa->es_correcta);
            }
        }

        DB::table('preguntas')->where('codigo', 'UNI_AM_01')->update(['enunciado' => 'Corrección del administrador']);
        $this->seed(BancoUniSeeder::class);
        $this->assertDatabaseCount('preguntas', count($banco['preguntas']));
        $this->assertDatabaseCount('alternativas', count($banco['preguntas']) * 5);
        $this->assertDatabaseHas('preguntas', ['codigo' => 'UNI_AM_01', 'enunciado' => 'Corrección del administrador']);
    }

    public function test_el_banco_json_permite_iniciar_los_tres_dias_uni(): void
    {
        $this->seed([AreaPostulacionSeeder::class, BancoUniSeeder::class]);
        $alumno = Usuario::create([
            'nombres' => 'Alumno', 'apellidos' => 'Prueba', 'usuario' => 'alumno_json',
            'password' => 'ClavePrueba2026!', 'rol' => 'estudiante', 'activo' => true,
            'area_postulacion_id' => DB::table('areas_postulacion')->where('codigo', 'B')->value('id'),
        ]);

        foreach ([1 => 100, 2 => 40, 3 => 40] as $dia => $cantidad) {
            $intento = app(ExamenIndependienteService::class)->iniciarDiaUni($alumno, $dia);
            $this->assertSame($cantidad, DB::table('respuestas_estudiante')->where('intento_id', $intento->id)->count());
        }
    }
}
