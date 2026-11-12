<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BancoUnmsmJson
{
    public const MATEMATICAS = ['Aritmética', 'Álgebra', 'Geometría', 'Trigonometría', 'Habilidad Matemática'];

    public static function ruta(): string
    {
        return database_path('data/unmsm_preguntas.json');
    }

    public static function version(): string
    {
        return substr(hash_file('sha256', self::ruta()), 0, 10);
    }

    public static function asignatura(string $area, string $nombre): string
    {
        return in_array($area, ['C', 'E'], true) && in_array($nombre, self::MATEMATICAS, true)
            ? 'San Marcos '.$area.' '.self::version().' · '.$nombre : $nombre;
    }

    public static function codigo(array $pregunta): string
    {
        return count($pregunta['areas']) === 1 && in_array($pregunta['areas'][0], ['C', 'E'], true)
            && in_array($pregunta['asignatura'], self::MATEMATICAS, true)
            ? $pregunta['codigo'].'-'.self::version() : $pregunta['codigo'];
    }

    public static function disponible(string $area): bool
    {
        if (! in_array($area, ['C', 'E'], true)) {
            return false;
        }
        $nombres = array_map(fn ($curso) => self::asignatura($area, $curso), self::MATEMATICAS);

        return DB::table('areas')->where('activo', true)->whereIn('nombre', $nombres)->count() === count($nombres);
    }

    public static function mapearCuotas(string $area, array $cuotas): array
    {
        if (! self::disponible($area)) {
            return $cuotas;
        }
        $resultado = [];
        foreach ($cuotas as $curso => $cantidad) {
            $resultado[self::asignatura($area, $curso)] = $cantidad;
        }

        return $resultado;
    }

    /** Valida todo antes de escribir registros o imágenes. */
    public static function leer(?string $ruta = null): array
    {
        $documento = json_decode(file_get_contents($ruta ?? self::ruta()), true, 512, JSON_THROW_ON_ERROR);
        if (($documento['version'] ?? null) !== 1 || ! is_array($documento['preguntas'] ?? null)
            || ! array_is_list($documento['preguntas']) || $documento['preguntas'] === []) {
            throw new RuntimeException('El banco San Marcos requiere version=1 y una lista de preguntas.');
        }
        $codigos = [];
        $cursos = array_unique(array_merge(array_keys(config('unmsm.fijas')), ...array_values(array_map('array_keys', config('unmsm.conocimientos')))));
        foreach ($documento['preguntas'] as $p) {
            $codigo = $p['codigo'] ?? '';
            if (! is_string($codigo) || ! preg_match('/^SM-[A-Z0-9-]{3,65}$/D', $codigo) || isset($codigos[$codigo])
                || ! in_array($p['asignatura'] ?? null, $cursos, true)
                || ! is_array($p['areas'] ?? null) || $p['areas'] === [] || array_diff($p['areas'], ['A', 'C', 'E']) !== []
                || ! is_string($p['enunciado'] ?? null) || trim($p['enunciado']) === ''
                || ! is_string($p['solucion'] ?? null) || trim($p['solucion']) === ''
                || ! in_array($p['nivel'] ?? null, ['basico', 'intermedio', 'avanzado'], true)
                || ! is_array($p['alternativas'] ?? null) || array_keys($p['alternativas']) !== ['A', 'B', 'C', 'D', 'E']
                || count(array_unique($p['alternativas'], SORT_STRING)) !== 5) {
                throw new RuntimeException('Pregunta San Marcos inválida o duplicada: '.$codigo);
            }
            foreach ($p['alternativas'] as $texto) {
                if (! is_string($texto) || trim($texto) === '' || mb_strlen($texto) > 500) {
                    throw new RuntimeException('Alternativa inválida en '.$codigo);
                }
            }
            if ($p['asignatura'] === PerfilExamenUnmsm::ASIGNATURA_ACTITUDINAL) {
                $rubrica = $p['rubrica'] ?? null;
                if ($p['correcta'] !== null || ! is_array($rubrica) || array_keys($rubrica) !== ['A', 'B', 'C', 'D', 'E']) {
                    throw new RuntimeException('Rúbrica incompleta en '.$codigo);
                }
                $valores = array_values($rubrica); sort($valores);
                if ($valores != [0, 5, 10, 15, 20]) {
                    throw new RuntimeException('Puntajes actitudinales inválidos en '.$codigo);
                }
            } elseif (! in_array($p['correcta'] ?? null, ['A', 'B', 'C', 'D', 'E'], true) || $p['rubrica'] !== null) {
                throw new RuntimeException('Clave inválida en '.$codigo);
            }
            if (($p['imagen'] ?? null) !== null) {
                self::validarImagen($p['imagen']);
            }
            $codigos[$codigo] = true;
        }

        return $documento;
    }

    private static function validarImagen(array $imagen): void
    {
        $svg = $imagen['svg'] ?? '';
        if (! is_string($svg) || strlen($svg) > 100000 || ! is_string($imagen['descripcion'] ?? null)
            || trim($imagen['descripcion']) === '' || preg_match('/<!DOCTYPE|<!ENTITY/i', $svg)) {
            throw new RuntimeException('Imagen SVG inválida.');
        }
        $xml = new \DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        try {
            if (! $xml->loadXML($svg, LIBXML_NONET) || $xml->documentElement?->localName !== 'svg'
                || $xml->documentElement?->namespaceURI !== 'http://www.w3.org/2000/svg') {
                throw new RuntimeException('La imagen no contiene un SVG válido.');
            }
            $permitidos = ['svg', 'title', 'desc', 'g', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'path', 'text', 'tspan', 'clipPath'];
            foreach ($xml->getElementsByTagName('*') as $nodo) {
                if (! in_array($nodo->localName, $permitidos, true)) {
                    throw new RuntimeException('Elemento SVG no permitido.');
                }
                foreach ($nodo->attributes as $atributo) {
                    $urlLocal = preg_match('/^url\(#[A-Za-z][A-Za-z0-9_-]*\)$/D', $atributo->value);
                    if (preg_match('/^on|href|style/i', $atributo->name)
                        || (preg_match('/url\s*\(|javascript:/i', $atributo->value) && ! $urlLocal)) {
                        throw new RuntimeException('Referencia activa o externa en SVG.');
                    }
                }
            }
        } finally {
            libxml_clear_errors(); libxml_use_internal_errors($anterior);
        }
    }
}
