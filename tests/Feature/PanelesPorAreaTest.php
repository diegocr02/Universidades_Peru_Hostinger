<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Database\Seeders\AreaPostulacionSeeder;
use Database\Seeders\CarreraSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Una sola ruta y una sola plantilla Blade, con cinco identidades visuales.
 */
class PanelesPorAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cni.alumno_areas_enabled' => true]);
        $this->seed(AreaPostulacionSeeder::class);
        $this->seed(CarreraSeeder::class);
    }

    private function estudiante(string $codigo, bool $conArea = true): Usuario
    {
        return Usuario::create([
            'nombres' => 'Alumno',
            'apellidos' => 'Area '.$codigo,
            'usuario' => uniqid('alumno_'.$codigo.'_'),
            'password' => 'ClaveCNI2026!',
            'rol' => 'estudiante',
            'activo' => true,
            'area_postulacion_id' => $conArea
                ? DB::table('areas_postulacion')->where('codigo', $codigo)->value('id')
                : null,
        ]);
    }

    public function test_cinco_areas_usan_misma_ruta_y_plantilla_pero_identidad_diferente(): void
    {
        $temas = config('panel_alumno.areas');
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], array_keys($temas));

        foreach ($temas as $codigo => $tema) {
            $this->actingAs($this->estudiante($codigo), 'web');
            $respuesta = $this->get(route('alumno.panel'))->assertOk();
            // La UNDC (A) tiene su panel propio con otra estructura.
            if ($codigo === 'A') {
                $respuesta->assertViewIs('alumno.undc.panel')->assertSee('undc-panel.css');

                continue;
            }
            $respuesta->assertSee('data-area="'.$codigo.'"', false)
                ->assertSee($tema['descripcion'])
                ->assertSee('aria-controls="cni-alumno-sidebar"', false)
                ->assertSee('data-sidebar-toggle', false);

            // La UNI (B) tiene su panel de tres días; las demás comparten la plantilla general.
            if ($codigo === 'B') {
                $respuesta->assertViewIs('alumno.uni.panel')->assertSee('panel-uni.css');

                continue;
            }
            // San Marcos (E) tiene su panel propio con las áreas A, C y E.
            if ($codigo === 'E') {
                $respuesta->assertViewIs('alumno.unmsm.panel')->assertSee('panel-unmsm.css');

                continue;
            }
            $respuesta->assertViewIs('alumno.panel')
                ->assertSee('panel-alumno.css')
                ->assertSee('Dar examen')
                ->assertSee('Mascota del área de')
                ->assertSee('href="#perfil"', false)
                ->assertSee('href="#progreso"', false)
                ->assertSee('href="#historial"', false);
        }
    }

    public function test_eleccion_persiste_y_llega_a_la_misma_pagina_para_cada_area(): void
    {
        foreach (['A', 'B', 'C', 'D', 'E'] as $codigo) {
            $usuario = $this->estudiante($codigo, false);
            $idArea = DB::table('areas_postulacion')->where('codigo', $codigo)->value('id');

            $this->actingAs($usuario, 'web')
                ->post(route('alumno.area.guardar'), ['area_id' => $idArea])
                ->assertRedirect(route('alumno.panel'));

            $this->assertDatabaseHas('usuarios', [
                'id' => $usuario->id,
                'area_postulacion_id' => $idArea,
                'carrera_id' => null,
            ]);

            $panel = $this->get(route('alumno.panel'))->assertOk();
            $codigo === 'A'
                ? $panel->assertViewIs('alumno.undc.panel')
                : $panel->assertSee('data-area="'.$codigo.'"', false);
        }
    }

    public function test_una_cuenta_sin_area_ve_el_selector_en_vez_del_panel(): void
    {
        $this->actingAs($this->estudiante('A', false), 'web');
        $this->get(route('alumno.panel'))
            ->assertRedirect(route('alumno.elegir-area'));
    }

    public function test_las_urls_anteriores_de_diego_y_dafne_redirigen_al_panel_unificado(): void
    {
        $this->actingAs($this->estudiante('B'), 'web');
        $this->get(route('alumno.ciencias-basicas'))->assertRedirect(route('alumno.panel'));

        $this->actingAs($this->estudiante('E'), 'web');
        $this->get(route('alumno.inicio'))->assertRedirect(route('alumno.panel'));
    }

    public function test_los_temas_de_otras_areas_no_se_abren_mediante_url_directa(): void
    {
        $this->actingAs($this->estudiante('E'), 'web');
        $this->get(route('alumno.tema', ['tema' => 9999]))->assertNotFound();
    }

    public function test_menu_lateral_tiene_cuatro_opciones_y_un_boton_para_contraerlo(): void
    {
        $this->actingAs($this->estudiante('C'), 'web');
        $html = $this->get(route('alumno.panel'))->assertOk()->getContent();

        $this->assertSame(1, preg_match(
            '/<nav class="cni-alumno-menu"[^>]*>(.*?)<\\/nav>/s',
            $html,
            $navegacion
        ));
        $this->assertSame(4, preg_match_all('/<a\\b/', $navegacion[1]));
        $this->assertStringContainsString('Mi perfil', $navegacion[1]);
        $this->assertStringContainsString('Mi progreso', $navegacion[1]);
        $this->assertStringContainsString('Historial', $navegacion[1]);
        $this->assertStringContainsString('Cambiar universidad', $navegacion[1]);
        $this->assertStringContainsString('href="'.route('alumno.universidad.cambiar').'"', $navegacion[1]);
        $this->assertStringContainsString('aria-expanded="true"', $html);
        $this->assertStringContainsString('data-sidebar-backdrop', $html);
        $this->assertStringContainsString('id="progreso"', $html);
        $this->assertStringContainsString('id="historial"', $html);
        $this->assertStringContainsString('id="perfil"', $html);
    }

    public function test_mi_perfil_abre_de_entrada_con_portada_y_datos_del_alumno(): void
    {
        $this->actingAs($this->estudiante('C'), 'web');
        $html = $this->get(route('alumno.panel'))->assertOk()->getContent();

        $this->assertStringContainsString('id="perfil" data-panel-view="perfil"', $html);
        $this->assertSame(1, preg_match_all('/class="cni-alumno-banner"/', $html));
        $this->assertStringContainsString('Datos del alumno', $html);
        $this->assertStringContainsString('href="#perfil" class="is-active"', $html);
        $this->assertStringContainsString('id="progreso" data-panel-view="progreso"', $html);
        $this->assertStringContainsString('id="historial" data-panel-view="historial"', $html);
        $this->assertMatchesRegularExpression('/id="progreso"[^>]*hidden>/', $html);
        $this->assertMatchesRegularExpression('/id="historial"[^>]*hidden>/', $html);
        $this->assertStringNotContainsString('data-panel-view="intentos"', $html);
        $this->assertStringContainsString('const mostrarSeccion =', $html);
    }

    public function test_el_panel_ofrece_dar_examen_del_area(): void
    {
        // El área E usa ahora el panel San Marcos (ver ExamenUnmsmTest); D sigue en el panel compartido.
        $this->actingAs($this->estudiante('D'), 'web');
        $html = $this->get(route('alumno.panel'))->assertOk()->getContent();

        $this->assertStringContainsString(route('examen.independiente'), $html);
        $this->assertStringContainsString('Dar examen', $html);
        $this->assertStringContainsString('Mi perfil', $html);
    }

    public function test_menu_usa_mi_perfil_mi_progreso_historial_para_cada_area(): void
    {
        // E (San Marcos) tiene su propio menú de cuatro opciones; ver ExamenUnmsmTest.
        foreach (['C', 'D'] as $codigo) {
            $this->actingAs($this->estudiante($codigo), 'web');
            $html = $this->get(route('alumno.panel'))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/href="#perfil"[^>]*>.*?Mi perfil/s', $html);
            $this->assertMatchesRegularExpression('/href="#progreso"[^>]*>.*?Mi progreso/s', $html);
            $this->assertMatchesRegularExpression('/href="#historial"[^>]*>.*?Historial/s', $html);
            $this->assertStringContainsString('data-panel-view="progreso"', $html);
            $this->assertStringContainsString('Completa tu primer simulacro para ver tu progreso.', $html);
            $this->assertStringNotContainsString('href="#intentos"', $html);
        }
    }

    public function test_docente_no_puede_abrir_el_panel_de_alumno(): void
    {
        $docente = Usuario::create([
            'nombres' => 'Docente', 'apellidos' => 'CNI',
            'usuario' => 'docente_unificado',
            'password' => 'ClaveCNI2026!',
            'rol' => 'docente', 'activo' => true,
        ]);

        $this->actingAs($docente, 'web')
            ->get(route('alumno.panel'))->assertForbidden();
    }
}
