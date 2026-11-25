@php
    $usuario = auth('web')->user();
    $nombre = trim($usuario->nombres.' '.($usuario->apellidos ?? ''));
    $iniciales = mb_strtoupper(mb_substr($usuario->nombres, 0, 1).mb_substr($usuario->apellidos ?? '', 0, 1));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Simulador de examen' }} · CNI</title>
    <link rel="stylesheet" href="{{ asset('css/examen/examen.css').'?v='.filemtime(public_path('css/examen/examen.css')) }}">
    @stack('styles')
    @livewireStyles
    @if(auth('web')->user()?->rol === 'estudiante') @include('alumno.partials.accesibilidad-cabecera') @endif
</head>
<body>
    <header class="cni-examen-barra">
        <a class="cni-examen-marca" href="{{ route('alumno.panel') }}" aria-label="CNI · Volver a mi panel">
            <img src="{{ asset('images/cni/escudo-cni-examen.webp') }}" width="52" height="58" alt="Escudo del Colegio Nacional de Imperial">
            <span><strong>CNI</strong><small>Colegio Nacional de Imperial</small></span>
        </a>
        <span class="cni-examen-avatar" title="{{ $nombre }}" aria-label="{{ $nombre }}">{{ $iniciales }}</span>
    </header>
    <main class="cni-examen-contenido" aria-label="Simulador de examen">
        {{ $slot ?? '' }}
        @yield('content')
    </main>
    @stack('scripts')
    @if(auth('web')->user()?->rol === 'estudiante') <x-alumno.preferencias-accesibilidad /> @endif
    @livewireScripts
</body>
</html>
