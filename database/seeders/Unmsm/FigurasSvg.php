<?php

namespace Database\Seeders\Unmsm;

/**
 * Figuras SVG de las preguntas San Marcos (geometría, trigonometría, física y gráficos).
 * Fondo blanco, trazo oscuro y acento dorado; se guardan en storage/app/public/unmsm.
 */
final class FigurasSvg
{
    private const TINTA = '#303030';

    private const ACENTO = '#B8860B';

    private const SUAVE = '#F6E6B8';

    private static function lienzo(int $ancho, int $alto, string $contenido): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$ancho.' '.$alto.'" width="'.$ancho.'" height="'.$alto.'" '
            .'font-family="Arial, Helvetica, sans-serif" font-size="15" fill="'.self::TINTA.'">'
            .'<rect width="100%" height="100%" fill="#ffffff"/>'.$contenido.'</svg>';
    }

    private static function texto(float $x, float $y, string $texto, string $extra = ''): string
    {
        return '<text x="'.round($x, 1).'" y="'.round($y, 1).'" '.$extra.'>'.htmlspecialchars($texto, ENT_XML1).'</text>';
    }

    /** Triángulo con dos ángulos interiores conocidos y el tercero como incógnita x. */
    public static function trianguloAngulos(int $a, int $b): string
    {
        $c = '<polygon points="40,200 320,200 210,40" fill="'.self::SUAVE.'" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .self::texto(58, 190, $a.'°', 'font-weight="bold"')
            .self::texto(272, 190, $b.'°', 'font-weight="bold"')
            .self::texto(203, 72, 'x', 'font-weight="bold" fill="'.self::ACENTO.'" font-size="18"')
            .self::texto(30, 218, 'A').self::texto(318, 218, 'B').self::texto(205, 32, 'C');

        return self::lienzo(360, 230, $c);
    }

    /** Circunferencia de centro O con ángulo central θ y ángulo inscrito x que subtienden el mismo arco. */
    public static function anguloInscrito(int $central): string
    {
        $cx = 180;
        $cy = 120;
        $r = 90;
        $mitad = deg2rad($central / 2);
        $ax = $cx + $r * cos(deg2rad(90) + $mitad);
        $ay = $cy + $r * sin(deg2rad(90) + $mitad);
        $bx = $cx + $r * cos(deg2rad(90) - $mitad);
        $by = $cy + $r * sin(deg2rad(90) - $mitad);
        $c = '<circle cx="'.$cx.'" cy="'.$cy.'" r="'.$r.'" fill="none" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<polyline points="'.round($ax, 1).','.round($ay, 1).' '.$cx.','.$cy.' '.round($bx, 1).','.round($by, 1).'" fill="none" stroke="'.self::ACENTO.'" stroke-width="2"/>'
            .'<polyline points="'.round($ax, 1).','.round($ay, 1).' '.$cx.','.($cy - $r).' '.round($bx, 1).','.round($by, 1).'" fill="none" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<circle cx="'.$cx.'" cy="'.$cy.'" r="3" fill="'.self::TINTA.'"/>'
            .self::texto($cx + 8, $cy + 4, 'O')
            .self::texto($cx - 12, $cy + 34, $central.'°', 'font-weight="bold" fill="'.self::ACENTO.'"')
            .self::texto($cx - 5, $cy - $r + 30, 'x', 'font-weight="bold" font-size="18"')
            .self::texto($ax - 18, $ay + 14, 'A').self::texto($bx + 6, $by + 14, 'B').self::texto($cx - 5, $cy - $r - 6, 'P');

        return self::lienzo(360, 240, $c);
    }

    /** Trapecio con bases y altura rotuladas. */
    public static function trapecio(int $baseMayor, int $baseMenor, int $altura): string
    {
        $c = '<polygon points="40,190 320,190 250,60 110,60" fill="'.self::SUAVE.'" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<line x1="110" y1="60" x2="110" y2="190" stroke="'.self::ACENTO.'" stroke-width="2" stroke-dasharray="6 4"/>'
            .self::texto(160, 212, $baseMayor.' cm', 'font-weight="bold"')
            .self::texto(158, 50, $baseMenor.' cm', 'font-weight="bold"')
            .self::texto(118, 130, 'h = '.$altura.' cm', 'fill="'.self::ACENTO.'" font-weight="bold"');

        return self::lienzo(360, 225, $c);
    }

    /** Rectángulo con su diagonal como incógnita. */
    public static function rectanguloDiagonal(int $largo, int $ancho): string
    {
        $c = '<rect x="50" y="50" width="260" height="140" fill="'.self::SUAVE.'" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<line x1="50" y1="190" x2="310" y2="50" stroke="'.self::ACENTO.'" stroke-width="2.5"/>'
            .self::texto(160, 212, $largo.' m', 'font-weight="bold"')
            .self::texto(318, 125, $ancho.' m', 'font-weight="bold"')
            .self::texto(170, 112, 'd', 'font-weight="bold" fill="'.self::ACENTO.'" font-size="18"');

        return self::lienzo(370, 225, $c);
    }

    /** Triángulo rectángulo con catetos y ángulo θ en el vértice A. */
    public static function trianguloRectangulo(string $catetoOpuesto, string $catetoAdyacente, string $hipotenusa): string
    {
        $c = '<polygon points="50,190 300,190 300,50" fill="'.self::SUAVE.'" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<polyline points="284,190 284,174 300,174" fill="none" stroke="'.self::TINTA.'"/>'
            .'<path d="M 92 190 A 42 42 0 0 0 87 170" fill="none" stroke="'.self::ACENTO.'" stroke-width="2"/>'
            .self::texto(98, 182, 'θ', 'fill="'.self::ACENTO.'" font-weight="bold" font-size="17"')
            .self::texto(310, 125, $catetoOpuesto, 'font-weight="bold"')
            .self::texto(165, 212, $catetoAdyacente, 'font-weight="bold"')
            .self::texto(150, 108, $hipotenusa, 'font-weight="bold"')
            .self::texto(36, 205, 'A').self::texto(306, 205, 'C').self::texto(306, 45, 'B');

        return self::lienzo(360, 225, $c);
    }

    /** Observador a una distancia d de una torre, con ángulo de elevación. */
    public static function anguloElevacion(int $distancia, int $angulo): string
    {
        $c = '<line x1="30" y1="190" x2="340" y2="190" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<rect x="280" y="50" width="26" height="140" fill="'.self::SUAVE.'" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<line x1="60" y1="190" x2="293" y2="50" stroke="'.self::ACENTO.'" stroke-width="2" stroke-dasharray="6 4"/>'
            .'<circle cx="60" cy="182" r="6" fill="'.self::TINTA.'"/>'
            .'<path d="M 105 190 A 45 45 0 0 0 100 166" fill="none" stroke="'.self::ACENTO.'" stroke-width="2"/>'
            .self::texto(110, 180, $angulo.'°', 'fill="'.self::ACENTO.'" font-weight="bold"')
            .self::texto(150, 212, $distancia.' m', 'font-weight="bold"')
            .self::texto(312, 125, 'h', 'font-weight="bold" font-size="18"');

        return self::lienzo(360, 225, $c);
    }

    /** Gráfica velocidad–tiempo por tramos: lista de puntos [t, v]. */
    public static function graficoVelocidadTiempo(array $puntos): string
    {
        $tMax = max(array_column($puntos, 0));
        $vMax = max(array_column($puntos, 1));
        $x = fn ($t) => 60 + $t * 260 / $tMax;
        $y = fn ($v) => 190 - $v * 140 / $vMax;
        $poligono = [];
        foreach ($puntos as [$t, $v]) {
            $poligono[] = round($x($t), 1).','.round($y($v), 1);
        }
        $c = '<polygon points="'.round($x(0), 1).','.$y(0).' '.implode(' ', $poligono).' '.round($x($tMax), 1).','.$y(0).'" fill="'.self::SUAVE.'"/>'
            .'<polyline points="'.implode(' ', $poligono).'" fill="none" stroke="'.self::ACENTO.'" stroke-width="3"/>'
            .'<line x1="60" y1="190" x2="335" y2="190" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<line x1="60" y1="190" x2="60" y2="35" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .self::texto(312, 212, 't (s)').self::texto(14, 40, 'v (m/s)');
        foreach ($puntos as [$t, $v]) {
            $c .= self::texto($x($t) - 5, 208, (string) $t, 'font-size="13"')
                .'<line x1="'.round($x($t), 1).'" y1="'.round($y($v), 1).'" x2="'.round($x($t), 1).'" y2="190" stroke="#c9b98f" stroke-dasharray="3 3"/>';
        }
        foreach (array_unique(array_column($puntos, 1)) as $v) {
            if ($v > 0) {
                $c .= self::texto(30, $y($v) + 5, (string) $v, 'font-size="13"')
                    .'<line x1="60" y1="'.round($y($v), 1).'" x2="'.round($x($tMax), 1).'" y2="'.round($y($v), 1).'" stroke="#e5d9b8" stroke-dasharray="3 3"/>';
            }
        }

        return self::lienzo(360, 225, $c);
    }

    /** Circuito: R1 en serie con el paralelo de R2 y R3, alimentado por una fuente V. */
    public static function circuitoMixto(int $voltaje, int $r1, int $r2, int $r3): string
    {
        $resistencia = fn ($x, $y, $vertical = false) => $vertical
            ? '<rect x="'.($x - 9).'" y="'.$y.'" width="18" height="44" fill="'.self::SUAVE.'" stroke="'.self::TINTA.'" stroke-width="2"/>'
            : '<rect x="'.$x.'" y="'.($y - 9).'" width="54" height="18" fill="'.self::SUAVE.'" stroke="'.self::TINTA.'" stroke-width="2"/>';
        $c = '<polyline points="50,150 50,50 140,50" fill="none" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .$resistencia(140, 50)
            .'<polyline points="194,50 300,50 300,180 50,180 50,170" fill="none" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<line x1="230" y1="50" x2="230" y2="93" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .$resistencia(230, 93, true)
            .'<line x1="230" y1="137" x2="230" y2="180" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<line x1="300" y1="50" x2="300" y2="93" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .$resistencia(300, 93, true)
            .'<line x1="36" y1="150" x2="64" y2="150" stroke="'.self::TINTA.'" stroke-width="3"/>'
            .'<line x1="42" y1="160" x2="58" y2="160" stroke="'.self::TINTA.'" stroke-width="3"/>'
            .'<line x1="50" y1="160" x2="50" y2="170" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .self::texto(4, 160, $voltaje.' V', 'font-weight="bold" fill="'.self::ACENTO.'"')
            .self::texto(150, 36, 'R₁ = '.$r1.' Ω', 'font-weight="bold"')
            .self::texto(150, 120, 'R₂ = '.$r2.' Ω', 'font-weight="bold" font-size="13"')
            .self::texto(312, 120, 'R₃ = '.$r3.' Ω', 'font-weight="bold" font-size="13"');

        return self::lienzo(390, 200, $c);
    }

    /** Bloque sobre un plano inclinado liso con su ángulo. */
    public static function planoInclinado(int $angulo, int $masa): string
    {
        $c = '<polygon points="40,190 320,190 320,40" fill="'.self::SUAVE.'" stroke="'.self::TINTA.'" stroke-width="2"/>'
            .'<g transform="rotate(-28.2 200 118)"><rect x="170" y="88" width="56" height="34" fill="#ffffff" stroke="'.self::ACENTO.'" stroke-width="2.5"/>'
            .self::texto(178, 111, $masa.' kg', 'font-weight="bold" font-size="14"').'</g>'
            .'<path d="M 100 190 A 60 60 0 0 0 95 162" fill="none" stroke="'.self::ACENTO.'" stroke-width="2"/>'
            .self::texto(106, 180, $angulo.'°', 'fill="'.self::ACENTO.'" font-weight="bold"');

        return self::lienzo(360, 215, $c);
    }

    /** Gráfico de barras con etiquetas y valores. */
    public static function barras(string $titulo, array $datos, string $unidad = ''): string
    {
        $max = max($datos);
        $ancho = 260 / count($datos);
        $c = self::texto(20, 26, $titulo, 'font-weight="bold"')
            .'<line x1="50" y1="200" x2="335" y2="200" stroke="'.self::TINTA.'" stroke-width="2"/>';
        $i = 0;
        foreach ($datos as $etiqueta => $valor) {
            $alto = $valor * 140 / $max;
            $x = 62 + $i * $ancho;
            $c .= '<rect x="'.round($x, 1).'" y="'.round(200 - $alto, 1).'" width="'.round($ancho * .6, 1).'" height="'.round($alto, 1).'" fill="'.($i % 2 ? self::ACENTO : '#D4A017').'"/>'
                .self::texto($x + 2, 192 - $alto, $valor.$unidad, 'font-size="13" font-weight="bold"')
                .self::texto($x + 2, 218, (string) $etiqueta, 'font-size="13"');
            $i++;
        }

        return self::lienzo(360, 230, $c);
    }

    /** Recta en el plano cartesiano que pasa por dos puntos rotulados. */
    public static function recta(array $p, array $q): string
    {
        $esc = 18;
        $ox = 180;
        $oy = 125;
        $px = fn ($v) => $ox + $v * $esc;
        $py = fn ($v) => $oy - $v * $esc;
        $m = ($q[1] - $p[1]) / ($q[0] - $p[0]);
        $x1 = -9;
        $x2 = 9;
        $c = '<line x1="10" y1="'.$oy.'" x2="350" y2="'.$oy.'" stroke="#9a8a63" stroke-width="1.5"/>'
            .'<line x1="'.$ox.'" y1="10" x2="'.$ox.'" y2="240" stroke="#9a8a63" stroke-width="1.5"/>'
            .self::texto(340, $oy - 6, 'x').self::texto($ox + 6, 20, 'y')
            .'<line x1="'.$px($x1).'" y1="'.round($py($p[1] + $m * ($x1 - $p[0])), 1).'" x2="'.$px($x2).'" y2="'.round($py($p[1] + $m * ($x2 - $p[0])), 1).'" stroke="'.self::ACENTO.'" stroke-width="2.5"/>';
        foreach ([$p, $q] as $punto) {
            $c .= '<circle cx="'.$px($punto[0]).'" cy="'.$py($punto[1]).'" r="4.5" fill="'.self::TINTA.'"/>'
                .self::texto($px($punto[0]) + 7, $py($punto[1]) - 7, '('.$punto[0].'; '.$punto[1].')', 'font-size="13" font-weight="bold"');
        }

        return self::lienzo(360, 250, '<clipPath id="c"><rect width="360" height="250"/></clipPath><g clip-path="url(#c)">'.$c.'</g>');
    }

    /** Gráfico circular con porcentajes por sector. */
    public static function sectores(string $titulo, array $porcentajes): string
    {
        $colores = ['#D4A017', '#684A13', '#E8D9B5', '#B8860B', '#34805A'];
        $cx = 120;
        $cy = 125;
        $r = 85;
        $inicio = -90.0;
        $c = self::texto(16, 24, $titulo, 'font-weight="bold"');
        $i = 0;
        foreach ($porcentajes as $etiqueta => $valor) {
            $fin = $inicio + 360 * $valor / 100;
            $x1 = $cx + $r * cos(deg2rad($inicio));
            $y1 = $cy + $r * sin(deg2rad($inicio));
            $x2 = $cx + $r * cos(deg2rad($fin));
            $y2 = $cy + $r * sin(deg2rad($fin));
            $c .= '<path d="M '.$cx.' '.$cy.' L '.round($x1, 1).' '.round($y1, 1).' A '.$r.' '.$r.' 0 '.($valor > 50 ? 1 : 0).' 1 '.round($x2, 1).' '.round($y2, 1).' Z" fill="'.$colores[$i % 5].'" stroke="#fff" stroke-width="2"/>'
                .'<rect x="230" y="'.(60 + $i * 26).'" width="14" height="14" fill="'.$colores[$i % 5].'"/>'
                .self::texto(250, 72 + $i * 26, $etiqueta.' ('.$valor.'%)', 'font-size="14"');
            $inicio = $fin;
            $i++;
        }

        return self::lienzo(380, 230, $c);
    }
}
