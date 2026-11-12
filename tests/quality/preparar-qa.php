<?php

/**
 * Prepara la base QA local (simulador_cni_qa) para k6 y Playwright.
 *
 *   APP_ENV=qa php tests/quality/preparar-qa.php
 *
 * - Solo actúa con APP_ENV=qa, host local y base simulador_cni_qa; en otro caso aborta.
 * - Crea (o actualiza) estudiantes de prueba del área C (Villarreal):
 *     k6_alumno_01 … k6_alumno_15  → carga concurrente de k6
 *     e2e_alumno, e2e_otro          → escenarios Playwright
 * - Genera una clave aleatoria nueva en cada ejecución y la guarda solo en
 *   tests/quality/.credenciales-qa.json (ignorado por git). No usa credenciales reales.
 * - Deja un intento finalizado de e2e_otro para el escenario de seguridad.
 */

use App\Models\Usuario;
use App\Services\Alumno\ExamenIndependienteService;
use App\Services\IntentoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$conexion = DB::connection();
$host = (string) config('database.connections.'.$conexion->getName().'.host');
if (app()->environment() !== 'qa' || $conexion->getDatabaseName() !== 'simulador_cni_qa'
    || ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "Bloqueado: solo se ejecuta con APP_ENV=qa sobre la base local simulador_cni_qa.\n");
    exit(1);
}

$area = DB::table('areas_postulacion')->where('codigo', 'C')->value('id');
if (! $area) {
    fwrite(STDERR, "Falta el área C. Ejecuta antes: APP_ENV=qa php artisan db:seed --force\n");
    exit(1);
}

$clave = bin2hex(random_bytes(16));
$hash = Hash::make($clave);
$crear = function (string $usuario, string $nombre) use ($area, $hash): Usuario {
    $existente = Usuario::query()->where('usuario', $usuario)->first();
    $datos = ['nombres' => $nombre, 'apellidos' => 'QA', 'password' => $hash, 'rol' => 'estudiante',
        'activo' => true, 'area_postulacion_id' => $area, 'carrera_id' => null];
    if ($existente) {
        $existente->forceFill($datos)->save();

        return $existente;
    }

    return Usuario::query()->forceCreate($datos + ['usuario' => $usuario]);
};

$k6 = [];
foreach (range(1, 15) as $n) {
    $usuario = sprintf('k6_alumno_%02d', $n);
    $crear($usuario, 'K6 '.$n);
    $k6[] = $usuario;
}
$alumno = $crear('e2e_alumno', 'Alumno E2E');
$otro = $crear('e2e_otro', 'Otro E2E');

// Administrador del Centro de contingencia (solo en la base QA local, misma clave aleatoria).
$adminQa = Usuario::query()->where('usuario', 'Super_Admin')->first() ?? new Usuario(['usuario' => 'Super_Admin']);
$adminQa->forceFill(['usuario' => 'Super_Admin', 'nombres' => 'Super', 'apellidos' => 'Admin QA', 'password' => $hash,
    'rol' => 'admin', 'activo' => true, 'area_postulacion_id' => null, 'carrera_id' => null])->save();

// Intento ajeno finalizado: el escenario de seguridad intenta abrir su resultado.
$intentoOtro = DB::table('intentos')->where('usuario_id', $otro->id)
    ->whereIn('estado', ['finalizado', 'enviado_auto'])->whereNotNull('puntaje_total')->value('id');
if (! $intentoOtro) {
    $intento = app(ExamenIndependienteService::class)->iniciar($otro);
    $intentoOtro = app(IntentoService::class)->finalizar($intento->id, $otro->id)->id;
}

$ruta = __DIR__.'/.credenciales-qa.json';
file_put_contents($ruta, json_encode([
    'base_url' => 'http://127.0.0.1:8001',
    'clave' => $clave,
    'k6_usuarios' => $k6,
    'e2e_alumno' => 'e2e_alumno',
    'e2e_otro' => 'e2e_otro',
    'e2e_admin' => 'Super_Admin',
    'intento_ajeno' => (int) $intentoOtro,
    'generado' => now()->toIso8601String(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'Base QA lista: '.count($k6).' cuentas k6, 2 cuentas E2E, Super_Admin QA, intento ajeno #'.$intentoOtro.".\n";
echo "Credenciales en tests/quality/.credenciales-qa.json (ignorado por git).\n";
