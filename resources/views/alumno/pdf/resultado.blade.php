<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Resultado de simulacro #{{ $intento->id }}</title>
<style>
    @page { margin: 28mm 18mm 22mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #14234b; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    .sub { color: #4d5d7d; margin: 0 0 14px; }
    .aviso { border: 1px solid #f0d58c; background: #fff6dc; padding: 6px 10px; margin-bottom: 14px; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    th, td { border: 1px solid #d7e2f6; padding: 6px 8px; text-align: left; }
    th { background: #eef4fc; font-size: 10px; text-transform: uppercase; }
    .grande { font-size: 22px; font-weight: bold; }
    .pie { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 9px; color: #6b7a96; text-align: center; }
</style>
</head>
<body>
@php $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ','); $t = $resultado['totales']; @endphp
<h1>Resultado de simulacro</h1>
<p class="sub">Simulador CNI · Imperial — {{ $examen }}</p>
<p class="aviso">Documento referencial de un simulacro de preparación. No es un resultado oficial de admisión.</p>

<table>
    <tr><th>Intento</th><td>#{{ str_pad((string) $intento->id, 3, '0', STR_PAD_LEFT) }}</td><th>Fecha</th><td>{{ $fecha->format('d/m/Y H:i') }}</td></tr>
    <tr><th>Alumno</th><td>{{ trim($usuario->nombres.' '.($usuario->apellidos ?? '')) }}</td><th>Universidad / área</th><td>{{ $universidad }}{{ $area ? ' · Área '.$area : '' }}</td></tr>
</table>

<table>
    <tr><th>Puntaje</th><th>Escala</th><th>Correctas</th><th>Incorrectas</th><th>En blanco</th></tr>
    <tr>
        <td class="grande">{{ $num($t['puntaje_total']) }} <small>/ {{ $num($maximo) }}</small></td>
        <td>{{ $intento->total_preguntas }} preguntas · +{{ $num($intento->puntaje_correcta) }} / {{ $num($intento->puntaje_incorrecta) }} / {{ $num($intento->puntaje_blanco) }}</td>
        <td>{{ $t['correctas'] }}</td>
        <td>{{ $t['incorrectas'] }}</td>
        <td>{{ $t['en_blanco'] + $t['doble_marca'] }}</td>
    </tr>
</table>

@if(count($resultado['resultados_area']) > 0)
    <table>
        <tr><th>Asignatura</th><th>Preguntas</th><th>Aciertos</th><th>Incorrectas</th><th>En blanco</th><th>Puntaje</th><th>%</th></tr>
        @foreach($resultado['resultados_area'] as $a)
            <tr>
                <td>{{ \App\Services\Alumno\ContextoAlumnoService::asignaturaVisible($a->area_nombre) }}</td>
                <td>{{ $a->total_preguntas }}</td><td>{{ $a->aciertos }}</td><td>{{ $a->incorrectas }}</td><td>{{ $a->en_blanco }}</td>
                <td>{{ $num($a->puntaje) }}</td><td>{{ $num($a->porcentaje) }} %</td>
            </tr>
        @endforeach
    </table>
@else
    <p>Este intento no tiene desglose por asignatura.</p>
@endif

<div class="pie">Generado el {{ now()->format('d/m/Y H:i') }} · Simulador CNI · Resultado de simulacro (referencial)</div>
</body>
</html>
