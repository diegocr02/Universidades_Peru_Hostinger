@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/panel-alumno.css') }}?v={{ filemtime(public_path('css/panel-alumno.css')) }}">
<link rel="stylesheet" href="{{ asset('css/panel-unmsm.css') }}?v={{ filemtime(public_path('css/panel-unmsm.css')) }}">
@endpush
@section('content')
@use('App\Support\PerfilExamenUnmsm')
@php
    $temaArea = config('panel_alumno.areas.E');
    $rutaLogo = $temaArea['mascota_png'];
    $nombreCompleto = trim($usuario->nombres.' '.($usuario->apellidos ?? ''));
    $iniciales = mb_strtoupper(mb_substr($usuario->nombres, 0, 1).mb_substr($usuario->apellidos ?? '', 0, 1));
    $seccionAlumno = $usuario->seccion;
    $fecha = fn ($valor) => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y H:i') : '—';
    $puntos = fn ($valor) => number_format((float) $valor, 3, '.', ',');
    $minutos = PerfilExamenUnmsm::minutos();
    $duracion = intdiv($minutos, 60).' h'.($minutos % 60 ? ' '.($minutos % 60).' min' : '');
    $areaAlumno = $unmsm['area_alumno'];
    $miArea = $areaAlumno ? $unmsm['areas'][$areaAlumno] : null;
    $estados = ['en_curso' => 'En curso', 'finalizado' => 'Finalizado', 'enviado_auto' => 'Enviado por tiempo'];

    // Estado de preparación: solo con datos reales del alumno.
    if (! $miArea) {
        $preparacion = 'Elige tu área de San Marcos para empezar.';
    } elseif ($miArea['en_curso']) {
        $preparacion = 'Tienes un examen en curso.';
    } elseif ($miArea['ultimo']) {
        $calificados = $unmsm['evolucion']->count();
        $preparacion = $calificados.' '.($calificados === 1 ? 'examen calificado' : 'exámenes calificados').' en el Área '.$areaAlumno.'.';
    } else {
        $preparacion = 'Aún no rindes tu primer examen.';
    }
@endphp
<div class="cni-alumno cni-unmsm" data-area="E" data-modalidad="unmsm" id="panel-unmsm">
    <button type="button" class="cni-alumno-sidebar-toggle" data-sidebar-toggle aria-controls="cni-alumno-sidebar"
            aria-expanded="true" aria-label="Cerrar menú lateral">
        <span class="cni-alumno-sidebar-toggle-icon" aria-hidden="true">❮</span>
        <span class="cni-alumno-sidebar-toggle-text">Menú</span>
    </button>
    <button type="button" class="cni-alumno-sidebar-backdrop" data-sidebar-backdrop aria-label="Cerrar menú lateral" hidden></button>
    <aside class="cni-alumno-sidebar" id="cni-alumno-sidebar" aria-label="Menú del estudiante">
        <div class="cni-alumno-sidebar-top">
            <a class="cni-alumno-marca" href="{{ route('alumno.panel') }}">
                <span class="cni-alumno-marca-icon" aria-hidden="true"><img src="{{ asset('images/escudo-cni.png') }}" width="36" height="36" alt=""></span>
                <span><strong>Simulador</strong><small>CNI · Imperial</small></span>
            </a>
            <span class="cni-alumno-area-chip"><span aria-hidden="true">{{ $temaArea['icono'] }}</span> San Marcos{{ $areaAlumno ? ' · Área '.$areaAlumno : '' }}</span>
            <nav class="cni-alumno-menu" aria-label="Navegación del alumno">
                <a href="#inicio" class="is-active" aria-current="location"><span aria-hidden="true">⌂</span> Inicio</a>
                <a href="#intentos"><span aria-hidden="true">▶</span> Mis intentos</a>
                <a href="#historial"><span aria-hidden="true">◷</span> Historial</a>
                <a href="#perfil"><span aria-hidden="true">♙</span> Mi perfil</a>
            </nav>
        </div>
        <div class="cni-alumno-sidebar-bottom">
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="cni-alumno-salir" type="submit">↪ &nbsp; Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <div class="cni-alumno-workspace">
        <header class="cni-alumno-topbar">
            <div class="cni-alumno-top-title"><span class="cni-alumno-topicon" data-panel-header-icon aria-hidden="true">⌂</span><span><strong data-panel-header-title>Inicio</strong></span></div>
            <div class="cni-alumno-top-actions">
                <div class="cni-alumno-top-profile"><span class="cni-alumno-avatar">{{ $iniciales }}</span><span><strong>{{ $nombreCompleto }}</strong><small>Estudiante</small></span></div>
            </div>
        </header>

        <main class="cni-alumno-content">
            @if(session('status')) <div class="cni-alumno-alert cni-alumno-alert-ok" role="status">{{ session('status') }}</div> @endif
            @if($errors->any())
                <div class="cni-alumno-alert sm-alerta-error" role="alert">@foreach($errors->all() as $mensaje)<p>{{ $mensaje }}</p>@endforeach</div>
            @endif

            {{-- Inicio --}}
            <section class="cni-alumno-vista" id="inicio" data-panel-view="inicio" aria-labelledby="sm-titulo">
                <header class="sm-hero">
                    <img class="sm-hero-logo" src="{{ asset($rutaLogo).'?v='.filemtime(public_path($rutaLogo)) }}"
                         width="96" height="96" alt="Escudo de la Universidad Nacional Mayor de San Marcos">
                    <span class="sm-hero-overline">Admisión {{ config('unmsm.proceso') }} · Formato San Marcos</span>
                    <h1 id="sm-titulo">Simulador CNI — Preparación San Marcos</h1>
                    <p class="sm-hero-saludo">¡Hola, {{ explode(' ', trim($usuario->nombres))[0] }}! {{ $temaArea['descripcion'] }}</p>
                    <ul class="sm-pildoras" aria-label="Datos del examen">
                        <li><strong>{{ PerfilExamenUnmsm::TOTAL_PREGUNTAS }}</strong> preguntas</li>
                        <li><strong>{{ $duracion }}</strong></li>
                        <li><strong>{{ number_format(PerfilExamenUnmsm::PUNTAJE_MAXIMO, 0, '.', ',') }}</strong> puntos máx.</li>
                    </ul>
                </header>

                <dl class="sm-ficha" aria-label="Tu preparación">
                    <div><dt>Estudiante</dt><dd>{{ $nombreCompleto }}</dd></div>
                    <div><dt>Área de postulación</dt><dd>{{ $miArea ? 'Área '.$areaAlumno.' · '.$miArea['nombre'] : 'Por elegir' }}</dd></div>
                    <div><dt>Carrera</dt><dd>{{ $carrera?->nombre ?? 'No seleccionada' }}</dd></div>
                    <div><dt>Estado de preparación</dt><dd>{{ $preparacion }}</dd></div>
                </dl>

                <p class="sm-info">Correcta <strong>+20</strong> · incorrecta <strong>−1.125</strong> · en blanco <strong>0</strong>.
                    Las 10 actitudinales valen hasta 20 puntos cada una y no descuentan.
                    @if(config('unmsm.provisional')) <span class="sm-provisional">Distribución de preguntas provisional del simulador, no oficial.</span> @endif</p>

                @unless($areaAlumno)
                    <p class="sm-aviso-eleccion">Elige el área a la que postulas. <strong>La elección es definitiva</strong>: solo podrás rendir el examen de esa área.</p>
                @endunless

                <div class="sm-areas">
                    @foreach($unmsm['areas'] as $tarjeta)
                        @php $disp = $tarjeta['disponibilidad']; @endphp
                        <article @class(['sm-area', 'is-autorizada' => $tarjeta['autorizada'], 'is-bloqueada' => $areaAlumno && ! $tarjeta['autorizada']])
                                 aria-labelledby="sm-area-{{ $tarjeta['codigo'] }}">
                            <header class="sm-area-cabecera">
                                <span class="sm-area-codigo">Área {{ $tarjeta['codigo'] }}</span>
                                @if($tarjeta['autorizada'])
                                    <span class="sm-etiqueta sm-etiqueta-ok">Tu área</span>
                                @elseif($areaAlumno)
                                    <span class="sm-etiqueta">No autorizada</span>
                                @endif
                            </header>
                            <h3 id="sm-area-{{ $tarjeta['codigo'] }}">{{ $tarjeta['nombre'] }}</h3>
                            <ul class="sm-area-datos">
                                <li><span>Preguntas</span><strong>{{ $tarjeta['total'] }}</strong></li>
                                <li><span>Duración</span><strong>{{ $duracion }}</strong></li>
                                <li><span>Puntaje máximo</span><strong>{{ number_format($tarjeta['maximo'], 0, '.', ',') }}</strong></li>
                                <li><span>Último resultado</span><strong>{{ $tarjeta['ultimo'] ? $puntos($tarjeta['ultimo']->puntaje_total) : '—' }}</strong></li>
                                <li><span>Mejor resultado</span><strong>{{ $tarjeta['mejor'] ? $puntos($tarjeta['mejor']->puntaje_total) : '—' }}</strong></li>
                            </ul>

                            @if($tarjeta['distribucion'])
                                <details class="sm-distribucion">
                                    <summary>Distribución de preguntas</summary>
                                    <ul>@foreach($tarjeta['distribucion'] as $fila)<li><span>{{ $fila['asignatura'] }}</span><strong>{{ $fila['cantidad'] }}</strong></li>@endforeach</ul>
                                </details>
                            @endif

                            @if(! $areaAlumno)
                                <form method="POST" action="{{ route('alumno.unmsm.area') }}" class="sm-area-acciones"
                                      onsubmit="return confirm('¿Confirmas que postulas al Área {{ $tarjeta['codigo'] }}? Esta elección es definitiva.')">@csrf
                                    <input type="hidden" name="area_unmsm" value="{{ $tarjeta['codigo'] }}">
                                    <button type="submit" class="cni-alumno-primary">Elegir Área {{ $tarjeta['codigo'] }}</button>
                                </form>
                            @elseif($tarjeta['autorizada'])
                                @if($tarjeta['en_curso'])
                                    <p class="sm-area-nota">{{ $tarjeta['en_curso']->respondidas }} de {{ $tarjeta['en_curso']->total_preguntas }} respondidas
                                        @if($tarjeta['en_curso']->segundos_restantes !== null) · quedan {{ intdiv($tarjeta['en_curso']->segundos_restantes, 60) }} min @endif</p>
                                @endif
                                @if($disp && $disp['estado'] !== 'disponible' && ! $tarjeta['en_curso'])
                                    <div class="sm-disponibilidad" role="status">
                                        <p>{{ $disp['mensaje'] }}</p>
                                        @if($disp['faltantes'])
                                            <ul>@foreach($disp['faltantes'] as $falta)<li>{{ $falta['asignatura'] }}: {{ $falta['hay'] }} de {{ $falta['requiere'] }}</li>@endforeach</ul>
                                        @endif
                                    </div>
                                @endif
                                <div class="sm-area-acciones">
                                    @if($tarjeta['en_curso'] || ($disp && $disp['estado'] === 'disponible'))
                                        <form method="POST" action="{{ route('examen.unmsm') }}" data-sm-iniciar>@csrf
                                            <button type="submit" class="cni-alumno-primary">
                                                {{ $tarjeta['en_curso'] ? 'Continuar' : ($tarjeta['cantidad_intentos'] > 0 ? 'Volver a rendir' : 'Iniciar examen') }}
                                                <span aria-hidden="true">→</span>
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" class="cni-alumno-primary" disabled>Iniciar examen</button>
                                    @endif
                                    @if($tarjeta['ultimo'] && ! $tarjeta['en_curso'])
                                        <a class="sm-enlace" href="{{ route('alumno.resultados', ['intento' => $tarjeta['ultimo']->id]) }}">Ver último resultado</a>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                @if($miArea && $miArea['minimo'] !== null)
                    <p class="sm-referencia">Puntaje mínimo referencial del Área {{ $areaAlumno }} en el proceso {{ config('unmsm.proceso') }}: <strong>{{ number_format($miArea['minimo'], 0, '.', ',') }} puntos</strong>.
                        Es solo una referencia: el ingreso real depende de las vacantes y del orden de mérito de cada carrera.</p>
                @endif
            </section>

            {{-- Mis intentos --}}
            <section class="cni-alumno-vista" id="intentos" data-panel-view="intentos" aria-labelledby="intentos-titulo" hidden>
                <div class="cni-historial-tarjeta">
                    <div class="cni-historial-cabecera"><h2 id="intentos-titulo">Mis intentos · San Marcos</h2></div>
                    <div class="cni-historial-tabla-marco" tabindex="0" role="region" aria-label="Tabla de mis intentos">
                        <table class="cni-historial-tabla">
                            <thead><tr>
                                <th scope="col">Modalidad</th><th scope="col">Área</th><th scope="col">Carrera</th><th scope="col">N.º</th>
                                <th scope="col">Fecha</th><th scope="col">Estado</th><th scope="col">Tiempo restante</th>
                                <th scope="col">Puntaje</th><th scope="col">Acciones</th>
                            </tr></thead>
                            <tbody>
                                @forelse($unmsm['intentos'] as $intento)
                                    <tr>
                                        <td>San Marcos</td>
                                        <td>Área {{ $intento->area_unmsm }}</td>
                                        <td>{{ $intento->carrera ?? '—' }}</td>
                                        <th scope="row">{{ $intento->numero }}</th>
                                        <td>{{ $fecha($intento->fecha_inicio) }}</td>
                                        <td>{{ $intento->en_curso ? 'En curso' : ($estados[$intento->estado] ?? 'Finalizado') }}</td>
                                        <td>{{ $intento->segundos_restantes !== null ? intdiv($intento->segundos_restantes, 60).' min' : '—' }}</td>
                                        <td class="cni-historial-puntaje">{{ $intento->calificado ? $puntos($intento->puntaje_total).' / 2,000' : '—' }}</td>
                                        <td class="sm-acciones-tabla">
                                            @if($intento->en_curso && $intento->area_unmsm === $areaAlumno)
                                                <form method="POST" action="{{ route('examen.unmsm') }}" data-sm-iniciar>@csrf<button type="submit" class="sm-boton-tabla">Continuar</button></form>
                                            @elseif($intento->calificado)
                                                <a class="cni-historial-resultado" href="{{ route('alumno.resultados', ['intento' => $intento->id]) }}">Ver resultado</a>
                                            @else
                                                <span>Sin calificar</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="cni-historial-vacio">Aún no tienes intentos en San Marcos.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($miArea && ! $miArea['en_curso'] && $miArea['cantidad_intentos'] > 0 && $miArea['disponibilidad']['estado'] === 'disponible')
                        <form method="POST" action="{{ route('examen.unmsm') }}" class="sm-rendir" data-sm-iniciar>@csrf
                            <button type="submit" class="cni-alumno-primary">Volver a rendir el Área {{ $areaAlumno }} <span aria-hidden="true">→</span></button>
                        </form>
                    @endif
                </div>
            </section>

            {{-- Historial --}}
            <section class="cni-alumno-vista cni-alumno-history" id="historial" data-panel-view="historial" aria-labelledby="historial-titulo" hidden>
                <h2 id="historial-titulo" class="sm-titulo-seccion">Historial · San Marcos</h2>
                @php $evolucion = $unmsm['evolucion']; @endphp
                @if($evolucion->isEmpty())
                    <p class="cni-alumno-empty">Cuando tengas un examen calificado verás aquí tu evolución y tus estadísticas por asignatura.</p>
                @else
                    @php
                        $ultimo = $evolucion->last();
                        $mejor = $evolucion->sortByDesc(fn ($i) => (float) $i->puntaje_total)->first();
                        $anterior = $evolucion->count() > 1 ? $evolucion->get($evolucion->count() - 2) : null;
                        $diferencia = $anterior ? (float) $ultimo->puntaje_total - (float) $anterior->puntaje_total : null;
                    @endphp
                    <dl class="sm-resumen">
                        <div><dt>Último resultado</dt><dd>{{ $puntos($ultimo->puntaje_total) }} <small>/ 2,000</small></dd></div>
                        <div><dt>Mejor puntaje</dt><dd>{{ $puntos($mejor->puntaje_total) }} <small>/ 2,000</small></dd></div>
                        <div><dt>Exámenes calificados</dt><dd>{{ $evolucion->count() }}</dd></div>
                        <div><dt>Frente al intento anterior</dt><dd>@if($diferencia === null) — @else <span @class(['sm-sube' => $diferencia >= 0, 'sm-baja' => $diferencia < 0])>{{ $diferencia >= 0 ? '+' : '−' }}{{ $puntos(abs($diferencia)) }}</span> @endif</dd></div>
                    </dl>

                    <section class="sm-bloque" aria-labelledby="evolucion-titulo">
                        <h3 id="evolucion-titulo">Evolución del rendimiento</h3>
                        <ol class="sm-barras">
                            @foreach($evolucion as $item)
                                @php $ancho = max(0, min(100, (float) $item->puntaje_total / 20)); @endphp
                                <li><span>Intento {{ $item->numero }}</span>
                                    <span class="sm-barra" role="img" aria-label="{{ $puntos($item->puntaje_total) }} de 2,000 puntos"><span style="width:{{ $ancho }}%"></span></span>
                                    <strong>{{ $puntos($item->puntaje_total) }}</strong></li>
                            @endforeach
                        </ol>
                    </section>

                    <section class="sm-bloque" aria-labelledby="asignaturas-titulo">
                        <h3 id="asignaturas-titulo">Estadísticas por asignatura</h3>
                        <ol class="sm-barras">
                            @foreach($unmsm['asignaturas'] as $fila)
                                <li><span>{{ $fila->etiqueta }}</span>
                                    <span class="sm-barra" role="img" aria-label="{{ $fila->porcentaje }} por ciento"><span style="width:{{ $fila->porcentaje }}%"></span></span>
                                    <strong>{{ $fila->actitudinal ? $puntos($fila->puntaje).' pts' : $fila->aciertos.' / '.$fila->total }} · {{ $fila->porcentaje }}%</strong></li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                <div class="cni-historial-tarjeta">
                    <div class="cni-historial-cabecera"><h3>Intentos anteriores</h3></div>
                    <div class="cni-historial-tabla-marco" tabindex="0" role="region" aria-label="Tabla del historial">
                        <table class="cni-historial-tabla">
                            <thead><tr><th scope="col">Área</th><th scope="col">N.º</th><th scope="col">Fecha</th><th scope="col">Correctas</th>
                                <th scope="col">Incorrectas</th><th scope="col">Actitudinal</th><th scope="col">Puntaje</th><th scope="col">Acciones</th></tr></thead>
                            <tbody>
                                @forelse($unmsm['historial'] as $intento)
                                    <tr>
                                        <td>Área {{ $intento->area_unmsm }}</td>
                                        <th scope="row">{{ $intento->numero }}</th>
                                        <td>{{ $fecha($intento->fecha_inicio) }}</td>
                                        <td>{{ $intento->correctas ?? '—' }}</td>
                                        <td>{{ $intento->incorrectas ?? '—' }}</td>
                                        <td>{{ $intento->actitudinal !== null ? $puntos($intento->actitudinal) : '—' }}</td>
                                        <td class="cni-historial-puntaje">{{ $intento->calificado ? $puntos($intento->puntaje_total) : '—' }}</td>
                                        <td>@if($intento->calificado)<a class="cni-historial-resultado" href="{{ route('alumno.resultados', ['intento' => $intento->id]) }}">Ver resultado</a>@else<span>Sin calificar</span>@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="cni-historial-vacio">Aún no has terminado ningún examen de San Marcos.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($unmsm['otros']->isNotEmpty())
                    <div class="cni-historial-tarjeta sm-otros">
                        <div class="cni-historial-cabecera"><h3>Otros simulacros anteriores</h3></div>
                        <p class="sm-nota">Se conservan como registro. Usan otra escala de puntaje y no se comparan con los exámenes San Marcos.</p>
                        <div class="cni-historial-tabla-marco" tabindex="0" role="region" aria-label="Otros simulacros">
                            <table class="cni-historial-tabla">
                                <thead><tr><th scope="col">Examen</th><th scope="col">Fecha</th><th scope="col">Puntaje</th><th scope="col">Acciones</th></tr></thead>
                                <tbody>
                                    @foreach($unmsm['otros'] as $intento)
                                        <tr><td>{{ $intento->examen }}</td><td>{{ $fecha($intento->fecha_inicio) }}</td>
                                            <td>{{ $intento->calificado ? $puntos($intento->puntaje_total).($intento->maximo > 0 ? ' / '.$puntos($intento->maximo) : '') : '—' }}</td>
                                            <td>@if($intento->calificado)<a class="cni-historial-resultado" href="{{ route('alumno.resultados', ['intento' => $intento->id]) }}">Ver resultado</a>@else<span>Sin calificar</span>@endif</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </section>

            {{-- Mi perfil --}}
            <section class="cni-alumno-vista" id="perfil" data-panel-view="perfil" aria-labelledby="datos-alumno-titulo" hidden>
                <section class="cni-alumno-datos">
                    <h2 id="datos-alumno-titulo"><span class="cni-alumno-datos-icono" aria-hidden="true">♙</span> Datos del alumno</h2>
                    <dl class="cni-alumno-datos-grid">
                        <div><dt>Nombre completo</dt><dd>{{ $nombreCompleto ?: 'No registrado' }}</dd></div>
                        <div><dt>Nombre de usuario</dt><dd>{{ $usuario->usuario ?: 'No registrado' }}</dd></div>
                        <div><dt>Grado</dt><dd>{{ $seccionAlumno?->grado ?? 'No asignado' }}</dd></div>
                        <div><dt>Sección</dt><dd>{{ $seccionAlumno?->nombre ?? 'No asignada' }}</dd></div>
                        <div><dt>Modalidad</dt><dd>San Marcos ({{ config('unmsm.universidad') }})</dd></div>
                        <div><dt>Área San Marcos</dt><dd>{{ $miArea ? 'Área '.$areaAlumno.' · '.$miArea['nombre'] : 'Por elegir' }}</dd></div>
                        <div><dt>Carrera de interés</dt><dd>{{ $carrera?->nombre ?? 'No seleccionada' }}</dd></div>
                    </dl>
                    @if(! $carrera && $carreras->isNotEmpty())
                        <form method="POST" action="{{ route('alumno.carrera.guardar') }}" class="sm-carrera">@csrf
                            <label for="sm-carrera">Elige tu carrera de interés (opcional, definitiva)</label>
                            <select id="sm-carrera" name="carrera_id" required>
                                <option value="">Selecciona una carrera</option>
                                @foreach($carreras as $opcion)<option value="{{ $opcion->id }}">{{ $opcion->nombre }}</option>@endforeach
                            </select>
                            <button type="submit" class="cni-alumno-primary">Guardar carrera</button>
                        </form>
                    @endif
                </section>
            </section>
        </main>
    </div>
</div>
<script>
(() => {
    const panel = document.querySelector('.cni-alumno');
    if (!panel) return;
    const sidebar = panel.querySelector('#cni-alumno-sidebar');
    const toggle = panel.querySelector('[data-sidebar-toggle]');
    const backdrop = panel.querySelector('[data-sidebar-backdrop]');
    const links = [...sidebar.querySelectorAll('.cni-alumno-menu a')];
    const mobile = window.matchMedia('(max-width: 680px)');
    let abierto = !mobile.matches;
    const actualizar = () => {
        panel.classList.toggle('is-sidebar-collapsed', !abierto);
        panel.classList.toggle('is-sidebar-open', abierto);
        toggle.setAttribute('aria-expanded', String(abierto));
        toggle.setAttribute('aria-label', abierto ? 'Cerrar menú lateral' : 'Abrir menú lateral');
        toggle.querySelector('.cni-alumno-sidebar-toggle-icon').textContent = abierto ? '❮' : '☰';
        backdrop.hidden = !mobile.matches || !abierto;
        sidebar.inert = !abierto;
    };
    const cerrarMovil = () => { if (mobile.matches) { abierto = false; actualizar(); toggle.focus(); } };
    toggle.addEventListener('click', () => { abierto = !abierto; actualizar(); });
    backdrop.addEventListener('click', cerrarMovil);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && abierto) cerrarMovil(); });

    const vistas = [...panel.querySelectorAll('[data-panel-view]')];
    const encabezados = { '#inicio': ['Inicio', '⌂'], '#intentos': ['Mis intentos', '▶'], '#historial': ['Historial', '◷'], '#perfil': ['Mi perfil', '♙'] };
    const mostrar = (destino, navegar = false) => {
        const sel = encabezados[destino] ? destino : '#inicio';
        panel.querySelector('[data-panel-header-title]').textContent = encabezados[sel][0];
        panel.querySelector('[data-panel-header-icon]').textContent = encabezados[sel][1];
        vistas.forEach((v) => { v.hidden = '#' + v.id !== sel; });
        links.forEach((a) => {
            const activa = a.getAttribute('href') === sel;
            a.classList.toggle('is-active', activa);
            activa ? a.setAttribute('aria-current', 'location') : a.removeAttribute('aria-current');
        });
        if (navegar) window.history.pushState(null, '', sel);
        window.scrollTo(0, 0);
    };
    links.forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); mostrar(a.getAttribute('href'), true); cerrarMovil(); }));
    // Evita doble envío al iniciar o continuar un examen.
    panel.querySelectorAll('[data-sm-iniciar]').forEach((f) => f.addEventListener('submit', () => {
        f.querySelector('button').disabled = true;
    }));
    window.addEventListener('popstate', () => mostrar(window.location.hash));
    window.addEventListener('hashchange', () => mostrar(window.location.hash));
    mostrar(window.location.hash);
    mobile.addEventListener('change', () => { abierto = !mobile.matches; actualizar(); });
    actualizar();
})();
</script>
@endsection
