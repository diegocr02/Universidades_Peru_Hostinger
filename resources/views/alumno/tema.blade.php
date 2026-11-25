@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/alumno/panel-alumno.css') }}?v={{ filemtime(public_path('css/alumno/panel-alumno.css')) }}">
@endpush
@section('content')
<div class="cni-alumno cni-alumno-topic-detail" data-area="{{ $area->codigo }}">
    <aside class="cni-alumno-sidebar" aria-label="Menú del estudiante">
        <div><a class="cni-alumno-marca" href="{{ route('alumno.panel') }}"><span class="cni-alumno-marca-icon" aria-hidden="true">✦</span><span><strong>Simulador</strong><small>CNI · Imperial</small></span></a><nav class="cni-alumno-menu"><a href="{{ route('alumno.panel') }}#perfil">← Volver al panel</a><a href="{{ route('alumno.panel') }}#intentos">▤ &nbsp; Simulacros</a></nav></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="cni-alumno-salir" type="submit">↪ &nbsp; Cerrar sesión</button></form>
    </aside>
    <div class="cni-alumno-workspace">
        <header class="cni-alumno-topbar"><div class="cni-alumno-top-title"><span class="cni-alumno-topicon" aria-hidden="true">▣</span><strong>Detalle de tema · {{ $area->nombre }}</strong></div><a class="cni-alumno-text-link" href="{{ route('alumno.panel') }}#perfil">Volver a temas</a></header>
        <main class="cni-alumno-content">
            <section class="cni-alumno-detail-card"><span class="cni-alumno-section-kicker">BANCO DE PREGUNTAS · {{ $tema->asignatura }}</span><h1>{{ $tema->nombre }}</h1>
                <p>Consulta tu avance real en este tema del banco académico CNI.</p>
                <div class="cni-alumno-detail-stats"><article><strong>{{ $tema->total }}</strong><span>Preguntas disponibles</span></article><article><strong>{{ $tema->realizadas }}</strong><span>Preguntas vistas</span></article><article><strong>{{ $tema->avance }}%</strong><span>Avance del tema</span></article></div>
                <div class="cni-alumno-progress-track" role="progressbar" aria-label="Avance de {{ $tema->nombre }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $tema->avance }}"><span style="width:{{ $tema->avance }}%"></span></div>
                <p class="cni-alumno-progress-note">Las preguntas se contabilizan una sola vez aunque aparezcan en distintos intentos.</p>
                <a class="cni-alumno-primary cni-alumno-detail-action" href="{{ route('alumno.panel') }}#intentos">Ver simulacros de mi área →</a>
            </section>
        </main>
    </div>
</div>
@endsection
