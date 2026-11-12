<?php

namespace App\Console\Commands;

use App\Support\PerfilExamenUndc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Diagnóstico interactivo del servidor del Simulador CNI, de SOLO LECTURA.
 * Agrupa las comprobaciones por atributo de calidad (ATAM) y termina con una nota global.
 *
 *   php artisan cni:diagnostico            → menú interactivo
 *   php artisan cni:diagnostico --todo     → todas las secciones sin preguntar
 */
class DiagnosticoCni extends Command
{
    protected $signature = 'cni:diagnostico {--todo : Ejecuta todas las secciones sin mostrar el menú}';

    protected $description = 'Diagnóstico interactivo (solo lectura) del servidor: disponibilidad, seguridad, integridad, exámenes, rendimiento y operación';

    private const SECCIONES = [
        'disponibilidad' => 'Disponibilidad · ¿el sitio y la base responden?',
        'seguridad' => 'Seguridad · configuración de producción',
        'integridad' => 'Integridad de datos · bancos, claves e imágenes',
        'examenes' => 'Funcionamiento · exámenes de cada universidad',
        'rendimiento' => 'Rendimiento · tiempos de respuesta',
        'operacion' => 'Operación · disco, permisos, errores y cron',
    ];

    /** Bancos por universidad: prefijo de código y archivo JSON de referencia. */
    private const BANCOS = [
        'UNDC' => ['UNDC-%', 'undc_preguntas_practica.json'],
        'UNI' => ['UNI_%', 'uni_preguntas.json'],
        'San Marcos' => ['SM-%', 'unmsm_preguntas.json'],
        'Villarreal (UNFV)' => ['UNFV-PRACTICA%', 'unfv_preguntas_practica.json'],
    ];

    /** @var array<string, array{ok:int, aviso:int, falla:int}> */
    private array $puntaje = [];

    private string $seccion = '';

    public function handle(): int
    {
        $this->portada();

        $elegidas = array_keys(self::SECCIONES);
        if (! $this->option('todo') && $this->input->isInteractive()) {
            $opciones = ['todo' => 'Diagnóstico completo (recomendado)'] + self::SECCIONES;
            $eleccion = $this->choice('¿Qué deseas revisar?', array_values($opciones), 0);
            $clave = array_search($eleccion, $opciones, true);
            $elegidas = $clave === 'todo' ? array_keys(self::SECCIONES) : [$clave];
        }

        foreach ($elegidas as $clave) {
            $this->seccion = self::SECCIONES[$clave];
            $this->puntaje[$this->seccion] = ['ok' => 0, 'aviso' => 0, 'falla' => 0];
            $this->newLine();
            $this->line('  <fg=cyan;options=bold>▌ '.mb_strtoupper($this->seccion).'</>');
            try {
                $this->{$clave}();
            } catch (Throwable $e) {
                $this->falla('La sección no pudo completarse', $e->getMessage());
            }
        }

        return $this->resumen();
    }

    // ------------------------------------------------------------------ secciones

    private function disponibilidad(): void
    {
        $url = rtrim((string) config('app.url'), '/');
        foreach (['/up' => 'Salud de Laravel (/up)', '/login' => 'Página de inicio de sesión'] as $ruta => $nombre) {
            [$codigo, $ms] = $this->http($url.$ruta);
            $codigo === 200
                ? $this->ok($nombre, "HTTP 200 · {$ms} ms")
                : $this->falla($nombre, $codigo ? "HTTP {$codigo}" : 'sin respuesta');
        }

        $inicio = microtime(true);
        DB::select('select 1');
        $ms = (int) round((microtime(true) - $inicio) * 1000);
        $this->ok('Conexión a la base de datos', DB::connection()->getDriverName()." · {$ms} ms");

        $mantenimiento = app()->isDownForMaintenance();
        $mantenimiento ? $this->falla('Modo mantenimiento', 'ACTIVO: los alumnos no pueden entrar (php artisan up)')
            : $this->ok('Modo mantenimiento', 'apagado');
    }

    private function seguridad(): void
    {
        app()->environment('production')
            ? $this->ok('Entorno', 'production')
            : $this->aviso('Entorno', app()->environment().' (en el hosting debe ser production)');

        config('app.debug')
            ? $this->falla('Modo debug', 'ENCENDIDO: expone errores internos (APP_DEBUG=false)')
            : $this->ok('Modo debug', 'apagado');

        str_starts_with((string) config('app.url'), 'https://')
            ? $this->ok('HTTPS en APP_URL', (string) config('app.url'))
            : $this->aviso('HTTPS en APP_URL', (string) config('app.url'));

        filled(config('app.key')) ? $this->ok('Clave de cifrado (APP_KEY)', 'configurada')
            : $this->falla('Clave de cifrado (APP_KEY)', 'vacía');

        [$codigo] = $this->http(rtrim((string) config('app.url'), '/').'/.env');
        in_array($codigo, [403, 404], true)
            ? $this->ok('Archivo .env protegido', "la web responde {$codigo}")
            : $this->falla('Archivo .env protegido', $codigo ? "la web responde {$codigo}" : 'no se pudo comprobar');

        $demo = DB::table('usuarios')->whereIn('usuario', ['admin_demo', 'docente_demo', 'estudiante_demo'])->count();
        $demo === 0 ? $this->ok('Cuentas demo con contraseña conocida', 'ninguna')
            : $this->aviso('Cuentas demo con contraseña conocida', "{$demo} encontradas: desactivarlas en producción");

        $admins = DB::table('usuarios')->where('rol', 'admin')->where('activo', true)->count();
        $admins > 0 ? $this->ok('Administradores activos', (string) $admins)
            : $this->falla('Administradores activos', 'ninguno');
    }

    private function integridad(): void
    {
        $pendientes = $this->migracionesPendientes();
        $pendientes === 0 ? $this->ok('Migraciones', 'todas aplicadas')
            : $this->falla('Migraciones', "{$pendientes} pendientes (php artisan migrate --force)");

        $areas = DB::table('areas_postulacion')->where('activo', true)->orderBy('codigo')->pluck('codigo')->implode('');
        $areas === 'ABCDE' ? $this->ok('Áreas / universidades A–E', 'completas y activas')
            : $this->falla('Áreas / universidades A–E', "activas: {$areas}");

        foreach (self::BANCOS as $universidad => [$prefijo, $archivo]) {
            $enBase = DB::table('preguntas')->where('codigo', 'like', $prefijo)->count();
            $esperado = $this->preguntasEnJson($archivo);
            if ($esperado !== null && $enBase >= $esperado) {
                $this->ok("Banco {$universidad}", "{$enBase} preguntas (archivo: {$esperado})");
            } elseif ($enBase > 0) {
                $this->aviso("Banco {$universidad}", "{$enBase} de {$esperado} preguntas");
            } else {
                $this->falla("Banco {$universidad}", 'vacío: ejecutar su seeder');
            }
        }

        $incompletas = DB::table('preguntas as p')->where('p.activo', true)
            ->whereRaw('(select count(*) from alternativas a where a.pregunta_id = p.id) < 4')->count();
        $incompletas === 0 ? $this->ok('Preguntas con alternativas completas', 'todas')
            : $this->falla('Preguntas con alternativas completas', "{$incompletas} incompletas");

        $rubrica = Schema::hasColumn('alternativas', 'puntaje_actitudinal')
            ? ' and not exists (select 1 from alternativas r where r.pregunta_id = p.id and r.puntaje_actitudinal is not null)' : '';
        $sinClave = DB::table('preguntas as p')->where('p.activo', true)
            ->whereRaw('not exists (select 1 from alternativas a where a.pregunta_id = p.id and a.es_correcta = 1)'.$rubrica)->count();
        $sinClave === 0 ? $this->ok('Preguntas con respuesta correcta', 'todas (las actitudinales usan rúbrica)')
            : $this->aviso('Preguntas con respuesta correcta', "{$sinClave} sin clave");

        $imagenes = DB::table('preguntas')->whereNotNull('imagen')->where('imagen', '<>', '')->pluck('imagen');
        $faltan = $imagenes->filter(fn (string $ruta) => ! is_file(public_path($ruta))
            && ! is_file(storage_path('app/public/'.preg_replace('#^/?storage/#', '', $ruta))))->count();
        $faltan === 0 ? $this->ok('Imágenes de preguntas', $imagenes->count().' archivos presentes')
            : $this->falla('Imágenes de preguntas', "{$faltan} de {$imagenes->count()} faltan");
    }

    private function examenes(): void
    {
        foreach (['A', 'B'] as $tipo) {
            $nombre = "UNDC - Admisión Ordinaria Tipo {$tipo} (preparación)";
            $s = DB::table('simulacros')->where('nombre', $nombre)->first();
            if (! $s) {
                $this->falla("UNDC · Examen tipo {$tipo}", 'no existe (UndcExamenSeeder)');
                continue;
            }
            $disponibles = DB::table('simulacro_area as sa')->join('temas as t', 't.area_id', '=', 'sa.area_id')
                ->join('preguntas as p', 'p.tema_id', '=', 't.id')->where('sa.simulacro_id', $s->id)
                ->where('p.activo', true)->count();
            $vigente = now()->between($s->fecha_inicio, $s->fecha_fin);
            $s->activo && $vigente && $disponibles >= PerfilExamenUndc::total($tipo)
                ? $this->ok("UNDC · Examen tipo {$tipo}", "activo · {$disponibles} preguntas · vigente hasta ".substr((string) $s->fecha_fin, 0, 10))
                : $this->falla("UNDC · Examen tipo {$tipo}", ($s->activo ? 'activo' : 'inactivo').", {$disponibles} preguntas".($vigente ? '' : ', fuera de fecha'));
        }

        $uni = DB::table('preguntas')->where('codigo', 'like', 'UNI_%')->where('activo', true)->count();
        $uni > 0 ? $this->ok('UNI · Examen en 3 días', "{$uni} preguntas activas") : $this->falla('UNI · Examen en 3 días', 'sin banco');

        $sm = DB::table('preguntas')->where('codigo', 'like', 'SM-%')->where('activo', true)->count();
        $sm > 0 ? $this->ok('San Marcos · Áreas A, C y E', "{$sm} preguntas activas") : $this->falla('San Marcos · Áreas A, C y E', 'sin banco');

        $unfv = DB::table('preguntas')->where('codigo', 'like', 'UNFV-PRACTICA%')->where('activo', true)->count();
        $unfv >= 50 ? $this->ok('Villarreal · Práctica general (50 preguntas)', "{$unfv} preguntas activas")
            : $this->falla('Villarreal · Práctica general (50 preguntas)', "{$unfv} preguntas");
    }

    private function rendimiento(): void
    {
        $url = rtrim((string) config('app.url'), '/');
        $tiempos = [];
        foreach (range(1, 5) as $_) {
            [$codigo, $ms] = $this->http($url.'/login');
            if ($codigo === 200) {
                $tiempos[] = $ms;
            }
        }
        if ($tiempos === []) {
            $this->falla('Respuesta de /login (5 peticiones)', 'sin respuesta');
        } else {
            sort($tiempos);
            $prom = (int) round(array_sum($tiempos) / count($tiempos));
            $detalle = "promedio {$prom} ms · mín {$tiempos[0]} ms · máx ".end($tiempos).' ms';
            $prom < 1000 ? $this->ok('Respuesta de /login (5 peticiones)', $detalle)
                : $this->aviso('Respuesta de /login (5 peticiones)', $detalle.' (meta: < 1 s)');
        }

        $inicio = microtime(true);
        DB::table('intentos as i')->leftJoin('simulacros as s', 's.id', '=', 'i.simulacro_id')
            ->select('i.id', 's.nombre')->orderByDesc('i.id')->limit(50)->get();
        $ms = (int) round((microtime(true) - $inicio) * 1000);
        $ms < 200 ? $this->ok('Consulta típica del historial', "{$ms} ms")
            : $this->aviso('Consulta típica del historial', "{$ms} ms (meta: < 200 ms)");

        app()->configurationIsCached() && app()->routesAreCached()
            ? $this->ok('Cachés de configuración y rutas', 'activas (php artisan optimize)')
            : $this->aviso('Cachés de configuración y rutas', 'no generadas: ejecutar php artisan optimize');
    }

    private function operacion(): void
    {
        $libre = @disk_free_space(base_path());
        $libre === false ? $this->aviso('Espacio libre en disco', 'no disponible')
            : ($libre > 1024 ** 3 ? $this->ok('Espacio libre en disco', $this->tamano($libre))
                : $this->aviso('Espacio libre en disco', $this->tamano($libre).' (menos de 1 GB)'));

        foreach (['storage/logs', 'storage/framework/sessions', 'storage/app/public'] as $carpeta) {
            is_writable(base_path($carpeta)) ? $this->ok("Permisos de escritura: {$carpeta}", 'correcto')
                : $this->falla("Permisos de escritura: {$carpeta}", 'sin permiso de escritura');
        }

        is_link(public_path('storage')) || is_dir(public_path('storage'))
            ? $this->ok('Enlace de imágenes (storage)', 'presente')
            : $this->aviso('Enlace de imágenes (storage)', 'falta: php artisan storage:link');

        $errores = $this->erroresRecientes();
        $errores === null ? $this->ok('Errores en las últimas 24 h', 'sin archivo de log')
            : ($errores === 0 ? $this->ok('Errores en las últimas 24 h', 'ninguno')
                : $this->aviso('Errores en las últimas 24 h', "{$errores} (revisar storage/logs/laravel.log)"));

        $vencidos = DB::table('intentos')->where('estado', 'en_curso')
            ->whereNotNull('fecha_limite')->where('fecha_limite', '<', now()->subMinutes(5))->count();
        $vencidos === 0 ? $this->ok('Cierre automático de exámenes vencidos', 'al día')
            : $this->aviso('Cierre automático de exámenes vencidos', "{$vencidos} sin cerrar: falta el cron schedule:run");

        $commit = $this->commitActual();
        $commit ? $this->ok('Versión desplegada', $commit) : $this->aviso('Versión desplegada', 'no se pudo leer .git');

        $estudiantes = DB::table('usuarios')->where('rol', 'estudiante')->count();
        $semana = DB::table('intentos')->where('fecha_inicio', '>=', now()->subDays(7))->count();
        $this->ok('Uso de la plataforma', "{$estudiantes} estudiantes · {$semana} exámenes en los últimos 7 días");
    }

    // ------------------------------------------------------------------ presentación

    private function portada(): void
    {
        $this->newLine();
        $this->line('  <fg=white;bg=blue;options=bold>                                                            </>');
        $this->line('  <fg=white;bg=blue;options=bold>   SIMULADOR CNI · DIAGNÓSTICO DEL SERVIDOR                 </>');
        $this->line('  <fg=white;bg=blue>   Solo lectura · atributos de calidad (ATAM)               </>');
        $this->line('  <fg=white;bg=blue;options=bold>                                                            </>');
        $this->line('  <fg=gray>'.config('app.url').' · '.now()->format('d/m/Y H:i').' · PHP '.PHP_VERSION.'</>');
        $this->newLine();
    }

    private function ok(string $que, string $detalle): void
    {
        $this->puntaje[$this->seccion]['ok']++;
        $this->components->twoColumnDetail("<fg=green>✔</> {$que}", "<fg=green>{$detalle}</>");
    }

    private function aviso(string $que, string $detalle): void
    {
        $this->puntaje[$this->seccion]['aviso']++;
        $this->components->twoColumnDetail("<fg=yellow>▲</> {$que}", "<fg=yellow>{$detalle}</>");
    }

    private function falla(string $que, string $detalle): void
    {
        $this->puntaje[$this->seccion]['falla']++;
        $this->components->twoColumnDetail("<fg=red>✘</> {$que}", "<fg=red>{$detalle}</>");
    }

    private function resumen(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>▌ RESUMEN</>');
        $total = ['ok' => 0, 'aviso' => 0, 'falla' => 0];
        foreach ($this->puntaje as $seccion => $p) {
            foreach ($p as $k => $v) {
                $total[$k] += $v;
            }
            $nota = $this->nota($p);
            $color = $nota >= 90 ? 'green' : ($nota >= 70 ? 'yellow' : 'red');
            $barra = str_repeat('█', (int) round($nota / 5)).str_repeat('░', 20 - (int) round($nota / 5));
            $titulo = mb_substr($seccion, 0, (int) mb_strpos($seccion.' ·', ' ·'));
            $titulo .= str_repeat(' ', max(1, 24 - mb_strlen($titulo)));
            $this->line("  {$titulo} <fg={$color}>{$barra} {$nota}%</>");
        }

        $nota = $this->nota($total);
        $this->newLine();
        [$fondo, $veredicto] = $total['falla'] > 0
            ? ['red', 'HAY PROBLEMAS QUE CORREGIR']
            : ($total['aviso'] > 0 ? ['yellow', 'FUNCIONANDO, CON AVISOS'] : ['green', 'TODO EN ORDEN']);
        $this->line("  <fg=black;bg={$fondo};options=bold>  NOTA GLOBAL: {$nota}% · {$veredicto}  </>");
        $this->line("  <fg=gray>{$total['ok']} correctos · {$total['aviso']} avisos · {$total['falla']} fallas</>");
        $this->newLine();

        return $total['falla'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array{ok:int, aviso:int, falla:int} $p */
    private function nota(array $p): int
    {
        $n = $p['ok'] + $p['aviso'] + $p['falla'];

        return $n === 0 ? 100 : (int) round(($p['ok'] + 0.5 * $p['aviso']) * 100 / $n);
    }

    // ------------------------------------------------------------------ utilidades

    /** @return array{0: int|null, 1: int} código HTTP y milisegundos */
    private function http(string $url): array
    {
        try {
            $inicio = microtime(true);
            $r = Http::timeout(10)->withOptions(['allow_redirects' => false])->get($url);

            return [$r->status(), (int) round((microtime(true) - $inicio) * 1000)];
        } catch (Throwable) {
            return [null, 0];
        }
    }

    private function migracionesPendientes(): int
    {
        $archivos = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));
        $aplicadas = DB::table('migrations')->pluck('migration');

        return $archivos->diff($aplicadas)->count();
    }

    private function preguntasEnJson(string $archivo): ?int
    {
        $ruta = database_path('data/'.$archivo);
        if (! is_file($ruta)) {
            return null;
        }
        $datos = json_decode((string) file_get_contents($ruta), true);

        return is_array($datos) ? count($datos['preguntas'] ?? $datos) : null;
    }

    private function erroresRecientes(): ?int
    {
        $log = storage_path('logs/laravel.log');
        if (! is_file($log)) {
            return null;
        }
        $desde = now()->subDay()->format('Y-m-d H:i:s');
        $errores = 0;
        $archivo = new \SplFileObject($log);
        while (! $archivo->eof()) {
            $linea = (string) $archivo->fgets();
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $linea, $m)
                && $m[1] >= $desde) {
                $errores++;
            }
        }

        return $errores;
    }

    private function commitActual(): ?string
    {
        $head = base_path('.git/HEAD');
        if (! is_file($head)) {
            return null;
        }
        $ref = trim((string) file_get_contents($head));
        if (str_starts_with($ref, 'ref: ')) {
            $rama = substr($ref, 5);
            $archivo = base_path('.git/'.$rama);
            $sha = is_file($archivo) ? trim((string) file_get_contents($archivo)) : null;
            if (! $sha && is_file(base_path('.git/packed-refs'))) {
                foreach (file(base_path('.git/packed-refs')) as $linea) {
                    if (str_ends_with(trim($linea), ' '.$rama)) {
                        $sha = strtok($linea, ' ');
                    }
                }
            }

            return $sha ? substr($sha, 0, 7).' (rama '.preg_replace('#^refs/heads/#', '', $rama).')' : null;
        }

        return substr($ref, 0, 7);
    }

    private function tamano(float $bytes): string
    {
        return $bytes >= 1024 ** 3 ? round($bytes / 1024 ** 3, 1).' GB' : round($bytes / 1024 ** 2).' MB';
    }
}
