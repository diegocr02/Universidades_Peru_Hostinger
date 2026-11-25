<?php

namespace App\Services\Alumno;

use App\Models\Usuario;
use App\Support\UniversidadExamen;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Universidad, área y estado académico del alumno autenticado.
 * Toda la identidad sale de la sesión: nunca de datos enviados por el navegador.
 */
class ContextoAlumnoService
{
    /**
     * @return array{codigo: string, universidad: string, area_nombre: string, etiqueta: string, menu: array}|null
     */
    public function contexto(Usuario $usuario): ?array
    {
        $area = $usuario->areaPostulacion()->first() ?? $usuario->carrera?->areaPostulacion;
        if (! $area || ! $area->activo || ! config('panel_alumno.areas.'.$area->codigo)) {
            return null;
        }

        $universidad = config('panel_alumno.areas.'.$area->codigo.'.titulo') ?? $area->nombre;

        return [
            'codigo' => $area->codigo,
            'universidad' => $universidad,
            'area_nombre' => $area->nombre,
            'etiqueta' => $universidad.' · Área '.$area->codigo,
            'menu' => config('panel_alumno.menus.'.$area->codigo, []),
        ];
    }

    /**
     * Regla del examen activo: si el alumno tiene un intento en curso y vigente,
     * el tutor no responde nada (ni tarjetas ni preguntas libres) hasta que termine.
     * Así no puede consultar ni explicar preguntas, respuestas o claves de ese intento,
     * aunque el alumno copie el enunciado en el chat o manipule la petición.
     */
    public function examenActivo(Usuario $usuario): ?object
    {
        return DB::table('intentos')
            ->where('usuario_id', $usuario->id)
            ->where('estado', 'en_curso')
            ->where(fn (Builder $q) => $q->whereNull('fecha_limite')->orWhere('fecha_limite', '>', now()))
            ->orderByDesc('id')
            ->first(['id', 'fecha_limite']);
    }

    /** Filtra intentos (alias i) y simulacros (alias s) a la universidad del contexto. */
    public function soloUniversidad(Builder $consulta, string $codigo): Builder
    {
        return UniversidadExamen::filtrar($consulta, $codigo);
    }

    /** Restringe preguntas (alias a = asignatura) al banco de práctica de la universidad. */
    public function bancoDeUniversidad(Builder $consulta, string $codigo): Builder
    {
        $prefijos = config('tutor.bancos.'.$codigo, []);

        return $consulta->where(function (Builder $q) use ($prefijos) {
            if ($prefijos === []) {
                // Banco general CNI: asignaturas sin prefijo de universidad.
                $q->where('a.nombre', 'not like', '% · %');

                return;
            }
            foreach ($prefijos as $prefijo) {
                $q->orWhere('a.nombre', 'like', $prefijo);
            }
        })->whereNotIn('a.nombre', config('tutor.asignaturas_excluidas', []));
    }

    /** «UNDC A · Álgebra» → «Álgebra». */
    public static function asignaturaVisible(?string $nombre): string
    {
        $partes = explode(' · ', (string) $nombre);

        return trim((string) end($partes)) ?: 'Sin asignatura';
    }
}
