<?php

namespace Tests\Feature;

use Database\Seeders\AreaPostulacionSeeder;
use Database\Seeders\UndcExamenSeeder;
use Database\Seeders\UndcPreguntasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class BancoUndcSeederTest extends TestCase
{
    use RefreshDatabase;

    private function comoProduccion(bool $autorizado): void
    {
        $this->app['env'] = 'production';
        config(['cni.undc_banco_produccion' => $autorizado]);
    }

    public function test_en_produccion_sin_autorizacion_no_carga_nada(): void
    {
        $this->seed(AreaPostulacionSeeder::class);
        $this->comoProduccion(false);

        $this->expectException(RuntimeException::class);
        try {
            app(UndcExamenSeeder::class)->run();
        } finally {
            $this->assertSame(0, DB::table('simulacros')->where('nombre', 'like', 'UNDC%')->count());
        }
    }

    public function test_en_produccion_autorizada_carga_dos_examenes_completos_solo_para_undc(): void
    {
        $this->seed(AreaPostulacionSeeder::class);
        $this->comoProduccion(true);
        app(UndcExamenSeeder::class)->run();
        app(UndcPreguntasSeeder::class)->run();

        $areaUndc = DB::table('areas_postulacion')->where('codigo', 'A')->value('id');
        foreach (['A', 'B'] as $tipo) {
            $simulacro = DB::table('simulacros')
                ->where('nombre', "UNDC - Admisión Ordinaria Tipo {$tipo} (preparación)")->first();
            $this->assertNotNull($simulacro);
            $this->assertTrue((bool) $simulacro->activo, "El examen tipo {$tipo} debe quedar habilitado.");
            $this->assertSame('2099-12-31 23:59:59', (string) $simulacro->fecha_fin, 'No debe caducar.');
            $this->assertSame([(int) $areaUndc], DB::table('area_postulacion_simulacro')
                ->where('simulacro_id', $simulacro->id)->pluck('area_postulacion_id')->map(fn ($id) => (int) $id)->all());
            $this->assertSame(100, (int) DB::table('simulacro_area')->where('simulacro_id', $simulacro->id)->sum('cantidad_preguntas'));
        }

        $this->assertSame(200, DB::table('preguntas')->where('codigo', 'like', 'UNDC-%')->count());
        $this->assertSame(200, DB::table('alternativas as a')->join('preguntas as p', 'p.id', '=', 'a.pregunta_id')
            ->where('p.codigo', 'like', 'UNDC-%')->where('a.es_correcta', true)->count());
    }
}
