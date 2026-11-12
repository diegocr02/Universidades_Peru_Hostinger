<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\PerfilExamenUndc;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Importa un banco original de 200 preguntas, separado por UNDC A y B. */
final class UndcPreguntasSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! config('cni.undc_banco_produccion')) {
            throw new RuntimeException('Fuera de local/testing requiere CNI_UNDC_BANCO_PRODUCCION=true en .env.');
        }

        $ruta = database_path('data/undc_preguntas_practica.json');
        if (! is_file($ruta)) {
            throw new RuntimeException("No se encontró el banco: {$ruta}");
        }
        $documento = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        $preguntas = $documento['preguntas'] ?? null;
        if (! is_array($preguntas) || count($preguntas) !== 200) {
            throw new RuntimeException('El banco debe contener exactamente 200 preguntas.');
        }

        PerfilExamenUndc::validar();
        $conteos = ['A' => [], 'B' => []];
        $codigos = [];
        $enunciados = [];
        foreach ($preguntas as $p) {
            $tipo = $p['tipo'] ?? null;
            $asignatura = $p['asignatura'] ?? null;
            $codigo = $p['codigo'] ?? null;
            $enunciado = trim((string) ($p['enunciado'] ?? ''));
            $alternativas = $p['alternativas'] ?? null;
            $correcta = $p['correcta'] ?? null;
            if (! in_array($tipo, ['A', 'B'], true)
                || ! isset(PerfilExamenUndc::CURSOS[$asignatura])
                || ! is_string($codigo)
                || ! str_starts_with($codigo, "UNDC-{$tipo}-{$asignatura}-")
                || $enunciado === ''
                || ! is_array($alternativas) || count($alternativas) !== 5
                || count(array_unique(array_map('strval', $alternativas))) !== 5
                || ! in_array($correcta, ['A', 'B', 'C', 'D', 'E'], true)
                || ! in_array($p['nivel'] ?? null, ['basico', 'intermedio', 'avanzado'], true)
                || trim((string) ($p['solucion'] ?? '')) === ''
                || isset($codigos[$codigo]) || isset($enunciados[mb_strtolower($enunciado)])) {
                throw new RuntimeException('Pregunta incompleta, duplicada o inválida: '.($codigo ?? '?'));
            }
            $codigos[$codigo] = true;
            $enunciados[mb_strtolower($enunciado)] = true;
            $conteos[$tipo][$asignatura] = ($conteos[$tipo][$asignatura] ?? 0) + 1;
        }
        foreach (['A', 'B'] as $tipo) {
            foreach (PerfilExamenUndc::distribucion($tipo) as $codigo => $cantidad) {
                if (($conteos[$tipo][$codigo] ?? 0) !== $cantidad) {
                    throw new RuntimeException("Cuota inválida: Tipo {$tipo}, {$codigo} requiere {$cantidad}.");
                }
            }
        }

        DB::transaction(function () use ($preguntas): void {
            $admin = DB::table('usuarios')->where('rol', 'admin')->value('id');
            $ahora = now();
            foreach ($preguntas as $p) {
                $tipo = $p['tipo'];
                [$nombreAsignatura] = PerfilExamenUndc::CURSOS[$p['asignatura']];
                $areaNombre = "UNDC {$tipo} · {$nombreAsignatura}";
                $areaId = DB::table('areas')->where('nombre', $areaNombre)->value('id');
                if (! $areaId) {
                    throw new RuntimeException("No existe el área {$areaNombre}; ejecuta primero UndcExamenSeeder.");
                }
                DB::table('temas')->updateOrInsert(
                    ['area_id' => $areaId, 'nombre' => 'Práctica de admisión'],
                    ['activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]
                );
                $temaId = DB::table('temas')->where('area_id', $areaId)
                    ->where('nombre', 'Práctica de admisión')->value('id');

                $existente = DB::table('preguntas')->where('codigo', $p['codigo'])->first();
                if ($existente && DB::table('respuestas_estudiante')
                    ->where('pregunta_id', $existente->id)->exists()) {
                    throw new RuntimeException('La pregunta '.$p['codigo'].' ya tiene respuestas de alumnos: no alterar su clave.');
                }
                $datos = [
                    'tema_id' => $temaId,
                    'enunciado' => $p['enunciado'],
                    'nivel' => $p['nivel'],
                    'solucion' => $p['solucion'],
                    'activo' => true,
                    'creado_por' => $admin,
                    'updated_at' => $ahora,
                ];
                if ($existente) {
                    DB::table('preguntas')->where('id', $existente->id)->update($datos);
                    $preguntaId = $existente->id;
                    DB::table('alternativas')->where('pregunta_id', $preguntaId)->delete();
                } else {
                    $preguntaId = DB::table('preguntas')->insertGetId([
                        'codigo' => $p['codigo'], 'created_at' => $ahora, ...$datos,
                    ]);
                }
                foreach ($p['alternativas'] as $i => $texto) {
                    DB::table('alternativas')->insert([
                        'pregunta_id' => $preguntaId,
                        'letra' => chr(65 + $i),
                        'texto' => (string) $texto,
                        'es_correcta' => chr(65 + $i) === $p['correcta'],
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
            }

            foreach (['A', 'B'] as $tipo) {
                $nombre = "UNDC - Admisión Ordinaria Tipo {$tipo} (preparación)";
                $simulacro = DB::table('simulacros')->where('nombre', $nombre)->first();
                if (! $simulacro) {
                    throw new RuntimeException("Falta el simulacro {$nombre}.");
                }
                $cuotas = DB::table('simulacro_area')->where('simulacro_id', $simulacro->id)->get();
                if ($cuotas->count() !== 19 || (int) $cuotas->sum('cantidad_preguntas') !== 100) {
                    throw new RuntimeException("Cuotas inválidas para Tipo {$tipo}.");
                }
                foreach ($cuotas as $cuota) {
                    $cantidad = DB::table('preguntas as p')
                        ->join('temas as t', 't.id', '=', 'p.tema_id')
                        ->where('t.area_id', $cuota->area_id)
                        ->where('p.activo', true)->count();
                    if ($cantidad !== (int) $cuota->cantidad_preguntas) {
                        throw new RuntimeException("Banco incompleto en área {$cuota->area_id}: {$cantidad} / {$cuota->cantidad_preguntas}.");
                    }
                }
                // Banco completo y validado: se habilita el examen.
                DB::table('simulacros')->where('id', $simulacro->id)->update([
                    'activo' => true, 'updated_at' => $ahora,
                ]);
            }
        });
    }
}
