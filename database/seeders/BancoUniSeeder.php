<?php

namespace Database\Seeders;

use App\Support\PerfilExamenCni;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Banco base del examen UNI (tres días). Solo agrega lo que falta:
 * una pregunta con el mismo código (UNI_AM_01, …) nunca se modifica ni se duplica,
 * así no se alteran intentos ni respuestas ya registradas.
 */
class BancoUniSeeder extends Seeder
{
    private const LETRAS = ['A', 'B', 'C', 'D', 'E'];

    public function run(): void
    {
        $banco = $this->leerBanco();
        $this->call(CatalogoBancoCniSeeder::class);
        $creadas = 0;

        DB::transaction(function () use ($banco, &$creadas) {
            $ahora = now();
            foreach ($banco as $pregunta) {
                $prefijo = $pregunta['asignatura'];
                $areaId = DB::table('areas')->where('nombre', PerfilExamenCni::CURSOS[$prefijo][0])->value('id');
                $temaId = DB::table('temas')->where('area_id', $areaId)->orderBy('id')->value('id');

                $codigo = $pregunta['codigo'];
                if (DB::table('preguntas')->where('codigo', $codigo)->exists()) {
                    continue;
                }
                $preguntaId = DB::table('preguntas')->insertGetId([
                    'codigo' => $codigo, 'tema_id' => $temaId, 'enunciado' => $pregunta['enunciado'],
                    'nivel' => $pregunta['nivel'], 'solucion' => $pregunta['solucion'], 'activo' => true,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
                foreach (self::LETRAS as $letra) {
                    DB::table('alternativas')->insert([
                        'pregunta_id' => $preguntaId, 'letra' => $letra, 'texto' => $pregunta['alternativas'][$letra],
                        'es_correcta' => $letra === $pregunta['correcta'], 'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                }
                $creadas++;
            }
        });

        $this->command?->info("Banco UNI: {$creadas} preguntas nuevas.");
    }

    /** Valida todo el documento antes de escribir en la base de datos. */
    private function leerBanco(): array
    {
        $ruta = database_path('data/uni_preguntas.json');
        if (! is_file($ruta)) {
            throw new RuntimeException('No se encontró el banco UNI: '.$ruta);
        }
        $documento = json_decode(file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        $preguntas = $documento['preguntas'] ?? null;
        if (($documento['version'] ?? null) !== 1 || ! is_array($preguntas)
            || ! array_is_list($preguntas) || $preguntas === []) {
            throw new RuntimeException('El JSON UNI requiere version=1 y una lista de preguntas no vacía.');
        }
        $codigos = [];
        foreach ($preguntas as $indice => $p) {
            $codigo = is_array($p) ? ($p['codigo'] ?? null) : null;
            $curso = is_array($p) ? ($p['asignatura'] ?? null) : null;
            $alternativas = is_array($p) ? ($p['alternativas'] ?? null) : null;
            if (! is_string($codigo) || ! is_string($curso)
                || ! isset(PerfilExamenCni::CURSOS[$curso])
                || ! preg_match('/^UNI_'.preg_quote($curso, '/').'_[0-9]{2,}$/D', $codigo)
                || strlen($codigo) > 30 || isset($codigos[$codigo])
                || ! is_string($p['enunciado'] ?? null) || trim($p['enunciado']) === ''
                || ! in_array($p['nivel'] ?? null, ['basico', 'intermedio', 'avanzado'], true)
                || ! is_string($p['solucion'] ?? null) || trim($p['solucion']) === ''
                || ! is_array($alternativas) || count($alternativas) !== 5
                || array_diff(self::LETRAS, array_keys($alternativas)) !== []
                || ! in_array($p['correcta'] ?? null, self::LETRAS, true)) {
                throw new RuntimeException('Pregunta UNI inválida o duplicada en posición '.($indice + 1).'.');
            }
            foreach ($alternativas as $texto) {
                if (! is_string($texto) || trim($texto) === '' || mb_strlen($texto) > 500) {
                    throw new RuntimeException('Alternativa inválida en '.$codigo.'.');
                }
            }
            $codigos[$codigo] = true;
        }

        return $preguntas;
    }
}
