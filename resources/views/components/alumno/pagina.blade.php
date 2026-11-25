@props(['ctx', 'usuario', 'activo', 'titulo', 'icono' => '✦', 'estilos' => []])
{{--
    Estructura común de las páginas de estudio del alumno (práctica, tutor, historial, guardadas):
    mismo menú lateral y barra superior que su panel, con los colores de su universidad.
    wire:ignore.self conserva el estado abierto/cerrado del menú cuando Livewire actualiza.
--}}
@php
    $menu = collect($ctx['menu']);
    $posicion = $menu->search(fn ($item) => in_array($item[2], ['historial', 'resultados'], true));
    $posicion = $posicion === false ? $menu->count() : $posicion + 1;
    $nombreCompleto = trim($usuario->nombres.' '.($usuario->apellidos ?? ''));
    $iniciales = mb_strtoupper(mb_substr($usuario->nombres, 0, 1).mb_substr($usuario->apellidos ?? '', 0, 1));
    $estudio = array_filter([
        'practica' => [route('alumno.practica'), '✎', 'Práctica'],
        'progreso' => [route('alumno.progreso'), '📈', 'Mi avance'],
        'tutor' => config('tutor.enabled') ? [route('alumno.tutor'), '✦', 'Tutor IA'] : null,
    ]);
    $hoja = fn (string $ruta) => asset($ruta).'?v='.@filemtime(public_path($ruta));
@endphp
<div class="cni-alumno cni-estudio" data-area="{{ $ctx['codigo'] }}" wire:ignore.self data-pagina-estudio>
    <link rel="stylesheet" href="{{ $hoja('css/alumno/panel-alumno.css') }}">
    <link rel="stylesheet" href="{{ $hoja('css/alumno/estudio.css') }}">
    @foreach($estilos as $estilo)<link rel="stylesheet" href="{{ $hoja($estilo) }}">@endforeach

    <button type="button" class="cni-alumno-sidebar-toggle" data-sidebar-toggle aria-controls="cni-alumno-sidebar"
            aria-expanded="true" aria-label="Cerrar menú lateral" wire:ignore>
        <span class="cni-alumno-sidebar-toggle-icon" aria-hidden="true">❮</span>
        <span class="cni-alumno-sidebar-toggle-text">Menú</span>
    </button>
    <button type="button" class="cni-alumno-sidebar-backdrop" data-sidebar-backdrop aria-label="Cerrar menú lateral" hidden wire:ignore></button>
    <aside class="cni-alumno-sidebar" id="cni-alumno-sidebar" aria-label="Menú del estudiante" wire:ignore>
        <div class="cni-alumno-sidebar-top">
            <a class="cni-alumno-marca" href="{{ route('alumno.panel') }}">
                <span class="cni-alumno-marca-icon" aria-hidden="true"><img src="{{ asset('images/cni/escudo-cni.webp') }}" width="36" height="36" alt=""></span>
                <span><strong>Simulador</strong><small>CNI · Imperial</small></span>
            </a>
            <span class="cni-alumno-area-chip"><span aria-hidden="true">{{ config('panel_alumno.areas.'.$ctx['codigo'].'.icono') }}</span> {{ $ctx['universidad'] }}</span>
            <nav class="cni-alumno-menu" aria-label="Navegación del alumno">
                @foreach($menu as $indice => [$etiqueta, $simbolo, $ancla])
                    @if($indice === $posicion)
                        @foreach($estudio as $clave => [$url, $ico, $texto])
                            <a href="{{ $url }}" @class(['is-active' => $activo === $clave]) @if($activo === $clave) aria-current="page" @endif><span aria-hidden="true">{{ $ico }}</span> {{ $texto }}</a>
                        @endforeach
                    @endif
                    <a href="{{ route('alumno.panel') }}#{{ $ancla }}"><span aria-hidden="true">{{ $simbolo }}</span> {{ $etiqueta }}</a>
                @endforeach
                @if($posicion >= $menu->count())
                    @foreach($estudio as $clave => [$url, $ico, $texto])
                        <a href="{{ $url }}" @class(['is-active' => $activo === $clave]) @if($activo === $clave) aria-current="page" @endif><span aria-hidden="true">{{ $ico }}</span> {{ $texto }}</a>
                    @endforeach
                @endif
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
            <div class="cni-alumno-top-title">
                <span class="estudio-top-icono" aria-hidden="true">{{ $icono }}</span>
                <h1 class="estudio-top-titulo">{{ $titulo }}</h1>
            </div>
            <div class="cni-alumno-top-actions">
                <div class="cni-alumno-top-profile"><span class="cni-alumno-avatar">{{ $iniciales }}</span><span><strong>{{ $nombreCompleto }}</strong><small>Estudiante</small></span></div>
            </div>
        </header>
        {{ $slot }}
    </div>

    <script>
    (() => {
        // Menú lateral: mismo comportamiento que los paneles (Escape y fondo lo cierran en celular).
        const raiz = document.currentScript.closest('[data-pagina-estudio]');
        const sidebar = raiz.querySelector('#cni-alumno-sidebar');
        const toggle = raiz.querySelector('[data-sidebar-toggle]');
        const backdrop = raiz.querySelector('[data-sidebar-backdrop]');
        const movil = window.matchMedia('(max-width: 680px)');
        let abierto = !movil.matches;
        const actualizar = () => {
            raiz.classList.toggle('is-sidebar-collapsed', !abierto);
            raiz.classList.toggle('is-sidebar-open', abierto);
            toggle.setAttribute('aria-expanded', String(abierto));
            toggle.setAttribute('aria-label', abierto ? 'Cerrar menú lateral' : 'Abrir menú lateral');
            toggle.querySelector('.cni-alumno-sidebar-toggle-icon').textContent = abierto ? '❮' : '☰';
            backdrop.hidden = !movil.matches || !abierto;
            sidebar.inert = !abierto;
        };
        const cerrarMovil = () => { if (movil.matches) { abierto = false; actualizar(); toggle.focus(); } };
        toggle.addEventListener('click', () => { abierto = !abierto; actualizar(); });
        backdrop.addEventListener('click', cerrarMovil);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && abierto) cerrarMovil(); });
        movil.addEventListener('change', () => { abierto = !movil.matches; actualizar(); });
        actualizar();
    })();
    </script>
</div>
