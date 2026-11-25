{{-- Resumen de inicio compartido por los paneles de las cinco universidades. --}}
@php
    $r = app(\App\Services\Alumno\InicioAlumnoService::class)->resumen($usuario);
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
@endphp
@if($r)
<link rel="stylesheet" href="{{ asset('css/alumno/estudio.css') }}?v={{ @filemtime(public_path('css/alumno/estudio.css')) }}">
<section class="inicio-resumen" aria-label="Resumen de tu preparación">
    <div class="estudio-tarjeta">
        <h2>Tu preparación</h2>
        <p class="inicio-dato">Hola, {{ explode(' ', trim($usuario->nombres))[0] }} 👋 Esto es lo que sigue para ti.</p>
        <p class="inicio-dato"><span>🎓 <strong>{{ $r['ctx']['universidad'] }}</strong> · Área {{ $r['ctx']['codigo'] }} — {{ $r['ctx']['area_nombre'] }}</span></p>

        @if($r['pendiente'])
            {{-- Examen pendiente: se CONTINÚA el mismo intento (no es una práctica). --}}
            <p class="inicio-dato"><span class="estado estado--pendiente">⏱ Examen en curso</span> <strong>{{ \App\Services\Alumno\ProgresoComparableService::examenBase($r['pendiente']->nombre) }}</strong>
                @if($r['pendiente']->fecha_limite) · vence {{ \Illuminate\Support\Carbon::parse($r['pendiente']->fecha_limite)->diffForHumans() }}@endif</p>
            <a class="inicio-continuar inicio-continuar--examen" href="{{ route('examen', ['intento' => $r['pendiente']->id]) }}">▶ Continuar examen pendiente</a>
        @elseif($r['ultimo'])
            <p class="inicio-dato">Último resultado: <strong>{{ $r['ultimo']->examen }}</strong> · {{ $num($r['ultimo']->puntaje) }} / {{ $num($r['ultimo']->maximo) }} ({{ $num($r['ultimo']->porcentaje) }} %) · {{ $r['ultimo']->fecha->format('d/m/Y') }}
                <a href="{{ route('alumno.resultados', ['intento' => $r['ultimo']->id]) }}">Ver detalle</a></p>
            <a class="inicio-continuar" href="{{ route('alumno.practica') }}">✎ Continuar preparación{{ $r['practica'] ? ' · práctica de '.$r['practica']->asignatura : '' }}</a>
        @else
            <p class="inicio-dato">Aún no rindes ningún examen de {{ $r['ctx']['universidad'] }}. Empieza con una práctica corta o rinde tu primer examen desde este panel.</p>
            <a class="inicio-continuar" href="{{ route('alumno.practica') }}">✎ Empezar a practicar</a>
        @endif

        <nav class="inicio-accesos" aria-label="Accesos rápidos">
            <a href="{{ route('alumno.practica') }}">✎ Práctica por temas</a>
            <a href="{{ route('alumno.progreso') }}">📈 Mi historial y avance</a>
            <a href="{{ route('alumno.guardadas') }}">★ Preguntas guardadas</a>
            @if(config('tutor.enabled'))<a href="{{ route('alumno.tutor') }}">✦ Tutor IA</a>@endif
        </nav>
    </div>

    <div class="estudio-tarjeta" aria-labelledby="meta-titulo">
        <h2 id="meta-titulo">🎯 Meta de la semana</h2>
        @php $hecho = $r['semana']['total']; $objetivo = $r['meta']; @endphp
        <p class="inicio-dato">Preguntas de práctica respondidas del {{ $r['semana']['inicio']->format('d/m') }} al {{ $r['semana']['fin']->format('d/m') }}.</p>
        @if($objetivo)
            <p><strong>{{ $hecho }}</strong> de {{ $objetivo }} {{ $hecho >= $objetivo ? '· ✓ ¡Meta cumplida!' : '' }}</p>
            <div class="meta-barra" role="progressbar" aria-label="Avance de la meta semanal" aria-valuemin="0" aria-valuemax="{{ $objetivo }}" aria-valuenow="{{ min($hecho, $objetivo) }}">
                <span style="width: {{ min(100, round($hecho / max(1, $objetivo) * 100)) }}%"></span>
            </div>
        @else
            <p>Llevas <strong>{{ $hecho }}</strong> esta semana. Define una meta para seguir tu avance.</p>
        @endif
        <form method="POST" action="{{ route('alumno.meta.guardar') }}" class="meta-form">@csrf
            <label>Preguntas por semana
                <input type="number" name="preguntas_semana" min="{{ config('panel_alumno.meta_semanal.minimo') }}" max="{{ config('panel_alumno.meta_semanal.maximo') }}"
                       value="{{ $objetivo ?? config('panel_alumno.meta_semanal.sugerida') }}" required>
            </label>
            <button type="submit">{{ $objetivo ? 'Cambiar meta' : 'Guardar meta' }}</button>
        </form>
    </div>
</section>
@endif
