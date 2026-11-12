@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/undc-resultados.css') }}?v=2">
@endpush

@section('content')
@php
    $totales = $resultado['totales'];
    $idIntento = (int) $resultado['intento_id'];
    $asignaturas = collect($resultado['resultados_area'])->map(fn ($a) => (object) $a);
@endphp

<div class="ur-shell">
    <header class="ur-header">
        <a href="{{ route('alumno.panel') }}#resultados" class="ur-brand">
            <img src="{{ asset('images/undc/logo-undc.png') }}" width="58" height="58" alt="Escudo UNDC">
            <span><strong>UNDC</strong><small>Simulador de admisión</small></span>
        </a>
        <a href="{{ route('alumno.panel') }}#resultados" class="ur-return">← Volver a mis resultados</a>
    </header>

    <main class="ur-main">
        <div class="ur-eyebrow">RESULTADO FINAL · PRÁCTICA DE ADMISIÓN</div>
        <h1>Revisión de tu examen</h1>
        <p class="ur-subtitle">{{ $simulacroNombre }} · Intento #{{ $idIntento }}</p>

        <div class="ur-summary" aria-label="Resumen del examen">
            <div class="ur-score"><span>Puntaje obtenido</span><strong>{{ number_format((float) $totales['puntaje_total'], 3) }}</strong><small>Calificación del simulacro</small></div>
            <div><span>Respuestas correctas</span><strong>{{ $totales['correctas'] }}</strong><small class="ur-correct">✓ Aciertos</small></div>
            <div><span>Respuestas incorrectas</span><strong>{{ $totales['incorrectas'] }}</strong><small class="ur-wrong">✕ Errores</small></div>
            <div><span>En blanco o doble marca</span><strong>{{ $totales['en_blanco'] + $totales['doble_marca'] }}</strong><small>Sin puntaje</small></div>
        </div>

        <section class="ur-admision" aria-labelledby="ur-admision-title">
            <span class="ur-eyebrow">ORIENTACIÓN DE ADMISIÓN · UNDC {{ $indicadorAdmision['proceso'] }}</span>
            <h2 id="ur-admision-title">¿Cómo te fue en tu preparación?</h2>
            <div class="ur-admision-summary">
                <div>
                    <span>Puntaje de tu simulacro</span>
                    <strong>{{ number_format($indicadorAdmision['puntaje'], 2) }}
                        <small>/ {{ number_format($indicadorAdmision['maximo'], 2) }}</small>
                    </strong>
                </div>
                <div>
                    <span>Rendimiento en esta práctica</span>
                    <strong>{{ number_format($indicadorAdmision['porcentaje'], 1) }}%</strong>
                </div>
            </div>
            <div class="ur-admision-track" role="img" aria-label="Rendimiento {{ number_format($indicadorAdmision['porcentaje'], 1) }} por ciento">
                <span style="width: {{ $indicadorAdmision['porcentaje'] }}%"></span>
            </div>
            @if($indicadorAdmision['comparacion_valida'])
                <p class="ur-admision-message">
                    {{ $indicadorAdmision['cumple_minimo']
                        ? '✓ Alcanzaste el puntaje mínimo configurado para esta convocatoria.'
                        : 'Aún no alcanzaste el puntaje mínimo configurado para esta convocatoria.' }}
                </p>
                <p>Mínimo registrado: {{ number_format($indicadorAdmision['minimo'], 2) }} puntos.</p>
            @else
                <p class="ur-admision-message">Tu resultado ya está registrado. El puntaje mínimo oficial del examen ordinario 2026-II aún está pendiente de verificación en este simulador.</p>
            @endif
            <p class="ur-admision-note">Este es un examen de práctica, no la lista oficial de ingresantes. Para ingresar se deben cumplir las reglas vigentes y obtener una vacante según el orden de mérito por carrera. Los valores históricos de 118,5 (CEPRE) y 120 (2018) no se aplican automáticamente a 2026-II.</p>
        </section>

        <section class="ur-section" aria-labelledby="ur-subjects">
            <h2 id="ur-subjects">Resultados por asignatura</h2>
            @if($asignaturas->isEmpty())
                <p>No hay datos por asignatura para este examen.</p>
            @else
                <div class="ur-subject-grid">
                    @foreach($asignaturas as $area)
                        <div class="ur-subject-row">
                            <span>{{ preg_replace('/^UNDC [AB] · /u', '', $area->area_nombre) }}</span>
                            <strong>{{ $area->aciertos }} / {{ $area->total_preguntas }}</strong>
                            <span>{{ number_format((float) $area->porcentaje, 1) }}%</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="ur-section" aria-labelledby="ur-review">
            <div class="ur-review-heading">
                <div>
                    <div class="ur-eyebrow">RETROALIMENTACIÓN</div>
                    <h2 id="ur-review">Respuestas y soluciones</h2>
                    <p>Las alternativas correctas están en verde; las respuestas equivocadas que marcaste, en rojo.</p>
                </div>
                <label class="ur-filter-label" for="ur-filter">Mostrar preguntas
                    <select id="ur-filter">
                        <option value="todas">Todas ({{ $preguntas->count() }})</option>
                        <option value="correctas">Correctas</option>
                        <option value="incorrectas">Incorrectas</option>
                        <option value="blanco">En blanco</option>
                    </select>
                </label>
            </div>

            <div class="ur-list" id="ur-list">
                @forelse($preguntas as $pregunta)
                    @php
                        $opciones = $alternativasPorPregunta->get($pregunta->pregunta_id, collect());
                        $seleccionada = $pregunta->alternativa_id === null ? null : (int) $pregunta->alternativa_id;
                        $correcta = $opciones->first(fn ($op) => (bool) $op->es_correcta);
                        $estado = $pregunta->estado_respuesta === 'correcta'
                            ? 'correctas'
                            : ($pregunta->estado_respuesta === 'incorrecta' ? 'incorrectas' : 'blanco');
                    @endphp
                    <article class="ur-question" data-ur-status="{{ $estado }}">
                        <div class="ur-question-top">
                            <span>Pregunta {{ $pregunta->orden }} de {{ $preguntas->count() }}</span>
                            <span class="ur-status ur-status-{{ $estado }}">
                                @if($estado === 'correctas') ✓ Correcta
                                @elseif($estado === 'incorrectas') ✕ Incorrecta
                                @else — Sin respuesta
                                @endif
                            </span>
                        </div>
                        <small class="ur-subject">{{ $pregunta->asignatura }}</small>
                        <h3>{{ $pregunta->enunciado }}</h3>
                        @if ($pregunta->imagen)
                            <img class="ur-question-image" src="{{ asset($pregunta->imagen) }}" alt="Imagen de la pregunta {{ $pregunta->orden }}">
                        @endif
                        <div class="ur-options">
                            @foreach($opciones as $opcion)
                                @php
                                    $esCorrecta = (bool) $opcion->es_correcta;
                                    $esSeleccionada = $seleccionada === (int) $opcion->id;
                                    $esIncorrectaMarcada = $esSeleccionada && ! $esCorrecta;
                                @endphp
                                <div @class(['ur-option', 'ur-option-correct' => $esCorrecta, 'ur-option-wrong' => $esIncorrectaMarcada])>
                                    <span class="ur-letter">{{ $opcion->letra }}</span>
                                    <span class="ur-option-text">{{ $opcion->texto }}
                                        @if ($opcion->imagen)
                                            <img class="ur-option-image" src="{{ asset($opcion->imagen) }}" alt="Imagen alternativa {{ $opcion->letra }}">
                                        @endif
                                    </span>
                                    @if($esCorrecta)
                                        <strong class="ur-option-tag">✓ Respuesta correcta</strong>
                                    @elseif($esIncorrectaMarcada)
                                        <strong class="ur-option-tag">✕ Tu respuesta</strong>
                                    @elseif($esSeleccionada)
                                        <strong class="ur-option-tag">Tu respuesta</strong>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="ur-explanation">
                            <strong>Explicación</strong>
                            <p>{{ $pregunta->solucion ?: 'No hay explicación disponible.' }}</p>
                            @if(!$correcta)
                                <small>Esta pregunta no cuenta con una clave válida registrada. Consulta al administrador del banco.</small>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="ur-empty">No se encontraron preguntas para revisar.</div>
                @endforelse
            </div>
            <p class="ur-empty" id="ur-filter-empty" hidden>No hay preguntas de esta categoría.</p>
        </section>
        <a class="ur-bottom-return" href="{{ route('alumno.panel') }}#resultados">← Volver al panel UNDC</a>
    </main>
</div>
<script>
(() => {
    const filter = document.getElementById('ur-filter');
    if (!filter) return;
    const items = [...document.querySelectorAll('.ur-question[data-ur-status]')];
    const empty = document.getElementById('ur-filter-empty');
    const update = () => {
        const value = filter.value;
        let shown = 0;
        items.forEach(item => {
            const visible = value === 'todas' || value === item.dataset.urStatus;
            item.hidden = !visible;
            if (visible) shown++;
        });
        if (empty) empty.hidden = shown > 0;
    };
    filter.addEventListener('change', update);
    update();
})();
</script>
@endsection
