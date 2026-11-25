<?php

namespace App\Http\Controllers\Alumno;

use App\Http\Controllers\Controller;
use App\Services\Alumno\ContextoAlumnoService;
use App\Services\Alumno\ProgresoComparableService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Historial visual: tabla, gráfico y comparación solo entre intentos equivalentes. */
class HistorialVisualController extends Controller
{
    public function __invoke(Request $request, ContextoAlumnoService $contextos, ProgresoComparableService $progreso): View
    {
        $usuario = $request->user('web');
        $ctx = $contextos->contexto($usuario);
        $filtros = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'examen' => ['nullable', 'string', 'size:32'],
        ]);

        $datos = $progreso->grupos($usuario, $ctx['codigo'], $filtros['desde'] ?? null, $filtros['hasta'] ?? null);
        $intentos = $progreso->intentos($usuario, $ctx['codigo'], $filtros['desde'] ?? null, $filtros['hasta'] ?? null);
        if ($examen = $filtros['examen'] ?? null) {
            $datos['grupos'] = array_values(array_filter($datos['grupos'], fn ($g) => $g['clave'] === $examen));
            $intentos = $intentos->where('grupo', $examen)->values();
        }

        return view('alumno.estudio.progreso', [
            'usuario' => $usuario,
            'ctx' => $ctx,
            'filtros' => $filtros,
            'grupos' => $datos['grupos'],
            // Opciones del filtro «examen»: todos los grupos, sin aplicar el propio filtro.
            'examenes' => collect($progreso->grupos($usuario, $ctx['codigo'])['grupos'])->map(fn ($g) => ['clave' => $g['clave'], 'examen' => $g['examen'], 'escala' => $g['escala']]),
            'intentos' => $intentos,
        ]);
    }
}
