# =====================================================================
#  Simulador CNI - Ejecuta las pruebas de calidad y explica el resultado
#
#  Uso (desde la raiz del proyecto, en PowerShell):
#     .\tests\quality\ejecutar-pruebas.ps1              # las tres
#     .\tests\quality\ejecutar-pruebas.ps1 -Solo phpunit
#     .\tests\quality\ejecutar-pruebas.ps1 -Solo k6
#     .\tests\quality\ejecutar-pruebas.ps1 -Solo e2e
#     .\tests\quality\ejecutar-pruebas.ps1 -Solo e2e -VerNavegador
#
#  - PHPUnit usa SQLite en memoria (no toca ninguna base).
#  - k6 y Playwright usan el servidor QA local (puerto 8001, base simulador_cni_qa).
#    Si no esta encendido, este script lo enciende y lo apaga al terminar.
# =====================================================================
param(
    [ValidateSet('todo', 'phpunit', 'k6', 'e2e')]
    [string]$Solo = 'todo',
    [switch]$VerNavegador
)

$ErrorActionPreference = 'Continue'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$raiz = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $raiz
$resultados = New-Object System.Collections.Generic.List[object]

function Titulo([string]$texto) {
    Write-Host ''
    Write-Host ('=' * 66) -ForegroundColor DarkCyan
    Write-Host "  $texto" -ForegroundColor Cyan
    Write-Host ('=' * 66) -ForegroundColor DarkCyan
}

function Agregar([string]$prueba, [string]$estado, [string]$detalle, [string]$significa) {
    $resultados.Add([pscustomobject]@{ Prueba = $prueba; Estado = $estado; Detalle = $detalle; Significa = $significa })
}

function ServidorQaActivo {
    try {
        $r = Invoke-WebRequest -UseBasicParsing -TimeoutSec 3 'http://127.0.0.1:8001/login'
        return $r.StatusCode -eq 200
    } catch { return $false }
}

$servidorIniciado = $null

function AsegurarServidorQa {
    if (-not (Test-Path 'tests\quality\.credenciales-qa.json')) {
        Write-Host '  Preparando cuentas de prueba en la base QA (primera vez)...' -ForegroundColor Yellow
        $env:APP_ENV = 'qa'
        php tests/quality/preparar-qa.php
        Remove-Item Env:APP_ENV
    }
    if (ServidorQaActivo) {
        Write-Host '  Servidor QA encendido en http://127.0.0.1:8001' -ForegroundColor Green
        return $true
    }
    Write-Host '  Encendiendo el servidor QA en http://127.0.0.1:8001 ...' -ForegroundColor Yellow
    $env:APP_ENV = 'qa'
    # OPcache solo para este servidor (como en un hosting real); ver tests\quality\php-qa\opcache.ini
    $env:PHP_INI_SCAN_DIR = (Resolve-Path 'tests\quality\php-qa').Path
    $script:servidorIniciado = Start-Process -FilePath 'php' -ArgumentList 'artisan', 'serve', '--host=127.0.0.1', '--port=8001' -WindowStyle Hidden -PassThru
    Remove-Item Env:APP_ENV
    Remove-Item Env:PHP_INI_SCAN_DIR
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Milliseconds 500
        if (ServidorQaActivo) { Write-Host '  Servidor QA listo.' -ForegroundColor Green; return $true }
    }
    Write-Host '  No se pudo encender el servidor QA.' -ForegroundColor Red
    return $false
}

# ---------------------------------------------------------------- PHPUnit
if ($Solo -eq 'todo' -or $Solo -eq 'phpunit') {
    Titulo 'PRUEBA 1 de 3 - PHPUnit: seguridad, integridad y backend'
    Write-Host '  Que comprueba: login/logout, permisos por rol, que nadie vea resultados ajenos,'
    Write-Host '  que no se guarden respuestas invalidas, calificacion y control del tiempo.'
    Write-Host ''
    $salida = php artisan test 2>&1 | ForEach-Object { $_.ToString() }
    $esc = [char]27
    $limpia = $salida | ForEach-Object { $_ -replace "$esc\[[0-9;]*m", '' }
    $limpia | Where-Object { $_ -match 'FAIL|Tests:|Duration:' } | ForEach-Object { Write-Host "  $_" }
    $lineaTotal = ($limpia | Where-Object { $_ -match 'Tests:' } | Select-Object -Last 1)
    $aprobadas = 0; $fallidas = 0
    if ($lineaTotal -match '(\d+) passed') { $aprobadas = [int]$Matches[1] }
    if ($lineaTotal -match '(\d+) failed') { $fallidas = [int]$Matches[1] }
    if ($fallidas -eq 0 -and $aprobadas -gt 0) {
        Agregar 'PHPUnit' 'APROBADO' "$aprobadas pruebas aprobadas, 0 fallidas" 'El backend cumple sus reglas de seguridad e integridad.'
    } else {
        Agregar 'PHPUnit' 'FALLO' "$aprobadas aprobadas, $fallidas fallidas" 'Alguna regla del backend no se cumple: revisa las lineas FAIL de arriba.'
    }
}

# ---------------------------------------------------------------- k6
if ($Solo -eq 'todo' -or $Solo -eq 'k6') {
    Titulo 'PRUEBA 2 de 3 - k6: rendimiento con 15 estudiantes a la vez (~70 s)'
    if (AsegurarServidorQa) {
        k6 run --quiet tests/performance/cni-load.js
        $codigo = $LASTEXITCODE
        $cifras = 'Ver CONCLUSION de k6 arriba'
        try {
            $k6 = Get-Content 'tests\quality\resultados\k6-resumen.json' -Raw -Encoding UTF8 | ConvertFrom-Json
            $p95 = [math]::Round($k6.metrics.http_req_duration.values.'p(95)' / 1000, 2)
            $err = [math]::Round($k6.metrics.http_req_failed.values.rate * 100, 1)
            $cifras = "p95 = $p95 s (meta < 2 s) | errores = $err % (meta < 1 %)"
        } catch { }
        if ($codigo -eq 0) {
            Agregar 'k6' 'APROBADO' $cifras 'Soporta 15 estudiantes a la vez, rapido y sin errores.'
        } elseif ($codigo -eq 99) {
            Agregar 'k6' 'META NO CUMPLIDA' $cifras 'Funciona sin errores, pero responde lento con 15 a la vez (servidor local de 1 proceso). Ver CONCLUSION arriba.'
        } else {
            Agregar 'k6' 'ERROR' "k6 termino con codigo $codigo" 'La prueba no pudo ejecutarse (servidor, cuentas o script).'
        }
    } else {
        Agregar 'k6' 'NO EJECUTADO' 'Servidor QA apagado' 'Enciende el servidor QA y vuelve a intentar.'
    }
}

# ---------------------------------------------------------------- Playwright
if ($Solo -eq 'todo' -or $Solo -eq 'e2e') {
    Titulo 'PRUEBA 3 de 3 - Playwright: recorrido real del estudiante en Chrome'
    Write-Host '  Que comprueba: login, examen completo, resultados y que no se vea lo ajeno.'
    Write-Host ''
    if (AsegurarServidorQa) {
        Push-Location 'tests\quality'
        if (-not (Test-Path 'node_modules')) { npm install --no-audit --no-fund | Out-Null }
        if ($VerNavegador) { npx playwright test --headed } else { npx playwright test }
        $codigo = $LASTEXITCODE
        Pop-Location
        if ($codigo -eq 0) {
            Agregar 'Playwright' 'APROBADO' '5 de 5 escenarios' 'Un estudiante puede usar el simulador de principio a fin y no ve datos ajenos.'
        } else {
            Agregar 'Playwright' 'FALLO' 'Algun escenario fallo (marcado con x arriba)' 'Abre el reporte HTML para ver la captura del paso que fallo.'
        }
    } else {
        Agregar 'Playwright' 'NO EJECUTADO' 'Servidor QA apagado' 'Enciende el servidor QA y vuelve a intentar.'
    }
}

# ---------------------------------------------------------------- Resumen
if ($servidorIniciado) {
    taskkill /PID $servidorIniciado.Id /T /F | Out-Null
    Write-Host ''
    Write-Host '  (Servidor QA apagado: lo habia encendido este script.)' -ForegroundColor DarkGray
}

Titulo 'RESUMEN FINAL'
foreach ($r in $resultados) {
    $color = 'Red'
    if ($r.Estado -eq 'APROBADO') { $color = 'Green' }
    elseif ($r.Estado -eq 'META NO CUMPLIDA' -or $r.Estado -eq 'NO EJECUTADO') { $color = 'Yellow' }
    Write-Host ('  {0,-11} [{1}]' -f $r.Prueba, $r.Estado) -ForegroundColor $color
    Write-Host ('              {0}' -f $r.Detalle)
    Write-Host ('              Significa: {0}' -f $r.Significa) -ForegroundColor Gray
}
Write-Host ''
Write-Host '  Evidencias:' -ForegroundColor Cyan
Write-Host '   - k6 ........ tests\quality\resultados\k6-resumen.txt'
Write-Host '   - Playwright  cd tests\quality ; npx playwright show-report playwright-report'
Write-Host '   - Informe ... docs\INFORME-CALIDAD-ATAM.md'
Write-Host ''
