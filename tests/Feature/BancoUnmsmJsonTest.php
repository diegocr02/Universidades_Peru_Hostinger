<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\Alumno\ExamenIndependienteService;
use App\Services\IntentoService;
use App\Support\BancoUnmsmJson;
use Database\Seeders\AreaPostulacionSeeder;
use Database\Seeders\BancoUnmsmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BancoUnmsmJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_json_con_claves_e_imagenes_y_no_reescribe_registros(): void
    {
        Storage::fake('public');
        $banco = BancoUnmsmJson::leer()['preguntas'];
        $this->seed(BancoUnmsmSeeder::class);
        $this->assertDatabaseCount('preguntas', count($banco));
        $this->assertDatabaseCount('alternativas', count($banco) * 5);
        foreach ($banco as $p) {
            $pregunta = DB::table('preguntas')->where('codigo', BancoUnmsmJson::codigo($p))->first();
            $this->assertSame($p['enunciado'], $pregunta->enunciado);
            $this->assertSame($p['solucion'], $pregunta->solucion);
            foreach ($p['alternativas'] as $letra => $texto) {
                $a = DB::table('alternativas')->where('pregunta_id', $pregunta->id)->where('letra', $letra)->first();
                $this->assertSame($texto, $a->texto);
                $this->assertSame($letra === $p['correcta'], (bool) $a->es_correcta);
            }
            if ($p['imagen']) {
                $this->assertSame($p['imagen']['svg'], Storage::disk('public')->get(substr($pregunta->imagen, strlen('storage/'))));
            }
        }
        $primera = DB::table('preguntas')->orderBy('id')->first();
        DB::table('preguntas')->where('id', $primera->id)->update(['enunciado' => 'Corrección conservada']);
        $this->seed(BancoUnmsmSeeder::class);
        $this->assertDatabaseCount('preguntas', count($banco));
        $this->assertDatabaseHas('preguntas', ['id' => $primera->id, 'enunciado' => 'Corrección conservada']);
    }

    public function test_c_y_e_sortean_solo_su_matematica_y_conservan_los_intentos_anteriores(): void
    {
        Storage::fake('public');
        $this->seed([AreaPostulacionSeeder::class, BancoUnmsmSeeder::class]);
        $motor = app(IntentoService::class);
        foreach (['C', 'E'] as $area) {
            $usuario = Usuario::create(['nombres' => 'Alumno', 'apellidos' => 'JSON', 'usuario' => 'json_'.$area,
                'password' => 'Prueba2026!', 'rol' => 'estudiante', 'activo' => true,
                'area_postulacion_id' => DB::table('areas_postulacion')->where('codigo', 'E')->value('id')]);
            DB::table('usuarios')->where('id', $usuario->id)->update(['area_unmsm' => $area]);
            $usuario->refresh();
            $intento = app(ExamenIndependienteService::class)->iniciarUnmsm($usuario);
            $preguntas = $motor->preguntas($intento->id);
            $this->assertCount(100, $preguntas);
            $matematicas = $preguntas->filter(fn ($p) => str_starts_with($p->area, 'San Marcos '));
            $this->assertGreaterThan(10, $matematicas->count());
            foreach ($matematicas as $p) {
                $this->assertStringStartsWith('San Marcos '.$area.' ', $p->area);
                $this->assertStringStartsWith('SM-'.$area.'-', DB::table('preguntas')->where('id', $p->id)->value('codigo'));
            }
            $conFigura = $matematicas->first(fn ($p) => $p->imagen !== null);
            $this->assertNotNull($conFigura);
            $indice = $preguntas->search(fn ($p) => $p->id === $conFigura->id);
            $this->actingAs($usuario, 'web')->get(route('examen', $intento->id))->assertOk();
            \Livewire\Livewire::actingAs($usuario, 'web')->test(\App\Livewire\Examen::class, ['intento' => $intento->id])
                ->call('ir', $indice)->assertSee('Ampliar figura');
            $ids = $preguntas->pluck('id')->all();
            $this->seed(BancoUnmsmSeeder::class);
            $this->assertSame($ids, $motor->preguntas($intento->id)->pluck('id')->all());
            $motor->finalizar($intento->id, $usuario->id);
            $this->get(route('alumno.resultados', $intento->id))->assertOk()
                ->assertSee('Proyección de ingreso')->assertSee('Ingreso oficial: no determinado')
                ->assertSee($area === 'C' ? '900.000' : '700.000');
        }
    }

    public function test_rechaza_una_clave_ambigua_y_svg_activo(): void
    {
        $documento = json_decode(file_get_contents(BancoUnmsmJson::ruta()), true);
        $ruta = tempnam(sys_get_temp_dir(), 'sm-json-');
        try {
            foreach (['alternativas', 'imagen'] as $tipo) {
                $malo = $documento;
                if ($tipo === 'alternativas') {
                    $malo['preguntas'][0]['alternativas']['B'] = $malo['preguntas'][0]['alternativas']['A'];
                } else {
                    $malo['preguntas'][0]['imagen'] = ['svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'descripcion' => 'Insegura'];
                }
                file_put_contents($ruta, json_encode($malo));
                try {
                    BancoUnmsmJson::leer($ruta);
                    $this->fail('Debió rechazar '.$tipo);
                } catch (RuntimeException $e) {
                    $this->assertNotEmpty($e->getMessage());
                }
            }
        } finally {
            unlink($ruta);
        }
    }
}
