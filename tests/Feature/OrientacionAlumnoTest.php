<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Database\Seeders\AreaPostulacionSeeder;
use Database\Seeders\CarreraSeeder;
use Database\Seeders\ExamenDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrientacionAlumnoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AreaPostulacionSeeder::class);
        $this->seed(CarreraSeeder::class);
    }

    private function alumno(string $nombre, ?int $carreraId = null): Usuario
    {
        return Usuario::create([
            'nombres' => $nombre, 'apellidos' => 'CNI', 'usuario' => $nombre,
            'password' => 'ClaveCNI2026!', 'rol' => 'estudiante', 'activo' => true,
            'carrera_id' => $carreraId,
        ]);
    }

    private function area(string $codigo): int
    {
        return DB::table('areas_postulacion')->where('codigo', $codigo)->value('id');
    }

    public function test_alumno_puede_volver_a_elegir_universidad(): void
    {
        $alumno = $this->alumno('cambiar', $this->carrera('Derecho'));
        $this->actingAs($alumno, 'web');
        $this->get(route('alumno.panel'))->assertOk()->assertSee('Cambiar universidad');
        $this->get(route('alumno.universidad.cambiar'))->assertOk()
            ->assertSee(route('alumno.universidad.guardar'));
        $this->assertDatabaseHas('usuarios', ['id' => $alumno->id, 'carrera_id' => $this->carrera('Derecho')]);
        $this->post(route('alumno.universidad.guardar'), ['area_id' => 99999])
            ->assertSessionHasErrors('area_id');
        $this->post(route('alumno.universidad.guardar'), ['area_id' => $this->area('C')])
            ->assertRedirect(route('alumno.panel'));
        $this->assertDatabaseHas('usuarios', ['id' => $alumno->id,
            'area_postulacion_id' => $this->area('C'), 'carrera_id' => null]);
        $this->actingAs($alumno->fresh(), 'web')->get(route('alumno.panel'))->assertOk()->assertSee('Villarreal');
        $alumno->update(['rol' => 'docente']);
        $this->actingAs($alumno->fresh(), 'web');
        $this->get(route('alumno.universidad.cambiar'))->assertForbidden();
        $this->post(route('alumno.universidad.guardar'), ['area_id' => $this->area('B')])->assertForbidden();
    }

    private function carrera(string $nombre): int
    {
        return DB::table('carreras')->where('nombre', $nombre)->value('id');
    }

    public function test_primer_login_ofrece_cinco_areas(): void
    {
        $this->assertSame(5, DB::table('areas_postulacion')->count());
        $this->alumno('primero');
        $this->post('/login', ['usuario' => 'primero', 'password' => 'ClaveCNI2026!'])
            ->assertRedirect(route('alumno.elegir-area'));
        $this->get(route('alumno.elegir-area'))->assertOk()
            ->assertSee('¿A qué universidad deseas postular?')
            ->assertSee('UNI')->assertSee('UNDC')->assertSee('Villarreal')->assertSee('San Marcos')
            ->assertDontSee('Ciencias Económicas y de Gestión');
        $this->get(route('alumno.panel'))->assertRedirect(route('alumno.elegir-area'));
        $this->get('/panel')->assertRedirect(route('alumno.panel'));
    }

    public function test_seleccion_de_area_abre_panel_y_guarda_carrera_del_mismo_grupo(): void
    {
        $alumno = $this->alumno('nuevo');
        $this->actingAs($alumno, 'web');
        $this->post(route('alumno.area.guardar'), ['area_id' => $this->area('C')])
            ->assertRedirect(route('alumno.panel'));
        $this->get(route('alumno.panel'))->assertOk()
            // El panel muestra la universidad del área; la carrera se guarda en el perfil.
            ->assertSee('Villarreal')->assertDontSee('Medicina Humana');
        $this->post(route('alumno.carrera.guardar'), ['carrera_id' => $this->carrera('Ingeniería de Sistemas')])
            ->assertRedirect(route('alumno.panel'));
        $this->assertDatabaseHas('usuarios', ['id' => $alumno->id,
            'carrera_id' => $this->carrera('Ingeniería de Sistemas')]);
        $this->get(route('alumno.panel'))->assertOk();
        $this->get(route('alumno.elegir-area'))->assertRedirect(route('alumno.panel'));
    }

    public function test_carrera_ajena_y_area_inactiva_son_rechazadas(): void
    {
        $this->actingAs($this->alumno('valida'), 'web');
        $this->post(route('alumno.area.guardar'), ['area_id' => $this->area('E')]);
        $this->post(route('alumno.carrera.guardar'), ['carrera_id' => $this->carrera('Ingeniería de Sistemas')])
            ->assertSessionHasErrors('carrera_id');
        $this->assertDatabaseHas('usuarios', ['usuario' => 'valida', 'carrera_id' => null]);

        // Una segunda cuenta aún no ha elegido área; se validan entradas inválidas.
        $this->actingAs($this->alumno('sin_area_todavia'), 'web');
        DB::table('areas_postulacion')->where('codigo', 'A')->update(['activo' => false]);
        $this->post(route('alumno.area.guardar'), ['area_id' => $this->area('A')])
            ->assertSessionHasErrors('area_id');
        $this->post(route('alumno.area.guardar'), ['area_id' => 99999])
            ->assertSessionHasErrors('area_id');
    }

    public function test_area_y_carrera_persisten_entre_sesiones_y_no_pueden_reemplazarse(): void
    {
        $this->alumno('persistente', $this->carrera('Derecho'));
        $this->post('/login', ['usuario' => 'persistente', 'password' => 'ClaveCNI2026!'])
            ->assertRedirect(route('alumno.panel'));
        $this->get(route('alumno.panel'))->assertOk()->assertSee('panel-alumno.css');
        $this->get(route('alumno.inicio'))->assertRedirect(route('alumno.panel'));
        $this->post(route('alumno.area.guardar'), ['area_id' => $this->area('C')])->assertForbidden();
        $this->post(route('alumno.carrera.guardar'), ['carrera_id' => $this->carrera('Ingeniería de Sistemas')])
            ->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['usuario' => 'persistente', 'password' => 'ClaveCNI2026!'])
            ->assertRedirect(route('alumno.panel'));
    }

    public function test_solo_muestra_examenes_de_su_area_y_backend_rechaza_otras_areas(): void
    {
        $this->seed(ExamenDemoSeeder::class);
        $simulacro = DB::table('simulacros')->where('nombre', ExamenDemoSeeder::SIMULACRO)->value('id');
        DB::table('area_postulacion_simulacro')->insert([
            'area_postulacion_id' => $this->area('C'),
            'simulacro_id' => $simulacro, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($this->alumno('ingeniera', $this->carrera('Ingeniería de Sistemas')), 'web')
            ->get(route('alumno.panel'))->assertOk();
        $this->actingAs($this->alumno('abogada', $this->carrera('Derecho')), 'web')
            ->get(route('alumno.intentos'))->assertDontSee(ExamenDemoSeeder::SIMULACRO);
        $this->post(route('examen.iniciar', $simulacro))->assertSessionHasErrors('carrera_id');
    }

    public function test_un_alumno_no_ve_resultados_ajenos(): void
    {
        $dueno = $this->alumno('dueno', $this->carrera('Derecho'));
        $otro = $this->alumno('otro', $this->carrera('Ingeniería de Sistemas'));
        $id = DB::table('intentos')->insertGetId([
            'usuario_id' => $dueno->id, 'tipo' => 'practica',
            'fecha_inicio' => now(), 'estado' => 'en_curso', 'total_preguntas' => 0,
        ]);
        $this->actingAs($otro, 'web')->get(route('alumno.resultados', $id))->assertNotFound();
        $docente = Usuario::create([
            'nombres' => 'Docente', 'apellidos' => 'CNI', 'usuario' => 'docente_x',
            'password' => 'ClaveCNI2026!', 'rol' => 'docente', 'activo' => true,
        ]);
        $this->actingAs($docente, 'web')->get(route('alumno.panel'))->assertForbidden();
    }

    public function test_url_del_examen_no_permite_saltarse_la_eleccion_de_carrera(): void
    {
        $this->seed(ExamenDemoSeeder::class);
        $alumno = $this->alumno('sin_carrera');
        $simulacroId = DB::table('simulacros')->where('nombre', ExamenDemoSeeder::SIMULACRO)->value('id');
        // API histórica admite pruebas genéricas, pero su interfaz web exige la elección previa.
        $intento = app(\App\Services\IntentoService::class)
            ->iniciar($alumno->id, ['tipo' => 'simulacro', 'simulacro_id' => $simulacroId]);
        $this->actingAs($alumno, 'web')->get(route('examen', $intento->id))
            ->assertRedirect(route('alumno.elegir-area'));
        $this->post(route('examen.iniciar', $simulacroId))->assertForbidden();
    }

    public function test_elegir_area_una_vez_muestra_panel_y_recuerda_eleccion_sin_carrera(): void
    {
        $estudiante = $this->alumno('primer_ingreso');
        $this->post('/login', ['usuario' => 'primer_ingreso', 'password' => 'ClaveCNI2026!'])
            ->assertRedirect(route('alumno.elegir-area'));
        $this->get(route('alumno.elegir-area'))->assertOk()
            ->assertSee('selector-page__grid')->assertSee('area-opcion');

        $this->post(route('alumno.area.guardar'), ['area_id' => $this->area('C')])
            ->assertRedirect(route('alumno.panel'));
        $this->assertDatabaseHas('usuarios', [
            'id' => $estudiante->id, 'area_postulacion_id' => $this->area('C'), 'carrera_id' => null,
        ]);
        $this->get(route('alumno.panel'))->assertOk()->assertSee('Villarreal')
            ->assertSee('Examen UNFV');
        $this->get(route('alumno.elegir-area'))->assertRedirect(route('alumno.panel'));
        $this->post('/logout')->assertRedirect('/login');

        $this->post('/login', ['usuario' => 'primer_ingreso', 'password' => 'ClaveCNI2026!'])
            ->assertRedirect(route('alumno.panel'));
        $this->get(route('alumno.panel'))->assertOk()->assertSee('Villarreal')
            ->assertSee('Examen UNFV');
        $this->post(route('alumno.area.guardar'), ['area_id' => $this->area('E')])->assertForbidden();
    }

    public function test_alumno_sin_carrera_rinde_simulacro_exclusivo_de_su_area(): void
    {
        $this->seed(ExamenDemoSeeder::class);
        $simulacro = DB::table('simulacros')->where('nombre', ExamenDemoSeeder::SIMULACRO)->value('id');
        DB::table('area_postulacion_simulacro')->insert([
            'area_postulacion_id' => $this->area('C'), 'simulacro_id' => $simulacro,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($this->alumno('sistemas'), 'web');
        $this->post(route('alumno.area.guardar'), ['area_id' => $this->area('C')]);
        $this->get(route('alumno.panel'))->assertOk();
        $respuesta = $this->post(route('examen.iniciar', $simulacro));
        $intento = DB::table('intentos')->where('usuario_id', auth('web')->id())->first();
        $this->assertNotNull($intento);
        $respuesta->assertRedirect(route('examen', $intento->id));
        $this->assertNull($intento->carrera_id);
        $this->get(route('examen', $intento->id))->assertOk()->assertSee('Simulador de examen');

        $otra = $this->alumno('humanidades');
        $this->actingAs($otra, 'web');
        $this->post(route('alumno.area.guardar'), ['area_id' => $this->area('E')]);
        $this->get(route('alumno.intentos'))->assertDontSee(ExamenDemoSeeder::SIMULACRO);
        $this->post(route('examen.iniciar', $simulacro))->assertSessionHasErrors('carrera_id');
    }

    public function test_seeder_no_reasigna_codigos_legados_sin_migracion(): void
    {
        DB::table('areas_postulacion')->where('codigo', 'B')->update(['nombre' => 'Ingenierías (catálogo legado)']);
        $this->expectException(\RuntimeException::class);
        $this->seed(AreaPostulacionSeeder::class);
    }
}
