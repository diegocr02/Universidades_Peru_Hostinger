<?php

namespace Database\Seeders\Unmsm;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Preguntas numéricas de nivel San Marcos con datos y respuesta calculados (no escritos a mano).
 * Determinista: la misma semilla produce siempre las mismas preguntas, así el seeder no duplica.
 *
 * Cada pregunta: codigo, asignatura, enunciado, opciones (la correcta en la posición 0),
 * solucion, nivel y svg (opcional, la figura de la pregunta).
 */
final class GeneradorPreguntas
{
    public const POR_ASIGNATURA = 16;

    /** Asignatura del banco => prefijo del código. */
    public const ASIGNATURAS = [
        'Aritmética' => 'ARI',
        'Álgebra' => 'ALG',
        'Geometría' => 'GEO',
        'Trigonometría' => 'TRI',
        'Física' => 'FIS',
        'Habilidad Matemática' => 'HLM',
    ];

    private Randomizer $azar;

    /** @return list<array<string, mixed>> */
    public function todas(): array
    {
        $preguntas = [];
        foreach (self::ASIGNATURAS as $asignatura => $prefijo) {
            for ($i = 1; $i <= self::POR_ASIGNATURA; $i++) {
                $this->azar = new Randomizer(new Mt19937(crc32($prefijo.'-'.$i)));
                $metodo = match ($prefijo) {
                    'ARI' => 'aritmetica', 'ALG' => 'algebra', 'GEO' => 'geometria',
                    'TRI' => 'trigonometria', 'FIS' => 'fisica', 'HLM' => 'habilidad',
                };
                $pregunta = $this->{$metodo}(($i - 1) % 4, $i);
                $preguntas[] = $pregunta + [
                    'codigo' => sprintf('SM-%s-%02d', $prefijo, $i),
                    'asignatura' => $asignatura,
                    'nivel' => $i % 3 === 0 ? 'avanzado' : 'intermedio',
                    'svg' => null,
                ];
            }
        }

        return $preguntas;
    }

    // ───────────────────────── Aritmética ─────────────────────────

    private function aritmetica(int $tipo, int $i): array
    {
        switch ($tipo) {
            case 0:
                $g = $this->uno([2, 3, 4, 5, 6]);
                [$m, $n] = $this->uno([[2, 3], [3, 4], [2, 5], [3, 5], [4, 5], [5, 6], [3, 7]]);
                $a = $g * $m;
                $b = $g * $n;
                $mcm = $g * $m * $n;

                return [
                    'enunciado' => "Dos faros destellan cada {$a} s y cada {$b} s, respectivamente. Si a las 20:00 h destellan juntos, ¿después de cuántos segundos volverán a destellar juntos por primera vez?",
                    'opciones' => $this->opciones($mcm, [$a * $b, $g, $a + $b, 2 * $mcm], fn ($v) => $v.' s'),
                    'solucion' => "Coinciden en un múltiplo común de {$a} y {$b}; la primera vez es el MCM. MCD = {$g}, MCM = {$a}·{$b}/{$g} = {$mcm} s.",
                ];
            case 1:
                $precio = $this->uno([400, 500, 600, 800, 1000, 1200]);
                $d1 = $this->uno([10, 20, 25]);
                $d2 = $this->uno([10, 20]);
                $paga = $precio * (100 - $d1) * (100 - $d2) / 10000;
                $unico = 100 - (100 - $d1) * (100 - $d2) / 100;

                return [
                    'enunciado' => "Un televisor cuesta S/ {$precio}. Se le aplican dos descuentos sucesivos del {$d1} % y del {$d2} %. ¿Cuánto se paga finalmente?",
                    'opciones' => $this->opciones($paga, [$precio * (100 - $d1 - $d2) / 100, $precio * (100 - $d1) / 100, $precio - $paga, $paga + $precio * 5 / 100], fn ($v) => 'S/ '.$this->num($v)),
                    'solucion' => "Cada descuento se aplica sobre el nuevo precio: {$precio} × ".(100 - $d1).'% × '.(100 - $d2).'% = S/ '.$this->num($paga).". Equivale a un descuento único de ".$this->num($unico).' %.',
                ];
            case 2:
                $n = $this->uno([6, 8, 10, 12, 15]);
                $dias = $this->uno([12, 18, 20, 24, 30]);
                $m = $this->uno(array_values(array_filter([4, 5, 9, 16, 18, 20, 24, 30], fn ($x) => $x !== $n && ($n * $dias) % $x === 0)) ?: [$n * 2]);
                $resp = $n * $dias / $m;

                return [
                    'enunciado' => "{$n} obreros construyen un muro en {$dias} días trabajando al mismo ritmo. ¿En cuántos días construirían el mismo muro {$m} obreros?",
                    'opciones' => $this->opciones($resp, [$dias * $m / $n, $dias + $n - $m, $resp + 2, $resp * 2], fn ($v) => $this->num($v).' días'),
                    'solucion' => "Obreros y días son inversamente proporcionales: {$n} × {$dias} = {$m} × x, de donde x = ".$this->num($resp).' días.',
                ];
            default:
                $total = $this->uno([200, 300, 400, 500, 600, 800]);
                $reparto = $this->uno([
                    ['Fútbol' => 40, 'Vóley' => 25, 'Básquet' => 20, 'Natación' => 15],
                    ['Fútbol' => 35, 'Vóley' => 30, 'Básquet' => 20, 'Natación' => 15],
                    ['Fútbol' => 45, 'Vóley' => 20, 'Básquet' => 25, 'Natación' => 10],
                ]);
                $dif = $total * ($reparto['Fútbol'] - $reparto['Básquet']) / 100;

                return [
                    'enunciado' => "El gráfico muestra el deporte preferido de {$total} estudiantes. ¿Cuántos estudiantes más prefieren fútbol que básquet?",
                    'opciones' => $this->opciones($dif, [$reparto['Fútbol'] - $reparto['Básquet'], $total * $reparto['Fútbol'] / 100, $total * $reparto['Básquet'] / 100, $dif + 10]),
                    'solucion' => 'Fútbol: '.$reparto['Fútbol'].' % de '.$total.' = '.$this->num($total * $reparto['Fútbol'] / 100).'; básquet: '.$reparto['Básquet'].' % = '.$this->num($total * $reparto['Básquet'] / 100).'. Diferencia: '.$this->num($dif).'.',
                    'svg' => FigurasSvg::sectores('Deporte preferido', $reparto),
                ];
        }
    }

    // ───────────────────────── Álgebra ─────────────────────────

    private function algebra(int $tipo, int $i): array
    {
        switch ($tipo) {
            case 0:
                do {
                    $r = $this->azar->getInt(-6, 7);
                    $s = $this->azar->getInt(-6, 7);
                } while ($r === 0 || $s === 0 || $r === $s);
                $suma = $r + $s;
                $producto = $r * $s;
                $resp = $suma * $suma - 2 * $producto;

                return [
                    'enunciado' => 'Si m y n son las raíces de la ecuación '.$this->cuadratica(-$suma, $producto).', halle el valor de m² + n².',
                    'opciones' => $this->opciones($resp, [$suma * $suma, $suma * $suma + 2 * $producto, $suma * $suma - $producto, 2 * $producto]),
                    'solucion' => "Por Cardano: m + n = {$suma} y m·n = {$producto}. Luego m² + n² = (m + n)² − 2mn = ".($suma * $suma).' − '.(2 * $producto)." = {$resp}.",
                ];
            case 1:
                $x = $this->azar->getInt(1, 9);
                $y = $this->azar->getInt(1, 9);
                do {
                    [$a1, $b1, $a2, $b2] = [$this->azar->getInt(1, 5), $this->azar->getInt(1, 5), $this->azar->getInt(1, 5), $this->azar->getInt(-4, -1)];
                } while ($a1 * $b2 - $a2 * $b1 === 0);
                $c1 = $a1 * $x + $b1 * $y;
                $c2 = $a2 * $x + $b2 * $y;

                return [
                    'enunciado' => "Resuelva el sistema:\n".$this->lineal($a1, $b1, $c1)."\n".$this->lineal($a2, $b2, $c2)."\nDé como respuesta el valor de x·y.",
                    'opciones' => $this->opciones($x * $y, [$x + $y, $x * $y + $x, abs($x - $y) + $x * $y + 1, ($x + 1) * $y]),
                    'solucion' => "Por reducción se obtiene x = {$x} e y = {$y}; por tanto x·y = ".($x * $y).'.',
                ];
            case 2:
                $base = $this->uno([2, 3]);
                $x = $this->azar->getInt(2, 5);
                $valor = ($base + 1) * $base ** $x;
                $sup = ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵'];

                return [
                    'enunciado' => "Halle x en la ecuación exponencial: {$base}ˣ⁺¹ + {$base}ˣ = {$valor}.",
                    'opciones' => $this->opciones($x, [$x + 1, $x - 1, $x + 2, 2 * $x]),
                    'solucion' => "Se factoriza {$base}ˣ: {$base}ˣ({$base} + 1) = {$valor} ⇒ {$base}ˣ = ".($valor / ($base + 1))." = {$base}".strtr((string) $x, $sup)." ⇒ x = {$x}.",
                ];
            default:
                do {
                    $m = $this->uno([-2, -1, 1, 2, 3]);
                    $b = $this->azar->getInt(-3, 3);
                    $x1 = $this->azar->getInt(-3, -1);
                    $x2 = $this->azar->getInt(1, 3);
                    $y1 = $m * $x1 + $b;
                    $y2 = $m * $x2 + $b;
                } while (abs($y1) > 6 || abs($y2) > 6);
                $k = $this->uno([4, 5, 6, -4]);
                $resp = $m * $k + $b;

                return [
                    'enunciado' => "La recta L pasa por los puntos ({$x1}; {$y1}) y ({$x2}; {$y2}), como muestra la figura. Si L es la gráfica de f(x) = ax + b, halle f({$k}).",
                    'opciones' => $this->opciones($resp, [$m * $k - $b, $m + $b + $k, -$m * $k + $b, $resp + $m]),
                    'solucion' => "Pendiente a = ({$y2} − ({$y1}))/({$x2} − ({$x1})) = {$m}; b = {$b}. Luego f({$k}) = {$m}·({$k}) + ({$b}) = {$resp}.",
                    'svg' => FigurasSvg::recta([$x1, $y1], [$x2, $y2]),
                ];
        }
    }

    // ───────────────────────── Geometría ─────────────────────────

    private function geometria(int $tipo, int $i): array
    {
        switch ($tipo) {
            case 0:
                $a = $this->uno([40, 45, 50, 55, 60, 65, 70]);
                $b = $this->uno([45, 50, 55, 60, 65, 75]);
                $x = 180 - $a - $b;

                return [
                    'enunciado' => 'En el triángulo ABC de la figura, halle la medida del ángulo x.',
                    'opciones' => $this->opciones($x, [$a + $b, 180 - $a, $x + 10, 90 - $x / 2 + 30], fn ($v) => $this->num($v).'°'),
                    'solucion' => "La suma de los ángulos interiores es 180°: x = 180° − {$a}° − {$b}° = {$x}°.",
                    'svg' => FigurasSvg::trianguloAngulos($a, $b),
                ];
            case 1:
                $central = $this->uno([80, 100, 120, 140, 160]);
                $x = $central / 2;

                return [
                    'enunciado' => 'En la circunferencia de centro O, el ángulo central AOB mide '.$central.'°. Halle la medida del ángulo inscrito x = ∠APB.',
                    'opciones' => $this->opciones($x, [$central, 180 - $central, 180 - $x, $x + 20], fn ($v) => $this->num($v).'°'),
                    'solucion' => "Un ángulo inscrito mide la mitad del ángulo central que subtiende el mismo arco: x = {$central}°/2 = {$x}°.",
                    'svg' => FigurasSvg::anguloInscrito($central),
                ];
            case 2:
                [$p, $q, $r] = $this->uno([[3, 4, 5], [5, 12, 13], [8, 15, 17], [6, 8, 10], [9, 12, 15], [12, 16, 20], [7, 24, 25]]);

                return [
                    'enunciado' => "Un terreno rectangular mide {$q} m de largo y {$p} m de ancho. ¿Cuánto mide su diagonal d?",
                    'opciones' => $this->opciones($r, [$p + $q, $r + 2, $q + 1, 2 * $r - $p], fn ($v) => $this->num($v).' m'),
                    'solucion' => "Por Pitágoras: d² = {$q}² + {$p}² = ".($q * $q + $p * $p)." ⇒ d = {$r} m.",
                    'svg' => FigurasSvg::rectanguloDiagonal($q, $p),
                ];
            default:
                $mayor = $this->uno([12, 14, 16, 18, 20]);
                $menor = $this->uno([4, 6, 8, 10]);
                $h = $this->uno([4, 5, 6, 7, 8]);
                $area = ($mayor + $menor) * $h / 2;

                return [
                    'enunciado' => 'Halle el área del trapecio de la figura.',
                    'opciones' => $this->opciones($area, [($mayor + $menor) * $h, $mayor * $h, $menor * $h, ($mayor - $menor) * $h / 2], fn ($v) => $this->num($v).' cm²'),
                    'solucion' => "A = (B + b)·h/2 = ({$mayor} + {$menor})·{$h}/2 = ".$this->num($area).' cm².',
                    'svg' => FigurasSvg::trapecio($mayor, $menor, $h),
                ];
        }
    }

    // ───────────────────────── Trigonometría ─────────────────────────

    private function trigonometria(int $tipo, int $i): array
    {
        switch ($tipo) {
            case 0:
                [$a, $b, $c] = $this->uno([[3, 4, 5], [5, 12, 13], [8, 15, 17], [7, 24, 25], [6, 8, 10], [20, 21, 29]]);
                $resp = $this->fraccion($a + $b, $c);

                return [
                    'enunciado' => 'En el triángulo rectángulo de la figura, recto en C, halle sen θ + cos θ.',
                    'opciones' => $this->opciones($resp, [$this->fraccion($a, $c), $this->fraccion($b, $c), $this->fraccion($c, $a + $b), $this->fraccion($a + $b, $b)]),
                    'solucion' => "Hipotenusa: √({$a}² + {$b}²) = {$c}. sen θ = {$a}/{$c} y cos θ = {$b}/{$c}; la suma es {$resp}.",
                    'svg' => FigurasSvg::trianguloRectangulo((string) $a, (string) $b, 'x'),
                ];
            case 1:
                $grados = $this->uno([30, 45, 60, 120, 135, 150, 210, 240, 300, 315]);
                $resp = $this->radianes($grados);

                return [
                    'enunciado' => "Exprese {$grados}° en radianes.",
                    'opciones' => $this->opciones($resp, [$this->radianes(180 - $grados % 180 ?: 90), $this->radianes($grados / 2), $this->radianes(($grados + 30) % 360 ?: 30), $this->radianes(360 - $grados)]),
                    'solucion' => "Se multiplica por π/180: {$grados}° × π/180 = {$resp} rad.",
                ];
            case 2:
                $a = $this->uno([5, 10, 15, 20, 25]);
                $b = $this->uno([5, 10, 15, 20, 25, 30]);
                $resp = (3 * $a + 4 * $b) / 5;

                return [
                    'enunciado' => "Calcule E = {$a}·sen 37° + {$b}·cos 37°.",
                    'opciones' => $this->opciones($resp, [(4 * $a + 3 * $b) / 5, 3 * ($a + $b) / 5, 4 * ($a + $b) / 5, $resp + 1]),
                    'solucion' => "En el triángulo notable de 37° y 53°: sen 37° = 3/5 y cos 37° = 4/5. E = {$a}(3/5) + {$b}(4/5) = ".$this->num($resp).'.',
                ];
            default:
                $angulo = $this->uno([37, 53]);
                $d = $angulo === 37 ? $this->uno([12, 16, 20, 24, 28, 32]) : $this->uno([12, 15, 18, 21, 24, 30]);
                $h = $angulo === 37 ? $d * 3 / 4 : $d * 4 / 3;

                return [
                    'enunciado' => "Desde un punto del suelo situado a {$d} m de la base de una torre, se observa su parte más alta con un ángulo de elevación de {$angulo}°. Halle la altura h de la torre.",
                    'opciones' => $this->opciones($h, [$angulo === 37 ? $d * 4 / 3 : $d * 3 / 4, $d * 3 / 5, $d * 4 / 5, $h + 2], fn ($v) => $this->num($v).' m'),
                    'solucion' => "tan {$angulo}° = h/{$d}. Como tan {$angulo}° = ".($angulo === 37 ? '3/4' : '4/3').", h = ".$this->num($h).' m.',
                    'svg' => FigurasSvg::anguloElevacion($d, $angulo),
                ];
        }
    }

    // ───────────────────────── Física ─────────────────────────

    private function fisica(int $tipo, int $i): array
    {
        switch ($tipo) {
            case 0:
                $v0 = $this->azar->getInt(2, 10);
                $a = $this->uno([2, 4, 6]);
                $t = $this->azar->getInt(2, 6);
                $d = $v0 * $t + $a * $t * $t / 2;

                return [
                    'enunciado' => "Un móvil parte con {$v0} m/s y acelera uniformemente a razón de {$a} m/s². ¿Qué distancia recorre en los primeros {$t} s?",
                    'opciones' => $this->opciones($d, [$v0 * $t + $a * $t * $t, $v0 * $t, $a * $t * $t / 2, $v0 + $a * $t], fn ($v) => $this->num($v).' m'),
                    'solucion' => "MRUV: d = v₀t + at²/2 = {$v0}·{$t} + {$a}·{$t}²/2 = ".$this->num($d).' m.',
                ];
            case 1:
                $t1 = $this->uno([2, 4]);
                $t2 = $t1 + $this->uno([2, 4, 6]);
                $t3 = $t2 + $this->uno([2, 4]);
                $v = $this->uno([6, 8, 10, 12]);
                $d = $v * $t1 / 2 + $v * ($t2 - $t1) + $v * ($t3 - $t2) / 2;

                return [
                    'enunciado' => 'La gráfica muestra la velocidad de un auto en línea recta. ¿Qué distancia recorre entre t = 0 y t = '.$t3.' s?',
                    'opciones' => $this->opciones($d, [$v * $t3, $v * $t3 / 2, $v * ($t2 - $t1), $d + $v], fn ($x) => $this->num($x).' m'),
                    'solucion' => 'La distancia es el área bajo la gráfica v–t: '.$this->num($v * $t1 / 2).' + '.$this->num($v * ($t2 - $t1)).' + '.$this->num($v * ($t3 - $t2) / 2).' = '.$this->num($d).' m.',
                    'svg' => FigurasSvg::graficoVelocidadTiempo([[0, 0], [$t1, $v], [$t2, $v], [$t3, 0]]),
                ];
            case 2:
                [$r2, $r3, $par] = $this->uno([[3, 6, 2], [4, 12, 3], [6, 12, 4], [10, 15, 6], [6, 6, 3], [8, 8, 4]]);
                $r1 = $this->azar->getInt(2, 8);
                $corriente = $this->azar->getInt(1, 4);
                $voltaje = $corriente * ($r1 + $par);

                return [
                    'enunciado' => 'En el circuito de la figura, halle la intensidad de corriente que entrega la fuente.',
                    'opciones' => $this->opciones($corriente, [$voltaje / ($r1 + $r2 + $r3), $voltaje / $r1, 2 * $corriente, $voltaje / $par], fn ($v) => $this->num($v).' A'),
                    'solucion' => "R₂ ∥ R₃ = {$r2}·{$r3}/({$r2} + {$r3}) = {$par} Ω. Req = {$r1} + {$par} = ".($r1 + $par)." Ω. I = V/Req = {$voltaje}/".($r1 + $par)." = {$corriente} A.",
                    'svg' => FigurasSvg::circuitoMixto($voltaje, $r1, $r2, $r3),
                ];
            default:
                if ($i % 2 === 0) {
                    $angulo = $this->uno([37, 53]);
                    $masa = $this->uno([2, 4, 5, 10]);
                    $a = $angulo === 37 ? 6 : 8;

                    return [
                        'enunciado' => "Un bloque de {$masa} kg se suelta sobre el plano inclinado liso de la figura. Halle el módulo de su aceleración (g = 10 m/s²).",
                        'opciones' => $this->opciones($a, [10, $angulo === 37 ? 8 : 6, $a * $masa / 10 + 1, 5], fn ($v) => $this->num($v).' m/s²'),
                        'solucion' => "Sin rozamiento, la fuerza neta es mg·sen θ, así que a = g·sen {$angulo}° = 10 × ".($angulo === 37 ? '3/5' : '4/5')." = {$a} m/s². No depende de la masa.",
                        'svg' => FigurasSvg::planoInclinado($angulo, $masa),
                    ];
                }
                $h = $this->uno([5, 20, 45, 80, 125]);
                $v = (int) sqrt(20 * $h);

                return [
                    'enunciado' => "Un cuerpo se suelta desde {$h} m de altura. Despreciando la resistencia del aire y con g = 10 m/s², ¿con qué rapidez llega al suelo?",
                    'opciones' => $this->opciones($v, [2 * $v, $v / 2, $v + 10, 2 * $h], fn ($x) => $this->num($x).' m/s'),
                    'solucion' => "Por conservación de la energía: mgh = mv²/2 ⇒ v = √(2gh) = √(2·10·{$h}) = {$v} m/s.",
                ];
        }
    }

    // ───────────────────── Habilidad Lógico-Matemática ─────────────────────

    private function habilidad(int $tipo, int $i): array
    {
        switch ($tipo) {
            case 0:
                $s = $this->azar->getInt(1, 9);
                $d = $this->azar->getInt(2, 5);
                $k = $this->azar->getInt(1, 3);
                $terminos = [$s];
                for ($n = 1; $n <= 5; $n++) {
                    $terminos[] = $terminos[$n - 1] + $d + ($n - 1) * $k;
                }
                $siguiente = array_pop($terminos);

                return [
                    'enunciado' => '¿Qué número continúa en la sucesión? '.implode(' ; ', $terminos).' ; …',
                    'opciones' => $this->opciones($siguiente, [$siguiente + $k, $siguiente - $k, end($terminos) + $d, $siguiente + 1]),
                    'solucion' => 'Las diferencias sucesivas aumentan de '.$k.' en '.$k.': '.implode(', ', array_map(fn ($n) => $d + $n * $k, range(0, 4))).'. El término siguiente es '.$siguiente.'.',
                ];
            case 1:
                $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May'];
                $valores = array_map(fn () => $this->azar->getInt(3, 12) * 10, $meses);
                $resto = array_sum($valores) % 50;
                $valores[4] += $resto === 0 ? 0 : 50 - $resto;
                $promedio = array_sum($valores) / 5;
                $datos = array_combine($meses, $valores);

                return [
                    'enunciado' => 'El gráfico muestra las ventas (en miles de soles) de una tienda. ¿Cuál fue el promedio mensual de ventas?',
                    'opciones' => $this->opciones($promedio, [max($valores) / 2 + 20, $promedio + 10, $promedio - 10, array_sum($valores) / 4]),
                    'solucion' => 'Promedio = ('.implode(' + ', $valores).')/5 = '.array_sum($valores).'/5 = '.$this->num($promedio).' mil soles.',
                    'svg' => FigurasSvg::barras('Ventas mensuales (miles de S/)', $datos),
                ];
            case 2:
                $b = $this->azar->getInt(10, 20);
                $hace = $this->azar->getInt(2, 6);
                $dentro = $this->azar->getInt(3, 8);
                $ana = 2 * ($b - $hace) + $hace;

                return [
                    'enunciado' => "Hace {$hace} años, la edad de Ana era el doble de la edad que tenía Bruno. Si Bruno tiene hoy {$b} años, ¿qué edad tendrá Ana dentro de {$dentro} años?",
                    'opciones' => $this->opciones($ana + $dentro, [2 * $b + $dentro, $ana, 2 * ($b - $hace) + $dentro, $ana + $dentro + $hace], fn ($v) => $v.' años'),
                    'solucion' => 'Hace '.$hace.' años, Bruno tenía '.($b - $hace).' y Ana '.(2 * ($b - $hace)).". Hoy Ana tiene {$ana} y dentro de {$dentro} años tendrá ".($ana + $dentro).'.',
                ];
            default:
                $p = $this->uno([2, 3]);
                $q = $this->uno([1, 2]);
                $op = fn ($a, $b) => $p * $a - $q * $b;
                [$x, $y, $z] = [$this->azar->getInt(1, 6), $this->azar->getInt(1, 6), $this->azar->getInt(1, 6)];
                $resp = $op($op($x, $y), $z);
                $primero = $op($x, $y) < 0 ? '('.$op($x, $y).')' : (string) $op($x, $y);

                return [
                    'enunciado' => "Se define a ∗ b = {$p}a − ".($q === 1 ? '' : $q)."b. Halle ({$x} ∗ {$y}) ∗ {$z}.",
                    'opciones' => $this->opciones($resp, [$op($x, $op($y, $z)), $op($x, $y) + $z, $resp + $p, $op($x, $y)]),
                    'solucion' => "{$x} ∗ {$y} = {$p}·{$x} − {$q}·{$y} = ".$op($x, $y).'. Luego '.$op($x, $y)." ∗ {$z} = {$p}·{$primero} − {$q}·{$z} = {$resp}.",
                ];
        }
    }

    // ───────────────────────── Utilidades ─────────────────────────

    private function uno(array $lista): mixed
    {
        return $lista[$this->azar->getInt(0, count($lista) - 1)];
    }

    /**
     * Cinco opciones distintas, la correcta primero. Si un distractor coincide con otro,
     * se completa con valores cercanos a la respuesta.
     */
    private function opciones(int|float|string $correcta, array $distractores, ?callable $formato = null): array
    {
        $formato ??= fn ($v) => is_string($v) ? $v : $this->num($v);
        $opciones = [$formato($correcta)];
        foreach ($distractores as $d) {
            $texto = $formato($d);
            if (! in_array($texto, $opciones, true) && count($opciones) < 5) {
                $opciones[] = $texto;
            }
        }
        $paso = 1;
        while (count($opciones) < 5) {
            $base = is_numeric($correcta) ? $correcta : 0;
            foreach ([$base + $paso, $base - $paso] as $candidato) {
                $texto = $formato($candidato);
                if (! in_array($texto, $opciones, true) && count($opciones) < 5) {
                    $opciones[] = $texto;
                }
            }
            $paso++;
        }

        return $opciones;
    }

    /** Número con coma decimal y hasta dos decimales (estilo escolar peruano). */
    private function num(int|float $valor): string
    {
        if (abs($valor - round($valor)) < 1e-9) {
            return (string) (int) round($valor);
        }

        return str_replace('.', ',', rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.'));
    }

    private function fraccion(int $num, int $den): string
    {
        $g = $this->mcd(abs($num), abs($den));

        return $den / $g === 1 ? (string) ($num / $g) : ($num / $g).'/'.($den / $g);
    }

    private function radianes(int|float $grados): string
    {
        $grados = (int) $grados;
        $g = $this->mcd($grados, 180);
        $num = $grados / $g;
        $den = 180 / $g;

        return ($num === 1 ? '' : $num).'π'.($den === 1 ? '' : '/'.$den);
    }

    private function mcd(int $a, int $b): int
    {
        return $b === 0 ? max(1, $a) : $this->mcd($b, $a % $b);
    }

    private function cuadratica(int $b, int $c): string
    {
        $termino = fn (int $v, string $x) => ($v < 0 ? ' − ' : ' + ').(abs($v) === 1 && $x !== '' ? '' : abs($v)).$x;

        return 'x²'.($b === 0 ? '' : $termino($b, 'x')).($c === 0 ? '' : $termino($c, '')).' = 0';
    }

    private function lineal(int $a, int $b, int $c): string
    {
        $coef = fn (int $v) => abs($v) === 1 ? '' : (string) abs($v);

        return ($a < 0 ? '−' : '').$coef($a).'x '.($b < 0 ? '−' : '+').' '.$coef($b).'y = '.$c;
    }
}
