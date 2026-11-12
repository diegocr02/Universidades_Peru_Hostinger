<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContingenciaPanelTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): Usuario
    {
        return Usuario::query()->create([
            'nombres' => 'Super', 'apellidos' => 'Admin', 'usuario' => 'Super_Admin',
            'password' => 'ClaveDePrueba!2026', 'rol' => 'admin', 'activo' => true,
        ]);
    }

    private function mensajeEsperadoSinClave(): string
    {
        // Sin sodium, ese es el primer requisito que se reporta; con sodium, la clave.
        return extension_loaded('sodium') ? 'CNI_BACKUP_KEY no es válida' : 'extensión PHP "sodium"';
    }

    public function test_panel_avisa_que_falta_configuracion_y_desactiva_el_boton(): void
    {
        config(['contingencia.habilitado' => true, 'contingencia.panel_habilitado' => true, 'contingencia.clave' => '']);

        $this->actingAs($this->superAdmin(), 'web')
            ->get(route('admin.contingencia.index'))
            ->assertOk()
            ->assertSee($this->mensajeEsperadoSinClave())
            ->assertSee('Falta configuración');
    }

    public function test_crear_respaldo_rechaza_con_mensaje_claro_si_falta_configuracion(): void
    {
        config(['contingencia.habilitado' => true, 'contingencia.panel_habilitado' => true, 'contingencia.clave' => '']);

        $this->actingAs($this->superAdmin(), 'web')
            ->from(route('admin.contingencia.index'))
            ->post(route('admin.contingencia.crear'))
            ->assertRedirect(route('admin.contingencia.index'))
            ->assertSessionHas('error', fn (string $e) => str_contains($e, $this->mensajeEsperadoSinClave()));
    }

    public function test_con_sodium_y_clave_valida_no_hay_aviso(): void
    {
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('Este PHP no tiene la extensión sodium.');
        }
        config(['contingencia.habilitado' => true, 'contingencia.panel_habilitado' => true,
            'contingencia.clave' => base64_encode(random_bytes(32))]);

        $this->actingAs($this->superAdmin(), 'web')
            ->get(route('admin.contingencia.index'))
            ->assertOk()
            ->assertSee('Listo para pruebas')
            ->assertDontSee('Falta configuración');
    }
}
