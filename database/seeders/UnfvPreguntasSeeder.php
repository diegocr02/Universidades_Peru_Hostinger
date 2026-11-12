<?php

namespace Database\Seeders;

use App\Support\BancoPracticaUnfv;
use App\Support\PerfilExamenCni;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Importación explícita e inmutable del Excel de Jimmy, imágenes incluidas. */
final class UnfvPreguntasSeeder extends Seeder
{
    public function run(): void
    {
        $documento = BancoPracticaUnfv::documento();
        $preguntas = $documento['preguntas'];
        if (count($preguntas) !== 150 || count(array_unique(array_column($preguntas, 'codigo'))) !== 150
            || array_sum($documento['cuotas_practica']) !== 50) {
            throw new RuntimeException('Se requieren 150 preguntas únicas y cuotas de 50.');
        }
        $conteos = [];
        foreach ($preguntas as $p) {
            if (! isset(PerfilExamenCni::CURSOS[$p['asignatura']])
                || ! preg_match('/^UNFV-PRACTICA-[A-Z]+_[0-9]+$/D', $p['codigo'])
                || ! in_array($p['bloque'], [1, 2, 3], true)
                || ! in_array($p['correcta'], ['A', 'B', 'C', 'D', 'E'], true)
                || array_keys($p['alternativas']) !== ['A', 'B', 'C', 'D', 'E']) {
                throw new RuntimeException('Pregunta inválida: '.$p['codigo']);
            }
            $this->validarImagen($p['imagen']);
            foreach ($p['alternativas'] as $a) {
                if (trim($a['texto']) === '' && $a['imagen'] === null) {
                    throw new RuntimeException('Alternativa incompleta: '.$p['codigo']);
                }
                if (mb_strlen($a['texto']) > 500) {
                    throw new RuntimeException('Alternativa demasiado larga: '.$p['codigo']);
                }
                if ($a['imagen'] !== null) {
                    $this->validarImagen($a['imagen']);
                }
            }
            $conteos[$p['bloque']][$p['asignatura']] = ($conteos[$p['bloque']][$p['asignatura']] ?? 0) + 1;
        }
        foreach ([1, 2, 3] as $bloque) {
            foreach ($documento['cuotas_practica'] as $curso => $cantidad) {
                if (($conteos[$bloque][$curso] ?? 0) !== $cantidad) {
                    throw new RuntimeException('Cuotas inválidas en bloque '.$bloque);
                }
            }
        }

        DB::transaction(function () use ($preguntas): void {
            $ahora = now();
            $version = BancoPracticaUnfv::version();
            foreach ($preguntas as $p) {
                $codigo = $p['codigo'].'-'.$version;
                // Nunca reescribir preguntas ni claves que puedan tener intentos.
                if (DB::table('preguntas')->where('codigo', $codigo)->exists()) {
                    continue;
                }
                $nombre = BancoPracticaUnfv::area($p['asignatura']);
                DB::table('areas')->insertOrIgnore([
                    'nombre' => $nombre, 'bloque' => PerfilExamenCni::CURSOS[$p['asignatura']][1],
                    'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
                $area = DB::table('areas')->where('nombre', $nombre)->value('id');
                if (! DB::table('temas')->where('area_id', $area)->where('nombre', 'Práctica general UNFV')->exists()) {
                    DB::table('temas')->insert([
                        'area_id' => $area, 'nombre' => 'Práctica general UNFV',
                        'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
                $tema = DB::table('temas')->where('area_id', $area)->where('nombre', 'Práctica general UNFV')->value('id');
                $id = DB::table('preguntas')->insertGetId([
                    'codigo' => $codigo, 'tema_id' => $tema, 'enunciado' => $p['enunciado'],
                    'imagen' => $this->guardarImagen($p['imagen']), 'nivel' => $p['nivel'],
                    'solucion' => $p['solucion'], 'activo' => true, 'creado_por' => null,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
                foreach ($p['alternativas'] as $letra => $a) {
                    DB::table('alternativas')->insert([
                        'pregunta_id' => $id, 'letra' => $letra, 'texto' => $a['texto'],
                        'imagen' => $a['imagen'] !== null ? $this->guardarImagen($a['imagen']) : null,
                        'es_correcta' => $letra === $p['correcta'],
                        'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
            }
        });
        $this->command?->info('Banco UNFV preparado: 150 preguntas, 750 alternativas y 153 imágenes.');
    }

    private function validarImagen(array $imagen): void
    {
        $bytes = base64_decode($imagen['base64'], true);
        if ($bytes === false || ! in_array($imagen['extension'], ['png', 'jpeg', 'jpg'], true)
            || ! hash_equals($imagen['sha256'], hash('sha256', $bytes))) {
            throw new RuntimeException('Imagen dañada o formato inválido.');
        }
    }

    private function guardarImagen(array $imagen): string
    {
        $ruta = 'banco/unfv/'.$imagen['sha256'].'.'.$imagen['extension'];
        if (! Storage::disk('public')->put($ruta, base64_decode($imagen['base64'], true))) {
            throw new RuntimeException('No se pudo guardar la imagen '.$ruta);
        }

        return 'storage/'.$ruta;
    }
}
