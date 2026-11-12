<?php

namespace App\Services\Alumno;

use App\Models\Usuario;
use App\Services\IntentoService;
use App\Support\PerfilExamenCni;
use App\Support\BancoPracticaUnfv;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Configura una plantilla por área y reutiliza el motor de intentos y calificación. */
class ExamenIndependienteService
{
    /** Escala del examen independiente CNI (A–E); la UNI usa config/examen_uni.php. */
    private const VALORES_CNI = ['puntaje_correcta' => 20, 'puntaje_incorrecta' => -1.125, 'puntaje_blanco' => 0];

    public function __construct(private readonly IntentoService $intentos) {}

    public function iniciar(Usuario $usuario): object
    {
        return DB::transaction(function () use ($usuario) {
            [$perfil, $area] = $this->perfilYArea($usuario);
            abort_unless(isset(PerfilExamenCni::DISTRIBUCION[$area->codigo]), 403);

            $esUnfv = $area->codigo === 'C' && DB::table('areas')->where('nombre', BancoPracticaUnfv::area('AV'))->exists();
            $cuotas = $esUnfv ? BancoPracticaUnfv::documento()['cuotas_practica'] : PerfilExamenCni::DISTRIBUCION[$area->codigo];
            // Una configuración nueva obtiene otra plantilla; no altera exámenes anteriores.
            $version = substr(hash('sha256', json_encode([$cuotas, PerfilExamenCni::ORDEN, 60, 5])), 0, 10);
            $nombre = $esUnfv
                ? 'Práctica general UNFV · '.BancoPracticaUnfv::version().' · '.$version
                : 'Examen independiente CNI · Área '.$area->codigo.' · '.$version;

            return $this->abrirIntento($perfil, $area, $nombre, $cuotas, 60);
        }, 3);
    }

    /** Jornada UNI (día 1, 2 o 3): solo para el área B, 180 minutos, mismo motor de intentos. */
    public function iniciarDiaUni(Usuario $usuario, int $dia): object
    {
        abort_unless(isset(PerfilExamenCni::UNI_DIAS[$dia]), 404);

        return DB::transaction(function () use ($usuario, $dia) {
            [$perfil, $area] = $this->perfilYArea($usuario);
            abort_unless($area->codigo === 'B', 403);

            $cuotas = PerfilExamenCni::UNI_DIAS[$dia]['cuotas'];
            $minutos = PerfilExamenCni::UNI_MINUTOS;
            $valores = app(CalificacionUniService::class)->valoresPlantilla($dia);
            $nombre = PerfilExamenCni::nombreDiaUni($dia, $valores);

            return $this->abrirIntento($perfil, $area, $nombre, $cuotas, $minutos, $valores);
        }, 3);
    }

    /** @return array{0: Usuario, 1: object} Perfil bloqueado y área activa del alumno. */
    private function perfilYArea(Usuario $usuario): array
    {
        // Serializa doble clic y peticiones simultáneas del mismo alumno.
        $perfil = Usuario::query()->whereKey($usuario->id)->lockForUpdate()->firstOrFail();
        abort_unless($perfil->activo && $perfil->rol === 'estudiante', 403);

        $carrera = $perfil->carrera;
        $areaId = $perfil->area_postulacion_id ?? $carrera?->area_postulacion_id;
        abort_if($carrera && (! $carrera->activo
            || (int) $carrera->area_postulacion_id !== (int) $areaId), 403);
        // El bloqueo del área evita crear dos plantillas al entrar dos alumnos a la vez.
        $area = DB::table('areas_postulacion')->where('id', $areaId)
            ->where('activo', true)->lockForUpdate()->first();
        abort_unless($area, 403);

        return [$perfil, $area];
    }

    /** Reanuda el intento abierto de la plantilla o crea la plantilla (si falta) y un intento nuevo. */
    private function abrirIntento(Usuario $perfil, object $area, string $nombre, array $cuotas, int $minutos, array $valores = self::VALORES_CNI): object
    {
        $simulacro = DB::table('simulacros')->where('nombre', $nombre)->first();
        if ($simulacro) {
            $abierto = $this->intentos->enCurso($perfil->id, $simulacro->id);
            if ($abierto) {
                return $abierto;
            }
        } else {
            $asignaturas = DB::table('areas')->where('activo', true)->pluck('id', 'nombre');
            foreach ($cuotas as $prefijo => $cantidad) {
                $curso = str_starts_with($nombre, 'Práctica general UNFV · ') ? BancoPracticaUnfv::area($prefijo) : PerfilExamenCni::CURSOS[$prefijo][0];
                if (! isset($asignaturas[$curso])) {
                    throw ValidationException::withMessages([
                        'preguntas' => 'El banco del área '.$area->codigo.' aún no está completo: falta '.$curso.'.',
                    ]);
                }
            }

            $ahora = now();
            $simulacroId = DB::table('simulacros')->insertGetId([
                'nombre' => $nombre, 'tiempo_limite' => $minutos,
                'total_preguntas' => array_sum($cuotas), 'cantidad_alternativas' => 5,
                'puntaje_correcta' => $valores['puntaje_correcta'],
                'puntaje_incorrecta' => $valores['puntaje_incorrecta'],
                'puntaje_blanco' => $valores['puntaje_blanco'],
                // Disponibilidad permanente; cada intento conserva su propio límite de tiempo.
                'fecha_inicio' => '2000-01-01 00:00:00', 'fecha_fin' => '2099-12-31 23:59:59',
                'activo' => true, 'carrera_id' => null, 'creado_por' => null,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            DB::table('area_postulacion_simulacro')->insert([
                'simulacro_id' => $simulacroId, 'area_postulacion_id' => $area->id,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            foreach (PerfilExamenCni::ORDEN as $prefijo) {
                if (! isset($cuotas[$prefijo])) {
                    continue;
                }
                DB::table('simulacro_area')->insert([
                    'simulacro_id' => $simulacroId,
                    'area_id' => $asignaturas[str_starts_with($nombre, 'Práctica general UNFV · ') ? BancoPracticaUnfv::area($prefijo) : PerfilExamenCni::CURSOS[$prefijo][0]],
                    'nivel' => 'mixto', 'cantidad_preguntas' => $cuotas[$prefijo],
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
            $simulacro = (object) ['id' => $simulacroId];
        }

        return $this->intentos->iniciar($perfil->id, [
            'tipo' => 'simulacro', 'simulacro_id' => $simulacro->id,
            'orden_por_curso' => true,
        ]);
    }
}

