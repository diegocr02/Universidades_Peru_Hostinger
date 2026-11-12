@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/panel-alumno.css') }}?v={{ filemtime(public_path('css/panel-alumno.css')) }}">
@endpush
@section('content')
@php
    $d = $dashboard;
    $esVistaPrevia = $esVistaPrevia ?? false;
    // Una sola plantilla para cinco áreas; los datos y la carrera vienen de MariaDB.
    $temaArea = config('panel_alumno.areas.'.$area->codigo);
    $rutaMascota = $temaArea['mascota_png'];
    $tituloArea = $temaArea['titulo'] ?? $area->nombre;
    $nombreCompleto = trim($usuario->nombres.' '.($usuario->apellidos ?? ''));
    $iniciales = mb_strtoupper(mb_substr($usuario->nombres, 0, 1).mb_substr($usuario->apellidos ?? '', 0, 1));
    $primeraPalabra = explode(' ', trim($usuario->nombres))[0];
    $seccionAlumno = $esVistaPrevia ? null : $usuario->seccion;
@endphp
<div class="cni-alumno" data-area="{{ $area->codigo }}" id="inicio">
    <button type="button" class="cni-alumno-sidebar-toggle"
            data-sidebar-toggle aria-controls="cni-alumno-sidebar"
            aria-expanded="true" aria-label="Cerrar menú lateral">
        <span class="cni-alumno-sidebar-toggle-icon" aria-hidden="true">❮</span>
        <span class="cni-alumno-sidebar-toggle-text">Menú</span>
    </button>
    <button type="button" class="cni-alumno-sidebar-backdrop" data-sidebar-backdrop
            aria-label="Cerrar menú lateral" hidden></button>
    <aside class="cni-alumno-sidebar" id="cni-alumno-sidebar" aria-label="Menú del estudiante">
        <div class="cni-alumno-sidebar-top">
            <a class="cni-alumno-marca" href="{{ route('alumno.panel') }}">
                <span class="cni-alumno-marca-icon" aria-hidden="true"><img src="{{ asset('images/escudo-cni.png') }}" width="36" height="36" alt=""></span>
                <span><strong>Simulador</strong><small>CNI · Imperial</small></span>
            </a>
            <span class="cni-alumno-area-chip"><span aria-hidden="true">{{ $temaArea['icono'] }}</span> {{ $tituloArea }}</span>
            <nav class="cni-alumno-menu" aria-label="Navegación del alumno">
                <a href="#perfil" class="is-active" aria-current="location"><span aria-hidden="true">♙</span> Mi perfil</a>
                <a href="#progreso"><span aria-hidden="true">▤</span> Mi progreso</a>
                <a href="#historial"><span aria-hidden="true">◷</span> Historial</a>
                <a href="{{ route('alumno.universidad.cambiar') }}"><span aria-hidden="true">&#8644;</span> Cambiar universidad</a>
        </nav>
        </div>
        <div class="cni-alumno-sidebar-bottom">
            @unless($esVistaPrevia)
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="cni-alumno-salir" type="submit">↪ &nbsp; Cerrar sesión</button>
                </form>
            @endunless
        </div>
    </aside>

    <div class="cni-alumno-workspace">
        <header class="cni-alumno-topbar">
            <div class="cni-alumno-top-title"><span class="cni-alumno-topicon" data-panel-header-icon aria-hidden="true">♙</span><span><strong data-panel-header-title>Mi perfil</strong></span></div>
            <div class="cni-alumno-top-actions">
                <form method="POST" action="{{ route('examen.independiente') }}" class="cni-alumno-header-examen-form">
                    @csrf
                    <button type="submit" class="cni-alumno-header-examen" @disabled($esVistaPrevia)><span aria-hidden="true">▶</span> Dar examen</button>
                </form>
                <div class="cni-alumno-top-profile"><span class="cni-alumno-avatar">{{ $iniciales }}</span><span><strong>{{ $nombreCompleto }}</strong><small>Estudiante</small></span></div>
            </div>
        </header>

        <main class="cni-alumno-content">
            @if($esVistaPrevia)
                <div class="cni-alumno-alert" role="status">Vista previa local · Sin inicio de sesión ni consultas a MySQL. Los indicadores están en cero porque no contienen datos ficticios.</div>
            @endif
            @if(session('status')) <div class="cni-alumno-alert cni-alumno-alert-ok" role="status">{{ session('status') }}</div> @endif

            <section class="cni-alumno-vista cni-alumno-vista-perfil" id="perfil" data-panel-view="perfil" aria-label="Mi perfil">
                <div class="cni-alumno-banner" aria-labelledby="bienvenida-titulo">
                <div class="cni-alumno-welcome">
                    <span class="cni-alumno-overline">TU ESPACIO DE PREPARACIÓN · ÁREA {{ $area->codigo }}</span>
                    <h1 id="bienvenida-titulo">¡Hola, {{ $primeraPalabra }}!</h1>
                    <h2>Bienvenido a {{ $tituloArea }}</h2>
                    <p>{{ $temaArea['descripcion'] }}</p>

                    <div class="cni-alumno-exam-cta">
                        <p>100 preguntas de tu área · 60 minutos · Preguntas aleatorias en cada nuevo intento.</p>
                    </div>
                </div>
                <div class="cni-alumno-banner-pattern" aria-hidden="true"></div>
                <img class="cni-alumno-mascota"
                     src="{{ asset($rutaMascota).'?v='.filemtime(public_path($rutaMascota)) }}"
                     width="520" height="520"
                     alt="Mascota del área de {{ $area->nombre }}">
                </div>

                <section class="cni-alumno-datos" aria-labelledby="datos-alumno-titulo">
                    <h2 id="datos-alumno-titulo"><span class="cni-alumno-datos-icono" aria-hidden="true">♙</span> Datos del alumno</h2>
                    <dl class="cni-alumno-datos-grid">
                        <div><dt>Nombre completo</dt><dd>{{ $nombreCompleto ?: 'No registrado' }}</dd></div>
                        <div><dt>Nombre de usuario</dt><dd>{{ $usuario->usuario ?: 'No registrado' }}</dd></div>
                        <div><dt>Grado</dt><dd>{{ $seccionAlumno?->grado ?? 'No asignado' }}</dd></div>
                        <div><dt>Sección</dt><dd>{{ $seccionAlumno?->nombre ?? 'No asignada' }}</dd></div>
                    </dl>
                </section>
            </section>

            <section class="cni-alumno-vista cni-alumno-vista-progreso" id="progreso" data-panel-view="progreso" aria-labelledby="progreso-titulo" hidden>
                @include('alumno.partials.progreso')
            </section>

            <section class="cni-alumno-vista cni-alumno-history" id="historial" data-panel-view="historial" aria-labelledby="historial-titulo" hidden>
                @include('alumno.partials.historial')
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

    toggle.addEventListener('click', () => {
        abierto = !abierto;
        actualizar();
    });
    backdrop.addEventListener('click', () => {
        abierto = false;
        actualizar();
        toggle.focus();
    });
    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && mobile.matches && abierto) {
            abierto = false;
            actualizar();
            toggle.focus();
        }
    });
    // Sólo una sección visible a la vez. Por defecto: bienvenida y acceso al examen.
    const vistas = [...panel.querySelectorAll('[data-panel-view]')];
    const destinos = ['#perfil', '#progreso', '#historial'];
    const mostrarSeccion = (destino, navegar = false) => {
        const seleccion = destinos.includes(destino) ? destino : '#perfil';
        const encabezados = {
            '#perfil': ['Mi perfil', '♙'],
            '#progreso': ['Mi progreso', '▤'],
            '#historial': ['Historial', '◷'],
        };
        const [titulo, icono] = encabezados[seleccion];
        panel.querySelector('[data-panel-header-title]').textContent = titulo;
        panel.querySelector('[data-panel-header-icon]').textContent = icono;
        vistas.forEach((vista) => {
            vista.hidden = '#' + vista.id !== seleccion;
        });
        links.forEach((enlace) => {
            const activa = enlace.getAttribute('href') === seleccion;
            enlace.classList.toggle('is-active', activa);
            if (activa) enlace.setAttribute('aria-current', 'location');
            else enlace.removeAttribute('aria-current');
        });
        if (navegar) window.history.pushState(null, '', seleccion);
        window.scrollTo(0, 0);
    };
    links.forEach((enlace) => enlace.addEventListener('click', (evento) => {
        evento.preventDefault();
        mostrarSeccion(enlace.getAttribute('href'), true);
        if (mobile.matches) {
            abierto = false;
            actualizar();
            toggle.focus();
        }
    }));
    panel.querySelectorAll('[data-go-panel]').forEach((boton) => {
        boton.addEventListener('click', () => mostrarSeccion(boton.getAttribute('data-go-panel'), true));
    });
    window.addEventListener('popstate', () => mostrarSeccion(window.location.hash));
    window.addEventListener('hashchange', () => mostrarSeccion(window.location.hash));
    mostrarSeccion(window.location.hash);
    mobile.addEventListener('change', () => {
        abierto = !mobile.matches;
        actualizar();
    });
    actualizar();
})();
</script>
@endsection
