<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Alumno\IndicadorAdmisionUndcService;
use Tests\TestCase;

final class IndicadorAdmisionUndcServiceTest extends TestCase
{
    public function test_sin_fuente_verificada_no_inventa_ingreso(): void
    {
        config()->set('admision_undc.minimo_oficial', null);
        config()->set('admision_undc.maximo_escala_oficial', null);
        $r = app(IndicadorAdmisionUndcService::class)->evaluar(49.0, 100, 1.0);
        $this->assertSame(49.0, $r['puntaje']);
        $this->assertSame(49.0, $r['porcentaje']);
        $this->assertFalse($r['comparacion_valida']);
        $this->assertNull($r['cumple_minimo']);
    }

    public function test_si_hay_regla_verificada_y_escala_compatible_compara(): void
    {
        config()->set('admision_undc.minimo_oficial', 60.0);
        config()->set('admision_undc.maximo_escala_oficial', 100.0);
        $s = app(IndicadorAdmisionUndcService::class);
        $this->assertTrue($s->evaluar(61.0, 100, 1.0)['cumple_minimo']);
        $this->assertFalse($s->evaluar(59.0, 100, 1.0)['cumple_minimo']);
    }

    public function test_no_compara_escala_oficial_de_250_con_simulacro_de_100(): void
    {
        config()->set('admision_undc.minimo_oficial', 118.5);
        config()->set('admision_undc.maximo_escala_oficial', 250.0);
        $r = app(IndicadorAdmisionUndcService::class)->evaluar(75.0, 100, 1.0);
        $this->assertFalse($r['comparacion_valida']);
        $this->assertNull($r['minimo']);
    }

    public function test_los_puntajes_negativos_no_rompen_porcentaje(): void
    {
        $r = app(IndicadorAdmisionUndcService::class)->evaluar(-3.0, 100, 1.0);
        $this->assertSame(0.0, $r['porcentaje']);
    }
}
