<?php

namespace App\Services\Alumno;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/** Datos del resumen de inicio: último resultado, examen pendiente, práctica abierta y meta semanal. */
class InicioAlumnoService
{
    public function __construct(
        private readonly ContextoAlumnoService $contexto,
        private readonly ProgresoComparableService $progreso,
        private readonly PracticaService $practicas,
    ) {}

    public function resumen(Usuario $usuario): ?array
    {
        $ctx = $this->contexto->contexto($usuario);
        if (! $ctx) {
            return null;
        }
        $pendiente = $this->contexto->examenActivo($usuario);
        $practica = DB::table('practicas')->where('usuario_id', $usuario->id)->where('origen', '!=', 'tutor')
            ->whereNull('finalizada_en')->where('updated_at', '>=', now()->subDay())->latest('id')->first(['id', 'asignatura']);
        $meta = DB::table('metas_estudio')->where('usuario_id', $usuario->id)->value('preguntas_semana');

        return [
            'ctx' => $ctx,
            'ultimo' => $this->progreso->intentos($usuario, $ctx['codigo'])->first(),
            // Examen en curso (cualquier universidad): se continúa, no se reinicia.
            'pendiente' => $pendiente ? DB::table('intentos as i')->leftJoin('simulacros as s', 's.id', '=', 'i.simulacro_id')
                ->where('i.id', $pendiente->id)->first(['i.id', 'i.fecha_limite', 's.nombre']) : null,
            'practica' => $practica,
            'meta' => $meta ? (int) $meta : null,
            'semana' => $this->practicas->respondidasEnSemana($usuario),
        ];
    }
}
