@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/panel-alumno.css') }}?v={{ filemtime(public_path('css/panel-alumno.css')) }}">
<link rel="stylesheet" href="{{ asset('css/panel-unfv.css') }}?v={{ filemtime(public_path('css/panel-unfv.css')) }}">
@endpush
@section('content')
@php
    $temaArea = config('panel_alumno.areas.C');
    $rutaMascota = $temaArea['mascota_png'];
    $nombreCompleto = trim($usuario->nombres.' '.($usuario->apellidos ?? ''));
    $iniciales = mb_strtoupper(mb_substr($usuario->nombres, 0, 1).mb_substr($usuario->apellidos ?? '', 0, 1));
    $primeraPalabra = explode(' ', trim($usuario->nombres))[0];
    $seccionAlumno = $usuario->seccion;
    $fecha = fn ($valor) => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y H:i') : '—';
    $puntos = fn ($valor) => number_format((float) $valor, 2, ',', '.');
    $enCurso = $unfv['en_curso'];
    $ultimo = $unfv['ultimo'];
    $mejor = $unfv['mejor'];
@endphp
<div class="cni-alumno cni-unfv" data-area="C" id="inicio">
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
            <span class="cni-alumno-area-chip"><span aria-hidden="true">{{ $temaArea['icono'] }}</span> {{ $temaArea['titulo'] }}</span>
            <nav class="cni-alumno-menu" aria-label="Navegación del alumno">
                <a href="#examen" class="is-active" aria-current="location"><span aria-hidden="true">▶</span> Examen UNFV</a>
                <a href="#progreso"><span aria-hidden="true">▤</span> Mi progreso</a>
                <a href="#historial"><span aria-hidden="true">◷</span> Historial</a>
                <a href="#perfil"><span aria-hidden="true">♙</span> Mi perfil</a>
                <a href="{{ route('alumno.universidad.cambiar') }}"><span aria-hidden="true">&#8644;</span> Cambiar universidad</a>
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
            <div class="cni-alumno-top-title"><span class="cni-alumno-topicon" data-panel-header-icon aria-hidden="true">▶</span><span><strong data-panel-header-title>Examen UNFV</strong></span></div>
            <div class="cni-alumno-top-actions">
                <div class="cni-alumno-top-profile"><span class="cni-alumno-avatar">{{ $iniciales }}</span><span><strong>{{ $nombreCompleto }}</strong><small>Estudiante</small></span></div>
            </div>
        </header>

        <main class="cni-alumno-content">
            @if(session('status')) <div class="cni-alumno-alert cni-alumno-alert-ok" role="status">{{ session('status') }}</div> @endif
            @if($errors->any())
                <div class="cni-alumno-alert cni-unfv-alerta-error" role="alert">@foreach($errors->all() as $mensaje)<p>{{ $mensaje }}</p>@endforeach</div>
            @endif

            {{-- Examen: práctica general UNFV --}}
            <section class="cni-alumno-vista" id="examen" data-panel-view="examen" aria-labelledby="unfv-titulo">
                <div class="cni-alumno-banner">
                    <div class="cni-alumno-welcome">
                        <span class="cni-alumno-overline">PRÁCTICA DE ADMISIÓN · FORMATO UNFV</span>
                        <h1 id="unfv-titulo">¡Hola, {{ $primeraPalabra }}!</h1>
                        <h2>{{ $temaArea['descripcion'] }}</h2>
                        <p>Práctica general con preguntas de habilidades y conocimientos. Cada intento sortea preguntas nuevas del banco.</p>
                        <div class="cni-unfv-chips">
                            <span><strong>{{ $unfv['total'] }}</strong> preguntas</span>
                            <span><strong>{{ $unfv['minutos'] }}</strong> minutos</span>
                            <span><strong>{{ $unfv['cuotas']->count() }}</strong> cursos</span>
                        </div>
                    </div>
                    <div class="cni-alumno-banner-pattern" aria-hidden="true"></div>
                    <img class="cni-alumno-mascota" src="{{ asset($rutaMascota).'?v='.filemtime(public_path($rutaMascota)) }}"
                         width="520" height="520" alt="Logo de la Universidad Nacional Federico Villarreal">
                </div>

                <div class="cni-unfv-stats" aria-label="Resumen de tu preparación">
                    <div><span>Intentos calificados</span><strong>{{ $unfv['cantidad_calificados'] }}</strong><small>Práctica general UNFV</small></div>
                    <div><span>Mejor puntaje</span><strong>{{ $mejor ? $puntos($mejor->puntaje_total) : '—' }}</strong><small>de {{ $puntos($mejor->maximo ?? $unfv['escala']['maximo']) }} pts</small></div>
                    <div><span>Rendimiento promedio</span><strong>{{ $unfv['promedio'] !== null ? number_format($unfv['promedio'], 1, ',', '.').'%' : '—' }}</strong><small>del puntaje máximo</small></div>
                    <div><span>Último intento</span><strong>{{ $ultimo ? $puntos($ultimo->puntaje_total) : '—' }}</strong><small>{{ $ultimo ? $fecha($ultimo->fecha_fin ?? $ultimo->fecha_inicio) : 'Aún sin rendir' }}</small></div>
                </div>

                <div class="cni-unfv-grid">
                    <article class="cni-unfv-examen is-{{ $enCurso ? 'en_curso' : ($ultimo ? 'finalizado' : 'disponible') }}" aria-labelledby="unfv-examen-titulo">
                        <div class="cni-unfv-examen-cabecera">
                            <span class="cni-unfv-kicker">Práctica general</span>
                            <span class="cni-unfv-estado">{{ $enCurso ? 'En curso' : 'Disponible' }}</span>
                        </div>
                        <h3 id="unfv-examen-titulo">{{ $unfv['banco_unfv'] ? 'Examen de práctica UNFV' : 'Examen general · Área C' }}</h3>
                        <p class="cni-unfv-meta">{{ $unfv['total'] }} preguntas · {{ $unfv['minutos'] }} minutos · 5 alternativas · máx. {{ $puntos($unfv['escala']['maximo']) }} pts</p>
                        <p class="cni-unfv-meta">Correcta +{{ $puntos($unfv['escala']['correcta']) }} · Incorrecta −{{ rtrim(rtrim(number_format($unfv['escala']['incorrecta'], 3, ',', '.'), '0'), ',') }} · En blanco 0</p>

                        @foreach($unfv['cuotas']->groupBy('grupo') as $grupo => $cursos)
                            <h4 class="cni-unfv-grupo">{{ $grupo }} <small>{{ $unfv['grupos'][$grupo] }} preguntas</small></h4>
                            <ul class="cni-unfv-cuotas">
                                @foreach($cursos as $cuota)
                                    <li><span>{{ $cuota['curso'] }}</span><strong>{{ $cuota['cantidad'] }}</strong></li>
                                @endforeach
                            </ul>
                        @endforeach
                    </article>

                    <aside class="cni-unfv-inicio" aria-labelledby="unfv-inicio-titulo">
                        <img src="{{ asset($rutaMascota).'?v='.filemtime(public_path($rutaMascota)) }}" width="96" height="96" alt="">
                        <h3 id="unfv-inicio-titulo">{{ $enCurso ? 'Tienes un intento en curso' : '¿Listo para practicar?' }}</h3>
                        @if($enCurso)
                            <p>
                                {{ $enCurso->respondidas }} de {{ $enCurso->total_preguntas }} respondidas
                                @if($enCurso->segundos_restantes !== null)
                                    · quedan {{ intdiv($enCurso->segundos_restantes, 60) }} min
                                @endif
                            </p>
                        @else
                            <p>Responderás {{ $unfv['total'] }} preguntas. El cronómetro empieza cuando entras al examen.</p>
                        @endif

                        <form method="POST" action="{{ route('examen.independiente') }}">@csrf
                            <button type="submit" class="cni-alumno-primary">
                                {{ $enCurso ? 'Continuar examen' : ($ultimo ? 'Volver a rendir' : 'Comenzar examen') }}
                                <span aria-hidden="true">→</span>
                            </button>
                        </form>
                        @unless($unfv['banco_unfv'])
                            <p class="cni-unfv-no-disponible">El banco de práctica UNFV no está cargado: se usará el examen general del área C.</p>
                        @endunless
                        @if(! $enCurso && $ultimo)
                            <a class="cni-unfv-enlace" href="{{ route('alumno.resultados', ['intento' => $ultimo->id]) }}">Ver último resultado</a>
                        @endif
                        <small>Práctica educativa; no es un examen oficial de la UNFV.</small>
                    </aside>
                </div>

                <p class="cni-unfv-aviso">La distribución corresponde a una práctica general del colegio, no a la prueba oficial. El ingreso a la UNFV depende del proceso de admisión, del orden de mérito y de las vacantes.</p>
            </section>

            <section class="cni-alumno-vista cni-alumno-vista-progreso" id="progreso" data-panel-view="progreso" aria-labelledby="progreso-titulo" hidden>
                @include('alumno.partials.progreso')
            </section>

            {{-- Historial --}}
            <section class="cni-alumno-vista cni-alumno-history" id="historial" data-panel-view="historial" aria-labelledby="historial-titulo" hidden>
                <div class="cni-historial-tarjeta">
                    <div class="cni-historial-cabecera"><h2 id="historial-titulo">Historial de prácticas UNFV</h2></div>
                    <div class="cni-historial-tabla-marco" tabindex="0" role="region" aria-label="Tabla del historial de prácticas">
                        <table class="cni-historial-tabla">
                            <thead><tr>
                                <th scope="col">Intento</th><th scope="col">Fecha</th><th scope="col">Examen</th><th scope="col">Respondidas</th>
                                <th scope="col">Puntaje</th><th scope="col">Rendimiento</th><th scope="col">Acciones</th>
                            </tr></thead>
                            <tbody>
                                @forelse($unfv['historial'] as $intento)
                                    <tr>
                                        <th scope="row">#{{ str_pad((string) $intento->id, 3, '0', STR_PAD_LEFT) }}</th>
                                        <td>{{ $fecha($intento->fecha_inicio) }}</td>
                                        <td>{{ $intento->examen }}</td>
                                        <td>{{ $intento->respondidas }} / {{ $intento->total_preguntas }}</td>
                                        <td class="cni-historial-puntaje">
                                            @if($intento->calificado)
                                                {{ $puntos($intento->puntaje_total) }} @if($intento->maximo > 0) / {{ $puntos($intento->maximo) }} @endif
                                            @else — @endif
                                        </td>
                                        <td>{{ $intento->porcentaje !== null ? number_format($intento->porcentaje, 1, ',', '.').'%' : '—' }}</td>
                                        <td>
                                            @if($intento->en_curso)
                                                <a class="cni-historial-resultado" href="{{ route('examen', ['intento' => $intento->id]) }}">Continuar</a>
                                            @elseif($intento->calificado)
                                                <a class="cni-historial-resultado" href="{{ route('alumno.resultados', ['intento' => $intento->id]) }}">Ver resultado</a>
                                            @else
                                                <span>Sin calificar</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="cni-historial-vacio">Aún no tienes exámenes de la Villarreal.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- Perfil --}}
            <section class="cni-alumno-vista" id="perfil" data-panel-view="perfil" aria-labelledby="datos-alumno-titulo" hidden>
                <section class="cni-alumno-datos">
                    <h2 id="datos-alumno-titulo"><span class="cni-alumno-datos-icono" aria-hidden="true">♙</span> Datos del alumno</h2>
                    <dl class="cni-alumno-datos-grid">
                        <div><dt>Nombre completo</dt><dd>{{ $nombreCompleto ?: 'No registrado' }}</dd></div>
                        <div><dt>Nombre de usuario</dt><dd>{{ $usuario->usuario ?: 'No registrado' }}</dd></div>
                        <div><dt>Grado</dt><dd>{{ $seccionAlumno?->grado ?? 'No asignado' }}</dd></div>
                        <div><dt>Sección</dt><dd>{{ $seccionAlumno?->nombre ?? 'No asignada' }}</dd></div>
                        <div><dt>Universidad</dt><dd>{{ $temaArea['titulo'] }} · Área {{ $area->codigo }}</dd></div>
                        @if($carrera)<div><dt>Carrera de interés</dt><dd>{{ $carrera->nombre }}</dd></div>@endif
                    </dl>
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
    const links = [...sidebar.querySelectorAll('.cni-alumno-menu a[href^="#"]')];
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
    const encabezados = {
        '#examen': ['Examen UNFV', '▶'], '#progreso': ['Mi progreso', '▤'],
        '#historial': ['Historial', '◷'], '#perfil': ['Mi perfil', '♙'],
    };
    const mostrar = (destino, navegar = false) => {
        const sel = encabezados[destino] ? destino : '#examen';
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
    // Evita doble envío al iniciar el examen.
    panel.querySelectorAll('.cni-unfv-inicio form').forEach((f) => f.addEventListener('submit', () => {
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
