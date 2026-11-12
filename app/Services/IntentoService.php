<?php

namespace App\Services;

use App\Support\PerfilExamenCni;
use App\Support\PerfilExamenUnmsm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IntentoService
{
    public function __construct(private readonly ResultadoService $resultados) {}

    public function iniciar(int $usuarioId, array $datos): object
    {
        return DB::transaction(function () use ($usuarioId, $datos) {
            $simulacro = null;
            $preguntas = collect();
            $ahora = now();

            if ($datos['tipo'] === 'simulacro') {
                $simulacro = DB::table('simulacros')->where('id', $datos['simulacro_id'] ?? null)
                    ->where('activo', true)->lockForUpdate()->first();
                if (! $simulacro || $ahora->lt($simulacro->fecha_inicio) || $ahora->gte($simulacro->fecha_fin)) {
                    throw ValidationException::withMessages([
                        'simulacro_id' => 'El simulacro no está disponible en este momento.',
                    ]);
                }
                $perfilCarreraId = DB::table('usuarios')->where('id', $usuarioId)->value('carrera_id');
                if ($simulacro->carrera_id !== null && (int) $simulacro->carrera_id !== (int) $perfilCarreraId) {
                    throw ValidationException::withMessages([
                        'carrera_id' => 'Este simulacro está reservado para la carrera configurada.',
                    ]);
                }
                $areasPermitidas = DB::table('area_postulacion_simulacro')
                    ->where('simulacro_id', $simulacro->id)->pluck('area_postulacion_id');
                $perfil = DB::table('usuarios')->where('id', $usuarioId)->first();
                // Un alumno que ya eligió área solo puede iniciar simulacros publicados
                // y asociados expresamente a un área. Sin asociación no hay autorización.
                // Cuentas de prueba antiguas sin área guardada mantienen compatibilidad API.
                if ($perfil->rol === 'estudiante' && $perfil->area_postulacion_id !== null
                    && $areasPermitidas->isEmpty()) {
                    throw ValidationException::withMessages([
                        'simulacro_id' => 'El simulacro no tiene un área de postulación habilitada.',
                    ]);
                }
                if ($perfil->rol === 'estudiante' && $areasPermitidas->isNotEmpty()) {
                    // Selección primaria por área; la carrera es opcional.
                    $areaId = $perfil->area_postulacion_id;
                    if ($perfil->carrera_id !== null) {
                        $carrera = DB::table('carreras')->where('id', $perfil->carrera_id)
                            ->where('activo', true)->first();
                        if (! $carrera || ($areaId !== null && (int) $carrera->area_postulacion_id !== (int) $areaId)) {
                            throw ValidationException::withMessages(['carrera_id' => 'La carrera no pertenece a tu área de preparación.']);
                        }
                        $areaId ??= $carrera->area_postulacion_id;
                    }
                    $habilitado = $areaId !== null
                        && DB::table('areas_postulacion')->where('id', $areaId)
                            ->where('activo', true)->exists()
                        && $areasPermitidas->contains($areaId);
                    if (! $habilitado) {
                        throw ValidationException::withMessages(['carrera_id' => 'Tu área no está habilitada para este simulacro.']);
                    }
                }
                $preguntas = $this->sortearPreguntas($simulacro, (bool) ($datos['orden_por_curso'] ?? false),
                    (bool) ($datos['orden_de_cuotas'] ?? false));
            }

            $intentoId = DB::table('intentos')->insertGetId([
                'usuario_id' => $usuarioId,
                // La carrera es una fotografía del perfil, no un valor confiado al navegador.
                'carrera_id' => DB::table('usuarios')->where('id', $usuarioId)->value('carrera_id'),
                'simulacro_id' => $simulacro?->id,
                'tipo' => $datos['tipo'],
                'area_id' => $datos['area_id'] ?? null,
                'tema_id' => $datos['tema_id'] ?? null,
                'fecha_inicio' => $ahora,
                'fecha_limite' => $simulacro ? $ahora->copy()->addMinutes((int) $simulacro->tiempo_limite) : null,
                'estado' => 'en_curso',
                'total_preguntas' => $preguntas->count(),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            foreach ($preguntas->values() as $orden => $pregunta) {
                DB::table('respuestas_estudiante')->insert([
                    'intento_id' => $intentoId, 'pregunta_id' => $pregunta->id,
                    'orden' => $orden + 1, 'alternativa_id' => null,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }

            return DB::table('intentos')->where('id', $intentoId)->first();
        });
    }

    /** Cuotas específicas primero, luego mixtas, sin repetir preguntas. */
    /**
     * Preguntas activas de una asignatura que pueden entrar a un examen: con la cantidad de
     * alternativas pedida y una única clave (o sin clave si son del Excel, o con rúbrica completa
     * si son actitudinales). La usan el sorteo y el aviso de disponibilidad del panel.
     */
    public function consultaElegibles(int $areaId, int $cantidadAlternativas, ?string $nivel = null): \Illuminate\Database\Query\Builder
    {
        $rubrica = PerfilExamenUnmsm::hayRubrica()
            ? ' OR ((SELECT COUNT(*) FROM alternativas WHERE pregunta_id = p.id AND es_correcta = 1) = 0
                AND (SELECT COUNT(*) FROM alternativas WHERE pregunta_id = p.id
                    AND puntaje_actitudinal IS NOT NULL AND puntaje_actitudinal BETWEEN 0 AND '.PerfilExamenUnmsm::MAXIMO_ACTITUDINAL.') = '.$cantidadAlternativas.')'
            : '';

        return DB::table('preguntas as p')
            ->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->where('a.id', $areaId)
            ->where('p.activo', true)->where('t.activo', true)->where('a.activo', true)
            ->when($nivel !== null && $nivel !== 'mixto', fn ($q) => $q->where('p.nivel', $nivel))
            ->whereRaw('(SELECT COUNT(*) FROM alternativas WHERE pregunta_id = p.id) = ?', [$cantidadAlternativas])
            // Una clave; o ninguna si es del banco del Excel (codigo) y aún no tiene respuesta: se muestra pero no se califica.
            ->whereRaw('((SELECT COUNT(*) FROM alternativas WHERE pregunta_id = p.id AND es_correcta = 1) = 1
                OR (p.codigo IS NOT NULL AND (SELECT COUNT(*) FROM alternativas WHERE pregunta_id = p.id AND es_correcta = 1) = 0)'
                .$rubrica.')');
    }

    private function sortearPreguntas(object $simulacro, bool $ordenPorCurso = false, bool $ordenDeCuotas = false): Collection
    {
        $cuotas = DB::table('simulacro_area')->where('simulacro_id', $simulacro->id)
            ->orderByRaw("CASE WHEN nivel = 'mixto' THEN 1 ELSE 0 END")->orderBy('id')->get();
        if ($cuotas->isEmpty() || (int) $cuotas->sum('cantidad_preguntas') !== (int) $simulacro->total_preguntas) {
            throw ValidationException::withMessages(['simulacro_id' => 'La distribución no coincide con el total de preguntas.']);
        }
        $seleccionadas = collect();
        foreach ($cuotas as $cuota) {
            $cantidad = (int) $cuota->cantidad_preguntas;
            $preguntas = $this->consultaElegibles((int) $cuota->area_id, (int) $simulacro->cantidad_alternativas, $cuota->nivel)
                ->whereNotIn('p.id', $seleccionadas->pluck('id')->all())
                ->inRandomOrder()->limit(max(0, $cantidad))->lockForUpdate()->get(['p.id', 'a.bloque', 'a.nombre as asignatura']);
            if ($cantidad < 1 || $preguntas->count() !== $cantidad) {
                throw ValidationException::withMessages([
                    'preguntas' => 'Banco insuficiente: '.DB::table('areas')->where('id', $cuota->area_id)->value('nombre')
                        .' requiere '.$cantidad.' preguntas con '.(int) $simulacro->cantidad_alternativas.' alternativas; se encontraron '.$preguntas->count().'.',
                ]);
            }
            $seleccionadas = $seleccionadas->concat($preguntas);
        }
        if ($ordenDeCuotas) {
            // San Marcos: bloques en el orden de la plantilla (actitudinal, habilidades, conocimientos).
            return $seleccionadas->values();
        }
        if ($ordenPorCurso) {
            return $seleccionadas->sortBy(fn ($p) => PerfilExamenCni::posicion($p->asignatura))->values();
        }
        // Orden de bloques documentado; sorteo independiente dentro de cada bloque.
        $grupos = $seleccionadas->groupBy('bloque');

        return collect(['habilidad_verbal', 'habilidad_logico_matematica', 'conocimientos'])
            ->flatMap(fn ($bloque) => $grupos->get($bloque, collect())->shuffle())->values();
    }

    public function obtenerPropio(int $intentoId, int $usuarioId): object
    {
        return DB::transaction(function () use ($intentoId, $usuarioId) {
            $intento = $this->bloquearPropio($intentoId, $usuarioId);

            return $this->vencido($intento) ? $this->cerrar($intento) : $intento;
        });
    }

    /** Intento en curso del simulacro para reanudarlo; si ya venció se cierra y devuelve null. */
    public function enCurso(int $usuarioId, int $simulacroId): ?object
    {
        return DB::transaction(function () use ($usuarioId, $simulacroId) {
            $intento = DB::table('intentos')->where('usuario_id', $usuarioId)->where('simulacro_id', $simulacroId)
                ->where('estado', 'en_curso')->orderByDesc('id')->lockForUpdate()->first();
            if ($intento && $this->vencido($intento)) {
                $this->cerrar($intento);

                return null;
            }

            return $intento;
        });
    }

    /** Enunciados y alternativas del examen propio, sin solución ni clave correcta. */
    public function preguntas(int $intentoId): Collection
    {
        $preguntas = DB::table('respuestas_estudiante as r')
            ->join('preguntas as p', 'p.id', '=', 'r.pregunta_id')
            ->join('temas as t', 't.id', '=', 'p.tema_id')
            ->join('areas as a', 'a.id', '=', 't.area_id')
            ->where('r.intento_id', $intentoId)->orderBy('r.orden')
            ->get(['p.id', 'p.enunciado', 'p.imagen', 'p.nivel', 'r.orden',
                't.nombre as tema', 'a.nombre as area', 'a.bloque']);
        $alternativas = DB::table('alternativas')->whereIn('pregunta_id', $preguntas->pluck('id'))
            ->orderBy('letra')->get(['id', 'pregunta_id', 'letra', 'texto', 'imagen'])->groupBy('pregunta_id');

        return $preguntas->map(function ($pregunta) use ($alternativas) {
            $pregunta->alternativas = $alternativas->get($pregunta->id, collect());

            return $pregunta;
        });
    }

    /** Respuestas guardadas del intento, en el orden del examen. */
    public function respuestas(int $intentoId): Collection
    {
        return DB::table('respuestas_estudiante')->where('intento_id', $intentoId)->orderBy('orden')
            ->get(['id', 'pregunta_id', 'orden', 'alternativa_id', 'respondida_en']);
    }

    public function guardarRespuesta(int $intentoId, int $usuarioId, array $datos): object
    {
        $respuesta = DB::transaction(function () use ($intentoId, $usuarioId, $datos) {
            $intento = $this->bloquearPropio($intentoId, $usuarioId);
            if ($this->vencido($intento)) {
                $this->cerrar($intento);

                return null;
            }
            if ($intento->estado !== 'en_curso') {
                throw ValidationException::withMessages(['intento' => 'El intento ya no está en curso.']);
            }
            $respuesta = DB::table('respuestas_estudiante')->where('intento_id', $intentoId)
                ->where('pregunta_id', $datos['pregunta_id'])->first();
            if (! $respuesta) {
                throw ValidationException::withMessages(['pregunta_id' => 'La pregunta no pertenece a este intento.']);
            }
            if (isset($datos['alternativa_id']) && ! DB::table('alternativas')->where('id', $datos['alternativa_id'])
                ->where('pregunta_id', $datos['pregunta_id'])->exists()) {
                throw ValidationException::withMessages(['alternativa_id' => 'La alternativa no pertenece a la pregunta.']);
            }
            DB::table('respuestas_estudiante')->where('id', $respuesta->id)->update([
                'alternativa_id' => $datos['alternativa_id'] ?? null,
                'respondida_en' => now(), 'updated_at' => now(),
            ]);

            return DB::table('respuestas_estudiante')->where('id', $respuesta->id)
                ->first(['id', 'intento_id', 'pregunta_id', 'orden', 'alternativa_id', 'respondida_en']);
        });
        // Lanzar después del commit: el cierre por vencimiento debe persistir.
        if ($respuesta === null) {
            throw ValidationException::withMessages(['intento' => 'El tiempo del intento ha terminado.']);
        }

        return $respuesta;
    }

    public function finalizar(int $intentoId, int $usuarioId): object
    {
        return DB::transaction(fn () => $this->cerrar($this->bloquearPropio($intentoId, $usuarioId)));
    }

    private function bloquearPropio(int $intentoId, int $usuarioId): object
    {
        $intento = DB::table('intentos')->where('id', $intentoId)->where('usuario_id', $usuarioId)
            ->lockForUpdate()->first();
        abort_unless($intento, 404, 'Intento no encontrado.');

        return $intento;
    }

    private function vencido(object $intento): bool
    {
        return $intento->estado === 'en_curso' && $intento->fecha_limite
            && now()->greaterThanOrEqualTo($intento->fecha_limite);
    }

    /** Mismo cierre para envío manual, vencimiento y reintentos HTTP. */
    private function cerrar(object $intento): object
    {
        if ($intento->estado === 'en_curso') {
            DB::table('intentos')->where('id', $intento->id)->update([
                'estado' => $this->vencido($intento) ? 'enviado_auto' : 'finalizado',
                'fecha_fin' => $this->vencido($intento) ? Carbon::parse($intento->fecha_limite) : now(),
                'updated_at' => now(),
            ]);
        }
        if ($intento->tipo === 'simulacro') {
            $this->resultados->calificar((int) $intento->id);
        }

        return DB::table('intentos')->where('id', $intento->id)->first();
    }
}
