<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reportes de preguntas enviados por alumnos. Cambiar el estado no modifica la pregunta:
 * la corrección se hace aparte, desde el banco, después de revisar el reporte.
 */
class ReportesPreguntaController extends Controller
{
    public const ESTADOS = ['pendiente' => 'Pendiente', 'en_revision' => 'En revisión', 'resuelto' => 'Resuelto', 'descartado' => 'Descartado'];

    public function index(Request $request): View
    {
        $estado = in_array($request->query('estado'), array_keys(self::ESTADOS), true) ? $request->query('estado') : null;

        $reportes = DB::table('reportes_pregunta as r')
            ->join('preguntas as p', 'p.id', '=', 'r.pregunta_id')
            ->join('usuarios as u', 'u.id', '=', 'r.usuario_id')
            ->leftJoin('usuarios as rv', 'rv.id', '=', 'r.revisado_por')
            ->when($estado, fn ($q, $e) => $q->where('r.estado', $e))
            ->orderByRaw("CASE r.estado WHEN 'pendiente' THEN 0 WHEN 'en_revision' THEN 1 ELSE 2 END")
            ->orderByDesc('r.id')
            ->select('r.*', 'p.codigo', 'p.enunciado', 'p.activo as pregunta_activa', 'u.usuario as alumno', 'rv.usuario as revisor')
            ->paginate(20)->withQueryString();

        return view('admin.reportes.index', [
            'reportes' => $reportes,
            'estado' => $estado,
            'conteo' => DB::table('reportes_pregunta')->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado'),
        ]);
    }

    public function actualizar(Request $request, int $reporte): RedirectResponse
    {
        $datos = $request->validate(['estado' => ['required', Rule::in(array_keys(self::ESTADOS))]]);
        $actualizado = DB::table('reportes_pregunta')->where('id', $reporte)->update([
            'estado' => $datos['estado'],
            'revisado_por' => $request->user('web')->id,
            'revisado_en' => now(),
            'updated_at' => now(),
        ]);
        abort_unless($actualizado === 1, 404);

        return back()->with('status', 'Reporte #'.$reporte.' marcado como '.mb_strtolower(self::ESTADOS[$datos['estado']]).'.');
    }
}
