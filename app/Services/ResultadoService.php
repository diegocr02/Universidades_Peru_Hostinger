<?php

namespace App\Services;

use App\Support\PerfilExamenUnmsm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResultadoService
{
    public function obtenerIntento(int $intentoId): object
    {
        $intento = DB::table('intentos')
            ->where('id', $intentoId)
            ->first();

        if (! $intento) {
            throw ValidationException::withMessages([
                'intento_id' => 'El intento no existe.',
            ]);
        }

        return $intento;
    }

    public function validarIntentoFinalizado(object $intento): void
    {
        if (! in_array($intento->estado, ['finalizado', 'enviado_auto'], true)) {
            throw ValidationException::withMessages([
                'intento_id' => 'El intento todavía no ha finalizado.',
            ]);
        }
    }

    public function obtenerRespuestas(int $intentoId): Collection
    {
        $rubrica = PerfilExamenUnmsm::hayRubrica();

        return DB::table('respuestas_estudiante as re')
            ->join('preguntas as p', 're.pregunta_id', '=', 'p.id')
            ->join('temas as t', 'p.tema_id', '=', 't.id')
            ->join('areas as a', 't.area_id', '=', 'a.id')
            ->leftJoin('alternativas as alt', 're.alternativa_id', '=', 'alt.id')
            ->where('re.intento_id', $intentoId)
            ->orderBy('re.orden')
            ->select(
                're.id',
                're.pregunta_id',
                're.alternativa_id',
                're.estado_respuesta',
                'a.id as area_id',
                'a.nombre as area_nombre',
                'alt.pregunta_id as alternativa_pregunta_id',
                'alt.es_correcta'
            )
            // Preguntas del banco aún sin respuesta registrada en el Excel (0 claves).
            ->selectRaw('(SELECT COUNT(*) FROM alternativas WHERE pregunta_id = re.pregunta_id AND es_correcta = 1) as claves')
            // Preguntas actitudinales de San Marcos: se califican con la rúbrica de cada alternativa.
            ->when($rubrica, fn ($q) => $q->addSelect('alt.puntaje_actitudinal')
                ->selectRaw('(SELECT COUNT(*) FROM alternativas WHERE pregunta_id = re.pregunta_id AND puntaje_actitudinal IS NOT NULL) as con_rubrica'))
            ->get();
    }

    /** Actitudinal: asignatura "Actitudinal", sin clave y con rúbrica registrada en sus alternativas. */
    public function esActitudinal(object $respuesta): bool
    {
        return ($respuesta->area_nombre ?? null) === PerfilExamenUnmsm::ASIGNATURA_ACTITUDINAL
            && (int) ($respuesta->claves ?? 0) === 0
            && (int) ($respuesta->con_rubrica ?? 0) > 0;
    }

    /** Puntaje actitudinal: el de la alternativa elegida (0 a 20); en blanco o doble marca, 0. Nunca descuenta. */
    public function puntajeActitudinal(object $respuesta): float
    {
        if ($respuesta->estado_respuesta === 'doble_marca' || $respuesta->alternativa_id === null) {
            return 0.0;
        }
        if ((int) $respuesta->alternativa_pregunta_id !== (int) $respuesta->pregunta_id) {
            throw ValidationException::withMessages([
                'respuesta' => 'La alternativa no pertenece a la pregunta.',
            ]);
        }

        return max(0.0, min(PerfilExamenUnmsm::MAXIMO_ACTITUDINAL, (float) $respuesta->puntaje_actitudinal));
    }

    public function validarAlternativasCorrectas(int $intentoId): void
    {
        $preguntasInvalidas = DB::table('respuestas_estudiante as re')
            ->leftJoin(
                'alternativas as alt',
                're.pregunta_id',
                '=',
                'alt.pregunta_id'
            )
            ->join('preguntas as p', 'p.id', '=', 're.pregunta_id')
            ->where('re.intento_id', $intentoId)
            ->groupBy('re.pregunta_id', 'p.codigo')
            // Excepción: una pregunta del banco del Excel (con codigo) aún sin respuesta no se califica.
            // Excepción San Marcos: una pregunta actitudinal no tiene clave, sino rúbrica.
            ->havingRaw(
                'SUM(CASE WHEN alt.es_correcta = 1 THEN 1 ELSE 0 END) > 1
                 OR (SUM(CASE WHEN alt.es_correcta = 1 THEN 1 ELSE 0 END) = 0 AND p.codigo IS NULL'
                .(PerfilExamenUnmsm::hayRubrica()
                    ? ' AND SUM(CASE WHEN alt.puntaje_actitudinal IS NOT NULL THEN 1 ELSE 0 END) = 0' : '')
                .')'
            )
            ->pluck('re.pregunta_id');

        if ($preguntasInvalidas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'preguntas' => 'Hay preguntas sin una única alternativa correcta.',
            ]);
        }
    }

    public function clasificarRespuesta(object $respuesta): string
    {
        if ($respuesta->estado_respuesta === 'doble_marca') {
            if ($respuesta->alternativa_id !== null) {
                throw ValidationException::withMessages([
                    'respuesta' => 'Una doble marca no debe tener una alternativa seleccionada.',
                ]);
            }

            return 'doble_marca';
        }

        if ($respuesta->alternativa_id === null) {
            return 'en_blanco';
        }

        if (
            $respuesta->alternativa_pregunta_id === null ||
            (int) $respuesta->alternativa_pregunta_id !== (int) $respuesta->pregunta_id
        ) {
            throw ValidationException::withMessages([
                'respuesta' => 'La alternativa no pertenece a la pregunta.',
            ]);
        }

        // Sin respuesta registrada no se puede saber si acertó: no suma ni resta (como en blanco).
        if (isset($respuesta->claves) && (int) $respuesta->claves === 0) {
            return 'en_blanco';
        }

        return (int) $respuesta->es_correcta === 1
            ? 'correcta'
            : 'incorrecta';
    }

    public function calcularPuntaje(string $estado, object $simulacro): float
    {
        return match ($estado) {
            'correcta' => (float) $simulacro->puntaje_correcta,
            'incorrecta' => (float) $simulacro->puntaje_incorrecta,
            'en_blanco', 'doble_marca' => (float) $simulacro->puntaje_blanco,
            default => throw ValidationException::withMessages([
                'estado' => 'Estado de respuesta no válido.',
            ]),
        };
    }

    public function calificar(int $intentoId): array
    {
        return DB::transaction(function () use ($intentoId) {
            $intento = DB::table('intentos')
                ->where('id', $intentoId)
                ->lockForUpdate()
                ->first();

            if (! $intento) {
                throw ValidationException::withMessages([
                    'intento_id' => 'El intento no existe.',
                ]);
            }

            $this->validarIntentoFinalizado($intento);

            if ($intento->tipo !== 'simulacro' || ! $intento->simulacro_id) {
                throw ValidationException::withMessages([
                    'intento_id' => 'Solo se admiten simulacros.',
                ]);
            }

            $simulacro = DB::table('simulacros')
                ->where('id', $intento->simulacro_id)
                ->first();

            if (! $simulacro) {
                throw ValidationException::withMessages([
                    'simulacro_id' => 'El simulacro no existe.',
                ]);
            }

            $respuestas = $this->obtenerRespuestas($intentoId);

            if (
                $respuestas->isEmpty() ||
                $respuestas->count() !== (int) $intento->total_preguntas
            ) {
                throw ValidationException::withMessages([
                    'respuestas' => 'La cantidad de preguntas no coincide con el intento.',
                ]);
            }

            $this->validarAlternativasCorrectas($intentoId);

            $yaCalificado = $intento->puntaje_total !== null;

            if ($yaCalificado) {
                $pendientes = DB::table('respuestas_estudiante')
                    ->where('intento_id', $intentoId)
                    ->whereNull('puntaje_obtenido')
                    ->exists();

                $cantidadAreas = $respuestas
                    ->pluck('area_id')
                    ->unique()
                    ->count();

                $areasGuardadas = DB::table('resultados_area')
                    ->where('intento_id', $intentoId)
                    ->count();

                if ($pendientes || $areasGuardadas !== $cantidadAreas) {
                    throw ValidationException::withMessages([
                        'intento_id' => 'El intento tiene una calificación incompleta.',
                    ]);
                }

                return $this->mostrar($intentoId);
            }

            $totales = [
                'correctas' => 0,
                'incorrectas' => 0,
                'en_blanco' => 0,
                'doble_marca' => 0,
                // Milésimas enteras: evita errores de punto flotante al sumar (p. ej. −1,2).
                'puntaje_total' => 0,
            ];

            $resultadosArea = [];
            $actualizaciones = [];

            foreach ($respuestas as $respuesta) {
                if ($this->esActitudinal($respuesta)) {
                    // Rúbrica: no es correcta ni incorrecta y no cuenta en los totales ordinarios.
                    // Misma unidad que las ordinarias: milésimas enteras.
                    $milesimas = (int) round($this->puntajeActitudinal($respuesta) * 1000);
                    $enBlanco = $respuesta->alternativa_id === null;
                    $totales['puntaje_total'] += $milesimas;
                    $actualizaciones[] = [
                        'id' => $respuesta->id,
                        'estado' => $respuesta->estado_respuesta === 'doble_marca' ? 'doble_marca'
                            : ($enBlanco ? 'en_blanco' : null),
                        'puntaje' => $milesimas / 1000,
                    ];
                    $areaId = (int) $respuesta->area_id;
                    $resultadosArea[$areaId] ??= [
                        'area_id' => $areaId, 'total_preguntas' => 0, 'aciertos' => 0,
                        'incorrectas' => 0, 'en_blanco' => 0, 'puntaje' => 0, 'actitudinal' => true,
                    ];
                    $resultadosArea[$areaId]['total_preguntas']++;
                    $resultadosArea[$areaId]['en_blanco'] += $enBlanco ? 1 : 0;
                    $resultadosArea[$areaId]['puntaje'] += $milesimas;

                    continue;
                }

                $estado = $this->clasificarRespuesta($respuesta);
                $puntaje = $this->calcularPuntaje($estado, $simulacro);
                $milesimas = (int) round($puntaje * 1000);

                $contador = match ($estado) {
                    'correcta' => 'correctas',
                    'incorrecta' => 'incorrectas',
                    default => $estado,
                };
                $totales[$contador]++;
                $totales['puntaje_total'] += $milesimas;

                $actualizaciones[] = [
                    'id' => $respuesta->id,
                    'estado' => $estado,
                    'puntaje' => $milesimas / 1000,
                ];

                $areaId = (int) $respuesta->area_id;

                if (! isset($resultadosArea[$areaId])) {
                    $resultadosArea[$areaId] = [
                        'area_id' => $areaId,
                        'total_preguntas' => 0,
                        'aciertos' => 0,
                        'incorrectas' => 0,
                        'en_blanco' => 0,
                        'puntaje' => 0,
                    ];
                }

                $resultadosArea[$areaId]['total_preguntas']++;

                if ($estado === 'correcta') {
                    $resultadosArea[$areaId]['aciertos']++;
                } elseif ($estado === 'incorrecta') {
                    $resultadosArea[$areaId]['incorrectas']++;
                } else {
                    $resultadosArea[$areaId]['en_blanco']++;
                }

                $resultadosArea[$areaId]['puntaje'] += $milesimas;
            }

            $totales['puntaje_total'] = $totales['puntaje_total'] / 1000;

            foreach ($resultadosArea as &$area) {
                $area['puntaje'] = $area['puntaje'] / 1000;

                // En la parte actitudinal el porcentaje es el del puntaje posible, no de aciertos.
                $area['porcentaje'] = ($area['actitudinal'] ?? false)
                    ? round(max(0, $area['puntaje']) / ($area['total_preguntas'] * PerfilExamenUnmsm::MAXIMO_ACTITUDINAL) * 100, 2)
                    : round(($area['aciertos'] / $area['total_preguntas']) * 100, 2);
                unset($area['actitudinal']);
            }

            unset($area);

            foreach ($actualizaciones as $actualizacion) {
                DB::table('respuestas_estudiante')
                    ->where('id', $actualizacion['id'])
                    ->where('intento_id', $intentoId)
                    ->update([
                        'estado_respuesta' => $actualizacion['estado'],
                        'puntaje_obtenido' => $actualizacion['puntaje'],
                        'updated_at' => now(),
                    ]);
            }

            foreach ($resultadosArea as $area) {
                DB::table('resultados_area')->updateOrInsert(
                    [
                        'intento_id' => $intentoId,
                        'area_id' => $area['area_id'],
                    ],
                    [
                        'total_preguntas' => $area['total_preguntas'],
                        'aciertos' => $area['aciertos'],
                        'incorrectas' => $area['incorrectas'],
                        'en_blanco' => $area['en_blanco'],
                        'puntaje' => $area['puntaje'],
                        'porcentaje' => $area['porcentaje'],
                        'updated_at' => now(),
                    ]
                );
            }

            DB::table('intentos')
                ->where('id', $intentoId)
                ->update([
                    'correctas' => $totales['correctas'],
                    'incorrectas' => $totales['incorrectas'],
                    'en_blanco' => $totales['en_blanco'],
                    'doble_marca' => $totales['doble_marca'],
                    'puntaje_total' => $totales['puntaje_total'],
                    'updated_at' => now(),
                ]);

            return $this->mostrar($intentoId);
        });
    }

    public function mostrar(int $intentoId): array
    {
        $intento = $this->obtenerIntento($intentoId);

        $this->validarIntentoFinalizado($intento);

        if ($intento->puntaje_total === null) {
            throw ValidationException::withMessages([
                'intento_id' => 'El intento aún no tiene resultados.',
            ]);
        }

        $areas = DB::table('resultados_area as ra')
            ->join('areas as a', 'ra.area_id', '=', 'a.id')
            ->where('ra.intento_id', $intentoId)
            ->select(
                'ra.area_id',
                'a.nombre as area_nombre',
                'ra.total_preguntas',
                'ra.aciertos',
                'ra.incorrectas',
                'ra.en_blanco',
                'ra.puntaje',
                'ra.porcentaje'
            )
            ->get();

        return [
            'intento_id' => $intento->id,
            'usuario_id' => $intento->usuario_id,
            'simulacro_id' => $intento->simulacro_id,
            'estado' => $intento->estado,
            'totales' => [
                'correctas' => (int) $intento->correctas,
                'incorrectas' => (int) $intento->incorrectas,
                'en_blanco' => (int) $intento->en_blanco,
                'doble_marca' => (int) $intento->doble_marca,
                'puntaje_total' => (float) $intento->puntaje_total,
            ],
            'resultados_area' => $areas->toArray(),
        ];
    }
}
