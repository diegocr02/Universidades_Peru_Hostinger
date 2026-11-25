<?php

namespace App\Http\Controllers\Alumno;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Meta semanal: preguntas de práctica por semana. Una fila por alumno (upsert). */
class MetaEstudioController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $limites = config('panel_alumno.meta_semanal');
        $datos = $request->validate([
            'preguntas_semana' => ['required', 'integer', 'min:'.$limites['minimo'], 'max:'.$limites['maximo']],
        ]);

        DB::table('metas_estudio')->upsert([[
            'usuario_id' => $request->user('web')->id,
            'preguntas_semana' => (int) $datos['preguntas_semana'],
            'created_at' => now(), 'updated_at' => now(),
        ]], ['usuario_id'], ['preguntas_semana', 'updated_at']);

        return back()->with('status', 'Meta semanal guardada: '.$datos['preguntas_semana'].' preguntas.');
    }
}
