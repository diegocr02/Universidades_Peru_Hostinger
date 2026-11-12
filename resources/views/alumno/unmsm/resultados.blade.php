@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/unmsm-resultados.css') }}?v={{ filemtime(public_path('css/unmsm-resultados.css')) }}">
<link rel="stylesheet" href="{{ asset('css/referencia-ingreso.css') }}?v={{ filemtime(public_path('css/referencia-ingreso.css')) }}">
@endpush
@section('content')
@use('App\Support\PerfilExamenUnmsm')
@php
    $totales = $resultado['totales'];
    $puntos = fn ($valor) => number_format((float) $valor, 3, '.', ',');
    $fecha = fn ($valor) => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y H:i') : '—';
    $segundos = $intento->fecha_fin ? max(0, (int) \Illuminate\Support\Carbon::parse($intento->fecha_inicio)->diffInSeconds(\Illuminate\Support\Carbon::parse($intento->fecha_fin))) : null;
    $usado = $segundos === null ? '—' : sprintf('%d min %02d s', intdiv($segundos, 60), $segundos % 60);
    $estado = ['finalizado' => 'Finalizado por el estudiante', 'enviado_auto' => 'Enviado automáticamente al terminar el tiempo'][$resultado['estado']] ?? $resultado['estado'];
    $total = $preguntas->count();
    $respondidas = $preguntas->whereNotNull('alternativa_id')->count();
    $ganados = $totales['correctas'] * PerfilExamenUnmsm::PUNTAJE_CORRECTA;
    $descontados = $totales['incorrectas'] * abs(PerfilExamenUnmsm::PUNTAJE_INCORRECTA);
    $asignaturas = collect($resultado['resultados_area'])->map(function ($a) {
        $a = (object) $a;
        $a->etiqueta = PerfilExamenUnmsm::etiqueta($a->area_nombre);
        $a->actitudinal = $a->area_nombre === PerfilExamenUnmsm::ASIGNATURA_ACTITUDINAL;
        return $a;
    });
    $ordinarias = $asignaturas->reject(fn ($a) => $a->actitudinal);
    $fortalezas = $ordinarias->filter(fn ($a) => (float) $a->porcentaje >= 60)->sortByDesc('porcentaje')->take(3);
    $practicar = $ordinarias->filter(fn ($a) => (float) $a->porcentaje < 50)->sortBy('porcentaje')->take(3);
    $minimo = PerfilExamenUnmsm::minimoReferencial($areaUnmsm);
@endphp
<div class="sm-res" data-modalidad="unmsm">
    <header class="sm-res-topbar">
        <a href="{{ route('alumno.panel') }}" class="sm-res-marca">
            <img src="{{ asset('images/escudo-cni.png') }}" width="44" height="44" alt="">
            <span><strong>Simulador CNI</strong><small>Preparación San Marcos</small></span>
        </a>
        <a href="{{ route('alumno.panel') }}#historial" class="sm-res-volver">← Volver a mi historial</a>
    </header>

    <main class="sm-res-main">
        @if(session('status'))<p class="sm-res-aviso" role="status">{{ session('status') }}</p>@endif
        <p class="sm-res-eyebrow">RESULTADOS · EXAMEN SAN MARCOS {{ config('unmsm.proceso') }}</p>
        <h1>Resultados de tu examen</h1>

        <section class="sm-res-tarjeta" aria-labelledby="sm-info">
            <h2 id="sm-info">Información general</h2>
            <dl class="sm-res-datos">
                <div><dt>Estudiante</dt><dd>{{ trim($usuario->nombres.' '.($usuario->apellidos ?? '')) }}</dd></div>
                <div><dt>Universidad</dt><dd>San Marcos</dd></div>
                <div><dt>Área</dt><dd>Área {{ $areaUnmsm }} · {{ PerfilExamenUnmsm::AREAS[$areaUnmsm] }}</dd></div>
                <div><dt>Carrera</dt><dd>{{ $carrera ?? 'No seleccionada' }}</dd></div>
                <div><dt>Fecha del examen</dt><dd>{{ $fecha($intento->fecha_inicio) }}</dd></div>
                <div><dt>Tiempo utilizado</dt><dd>{{ $usado }}</dd></div>
                <div><dt>Estado del intento</dt><dd>{{ $estado }} · Intento {{ $numero }}</dd></div>
            </dl>
        </section>

        <section class="sm-res-tarjeta" aria-labelledby="sm-resumen">
            <h2 id="sm-resumen">Resumen de calificación</h2>
            <div class="sm-res-puntaje">
                <span>Puntaje total</span>
                <strong>{{ $puntos($totales['puntaje_total']) }}</strong>
                <small>de {{ number_format(PerfilExamenUnmsm::PUNTAJE_MAXIMO, 0, '.', ',') }} puntos</small>
            </div>
            <dl class="sm-res-kpis">
                <div><dt>Correctas ordinarias</dt><dd class="sm-ok">{{ $totales['correctas'] }}</dd></div>
                <div><dt>Incorrectas ordinarias</dt><dd class="sm-mal">{{ $totales['incorrectas'] }}</dd></div>
                <div><dt>En blanco</dt><dd>{{ $totales['en_blanco'] + $totales['doble_marca'] }}</dd></div>
                <div><dt>Puntos ganados</dt><dd>+{{ $puntos($ganados) }}</dd></div>
                <div><dt>Puntos descontados</dt><dd>−{{ $puntos($descontados) }}</dd></div>
                <div><dt>Puntaje actitudinal</dt><dd>{{ $puntos($actitudinal) }}</dd></div>
                <div><dt>Preguntas respondidas</dt><dd>{{ $total > 0 ? round($respondidas * 100 / $total, 1) : 0 }}% <small>({{ $respondidas }} de {{ $total }})</small></dd></div>
            </dl>
            <p class="sm-res-formula">Puntaje = (correctas × 20) − (incorrectas × 1.125) + actitudinal.</p>
            @if($minimo !== null)
                <p class="sm-res-referencia">Referencia del proceso {{ config('unmsm.proceso') }} para el Área {{ $areaUnmsm }}: <strong>{{ number_format($minimo, 0, '.', ',') }} puntos</strong>.
                    Es un valor referencial; no significa ingreso. El ingreso real depende de las vacantes y del orden de mérito.</p>
            @endif
        </section>

        @include('alumno.partials.referencia-ingreso')

        <section class="sm-res-tarjeta" aria-labelledby="sm-asignaturas">
            <h2 id="sm-asignaturas">Resultados por asignatura</h2>
            @if($asignaturas->isEmpty())
                <p>No hay datos por asignatura para este examen.</p>
            @else
                <ol class="sm-res-barras">
                    @foreach($asignaturas as $a)
                        <li>
                            <span class="sm-res-asig">{{ $a->etiqueta }}</span>
                            <span class="sm-res-barra" role="img" aria-label="{{ $a->porcentaje }} por ciento"><span style="width:{{ max(0, min(100, (float) $a->porcentaje)) }}%"></span></span>
                            <span class="sm-res-valor">
                                @if($a->actitudinal) {{ $puntos($a->puntaje) }} pts
                                @else {{ $a->aciertos }} ✓ · {{ $a->incorrectas }} ✕ · {{ $a->en_blanco }} —
                                @endif
                                <strong>{{ number_format((float) $a->porcentaje, 1) }}%</strong>
                            </span>
                        </li>
                    @endforeach
                </ol>
                <div class="sm-res-columnas">
                    <div><h3>Mayor rendimiento</h3>
                        @if($fortalezas->isEmpty()) <p>Aún ninguna asignatura llega al 60 % de aciertos.</p>
                        @else <ul>@foreach($fortalezas as $a)<li>{{ $a->etiqueta }} · {{ number_format((float) $a->porcentaje, 1) }}%</li>@endforeach</ul> @endif
                    </div>
                    <div><h3>Requieren práctica</h3>
                        @if($practicar->isEmpty()) <p>Ninguna asignatura está por debajo del 50 % de aciertos.</p>
                        @else <ul>@foreach($practicar as $a)<li>{{ $a->etiqueta }} · {{ number_format((float) $a->porcentaje, 1) }}%</li>@endforeach</ul> @endif
                    </div>
                </div>
            @endif
        </section>

        <section class="sm-res-tarjeta" aria-labelledby="sm-retro">
            <div class="sm-res-retro-cabecera">
                <div>
                    <h2 id="sm-retro">Retroalimentación</h2>
                    <p>La alternativa correcta está en verde; tu respuesta equivocada, en rojo.</p>
                </div>
                <label for="sm-filtro">Mostrar
                    <select id="sm-filtro">
                        <option value="incorrecta">Incorrectas</option>
                        <option value="todas">Todas ({{ $total }})</option>
                        <option value="correcta">Correctas</option>
                        <option value="blanco">En blanco</option>
                        <option value="actitudinal">Actitudinales</option>
                    </select>
                </label>
            </div>
            <div class="sm-res-lista">
                @foreach($preguntas as $pregunta)
                    @php
                        $opciones = $alternativasPorPregunta->get($pregunta->pregunta_id, collect());
                        $elegida = $pregunta->alternativa_id === null ? null : (int) $pregunta->alternativa_id;
                        $esActitudinal = $pregunta->asignatura === PerfilExamenUnmsm::ASIGNATURA_ACTITUDINAL
                            && $opciones->contains(fn ($o) => ($o->puntaje_actitudinal ?? null) !== null);
                        $tipo = $esActitudinal ? 'actitudinal'
                            : ($pregunta->estado_respuesta === 'correcta' ? 'correcta' : ($pregunta->estado_respuesta === 'incorrecta' ? 'incorrecta' : 'blanco'));
                        $elegidaOpcion = $opciones->firstWhere('id', $elegida);
                        $correcta = $opciones->first(fn ($o) => (bool) $o->es_correcta);
                    @endphp
                    <article class="sm-res-pregunta" data-sm-tipo="{{ $tipo }}">
                        <div class="sm-res-pregunta-top">
                            <span>Pregunta {{ $pregunta->orden }} · {{ PerfilExamenUnmsm::etiqueta($pregunta->asignatura) }}</span>
                            <span class="sm-res-estado sm-res-estado-{{ $tipo }}">
                                @if($tipo === 'actitudinal') {{ $puntos($pregunta->puntaje_obtenido ?? 0) }} / 20 pts
                                @elseif($tipo === 'correcta') ✓ Correcta (+20)
                                @elseif($tipo === 'incorrecta') ✕ Incorrecta (−1.125)
                                @else — En blanco (0)
                                @endif
                            </span>
                        </div>
                        @if(trim($pregunta->enunciado) !== '')<h3>{!! nl2br(e($pregunta->enunciado)) !!}</h3>@endif
                        @if($pregunta->imagen)
                            <img class="sm-res-imagen" src="{{ asset($pregunta->imagen) }}" alt="Imagen de la pregunta {{ $pregunta->orden }}">
                            <p><a class="sm-res-ampliar" href="{{ asset($pregunta->imagen) }}" target="_blank" rel="noopener noreferrer">Ampliar figura de la pregunta {{ $pregunta->orden }} ↗</a></p>
                        @endif
                        <p class="sm-res-respuestas">
                            <span>Tu respuesta: <strong>{{ $elegidaOpcion ? $elegidaOpcion->letra.') '.$elegidaOpcion->texto : 'En blanco' }}</strong></span>
                            @unless($esActitudinal)
                                <span>Respuesta correcta: <strong>{{ $correcta ? $correcta->letra.') '.$correcta->texto : 'Sin clave registrada' }}</strong></span>
                            @endunless
                        </p>
                        <div class="sm-res-opciones">
                            @foreach($opciones as $opcion)
                                @php $marcada = $elegida === (int) $opcion->id; @endphp
                                <div @class(['sm-res-opcion', 'is-correcta' => ! $esActitudinal && $opcion->es_correcta,
                                    'is-error' => ! $esActitudinal && $marcada && ! $opcion->es_correcta, 'is-elegida' => $marcada])>
                                    <span class="sm-res-letra">{{ $opcion->letra }}</span>
                                    <span>{{ $opcion->texto }}
                                        @if($opcion->imagen)<img class="sm-res-imagen-alt" src="{{ asset($opcion->imagen) }}" alt="Alternativa {{ $opcion->letra }}">@endif
                                    </span>
                                    @if($esActitudinal)<small>{{ $puntos($opcion->puntaje_actitudinal) }} pts</small>@endif
                                </div>
                            @endforeach
                        </div>
                        @if(! $esActitudinal && trim((string) $pregunta->solucion) !== '')
                            <div class="sm-res-explicacion"><strong>Explicación</strong><p>{{ $pregunta->solucion }}</p></div>
                        @endif
                    </article>
                @endforeach
            </div>
            <p class="sm-res-vacio" id="sm-filtro-vacio" hidden>No hay preguntas en esta categoría.</p>
        </section>
        <a class="sm-res-volver sm-res-volver-pie" href="{{ route('alumno.panel') }}">← Volver al inicio</a>
    </main>
</div>
<script>
(() => {
    const filtro = document.getElementById('sm-filtro');
    if (!filtro) return;
    const items = [...document.querySelectorAll('.sm-res-pregunta')];
    const vacio = document.getElementById('sm-filtro-vacio');
    const aplicar = () => {
        let visibles = 0;
        items.forEach((item) => {
            const ver = filtro.value === 'todas' || filtro.value === item.dataset.smTipo;
            item.hidden = !ver;
            if (ver) visibles++;
        });
        vacio.hidden = visibles > 0;
    };
    filtro.addEventListener('change', aplicar);
    aplicar();
})();
</script>
@endsection
