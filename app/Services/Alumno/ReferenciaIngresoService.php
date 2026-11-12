<?php

namespace App\Services\Alumno;

/** Compara el entrenamiento con referencias publicadas, sin adjudicar vacantes. */
class ReferenciaIngresoService
{
    public function unmsm(float $puntaje, string $area): array
    {
        $datos = config('admision_referencial.unmsm');
        $minimo = $datos['minimos'][$area] ?? null;

        return $this->comparar($puntaje, 2000, $minimo, true) + [
            'universidad' => 'San Marcos', 'proceso' => $datos['proceso'],
            'referencia_contexto' => 'Área '.$area.' · '.$datos['sede'],
            'detalle' => 'Área '.$area.' · sede '.$datos['sede'].'. El mínimo del área no es el puntaje del último ingresante de tu carrera.',
            'regla' => 'Mínimo publicado para el área: '.($minimo ?? 'sin referencia').' puntos. Las vacantes dependen del orden de mérito por carrera.',
            'fuentes' => [['titulo' => 'Mínimos publicados por la UNMSM', 'url' => $datos['fuente']]],
        ];
    }

    public function uni(array $acumulado): array
    {
        $datos = config('admision_referencial.uni');
        $primero = $datos['primer_puesto'];
        $maximo = (float) $acumulado['maximo'];
        // No aplicar un umbral en puntos a una escala distinta de la referencia.
        $minimo = is_numeric($primero) && $primero > 0 && $primero <= $datos['maximo']
            && $maximo === (float) $datos['maximo'] && ($acumulado['escala_compatible'] ?? true)
            ? $primero / 2 : null;
        $regla = 'Art. 22: al menos la mitad del puntaje del primer ingresante y una vacante por orden de mérito. ';
        $regla .= $minimo === null ? 'Falta una referencia compatible con la escala de este entrenamiento.'
            : 'Referencia '.$datos['proceso'].': '.number_format((float) $primero, 3, '.', ',').' ÷ 2 = '.number_format($minimo, 3, '.', ',').' puntos.';

        $comparacion = $this->comparar((float) $acumulado['puntaje'], $maximo, $minimo, (bool) $acumulado['completo']);
        if ($comparacion['supera'] !== null) {
            $comparacion['estado'] = $comparacion['supera'] ? 'En el rango de ingreso referencial' : 'Fuera del rango de ingreso referencial';
        }

        return $comparacion + [
            'universidad' => 'UNI', 'proceso' => $datos['proceso'],
            'referencia_contexto' => 'Acumulado de los tres días · Ciencias e Ingeniería',
            'dias_rendidos' => $acumulado['dias_rendidos'] ?? ($acumulado['completo'] ? 3 : 0),
            'detalle' => 'Acumulado actual: último intento calificado de cada uno de los tres días. Referencia histórica para Ciencias e Ingeniería; no incluye Aptitud Vocacional de Arquitectura.',
            'regla' => $regla.' La escala del entrenamiento es referencial.',
            'fuentes' => [
                ['titulo' => 'Reglamento UNI · arts. '.$datos['articulos'], 'url' => $datos['reglamento']],
                ['titulo' => 'Primer puesto: publicación de la UNI', 'url' => $datos['fuente_primer_puesto']],
            ],
        ];
    }

    private function comparar(float $puntaje, float $maximo, ?float $minimo, bool $completo): array
    {
        $puntos = (int) round($puntaje * 1000);
        $meta = $minimo === null ? null : (int) round($minimo * 1000);
        $supera = $completo && $meta !== null ? $puntos >= $meta : null;

        return [
            'puntaje' => $puntaje, 'maximo' => $maximo, 'minimo' => $minimo,
            'completo' => $completo, 'supera' => $supera,
            'faltan' => ! $completo || $meta === null ? null : max(0, $meta - $puntos) / 1000,
            'ingreso_confirmado' => false,
            'estado' => ! $completo ? 'Completa los tres días para evaluar tu resultado'
                : ($supera === null ? 'Referencia de puntaje no disponible'
                    : ($supera ? 'Superas o alcanzas el mínimo de referencia' : 'Aún no alcanzas el mínimo de referencia')),
        ];
    }
}
