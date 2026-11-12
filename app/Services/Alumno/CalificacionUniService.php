<?php

namespace App\Services\Alumno;

use App\Support\PerfilExamenCni;
use InvalidArgumentException;

/**
 * Reglas de puntaje del examen UNI en tres días (escala referencial de entrenamiento).
 * Único lugar donde se calculan puntos ganados, descuentos, acumulado y nota sobre 20.
 * Todo se calcula en milésimas enteras (la precisión de la base de datos) y se
 * convierte a decimales solo al devolver el resultado.
 */
class CalificacionUniService
{
    /** @return array{correcta: float, incorrecta: float, blanco: float, maximo: float} */
    public function escala(int $dia): array
    {
        $escala = config('examen_uni.dias.'.$dia);
        if (! is_array($escala)) {
            throw new InvalidArgumentException("El día UNI {$dia} no tiene escala configurada.");
        }

        return [
            'correcta' => (float) $escala['correcta'],
            'incorrecta' => abs((float) $escala['incorrecta']),
            'blanco' => (float) ($escala['blanco'] ?? 0),
            'maximo' => (float) $escala['maximo'],
        ];
    }

    /** Valores para la plantilla del simulacro (la penalización se guarda negativa). */
    public function valoresPlantilla(int $dia): array
    {
        $escala = $this->escala($dia);

        return [
            'puntaje_correcta' => $escala['correcta'],
            'puntaje_incorrecta' => -$escala['incorrecta'],
            'puntaje_blanco' => $escala['blanco'],
        ];
    }

    /**
     * Puntaje de una prueba: (correctas × acierto) − (incorrectas × penalización).
     * Las preguntas en blanco no suman ni descuentan.
     *
     * @param  array{correcta: float, incorrecta: float}  $valores  penalización en positivo
     */
    public function puntajeDia(int $correctas, int $incorrectas, int $enBlanco, array $valores, float $maximo): array
    {
        if ($correctas < 0 || $incorrectas < 0 || $enBlanco < 0) {
            throw new InvalidArgumentException('Las cantidades no pueden ser negativas.');
        }
        $ganados = $correctas * $this->milesimas($valores['correcta']);
        $descontados = $incorrectas * $this->milesimas(abs($valores['incorrecta']));

        return [
            'total_preguntas' => $correctas + $incorrectas + $enBlanco,
            'correctas' => $correctas,
            'incorrectas' => $incorrectas,
            'en_blanco' => $enBlanco,
            'ganados' => $this->decimal($ganados),
            'descontados' => $this->decimal($descontados),
            'puntaje' => $this->decimal($ganados - $descontados),
            'maximo' => $maximo,
            'con_penalizacion' => $descontados > 0,
        ];
    }

    /**
     * Acumulado de los tres días (suma, no promedio) y equivalencia sobre 20.
     * La nota visual usa un mínimo de 0; el puntaje real conserva su signo.
     *
     * @param  array<int, array|null>  $dias  resultado de puntajeDia() por día, o null si falta rendirlo
     */
    public function acumulado(array $dias): array
    {
        $rendidos = array_filter($dias);
        $total = array_sum(array_map(fn ($d) => $this->milesimas($d['puntaje']), $rendidos));
        $maximoTotal = (float) config('examen_uni.maximo_total');
        $escala = (int) config('examen_uni.escala_vigesimal');
        $completo = count($rendidos) === count(PerfilExamenCni::UNI_DIAS);

        // nota = total × 20 / 1800, redondeada a centésimas en aritmética entera.
        $nota = null;
        if ($completo) {
            $numerador = max(0, $total) * $escala * 100;
            $denominador = $this->milesimas($maximoTotal);
            $nota = intdiv($numerador * 2 + $denominador, $denominador * 2) / 100;
        }
        $suma = fn (string $clave) => array_sum(array_column($rendidos, $clave));

        return [
            'completo' => $completo,
            'escala_compatible' => count(array_filter($rendidos, fn ($d) => ! isset($d['maximo']) || (float) $d['maximo'] !== 600.0)) === 0,
            'dias_rendidos' => count($rendidos),
            'puntaje' => $this->decimal($total),
            'maximo' => $maximoTotal,
            'nota_vigesimal' => $nota,
            'escala_vigesimal' => $escala,
            'correctas' => $suma('correctas'),
            'incorrectas' => $suma('incorrectas'),
            'en_blanco' => $suma('en_blanco'),
            'respondidas' => $suma('correctas') + $suma('incorrectas'),
            'preguntas' => $suma('total_preguntas'),
        ];
    }

    private function milesimas(float $valor): int
    {
        return (int) round($valor * 1000);
    }

    private function decimal(int $milesimas): float
    {
        return $milesimas / 1000;
    }
}
