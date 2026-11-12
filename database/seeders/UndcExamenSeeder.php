<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\PerfilExamenUndc;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Exámenes UNDC tipo A y B. Mantiene bancos separados entre tipos. */
final class UndcExamenSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! config('cni.undc_banco_produccion')) {
            throw new RuntimeException('Fuera de local/testing requiere CNI_UNDC_BANCO_PRODUCCION=true en .env.');
        }

        PerfilExamenUndc::validar();

        DB::transaction(function (): void {
            $ahora = now();
            $usuarioAdmin = DB::table('usuarios')->where('rol', 'admin')->value('id');
            foreach (['A', 'B'] as $tipo) {
                $cuotas = PerfilExamenUndc::distribucion($tipo);
                $areas = [];

                foreach (PerfilExamenUndc::CURSOS as $codigo => [$asignatura, $bloque]) {
                    // Evita mezclar o sortear preguntas A dentro del examen B.
                    $nombreArea = "UNDC {$tipo} · {$asignatura}";
                    DB::table('areas')->updateOrInsert(
                        ['nombre' => $nombreArea],
                        ['bloque' => $bloque, 'activo' => true,
                            'created_at' => $ahora, 'updated_at' => $ahora]
                    );
                    $areas[$codigo] = (int) DB::table('areas')
                        ->where('nombre', $nombreArea)->value('id');
                }

                $nombre = "UNDC - Admisión Ordinaria Tipo {$tipo} (preparación)";
                $existente = DB::table('simulacros')->where('nombre', $nombre)->first();
                if ($existente && DB::table('intentos')->where('simulacro_id', $existente->id)->exists()) {
                    throw new RuntimeException("El examen {$tipo} ya tiene intentos: no se puede cambiar su configuración.");
                }

                $datos = [
                    // Parámetros PROVISIONALES de demostración; NO son reglas oficiales 2026-II.
                    'tiempo_limite' => 60,
                    'total_preguntas' => 100,
                    'cantidad_alternativas' => 5,
                    'puntaje_correcta' => 1.000,
                    'puntaje_incorrecta' => -0.200,
                    'puntaje_blanco' => 0.000,
                    // Disponibilidad permanente, igual que las demás plantillas de examen.
                    'fecha_inicio' => '2000-01-01 00:00:00',
                    'fecha_fin' => '2099-12-31 23:59:59',
                    'activo' => false, // El banco se valida y habilita en UndcPreguntasSeeder.
                    'updated_at' => $ahora,
                ];
                if ($existente) {
                    DB::table('simulacros')->where('id', $existente->id)->update($datos);
                    $simulacroId = (int) $existente->id;
                    DB::table('simulacro_area')->where('simulacro_id', $simulacroId)->delete();
                } else {
                    $simulacroId = (int) DB::table('simulacros')->insertGetId([
                        'nombre' => $nombre,
                        'creado_por' => $usuarioAdmin,
                        'created_at' => $ahora,
                        ...$datos,
                    ]);
                }

                foreach ($cuotas as $codigo => $cantidad) {
                    DB::table('simulacro_area')->insert([
                        'simulacro_id' => $simulacroId,
                        'area_id' => $areas[$codigo],
                        'nivel' => 'mixto',
                        'cantidad_preguntas' => $cantidad,
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }

                // Solo los estudiantes de la UNDC (área A del selector de universidades) rinden estos exámenes.
                DB::table('area_postulacion_simulacro')
                    ->where('simulacro_id', $simulacroId)->delete();
                $ids = DB::table('areas_postulacion')->where('activo', true)->where('codigo', 'A')->pluck('id');
                foreach ($ids as $id) {
                    DB::table('area_postulacion_simulacro')->insert([
                        'area_postulacion_id' => $id,
                        'simulacro_id' => $simulacroId,
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
            }
        });
    }
}
