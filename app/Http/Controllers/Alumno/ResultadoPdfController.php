<?php

namespace App\Http\Controllers\Alumno;

use App\Http\Controllers\Controller;
use App\Services\Alumno\ContextoAlumnoService;
use App\Services\Alumno\ProgresoComparableService;
use App\Services\ResultadoService;
use App\Support\UniversidadExamen;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Resumen en PDF de un intento propio, finalizado y calificado (mismas reglas que la pantalla de resultados). */
class ResultadoPdfController extends Controller
{
    public function __invoke(Request $request, int $intento, ResultadoService $resultados): Response
    {
        $usuario = $request->user('web');
        // Propiedad primero: un intento ajeno responde 404 sin revelar que existe.
        $registro = DB::table('intentos as i')
            ->join('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->where('i.id', $intento)->where('i.usuario_id', $usuario->id)->where('i.tipo', 'simulacro')
            ->first(['i.id', 'i.fecha_inicio', 'i.fecha_fin', 'i.total_preguntas', 's.nombre', 's.puntaje_correcta', 's.puntaje_incorrecta', 's.puntaje_blanco']);
        abort_unless($registro, 404);

        try {
            // Rechaza intentos sin finalizar o sin calificar, igual que la pantalla de resultados.
            $resultado = $resultados->mostrar($intento);
        } catch (ValidationException) {
            abort(404);
        }

        $codigo = $this->universidadDe($registro->nombre) ?? app(ContextoAlumnoService::class)->contexto($usuario)['codigo'] ?? null;
        $pdf = Pdf::loadView('alumno.pdf.resultado', [
            'usuario' => $usuario,
            'intento' => $registro,
            'examen' => ProgresoComparableService::examenBase($registro->nombre),
            'universidad' => $codigo ? (config('panel_alumno.areas.'.$codigo.'.titulo') ?? 'CNI') : 'CNI',
            'area' => $codigo,
            'fecha' => Carbon::parse($registro->fecha_fin ?? $registro->fecha_inicio),
            'resultado' => $resultado,
            'maximo' => (int) $registro->total_preguntas * (float) $registro->puntaje_correcta,
        ])->setPaper('a4')
            // Solo los caracteres usados de la fuente: el archivo pasa de ~860 KB a decenas de KB.
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download('resultado-simulacro-'.str_pad((string) $intento, 3, '0', STR_PAD_LEFT).'.pdf');
    }

    /** Universidad del examen según el prefijo de su plantilla (la misma regla del historial). */
    private function universidadDe(string $nombre): ?string
    {
        foreach (UniversidadExamen::PREFIJOS as $codigo => $prefijos) {
            foreach ($prefijos as $prefijo) {
                if (str_starts_with($nombre, $prefijo)) {
                    return $codigo;
                }
            }
        }

        return null;
    }
}
