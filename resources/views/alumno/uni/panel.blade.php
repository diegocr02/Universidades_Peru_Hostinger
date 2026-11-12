@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/panel-alumno.css') }}?v={{ filemtime(public_path('css/panel-alumno.css')) }}">
<link rel="stylesheet" href="{{ asset('css/panel-uni.css') }}?v={{ filemtime(public_path('css/panel-uni.css')) }}">
<link rel="stylesheet" href="{{ asset('css/referencia-ingreso.css').'?v='.filemtime(public_path('css/referencia-ingreso.css')) }}">
@endpush
@section('content')
@php
    $temaArea = config('panel_alumno.areas.B');
    $rutaMascota = $temaArea['mascota_png'];
    $nombreCompleto = trim($usuario->nombres.' '.($usuario->apellidos ?? ''));
    $iniciales = mb_strtoupper(mb_substr($usuario->nombres, 0, 1).mb_substr($usuario->apellidos ?? '', 0, 1));
    $primeraPalabra = explode(' ', trim($usuario->nombres))[0];
    $seccionAlumno = $usuario->seccion;
    $fecha = fn ($valor) => $valor ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y H:i') : '—';
    $puntos = fn ($valor) => number_format((float) $valor, 2, ',', '.');
    $estados = ['disponible' => 'Disponible', 'en_curso' => 'En curso', 'finalizado' => 'Finalizado'];
@endphp
<div class="cni-alumno cni-uni" data-area="B" id="inicio">
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
                <a href="#examen" class="is-active" aria-current="location"><span aria-hidden="true">▶</span> Examen UNI</a>
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
            <div class="cni-alumno-top-title"><span class="cni-alumno-topicon" data-panel-header-icon aria-hidden="true">▶</span><span><strong data-panel-header-title>Examen UNI</strong></span></div>
            <div class="cni-alumno-top-actions">
                <div class="cni-alumno-top-profile"><span class="cni-alumno-avatar">{{ $iniciales }}</span><span><strong>{{ $nombreCompleto }}</strong><small>Estudiante</small></span></div>
            </div>
        </header>

        <main class="cni-alumno-content">
            @if(session('status')) <div class="cni-alumno-alert cni-alumno-alert-ok" role="status">{{ session('status') }}</div> @endif
            @if($errors->any())
                <div class="cni-alumno-alert cni-uni-alerta-error" role="alert">@foreach($errors->all() as $mensaje)<p>{{ $mensaje }}</p>@endforeach</div>
            @endif

            {{-- Examen: tres jornadas independientes --}}
            <section class="cni-alumno-vista" id="examen" data-panel-view="examen" aria-labelledby="uni-titulo">
                <div class="cni-alumno-banner">
                    <div class="cni-alumno-welcome">
                        <span class="cni-alumno-overline">EXAMEN DE ADMISIÓN · FORMATO UNI</span>
                        <h1 id="uni-titulo">¡Hola, {{ $primeraPalabra }}!</h1>
                        <h2>{{ $temaArea['descripcion'] }}</h2>
                        <p>El examen se rinde en tres días. Cada día es un examen propio de {{ \App\Support\PerfilExamenCni::UNI_MINUTOS }} minutos; puedes rendirlos en cualquier orden.</p>
                        <div class="cni-uni-avance" role="progressbar" aria-label="Días completados" aria-valuemin="0" aria-valuemax="{{ $uni['dias']->count() }}" aria-valuenow="{{ $uni['dias_completados'] }}">
                            <strong>{{ $uni['dias_completados'] }} de {{ $uni['dias']->count() }}</strong> días completados · {{ $uni['total_preguntas'] }} preguntas en total
                        </div>
                    </div>
                    <div class="cni-alumno-banner-pattern" aria-hidden="true"></div>
                    <img class="cni-alumno-mascota" src="{{ asset($rutaMascota).'?v='.filemtime(public_path($rutaMascota)) }}"
                         width="520" height="520" alt="Logo de la Universidad Nacional de Ingeniería">
                </div>

                <div class="cni-uni-dias">
                    @foreach($uni['dias'] as $dia)
                        <article class="cni-uni-dia is-{{ $dia['estado'] }}" aria-labelledby="dia-{{ $dia['numero'] }}-titulo">
                            <header class="cni-uni-dia-cabecera">
                                <span class="cni-uni-dia-numero">Día {{ $dia['numero'] }}</span>
                                <span class="cni-uni-estado">{{ $estados[$dia['estado']] }}</span>
                            </header>
                            <h3 id="dia-{{ $dia['numero'] }}-titulo">{{ $dia['titulo'] }}</h3>
                            <p class="cni-uni-dia-meta">{{ $dia['total'] }} preguntas · {{ $dia['minutos'] }} minutos · máx. {{ $puntos($dia['escala']['maximo']) }} pts</p>
                            <p class="cni-uni-dia-meta">Correcta +{{ $puntos($dia['escala']['correcta']) }} · Incorrecta −{{ $puntos($dia['escala']['incorrecta']) }} · En blanco 0</p>
                            <ul class="cni-uni-cuotas">
                                @foreach($dia['cuotas'] as $cuota)
                                    <li><span>{{ $cuota['curso'] }}</span><strong>{{ $cuota['cantidad'] }}</strong></li>
                                @endforeach
                            </ul>

                            @if($dia['en_curso'])
                                <p class="cni-uni-dia-nota">
                                    {{ $dia['en_curso']->respondidas }} de {{ $dia['en_curso']->total_preguntas }} respondidas
                                    @if($dia['en_curso']->segundos_restantes !== null)
                                        · quedan {{ intdiv($dia['en_curso']->segundos_restantes, 60) }} min
                                    @endif
                                </p>
                            @elseif($dia['ultimo'])
                                <p class="cni-uni-dia-nota">
                                    Último intento: {{ $fecha($dia['ultimo']->fecha_fin ?? $dia['ultimo']->fecha_inicio) }}
                                    @if($dia['calificacion'])
                                        · {{ $puntos($dia['calificacion']['puntaje']) }} / {{ $puntos($dia['calificacion']['maximo']) }} pts
                                    @endif
                                </p>
                            @endif

                            <div class="cni-uni-dia-acciones">
                                <form method="POST" action="{{ route('examen.uni.dia', ['dia' => $dia['numero']]) }}">@csrf
                                    <button type="submit" class="cni-alumno-primary">
                                        {{ $dia['en_curso'] ? 'Continuar' : ($dia['ultimo'] ? 'Volver a rendir' : 'Iniciar día '.$dia['numero']) }}
                                        <span aria-hidden="true">→</span>
                                    </button>
                                </form>
                                @if(! $dia['en_curso'] && $dia['ultimo']?->calificado)
                                    <a class="cni-uni-enlace" href="{{ route('alumno.resultados', ['intento' => $dia['ultimo']->id]) }}">Ver resultado</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                @include('alumno.partials.referencia-ingreso', ['evaluacionIngreso' => app(\App\Services\Alumno\ReferenciaIngresoService::class)->uni($uni['resumen'])])

                <section class="cni-uni-resumen" aria-labelledby="resumen-titulo">
                    @php $r = $uni['resumen']; @endphp
                    <h2 id="resumen-titulo">Resumen de los tres días
                        @unless($r['completo'])<span class="cni-uni-estado">Resultado parcial</span>@endunless
                    </h2>
                    @if($r['dias_rendidos'] === 0)
                        <p class="cni-alumno-empty">Aún no tienes días calificados. El resumen se completa al rendir los tres días.</p>
                    @else
                        <dl class="cni-uni-resumen-grid">
                            @foreach($uni['dias'] as $dia)
                                <div><dt>Día {{ $dia['numero'] }} · {{ $dia['titulo'] }}</dt>
                                    <dd>{{ $dia['calificacion'] ? $puntos($dia['calificacion']['puntaje']) : '—' }} <small>/ {{ $puntos($dia['escala']['maximo']) }}</small></dd></div>
                            @endforeach
                            <div><dt>Nota referencial</dt><dd>{{ $r['nota_vigesimal'] !== null ? $puntos($r['nota_vigesimal']) : '—' }} <small>/ {{ $r['escala_vigesimal'] }}</small></dd></div>
                        </dl>
                        @unless($r['completo'])
                            <p class="cni-uni-aviso"><strong>Resultado parcial:</strong> faltan {{ $uni['dias']->count() - $r['dias_rendidos'] }} día(s). La nota referencial aparece al completar los tres.</p>
                        @endunless
                        @if($r['incorrectas'] > 0)
                            <p class="cni-uni-aviso">Tu puntaje incluye penalizaciones por respuestas incorrectas. Las preguntas en blanco no generan descuento.</p>
                        @endif
                        <p class="cni-uni-aviso">Se toma el último intento calificado de cada día.</p>
                    @endif
                </section>
            </section>

            {{-- Historial --}}
            <section class="cni-alumno-vista cni-alumno-history" id="historial" data-panel-view="historial" aria-labelledby="historial-titulo" hidden>
                <div class="cni-historial-tarjeta">
                    <div class="cni-historial-cabecera"><h2 id="historial-titulo">Historial de exámenes</h2></div>
                    <div class="cni-historial-tabla-marco" tabindex="0" role="region" aria-label="Tabla del historial de exámenes">
                        <table class="cni-historial-tabla">
                            <thead><tr>
                                <th scope="col">Intento</th><th scope="col">Fecha</th><th scope="col">Examen</th>
                                <th scope="col">Respondidas</th><th scope="col">Puntaje</th><th scope="col">Acciones</th>
                            </tr></thead>
                            <tbody>
                                @forelse($uni['historial'] as $intento)
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
                                        <td>
                                            @if($intento->calificado)
                                                <a class="cni-historial-resultado" href="{{ route('alumno.resultados', ['intento' => $intento->id]) }}">Ver resultado</a>
                                            @else
                                                <span>Sin calificar</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="cni-historial-vacio">Aún no has terminado ningún examen.</td></tr>
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
    const encabezados = { '#examen': ['Examen UNI', '▶'], '#historial': ['Historial', '◷'], '#perfil': ['Mi perfil', '♙'] };
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
    // Evita doble envío al iniciar un día.
    panel.querySelectorAll('.cni-uni-dia form').forEach((f) => f.addEventListener('submit', () => {
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
