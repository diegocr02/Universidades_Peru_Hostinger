<?php

namespace Tests\Feature;

use Database\Seeders\AreaPostulacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiagnosticoCniTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AreaPostulacionSeeder::class);
        config(['app.url' => 'https://simulador.test']);
        Http::fake([
            'simulador.test/.env' => Http::response('', 404),
            'simulador.test/*' => Http::response('ok', 200),
        ]);
    }

    public function test_diagnostico_completo_muestra_todas_las_secciones_y_la_nota(): void
    {
        $this->artisan('cni:diagnostico', ['--todo' => true])
            ->expectsOutputToContain('DISPONIBILIDAD')
            ->expectsOutputToContain('SEGURIDAD')
            ->expectsOutputToContain('INTEGRIDAD DE DATOS')
            ->expectsOutputToContain('FUNCIONAMIENTO')
            ->expectsOutputToContain('RENDIMIENTO')
            ->expectsOutputToContain('OPERACIÓN')
            ->expectsOutputToContain('Salud de Laravel (/up)')
            ->expectsOutputToContain('Archivo .env protegido')
            ->expectsOutputToContain('NOTA GLOBAL');
    }

    public function test_el_menu_permite_elegir_una_sola_seccion(): void
    {
        $this->artisan('cni:diagnostico')
            ->expectsChoice('¿Qué deseas revisar?', 'Disponibilidad · ¿el sitio y la base responden?', [
                'Diagnóstico completo (recomendado)',
                'Disponibilidad · ¿el sitio y la base responden?',
                'Seguridad · configuración de producción',
                'Integridad de datos · bancos, claves e imágenes',
                'Funcionamiento · exámenes de cada universidad',
                'Rendimiento · tiempos de respuesta',
                'Operación · disco, permisos, errores y cron',
            ])
            ->expectsOutputToContain('DISPONIBILIDAD')
            ->doesntExpectOutputToContain('INTEGRIDAD DE DATOS')
            ->expectsOutputToContain('NOTA GLOBAL: 100%')
            ->assertSuccessful();
    }

    public function test_es_de_solo_lectura(): void
    {
        $antes = collect(['usuarios', 'preguntas', 'intentos', 'simulacros'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);

        $this->artisan('cni:diagnostico', ['--todo' => true]);

        foreach ($antes as $tabla => $cantidad) {
            $this->assertSame($cantidad, DB::table($tabla)->count(), "El diagnóstico modificó {$tabla}.");
        }
    }
}
