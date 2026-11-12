@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/undc-panel.css') }}?v=3">
@endpush

@section('content')
@php
    // Solo se muestran simulacros UNDC publicados y autorizados para este estudiante.
    $simulacrosUndc = collect($simulacros)->filter(
        fn (array $s) => str_starts_with((string) ($s['nombre'] ?? ''), 'UNDC - Admisión Ordinaria Tipo ')
    );
    $tipoA = $simulacrosUndc->first(fn (array $s) => str_contains($s['nombre'], 'Tipo A'));
    $tipoB = $simulacrosUndc->first(fn (array $s) => str_contains($s['nombre'], 'Tipo B'));
    $idsUndc = $simulacrosUndc->pluck('id')->map(fn ($id) => (int) $id)->all();
    $historialUndc = $intentos->getCollection()->filter(
        fn ($i) => in_array((int) $i->simulacro_id, $idsUndc, true)
    );
    $nombreEstudiante = trim($usuario->nombres.' '.($usuario->apellidos ?? ''));
    $logoUndc = asset('images/undc/logo-undc.png');
@endphp

<div class="ud-shell">
    <aside class="ud-sidebar" aria-label="Navegación UNDC">
        <a class="ud-brand" href="{{ route('alumno.panel') }}">
            <img src="{{ $logoUndc }}" alt="Escudo Universidad Nacional de Cañete" loading="eager">
            <span><strong>UNDC</strong><small>Simulador de admisión</small></span>
        </a>

        <div class="ud-sidebar-label">PORTAL DEL POSTULANTE</div>
        <nav class="ud-nav" aria-label="Secciones del panel">
            <a href="#inicio" class="ud-active" data-ud-nav="inicio">⌂ <span>Inicio</span></a>
            <a href="#examenes" data-ud-nav="examenes">▤ <span>Exámenes</span></a>
            <a href="#resultados" data-ud-nav="resultados">◷ <span>Mis resultados</span></a>
                <a href="{{ route('alumno.universidad.cambiar') }}"><span aria-hidden="true">&#8644;</span> Cambiar universidad</a>
        </nav>

        <div class="ud-sidebar-bottom">
            <p>Universidad Nacional de Cañete</p>
            <small>Plataforma académica de preparación · No es un portal oficial de admisión.</small>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Cerrar sesión →</button>
            </form>
        </div>
    </aside>

    <div class="ud-main">
        <header class="ud-topbar" id="inicio">
            <div><span class="ud-eyebrow">SIMULADOR DE ADMISIÓN · UNDC</span><h1 data-ud-heading>Inicio</h1></div>
            <div class="ud-avatar" title="{{ $nombreEstudiante }}">{{ mb_strtoupper(mb_substr($usuario->nombres, 0, 1)) }}</div>
        </header>

        <main class="ud-content">
            @if(session('status'))
                <div class="ud-message" role="status">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="ud-message ud-message-error" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="ud-screen" id="inicio" data-ud-screen="inicio" aria-label="Inicio UNDC">
            <section class="ud-hero" aria-labelledby="ud-hero-title">
                <img class="ud-campus-photo" src="{{ asset('images/undc/campus-undc.webp') }}" alt="Ingreso principal a la Universidad Nacional de Cañete">
                <div class="ud-hero-text">
                    <span class="ud-pill">UNIVERSIDAD NACIONAL DE CAÑETE</span>
                    <h2 id="ud-hero-title">Tu camino a la universidad comienza aquí.</h2>
                    <p>¡Hola, {{ $usuario->nombres }}! Bienvenido a tu espacio de preparación para la Universidad Nacional de Cañete.</p>
                    <a class="ud-hero-link" href="#examenes">Ver exámenes disponibles <span aria-hidden="true">→</span></a>
                </div>
                <div class="ud-hero-emblem" aria-hidden="true">
                    <img src="{{ $logoUndc }}" alt="" loading="lazy">
                    <span>UNDC</span>
                </div>
            </section>

            <div class="ud-stats" aria-label="Resumen de la preparación">
                <div><span>Modalidades disponibles</span><strong>02</strong><small>Tipo A y Tipo B</small></div>
                <div><span>Preguntas por examen</span><strong>100</strong><small>19 asignaturas por perfil</small></div>
                <div><span>Tu historial</span><strong>{{ $historialUndc->count() }}</strong><small>Intentos visibles en esta página</small></div>
            </div>
            <div class="ud-welcome-note"><img src="{{ $logoUndc }}" alt="" width="60" height="60"><div><strong>Universidad Nacional de Cañete</strong><p>Practica a tu ritmo. Selecciona Exámenes para elegir Tipo A o Tipo B.</p></div></div>
            </section>

            <section id="examenes" class="ud-section ud-screen" data-ud-screen="examenes" hidden aria-labelledby="ud-examenes-title">
                <div class="ud-section-heading">
                    <div><span class="ud-eyebrow">PRÁCTICA DE ADMISIÓN</span><h2 id="ud-examenes-title">Elige tu tipo de examen</h2></div>
                    <span class="ud-count">2 perfiles</span>
                </div>
                <div class="ud-exams">
                    @foreach(['A' => $tipoA, 'B' => $tipoB] as $tipo => $examen)
                        <article class="ud-exam-card">
                            <div class="ud-exam-top"><span class="ud-exam-symbol">0{{ $tipo === 'A' ? '1' : '2' }}</span><span class="ud-exam-state">{{ $examen ? 'Disponible' : 'Pendiente de habilitar' }}</span></div>
                            <h3>Examen Tipo {{ $tipo }}</h3>
                            <p>Modelo de práctica para el proceso de admisión UNDC, con distribución por asignaturas.</p>
                            <div class="ud-exam-facts">
                                <span>▤ {{ $examen['total_preguntas'] ?? 100 }} preguntas</span>
                                <span>◷ {{ $examen['tiempo_limite'] ?? 60 }} min {{ $examen ? '' : '(previstos)' }}</span>
                                <span>◇ 5 alternativas</span>
                            </div>
                            @if($examen)
                                <a href="#tipo-{{ strtolower($tipo) }}" class="ud-start">Ver detalles del examen <span aria-hidden="true">→</span></a>
                            @else
                                <span class="ud-disabled">El banco aún no está habilitado en esta instalación.</span>
                            @endif
                        </article>
                    @endforeach
                </div>
                <p class="ud-notice">Los ejercicios y la puntuación son de demostración educativa; no constituyen un examen oficial de la UNDC. El tiempo y las reglas deberán verificarse con las bases vigentes.</p>
            </section>


            {{-- El alumno primero ve los detalles de cada tipo; el examen inicia únicamente al confirmar. --}}
            @foreach (['A' => $tipoA, 'B' => $tipoB] as $tipo => $examen)
                <section id="tipo-{{ strtolower($tipo) }}" class="ud-section ud-screen" data-ud-screen="tipo-{{ strtolower($tipo) }}" hidden aria-label="Examen Tipo {{ $tipo }}">
                    <a href="#examenes" class="ud-back">← Volver a exámenes</a>
                    <div class="ud-section-heading"><div><span class="ud-eyebrow">DETALLES DEL EXAMEN UNDC</span><h2>Examen Tipo {{ $tipo }}</h2></div></div>
                    <div class="ud-type-grid">
                        <div class="ud-type-courses">
                            <div class="ud-type-summary">
                                <div><strong>100</strong><span>Preguntas</span></div>
                                <div><strong>{{ $examen['tiempo_limite'] ?? 60 }}</strong><span>Minutos</span></div>
                                <div><strong>19</strong><span>Asignaturas</span></div>
                            </div>
                            <h3>Distribución de preguntas</h3>
                            @foreach (\App\Support\PerfilExamenUndc::distribucion($tipo) as $codigo => $cantidad)
                                <div class="ud-type-row"><span>{{ \App\Support\PerfilExamenUndc::CURSOS[$codigo][0] }}</span><strong>{{ $cantidad }}</strong></div>
                            @endforeach
                        </div>
                        <aside class="ud-type-start">
                            <img src="{{ $logoUndc }}" width="115" height="115" alt="Universidad Nacional de Cañete">
                            <h3>¿Listo para comenzar?</h3>
                            <p>Responderás 100 preguntas. El cronómetro se inicia cuando entras al examen.</p>
                            @if ($examen)
                                <form method="POST" action="{{ route('examen.iniciar', ['simulacro' => $examen['id']]) }}">
                                    @csrf
                                    <button type="submit" class="ud-start">Comenzar examen Tipo {{ $tipo }} →</button>
                                </form>
                            @else
                                <div class="ud-disabled">Examen pendiente de habilitar en tu cuenta.</div>
                            @endif
                            <small>Examen de práctica educativa. Duración y puntajes definidos para la demostración.</small>
                        </aside>
                    </div>
                </section>
            @endforeach

            <section id="resultados" class="ud-section ud-screen" data-ud-screen="resultados" hidden aria-labelledby="ud-resultados-title">
                <div class="ud-section-heading"><div><span class="ud-eyebrow">SEGUIMIENTO</span><h2 id="ud-resultados-title">Mis resultados</h2></div></div>
                <div class="ud-history">
                    @forelse($historialUndc as $intento)
                        <div class="ud-history-item">
                            <span><strong>Intento #{{ $intento->id }}</strong><small>{{ $intento->fecha_inicio }}</small></span>
                            <span class="ud-history-score">{{ $intento->puntaje_total === null ? 'En curso' : number_format((float) $intento->puntaje_total, 2).' pts' }}</span>
                            @if($intento->estado === 'en_curso')
                                <a href="{{ route('examen', ['intento' => $intento->id]) }}">Continuar →</a>
                            @else
                                <a href="{{ route('alumno.resultados', ['intento' => $intento->id]) }}">Ver resultado →</a>
                            @endif
                        </div>
                    @empty
                        <div class="ud-empty">Aún no tienes resultados UNDC. Comienza un examen para registrar tu primer intento.</div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
</div>
<script>
(() => {
    const root = document.querySelector('.ud-shell');
    if (!root) return;
    const headings = {inicio:'Inicio',examenes:'Exámenes','tipo-a':'Examen Tipo A','tipo-b':'Examen Tipo B',resultados:'Mis resultados'};
    const show = () => {
        const requested = location.hash.slice(1);
        const page = Object.prototype.hasOwnProperty.call(headings, requested) ? requested : 'inicio';
        root.querySelectorAll('[data-ud-screen]').forEach(el => { el.hidden = el.dataset.udScreen !== page; });
        root.querySelector('[data-ud-heading]').textContent = headings[page];
        const nav = page.startsWith('tipo-') ? 'examenes' : page;
        root.querySelectorAll('[data-ud-nav]').forEach(link => {
            const active = link.dataset.udNav === nav;
            link.classList.toggle('ud-active', active);
            if (active) link.setAttribute('aria-current','page');
            else link.removeAttribute('aria-current');
        });
        window.scrollTo(0,0);
    };
    window.addEventListener('hashchange',show);
    show();
})();
</script>

@endsection
