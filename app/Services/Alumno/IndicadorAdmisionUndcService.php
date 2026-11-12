<?php

declare(strict_types=1);

namespace App\Services\Alumno;

final class IndicadorAdmisionUndcService
{
    /**
     * Orientación educativa para UNDC. NO asigna vacantes ni confirma ingreso.
     * @return array{puntaje:float,maximo:float,porcentaje:float,minimo:?float,comparacion_valida:bool,cumple_minimo:?bool,estado:string,proceso:string}
     */
    public function evaluar(float $puntaje, int $preguntas, float $puntajeCorrecta): array
    {
        $maximo = ($preguntas > 0 && $puntajeCorrecta > 0)
            ? round($preguntas * $puntajeCorrecta, 3) : 0.0;
        $porcentaje = $maximo > 0
            ? round(max(0.0, min(100.0, $puntaje / $maximo * 100)), 1) : 0.0;

        $minimoConfig = config('admision_undc.minimo_oficial');
        $escalaConfig = config('admision_undc.maximo_escala_oficial');
        $minimo = is_numeric($minimoConfig) ? (float) $minimoConfig : null;
        $escala = is_numeric($escalaConfig) ? (float) $escalaConfig : null;

        // Nunca aplicar una cifra de otro proceso o de otra escala de notas.
        $comparacionValida = $minimo !== null && $escala !== null
            && $minimo >= 0 && $escala > 0 && $minimo <= $escala
            && $maximo > 0 && abs($maximo - $escala) < 0.001;
        $cumple = $comparacionValida ? $puntaje >= $minimo : null;

        return [
            'puntaje' => $puntaje,
            'maximo' => $maximo,
            'porcentaje' => $porcentaje,
            'minimo' => $comparacionValida ? $minimo : null,
            'comparacion_valida' => $comparacionValida,
            'cumple_minimo' => $cumple,
            'estado' => $comparacionValida
                ? ($cumple ? 'minimo_alcanzado' : 'minimo_pendiente')
                : 'pendiente_verificacion',
            'proceso' => (string) config('admision_undc.proceso', '2026-II'),
        ];
    }
}
