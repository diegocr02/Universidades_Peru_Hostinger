@extends('layouts.app')
@section('content')
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $escala = fn ($e) => $e['preguntas'].' preguntas · +'.$num($e['correcta']).' / '.$num($e['incorrecta']).' · máx. '.$num($e['maximo']).' pts';
@endphp
<x-alumno.pagina :ctx="$ctx" :usuario="$usuario" activo="progreso" titulo="Mi historial y avance" icono="📈">
    <main class="cni-alumno-content estudio-grid">
        <section class="estudio-tarjeta" aria-labelledby="filtros-titulo">
            <h2 id="filtros-titulo">Historial de {{ $ctx['etiqueta'] }}</h2>
            <p class="estudio-ayuda">Solo se comparan exámenes equivalentes: misma universidad, mismo examen y la misma escala de puntaje. Los demás se muestran por separado.</p>
            <form method="GET" class="estudio-campos" aria-label="Filtrar historial">
                <label class="estudio-campo">Examen
                    <select name="examen">
                        <option value="">Todos</option>
                        @foreach($examenes as $e)<option value="{{ $e['clave'] }}" @selected(($filtros['examen'] ?? '') === $e['clave'])>{{ $e['examen'] }} ({{ $e['escala']['preguntas'] }} preg.)</option>@endforeach
                    </select>
                </label>
                <label class="estudio-campo">Desde <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}"></label>
                <label class="estudio-campo">Hasta <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}"></label>
                <div class="estudio-acciones" style="align-self:end">
                    <button type="submit" class="estudio-boton estudio-boton--primario">Filtrar</button>
                    <a class="estudio-boton estudio-boton--secundario" href="{{ route('alumno.progreso') }}">Limpiar</a>
                </div>
            </form>
            @error('hasta')<p class="estudio-aviso estudio-aviso--error">✕ La fecha «hasta» debe ser posterior a «desde».</p>@enderror
        </section>

        @forelse($grupos as $g)
            <section class="estudio-tarjeta progreso-grupo" aria-labelledby="grupo-{{ $g['clave'] }}">
                <h2 id="grupo-{{ $g['clave'] }}">{{ $g['examen'] }}</h2>
                <p class="estudio-ayuda">Escala: {{ $escala($g['escala']) }}. El gráfico muestra el % del puntaje máximo.</p>
                @if($g['intentos'] < 2)
                    <div class="estudio-vacio">Tienes {{ $g['intentos'] }} intento de este examen. Con dos o más verás tu tendencia aquí.</div>
                @else
                    <svg class="progreso-grafico" viewBox="0 0 1000 200" role="img"
                         aria-label="Evolución de {{ $g['examen'] }}: {{ collect($g['grafico']['puntos'])->map(fn ($p) => $p['fecha'].' '.$num($p['porcentaje']).'%')->implode(', ') }}">
                        @foreach([0, 50, 100] as $marca)
                            <line class="eje" x1="40" x2="990" y1="{{ 180 - $marca * 1.6 }}" y2="{{ 180 - $marca * 1.6 }}"/>
                            <text x="2" y="{{ 184 - $marca * 1.6 }}">{{ $marca }}%</text>
                        @endforeach
                        <path class="linea" d="{{ $g['grafico']['trazo'] }}"/>
                        @foreach($g['grafico']['puntos'] as $p)
                            <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="5"><title>{{ $p['fecha'] }}: {{ $num($p['porcentaje']) }} %</title></circle>
                        @endforeach
                    </svg>
                @endif
                <div class="progreso-datos">
                    <span>Intentos: <strong>{{ $g['intentos'] }}</strong></span>
                    <span>Primero: <strong>{{ $num($g['evolucion'][0]['puntaje']) }}</strong> ({{ $num($g['evolucion'][0]['porcentaje']) }} %)</span>
                    <span>Último: <strong>{{ $num(end($g['evolucion'])['puntaje']) }}</strong> ({{ $num(end($g['evolucion'])['porcentaje']) }} %)</span>
                    <span>Mejor: <strong>{{ $num($g['mejor']) }} %</strong></span>
                    @if($g['cambio'] !== null)
                        <span>{{ $g['cambio'] > 0 ? '▲ Subiste' : ($g['cambio'] < 0 ? '▼ Bajaste' : '■ Igual') }} <strong>{{ $num(abs($g['cambio'])) }}</strong> puntos porcentuales</span>
                    @endif
                </div>
                @if($g['reforzar'] !== [])
                    <p class="estudio-ayuda">🎯 Por reforzar: @foreach($g['reforzar'] as $r)<a href="{{ route('alumno.practica', ['asignatura' => $r['asignatura']]) }}">{{ $r['asignatura'] }} ({{ $num($r['porcentaje']) }} %)</a>@if(! $loop->last), @endif @endforeach</p>
                @endif
            </section>
        @empty
            <div class="estudio-vacio">
                <p><strong>Aún no tienes exámenes calificados{{ array_filter($filtros) ? ' con estos filtros' : '' }}.</strong></p>
                <p>Rinde un examen desde tu panel o empieza con una práctica.</p>
                <p><a class="estudio-boton estudio-boton--primario" href="{{ route('alumno.practica') }}">Empezar a practicar</a></p>
            </div>
        @endforelse

        @if($intentos->isNotEmpty())
            <section class="estudio-tarjeta" aria-labelledby="tabla-titulo">
                <h2 id="tabla-titulo">Todos tus intentos</h2>
                <div class="estudio-tabla-marco" tabindex="0" role="region" aria-label="Tabla de intentos">
                    <table class="estudio-tabla">
                        <thead><tr><th scope="col">Fecha</th><th scope="col">Universidad</th><th scope="col">Área</th><th scope="col">Examen</th><th scope="col">Puntaje</th><th scope="col">%</th><th scope="col">Detalle</th></tr></thead>
                        <tbody>
                            @foreach($intentos as $i)
                                <tr>
                                    <td data-etiqueta="Fecha">{{ $i->fecha->format('d/m/Y H:i') }}</td>
                                    <td data-etiqueta="Universidad">{{ $ctx['universidad'] }}</td>
                                    <td data-etiqueta="Área">Área {{ $ctx['codigo'] }}</td>
                                    <td data-etiqueta="Examen">{{ $i->examen }}</td>
                                    <td data-etiqueta="Puntaje">{{ $num($i->puntaje) }} / {{ $num($i->maximo) }}</td>
                                    <td data-etiqueta="%">{{ $num($i->porcentaje) }} %</td>
                                    <td data-etiqueta="Detalle"><a href="{{ route('alumno.resultados', ['intento' => $i->id]) }}">Ver resultado</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </main>
</x-alumno.pagina>
@endsection
