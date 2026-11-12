<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogoController extends Controller
{
    public function asignatura(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:areas,nombre'],
            'bloque' => ['required', Rule::in(['habilidad_verbal', 'habilidad_logico_matematica', 'conocimientos'])],
        ]);
        DB::table('areas')->insert($datos + ['activo' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Asignatura creada.');
    }

    public function tema(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'area_id' => ['required', Rule::exists('areas', 'id')->where('activo', true)],
            'nombre' => ['required', 'string', 'max:150', Rule::unique('temas')->where('area_id', $request->input('area_id'))],
        ]);
        DB::table('temas')->insert($datos + ['activo' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Tema creado.');
    }

    public function carrera(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'area_postulacion_id' => ['required', Rule::exists('areas_postulacion', 'id')->where('activo', true)],
            'nombre' => ['required', 'string', 'max:150', 'unique:carreras,nombre'],
        ]);
        DB::table('carreras')->insert($datos + ['activo' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Carrera registrada.');
    }
}
