{{--
    "Resultados de tu examen": pantalla a la que llega el alumno al enviar (o vencer) el examen.
    Datos: ResultadoService a través de OrientacionController@resultados.
--}}
@extends('layouts.app')

@php
    $intento = (object) $resultado['totales'];
    $areas = collect($resultado['resultados_area'])->map(function ($area) {
        $area = clone (object) $area;
        $area->nombre = $area->area_nombre;
        return $area;
    });
    $numeroIntento = $resultado['intento_id'];
    $volver = route('alumno.panel');
    $puntos = fn ($valor) => $valor === null ? '—' : number_format((float) $valor, 3);
    // Las prácticas no se califican: sin puntaje no hay cifras que mostrar (no son ceros).
    $calificado = $intento->puntaje_total !== null;
    $cifra = fn ($valor) => $calificado ? (int) $valor : '—';
    $dobleMarca = (int) $intento->doble_marca;
    // Dos tablas lado a lado, como en el diseño.
    $columnas = $areas->isEmpty() ? collect() : $areas->chunk((int) ceil($areas->count() / 2));
    // Íconos de línea estilo Lucide (licencia ISC).
    $icono = fn (string $trazos, int $tamano = 20) => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$tamano.'" height="'.$tamano.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$trazos.'</svg>';
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/resultados.css').'?v='.filemtime(public_path('css/resultados.css')) }}">
    @if($evaluacionIngreso ?? null)
        <link rel="stylesheet" href="{{ asset('css/referencia-ingreso.css').'?v='.filemtime(public_path('css/referencia-ingreso.css')) }}">
    @endif
@endpush

@section('content')
<div class="resultados">
    <section class="resultados-portada">
        <img class="resultados-portada__escudo" src="{{ asset('img/examen/escudo-cni.png') }}" width="206" height="184"
            alt="Escudo institucional CNI">
        <div class="resultados-portada__titulo">
            <p>SIMULADOR CNI</p>
            <h1>Resultados de tu examen</h1>
            <span>Intento #{{ $numeroIntento }} · {{ $usuario->nombres }}</span>
        </div>
        <img class="resultados-portada__mascota" src="{{ asset('img/examen/gato-cni.webp') }}" width="331" height="380" alt="">
    </section>

    <div class="resultados-barra">
        @if (session('status'))
            <p class="resultados-aviso" role="status">
                {!! $icono('<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>', 18) !!}
                {{ session('status') }}
            </p>
        @endif
        <a class="resultados-volver" href="{{ $volver }}">
            {!! $icono('<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>', 18) !!}
            Regresar a mi panel
        </a>
    </div>

    <ul class="resultados-cifras">
        <li class="is-puntaje">
            <span class="resultados-cifras__icono">{!! $icono('<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>', 24) !!}</span>
            <span><small>Puntaje total</small><strong>{{ $puntos($intento->puntaje_total) }}</strong></span>
        </li>
        <li class="is-correctas">
            <span class="resultados-cifras__icono">{!! $icono('<path d="M20 6 9 17l-5-5"/>', 26) !!}</span>
            <span><small>Correctas</small><strong>{{ $cifra($intento->correctas) }}</strong></span>
        </li>
        <li class="is-incorrectas">
            <span class="resultados-cifras__icono">{!! $icono('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>', 26) !!}</span>
            <span><small>Incorrectas</small><strong>{{ $cifra($intento->incorrectas) }}</strong></span>
        </li>
        <li class="is-blanco">
            <span class="resultados-cifras__icono">{!! $icono('<path d="M5 12h14"/>', 26) !!}</span>
            <span><small>{{ $dobleMarca > 0 ? 'En blanco o doble marca' : 'En blanco' }}</small><strong>{{ $calificado ? (int) $intento->en_blanco + $dobleMarca : '—' }}</strong></span>
        </li>
    </ul>

    @php $uniDia = ($intentoUni ?? null)?->calificacion; @endphp
    @if ($uniDia)
        <section class="resultados-uni" aria-labelledby="titulo-uni">
            <header>
                <h2 id="titulo-uni">DÍA {{ $intentoUni->dia }} — {{ mb_strtoupper(\App\Support\PerfilExamenCni::UNI_DIAS[$intentoUni->dia]['titulo']) }}</h2>
                <span>{{ \Illuminate\Support\Carbon::parse($intentoUni->fecha_inicio)->format('d/m/Y H:i') }}</span>
            </header>
            <dl class="resultados-uni__grid">
                <div><dt>Preguntas</dt><dd>{{ $uniDia['total_preguntas'] }}</dd></div>
                <div><dt>Correctas</dt><dd>{{ $uniDia['correctas'] }}</dd></div>
                <div><dt>Incorrectas</dt><dd>{{ $uniDia['incorrectas'] }}</dd></div>
                <div><dt>En blanco</dt><dd>{{ $uniDia['en_blanco'] }}</dd></div>
                <div><dt>Puntos ganados</dt><dd class="is-mas">+{{ number_format($uniDia['ganados'], 2) }}</dd></div>
                <div><dt>Puntos descontados</dt><dd class="is-menos">−{{ number_format($uniDia['descontados'], 2) }}</dd></div>
            </dl>
            <p class="resultados-uni__final">Puntaje final: <strong>{{ number_format($uniDia['puntaje'], 2) }} / {{ number_format($uniDia['maximo'], 2) }}</strong></p>
            @if ($uniDia['con_penalizacion'])
                <p class="resultados-uni__nota">Tu puntaje incluye penalizaciones por respuestas incorrectas. Las preguntas en blanco no generan descuento.</p>
            @endif
            <p class="resultados-uni__nota">Escala referencial de entrenamiento; no es el puntaje oficial de la UNI.</p>
        </section>
    @endif

    @if($evaluacionIngreso ?? null)
        @include('alumno.partials.referencia-ingreso')
    @endif

    <section class="resultados-asignaturas" aria-labelledby="titulo-asignaturas">
        <header>
            <h2 id="titulo-asignaturas">
                {!! $icono('<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M8 17v-3"/><path d="M13 17V9"/><path d="M18 17V5"/>', 22) !!}
                Resultados por asignatura
            </h2>
            <span>{{ $areas->count() }} {{ $areas->count() === 1 ? 'asignatura' : 'asignaturas' }}</span>
        </header>

        @if ($areas->isEmpty())
            <p class="resultados-vacio">{{ $calificado ? 'Este examen no tiene detalle por asignatura.' : 'Este examen todavía no tiene calificación.' }}</p>
        @else
            <div class="resultados-tablas">
                @foreach ($columnas as $columna)
                    <table>
                        <thead>
                            <tr><th scope="col">Asignatura</th><th scope="col">Aciertos</th><th scope="col">%</th><th scope="col">Puntaje</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($columna as $area)
                                <tr>
                                    <th scope="row">{{ $area->nombre }}</th>
                                    <td>{{ $area->aciertos }} / {{ $area->total_preguntas }}</td>
                                    <td>{{ number_format((float) $area->porcentaje, 2) }}%</td>
                                    <td>{{ $puntos($area->puntaje) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
        @endif
    </section>

    @php
        $preguntas = $preguntas ?? collect();
        $alternativasPorPregunta = $alternativasPorPregunta ?? collect();
        // Doble marca cuenta como "en blanco" en el filtro: no suma ni resta como respuesta elegida.
        $estadoDe = fn ($p) => match ($p->estado_respuesta) {
            'correcta' => 'correctas', 'incorrecta' => 'incorrectas', default => 'blanco',
        };
        $conteo = $preguntas->countBy($estadoDe);
    @endphp
    @if ($calificado && $preguntas->isNotEmpty())
        <section class="revision" aria-labelledby="titulo-revision">
            <div class="revision-cabecera">
                <div>
                    <p class="revision-kicker">RETROALIMENTACIÓN</p>
                    <h2 id="titulo-revision">Revisión de preguntas y respuestas</h2>
                    <p class="revision-ayuda">La alternativa correcta está en verde; si marcaste otra, tu respuesta aparece en rojo.</p>
                </div>
                <label class="revision-filtro">Mostrar
                    <select data-revision-filtro>
                        <option value="todas">Todas ({{ $preguntas->count() }})</option>
                        <option value="incorrectas">Incorrectas ({{ $conteo['incorrectas'] ?? 0 }})</option>
                        <option value="correctas">Correctas ({{ $conteo['correctas'] ?? 0 }})</option>
                        <option value="blanco">En blanco ({{ $conteo['blanco'] ?? 0 }})</option>
                    </select>
                </label>
            </div>

            @foreach ($preguntas as $pregunta)
                @php
                    $opciones = $alternativasPorPregunta->get($pregunta->pregunta_id, collect());
                    $marcada = $pregunta->alternativa_id === null ? null : (int) $pregunta->alternativa_id;
                    $estado = $estadoDe($pregunta);
                    $etiquetas = ['correctas' => '✓ Correcta', 'incorrectas' => '✕ Incorrecta',
                        'blanco' => $pregunta->estado_respuesta === 'doble_marca' ? '— Doble marca' : '— Sin responder'];
                @endphp
                <article class="revision-pregunta" data-revision-estado="{{ $estado }}">
                    <div class="revision-pregunta__top">
                        <span>PREGUNTA {{ $pregunta->orden }} DE {{ $preguntas->count() }} · {{ mb_strtoupper($pregunta->asignatura) }}</span>
                        <em class="is-{{ $estado }}">{{ $etiquetas[$estado] }}</em>
                    </div>
                    <h3>{{ $pregunta->enunciado }}</h3>
                    @if ($pregunta->imagen)
                        <img class="revision-imagen" src="{{ asset($pregunta->imagen) }}" alt="Imagen de la pregunta {{ $pregunta->orden }}">
                    @endif
                    <div class="revision-opciones">
                        @foreach ($opciones as $opcion)
                            @php
                                $esCorrecta = (bool) $opcion->es_correcta;
                                $esMarcada = $marcada === (int) $opcion->id;
                            @endphp
                            <div @class(['revision-opcion', 'is-correcta' => $esCorrecta, 'is-error' => $esMarcada && ! $esCorrecta])>
                                <span class="revision-letra">{{ $opcion->letra }}</span>
                                <span class="revision-texto">{{ $opcion->texto }}
                                    @if ($opcion->imagen)
                                        <img class="revision-imagen" src="{{ asset($opcion->imagen) }}" alt="Imagen de la alternativa {{ $opcion->letra }}">
                                    @endif
                                </span>
                                @if ($esCorrecta && $esMarcada)
                                    <strong>✓ Tu respuesta · correcta</strong>
                                @elseif ($esCorrecta)
                                    <strong>✓ Respuesta correcta</strong>
                                @elseif ($esMarcada)
                                    <strong>✕ Tu respuesta</strong>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="revision-explicacion">
                        <strong>Explicación</strong>
                        <p>{{ $pregunta->solucion ?: 'Esta pregunta aún no tiene explicación registrada.' }}</p>
                    </div>
                </article>
            @endforeach
            <p class="revision-vacio" data-revision-vacio hidden>No hay preguntas en esta categoría.</p>
        </section>
        <script>
        (() => {
            const filtro = document.querySelector('[data-revision-filtro]');
            if (!filtro) return;
            const preguntas = [...document.querySelectorAll('[data-revision-estado]')];
            const vacio = document.querySelector('[data-revision-vacio]');
            filtro.addEventListener('change', () => {
                let visibles = 0;
                preguntas.forEach((p) => {
                    p.hidden = filtro.value !== 'todas' && filtro.value !== p.dataset.revisionEstado;
                    if (!p.hidden) visibles++;
                });
                vacio.hidden = visibles > 0;
            });
        })();
        </script>
    @endif
</div>
@endsection
