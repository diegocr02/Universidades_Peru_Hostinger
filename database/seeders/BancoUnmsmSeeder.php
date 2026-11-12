<?php

namespace Database\Seeders;

use App\Support\PerfilExamenUnmsm;
use Database\Seeders\Unmsm\GeneradorPreguntas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Banco San Marcos completo: asignaturas, temas, preguntas, alternativas e imágenes.
 *   php artisan db:seed --class=BancoUnmsmSeeder   (requiere php artisan storage:link)
 *
 * - Numéricas (Aritmética, Álgebra, Geometría, Trigonometría, Física, Habilidad Lógico-Matemática):
 *   GeneradorPreguntas calcula datos y respuestas; las figuras se guardan como SVG en
 *   storage/app/public/unmsm y la pregunta apunta a storage/unmsm/<código>.svg.
 * - Teoría, Habilidad Verbal y actitudinales: database/seeders/data/unmsm/*.php.
 *
 * Idempotente: cada pregunta tiene un código fijo (SM-…); se actualiza, nunca se duplica ni se borra.
 */
class BancoUnmsmSeeder extends Seeder
{
    private const BLOQUES = [
        'Razonamiento Verbal' => 'habilidad_verbal',
        'Habilidad Matemática' => 'habilidad_logico_matematica',
    ];

    private const PREFIJOS = [
        'Biología' => 'BIO', 'Química' => 'QUI', 'Lenguaje' => 'LEN', 'Literatura' => 'LIT',
        'Psicología' => 'PSI', 'Educación Cívica' => 'CIV', 'Geografía' => 'GEG', 'Economía' => 'ECO',
        'Filosofía y Lógica' => 'FIL', 'Historia del Perú' => 'HPE', 'Historia Universal' => 'HUN',
        'Razonamiento Verbal' => 'HV', 'Actitudinal' => 'ACT',
    ];

    /** Puntajes de la rúbrica del simulador, de la mejor a la peor alternativa (no es la rúbrica oficial). */
    private const RUBRICA = [20, 15, 10, 5, 0];

    private const LETRAS = ['A', 'B', 'C', 'D', 'E'];

    public function run(): void
    {
        $disco = Storage::disk('public');
        $conRubrica = PerfilExamenUnmsm::hayRubrica();
        $resumen = [];

        foreach ($this->preguntas() as $p) {
            DB::transaction(function () use ($p, $disco, $conRubrica, &$resumen) {
                $temaId = $this->tema($p['asignatura']);
                $imagen = null;
                if ($p['svg'] !== null) {
                    $ruta = 'unmsm/'.$p['codigo'].'.svg';
                    $disco->put($ruta, $p['svg']);
                    $imagen = 'storage/'.$ruta;
                }
                $ahora = now();
                DB::table('preguntas')->updateOrInsert(['codigo' => $p['codigo']], [
                    'tema_id' => $temaId, 'enunciado' => $p['enunciado'], 'imagen' => $imagen,
                    'nivel' => $p['nivel'], 'solucion' => $p['solucion'], 'activo' => true,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
                $preguntaId = DB::table('preguntas')->where('codigo', $p['codigo'])->value('id');

                foreach ($this->ordenar($p) as $indice => [$texto, $esCorrecta, $rubrica]) {
                    $fila = ['texto' => mb_substr($texto, 0, 500), 'imagen' => null, 'es_correcta' => $esCorrecta,
                        'created_at' => $ahora, 'updated_at' => $ahora];
                    if ($conRubrica) {
                        $fila['puntaje_actitudinal'] = $rubrica;
                    }
                    // Por letra: no cambia los id de alternativas que ya respondieron los alumnos.
                    DB::table('alternativas')->updateOrInsert(
                        ['pregunta_id' => $preguntaId, 'letra' => self::LETRAS[$indice]], $fila
                    );
                }
                $resumen[$p['asignatura']] = ($resumen[$p['asignatura']] ?? 0) + 1;
            });
        }

        ksort($resumen);
        foreach ($resumen as $asignatura => $total) {
            $this->command?->info(str_pad($asignatura, 26).$total.' preguntas');
        }
        $this->command?->info('Total: '.array_sum($resumen).' preguntas San Marcos. Imágenes en storage/app/public/unmsm.');
        if (! $conRubrica) {
            $this->command?->warn('Falta la migración de puntaje_actitudinal: las actitudinales no se pueden calificar.');
        }
    }

    /** @return list<array<string, mixed>> */
    private function preguntas(): array
    {
        $lista = (new GeneradorPreguntas)->todas();
        $datos = (require database_path('seeders/data/unmsm/teoria.php'))
            + (require database_path('seeders/data/unmsm/habilidades.php'));

        foreach ($datos as $asignatura => $preguntas) {
            foreach ($preguntas as $n => $fila) {
                $actitudinal = $asignatura === PerfilExamenUnmsm::ASIGNATURA_ACTITUDINAL;
                $lista[] = [
                    'codigo' => sprintf('SM-%s-%02d', self::PREFIJOS[$asignatura], $n + 1),
                    'asignatura' => $asignatura,
                    'enunciado' => $fila[0],
                    'opciones' => $fila[1],
                    'solucion' => $actitudinal
                        ? 'Pregunta actitudinal: se valora la respuesta según una rúbrica del simulador (no oficial).'
                        : $fila[2],
                    'nivel' => 'intermedio',
                    'svg' => null,
                    'actitudinal' => $actitudinal,
                ];
            }
        }

        return $lista;
    }

    /**
     * Las cinco alternativas con su posición final. La correcta (o la mejor en la rúbrica) viene
     * primero en los datos; aquí se reparte de forma estable entre A y E según el código.
     *
     * @return list<array{0: string, 1: bool, 2: float|null}>
     */
    private function ordenar(array $p): array
    {
        $actitudinal = $p['actitudinal'] ?? false;
        $opciones = [];
        foreach (array_values($p['opciones']) as $i => $texto) {
            $opciones[] = [$texto, ! $actitudinal && $i === 0, $actitudinal ? (float) self::RUBRICA[$i] : null];
        }
        // Orden estable y distinto por pregunta: el mismo código siempre produce las mismas letras.
        usort($opciones, fn ($a, $b) => crc32($p['codigo'].$a[0]) <=> crc32($p['codigo'].$b[0]));

        return $opciones;
    }

    private function tema(string $asignatura): int
    {
        $areaId = DB::table('areas')->where('nombre', $asignatura)->value('id');
        if (! $areaId) {
            $areaId = DB::table('areas')->insertGetId([
                'nombre' => $asignatura, 'bloque' => self::BLOQUES[$asignatura] ?? 'conocimientos',
                'activo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $nombreTema = 'San Marcos · '.PerfilExamenUnmsm::etiqueta($asignatura);
        $temaId = DB::table('temas')->where('area_id', $areaId)->where('nombre', $nombreTema)->value('id');

        return (int) ($temaId ?: DB::table('temas')->insertGetId([
            'area_id' => $areaId, 'nombre' => $nombreTema, 'activo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }
}
