<?php

namespace App\Http\Controllers\Alumno;

use App\Http\Controllers\Controller;
use App\Support\UniversidadExamen;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Historial": exámenes terminados del alumno con su puntaje, y el detalle por área de cada uno.
 * Solo se muestran intentos propios; el de otro alumno responde 404.
 */
class HistorialController extends Controller
{
    public function index(): View
    {
        $codigo = UniversidadExamen::codigoDelAlumno(auth('web')->user());

        $intentos = DB::table('intentos as i')
            ->leftJoin('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->where('i.usuario_id', auth('web')->id())
            ->where('i.estado', '!=', 'en_curso')
            // Simulacros solo de la universidad actual; las prácticas sin simulacro se conservan.
            ->when($codigo !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('i.simulacro_id')
                ->orWhere(fn ($s) => UniversidadExamen::filtrar($s, $codigo))))
            ->orderByDesc('i.fecha_inicio')->orderByDesc('i.id')
            ->select('i.id', 'i.tipo', 'i.estado', 'i.fecha_inicio', 'i.fecha_fin', 'i.total_preguntas',
                'i.correctas', 'i.incorrectas', 'i.en_blanco', 'i.puntaje_total', 's.nombre as simulacro')
            ->paginate(10);

        return view('alumno.historial', ['intentos' => $intentos]);
    }

    public function show(int $intento): View
    {
        $registro = DB::table('intentos as i')
            ->leftJoin('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->where('i.id', $intento)
            ->where('i.usuario_id', auth('web')->id())
            ->where('i.estado', '!=', 'en_curso')
            ->first(['i.*', 's.nombre as simulacro', 's.puntaje_maximo']);
        abort_unless($registro, 404);

        $areas = DB::table('resultados_area as ra')
            ->join('areas as a', 'a.id', '=', 'ra.area_id')
            ->where('ra.intento_id', $registro->id)
            ->orderBy('a.id')
            ->get(['a.nombre', 'ra.total_preguntas', 'ra.aciertos', 'ra.incorrectas', 'ra.en_blanco',
                'ra.puntaje', 'ra.porcentaje']);

        return view('alumno.historial-detalle', ['intento' => $registro, 'areas' => $areas]);
    }
}
