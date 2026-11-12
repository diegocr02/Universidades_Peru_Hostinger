<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Alumno\ExamenIndependienteService;
use App\Support\BancoPracticaUnfv;
use Database\Seeders\AreaPostulacionSeeder;
use Database\Seeders\UnfvPreguntasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BancoPracticaUnfvTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_imagenes_y_claves_sin_duplicar_y_el_examen_solo_usa_el_banco_unfv(): void
    {
        Storage::fake('public');
        $this->seed(AreaPostulacionSeeder::class);
        $this->seed(UnfvPreguntasSeeder::class);
        $ids = DB::table('preguntas')->orderBy('id')->pluck('id')->all();
        $this->seed(UnfvPreguntasSeeder::class);
        $this->assertDatabaseCount('preguntas', 150);
        $this->assertDatabaseCount('alternativas', 750);
        $this->assertDatabaseCount('temas', 18);
        $this->assertSame($ids, DB::table('preguntas')->orderBy('id')->pluck('id')->all());
        foreach (BancoPracticaUnfv::documento()['preguntas'] as $p) {
            $pregunta = DB::table('preguntas')->where('codigo', $p['codigo'].'-'.BancoPracticaUnfv::version())->first();
            Storage::disk('public')->assertExists(substr($pregunta->imagen, strlen('storage/')));
            $this->assertSame($p['correcta'], DB::table('alternativas')->where('pregunta_id', $pregunta->id)
                ->where('es_correcta', true)->sole()->letra);
        }
        $usuario = Usuario::create([
            'nombres' => 'Prueba', 'apellidos' => 'UNFV', 'usuario' => 'unfv_test',
            'password' => 'ClavePrueba2026!', 'activo' => true, 'rol' => 'estudiante',
            'area_postulacion_id' => DB::table('areas_postulacion')->where('codigo', 'C')->value('id'),
        ]);
        $servicio = app(ExamenIndependienteService::class);
        $intento = $servicio->iniciar($usuario);
        $this->assertSame(50, (int) $intento->total_preguntas);
        $preguntas = DB::table('respuestas_estudiante as r')->join('preguntas as p', 'p.id', '=', 'r.pregunta_id')
            ->where('r.intento_id', $intento->id)->get(['p.id', 'p.codigo']);
        $this->assertCount(50, $preguntas->pluck('id')->unique());
        $this->assertTrue($preguntas->every(fn ($p) => str_starts_with($p->codigo, 'UNFV-PRACTICA-')));
        $this->assertSame($intento->id, $servicio->iniciar($usuario)->id);
    }
}
