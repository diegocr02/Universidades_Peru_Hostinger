<?php

namespace Tests\Feature;

use App\Services\Alumno\ReferenciaIngresoService;
use Tests\TestCase;

class ReferenciaIngresoTest extends TestCase
{
    public function test_uni_no_muestra_veredicto_ni_puntos_faltantes_antes_de_completar_los_tres_dias(): void
    {
        foreach ([0, 1, 2, 3] as $dias) {
            $evaluacion = app(ReferenciaIngresoService::class)->uni([
                'puntaje' => 100, 'maximo' => 1800,
                'completo' => $dias === 3, 'dias_rendidos' => $dias,
            ]);
            $html = view('alumno.partials.referencia-ingreso', ['evaluacionIngreso' => $evaluacion])->render();
            $this->assertStringContainsString('800.700', $html);
            $this->assertStringContainsString('cni-ingreso-meta', $html);
            if ($dias < 3) {
                $this->assertNull($evaluacion['faltan']);
                $this->assertStringNotContainsString('Fuera del rango', $html);
                $this->assertStringNotContainsString('Puntos para alcanzar', $html);
                $this->assertStringContainsString('Días completados', $html);
            } else {
                $this->assertStringContainsString('Fuera del rango de ingreso referencial', $html);
                $this->assertStringContainsString('700.700', $html);
            }
        }
    }

    public function test_san_marcos_compara_milesimas_y_el_minimo_depende_del_area(): void
    {
        $servicio = app(ReferenciaIngresoService::class);
        $this->assertFalse($servicio->unmsm(899.999, 'C')['supera']);
        $this->assertSame(0.001, $servicio->unmsm(899.999, 'C')['faltan']);
        $this->assertTrue($servicio->unmsm(900.0, 'C')['supera']);
        $this->assertFalse($servicio->unmsm(699.999, 'E')['supera']);
        $this->assertTrue($servicio->unmsm(700.0, 'E')['supera']);
        $this->assertSame(0, $servicio->unmsm(1800.0, 'C')['faltan']);
        $this->assertFalse($servicio->unmsm(2000.0, 'C')['ingreso_confirmado']);
        $this->assertNull($servicio->unmsm(2000.0, 'X')['supera']);
        $this->assertSame(902.25, $servicio->unmsm(-2.25, 'C')['faltan']);
    }

    public function test_uni_requiere_tres_dias_y_compara_la_mitad_del_primer_puesto(): void
    {
        $servicio = app(ReferenciaIngresoService::class);
        $resumen = ['puntaje' => 800.699, 'maximo' => 1800, 'completo' => true];
        $r = $servicio->uni($resumen);
        $this->assertSame(800.7, $r['minimo']);
        $this->assertSame(0.001, $r['faltan']);
        $this->assertFalse($r['supera']);
        $resumen['puntaje'] = 800.700;
        $this->assertTrue($servicio->uni($resumen)['supera']);
        $this->assertSame('En el rango de ingreso referencial', $servicio->uni($resumen)['estado']);
        $this->assertStringNotContainsString('trome', strtolower(json_encode($servicio->uni($resumen))));
        $this->assertFalse($servicio->uni($resumen)['ingreso_confirmado']);
        $resumen['completo'] = false;
        $this->assertNull($servicio->uni($resumen)['supera']);
        $this->assertStringContainsString('tres días', $servicio->uni($resumen)['estado']);
        $resumen['completo'] = true;
        $resumen['escala_compatible'] = false;
        $this->assertNull($servicio->uni($resumen)['minimo']);
        config(['admision_referencial.uni.primer_puesto' => null]);
        unset($resumen['escala_compatible']);
        $this->assertNull($servicio->uni($resumen)['supera']);
    }
}
