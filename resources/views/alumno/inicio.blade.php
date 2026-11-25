{{--
    Inicio del alumno (diseño "Simulador CNI · Derecho").
    Datos: App\Http\Controllers\Alumno\InicioController. Colores e íconos: config/alumno.php.
--}}
@extends('layouts.alumno-derecho', [
    'variante' => 'derecho',
    'title' => 'Inicio',
])

@section('content')
<div class="inicio">
    {{-- Bienvenida --}}
    <section class="inicio-banner">
        <div class="inicio-banner__texto">
            <h1>¡Hola, {{ $nombre }}!</h1>
            <p>Te damos la bienvenida a tu espacio de preparación. Practica con preguntas tipo examen, revisa tus temas y
                sigue avanzando hacia tu ingreso{{ $carrera ? ' a '.$carrera : '' }}.</p>
            @if ($cita)
                <figure class="inicio-cita">
                    <x-alumno.icono nombre="comillas" :tamano="20" class="inicio-cita__icono" />
                    <blockquote>{{ $cita['texto'] }}</blockquote>
                    <figcaption>— {{ $cita['autor'] }}</figcaption>
                </figure>
            @endif
        </div>
        <img class="inicio-banner__buho" src="{{ asset('images/mascotas/buho-humanidades.webp') }}" width="640" height="407"
            alt="Ilustración de un búho con toga y balanza">
    </section>

    <div class="inicio-fila">
        {{-- Progreso general --}}
        <section class="inicio-tarjeta inicio-progreso" aria-labelledby="titulo-progreso">
            <h2 id="titulo-progreso">Tu progreso{{ $carrera ? ' en '.$carrera : '' }}</h2>

            <div class="inicio-progreso__avance">
                <div class="inicio-progreso__detalle">
                    <span>Avance del banco de preguntas</span>
                    <strong>{{ $progreso['porcentaje'] }}%</strong>
                </div>
                <div class="inicio-barra" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                    aria-valuenow="{{ $progreso['porcentaje'] }}" aria-label="Avance del banco de preguntas">
                    <span style="width: {{ $progreso['porcentaje'] }}%"></span>
                </div>
            </div>

            <ul class="inicio-cifras">
                <li>
                    <span class="inicio-cifras__icono"><x-alumno.icono nombre="diana" :tamano="20" /></span>
                    <span><strong>{{ $progreso['resueltas'] }}</strong><small>Preguntas resueltas</small></span>
                </li>
                <li>
                    <span class="inicio-cifras__icono"><x-alumno.icono nombre="check-circulo" :tamano="20" /></span>
                    <span><strong>{{ $progreso['correctas'] }}</strong><small>Respuestas correctas</small></span>
                </li>
                <li>
                    <span class="inicio-cifras__icono"><x-alumno.icono nombre="porcentaje" :tamano="20" /></span>
                    <span><strong>{{ $progreso['acierto'] }}%</strong><small>Porcentaje de acierto</small></span>
                </li>
            </ul>
        </section>

        {{-- Racha de estudio --}}
        <section class="inicio-tarjeta inicio-racha" aria-labelledby="titulo-racha">
            <h2 id="titulo-racha">Racha de estudio</h2>
            <p class="inicio-racha__dias">
                <x-alumno.icono nombre="fuego" :tamano="28" />
                <span><strong>{{ $racha['dias'] }}</strong> {{ $racha['dias'] === 1 ? 'día seguido' : 'días seguidos' }}</span>
            </p>
            <ol class="inicio-semana" aria-label="Días de esta semana en que estudiaste">
                @foreach ($racha['semana'] as $dia)
                    <li @class(['is-estudio' => $dia['estudio'], 'is-hoy' => $dia['es_hoy']])>
                        <span class="inicio-semana__marca">
                            @if ($dia['estudio'])
                                <x-alumno.icono nombre="check" :tamano="14" />
                            @endif
                        </span>
                        <span class="inicio-semana__nombre">{{ $dia['nombre'] }}</span>
                        <span class="sr-only">{{ $dia['estudio'] ? 'estudiaste' : 'sin actividad' }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    {{-- Temas de estudio --}}
    <section class="inicio-temas" aria-labelledby="titulo-temas">
        <div class="inicio-temas__encabezado">
            <h2 id="titulo-temas">Temas de estudio</h2>
            @php $rutaTemas = \Illuminate\Support\Facades\Route::has('alumno.temas') ? route('alumno.temas') : '#'; @endphp
            <a href="{{ $rutaTemas }}">Ver todos <x-alumno.icono nombre="flecha-derecha" :tamano="16" /></a>
        </div>

        @if ($temas->isEmpty())
            <p class="inicio-vacio">Todavía no hay temas registrados.</p>
        @else
            <ul class="inicio-temas__lista">
                @foreach ($temas as $tema)
                    <li class="inicio-tema" style="--tema-acento: {{ $tema['color']['acento'] }}; --tema-fondo: {{ $tema['color']['fondo'] }}">
                        <span class="inicio-tema__icono"><x-alumno.icono :nombre="$tema['icono']" :tamano="22" /></span>
                        <h3>{{ $tema['nombre'] }}</h3>
                        <div class="inicio-barra" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                            aria-valuenow="{{ $tema['porcentaje'] }}" aria-label="Avance en {{ $tema['nombre'] }}">
                            <span style="width: {{ $tema['porcentaje'] }}%"></span>
                        </div>
                        <p>{{ $tema['resueltas'] }}/{{ $tema['total'] }} preguntas</p>
                        {{-- Por ahora lleva a "Temas de estudio"; cuando exista la página de cada tema, enlazarla aquí. --}}
                        <a class="inicio-tema__ir" href="{{ $rutaTemas }}" aria-label="Practicar {{ $tema['nombre'] }}">
                            <x-alumno.icono nombre="flecha-derecha" :tamano="18" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
