<?php

namespace Database\Seeders;

use App\Support\BancoUnmsmJson;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Importa JSON inmutable. No cambia preguntas ni claves de intentos anteriores. */
class BancoUnmsmSeeder extends Seeder
{
    public function run(): void
    {
        $documento = BancoUnmsmJson::leer();
        $creadas = 0;
        DB::transaction(function () use ($documento, &$creadas) {
            foreach ($documento['preguntas'] as $p) {
                $codigo = BancoUnmsmJson::codigo($p);
                if (DB::table('preguntas')->where('codigo', $codigo)->exists()) {
                    continue;
                }
                $curso = count($p['areas']) === 1
                    ? BancoUnmsmJson::asignatura($p['areas'][0], $p['asignatura']) : $p['asignatura'];
                $bloque = match ($p['asignatura']) {
                    'Razonamiento Verbal' => 'habilidad_verbal',
                    'Habilidad Matemática' => 'habilidad_logico_matematica',
                    default => 'conocimientos',
                };
                $area = DB::table('areas')->where('nombre', $curso)->value('id');
                if (! $area) {
                    $area = DB::table('areas')->insertGetId(['nombre' => $curso, 'bloque' => $bloque,
                        'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
                $temaNombre = 'San Marcos · '.$p['tema'];
                $tema = DB::table('temas')->where('area_id', $area)->where('nombre', $temaNombre)->value('id');
                if (! $tema) {
                    $tema = DB::table('temas')->insertGetId(['area_id' => $area, 'nombre' => $temaNombre,
                        'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
                $imagen = null;
                if ($p['imagen'] !== null) {
                    $ruta = 'unmsm/'.substr(hash('sha256', $p['imagen']['svg']), 0, 24).'.svg';
                    if (! Storage::disk('public')->put($ruta, $p['imagen']['svg'])) {
                        throw new RuntimeException('No se pudo guardar la imagen de '.$p['codigo']);
                    }
                    $imagen = 'storage/'.$ruta;
                }
                $id = DB::table('preguntas')->insertGetId([
                    'codigo' => $codigo, 'tema_id' => $tema, 'enunciado' => $p['enunciado'],
                    'imagen' => $imagen, 'nivel' => $p['nivel'], 'solucion' => $p['solucion'], 'activo' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($p['alternativas'] as $letra => $texto) {
                    DB::table('alternativas')->insert([
                        'pregunta_id' => $id, 'letra' => $letra, 'texto' => $texto,
                        'es_correcta' => $p['correcta'] === $letra,
                        'puntaje_actitudinal' => $p['rubrica'][$letra] ?? null,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $creadas++;
            }
        });
        $this->command?->info('Banco San Marcos JSON: '.$creadas.' preguntas nuevas. '.count($documento['preguntas']).' registros en el documento.');
    }
}
