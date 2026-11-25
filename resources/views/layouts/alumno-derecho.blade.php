{{--
    Layout del portal del alumno (Figma: "CNI · Simulador de examen", nodo 214:3).
    PROVISIONAL: cuando Jimmy publique la plantilla oficial, reemplazar este archivo
    (o hacer que extienda la oficial) manteniendo las mismas zonas:
      - $slot / @yield('content'): contenido de la página
      - $migas: último tramo de las migas de pan (por defecto "Examen en curso")
      - $seccionActiva: enlace resaltado del menú (por defecto "simulador")
      - $variante: "cni" (por defecto, azul marino; la usa el examen) o "derecho"
        (guinda: menú de config/alumno.php, barra con campana y menú de usuario).
        Las piezas de la variante "derecho" están en layouts/partes/.
    Usa su propia hoja public/css/alumno/portal-humanidades.css; no carga app.css para no mezclar estilos globales.
--}}
@php
    $usuario = auth('web')->user();
    $nombreCorto = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::before(trim($usuario->nombres), ' '));
    $iniciales = \Illuminate\Support\Str::upper(mb_substr($usuario->nombres, 0, 1).mb_substr($usuario->apellidos ?? '', 0, 1));
    $perfil = $usuario->rol === 'estudiante' ? 'Alumno CNI' : ucfirst($usuario->rol);
    $seccionActiva ??= 'simulador';
    $variante ??= 'cni';
    // Los alumnos vuelven a su portal (rutas alumno.*); admin y docente, al panel.
    $esAlumno = $usuario->rol === 'estudiante';
    $enlaceInicio = $esAlumno ? route('alumno.inicio') : route('panel');
    $menu = [
        'inicio' => ['Inicio', 'nav-inicio.svg', $enlaceInicio],
        'perfil' => ['Mi perfil', 'nav-perfil.svg', $esAlumno ? route('alumno.perfil') : '#'],
        'simulador' => ['Simulador', 'nav-simulador.svg', $esAlumno ? route('alumno.intentos') : '#'],
        'oferta' => ['Oferta formativa', 'nav-oferta.svg', '#'],
        'recursos' => ['Recursos', 'nav-recursos.svg', '#'],
        'ayuda' => ['Ayuda y contacto', 'nav-ayuda.svg', '#'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Simulador de examen' }} · CNI</title>
    {{-- ?v= con la fecha del archivo: el navegador descarga la hoja nueva después de cada cambio. --}}
    <link rel="stylesheet" href="{{ asset('css/alumno/portal-humanidades.css').'?v='.filemtime(public_path('css/alumno/portal-humanidades.css')) }}">
    @livewireStyles
    @if(auth('web')->user()?->rol === 'estudiante') @include('alumno.partials.accesibilidad-cabecera') @endif
</head>
<body @class(['portal', 'portal--derecho' => $variante === 'derecho'])>
    @if ($variante === 'derecho')
        @include('layouts.partes.lateral-derecho')
    @else
    <aside class="portal-lateral" x-data="{ abierto: false }" :class="{ 'is-abierto': abierto }"
        @keydown.escape.window="abierto = false">
        <div class="portal-lateral__principal">
            <div class="portal-lateral__cabecera">
                <a class="portal-identidad" href="{{ $enlaceInicio }}">
                    <img class="portal-identidad__escudo" src="{{ asset('images/cni/escudo-cni-examen.webp') }}" alt="Escudo institucional CNI">
                    <span class="portal-identidad__nombre">
                        <strong>CNI</strong>
                        <small>Portal del alumno</small>
                    </span>
                </a>
                {{-- Solo visible en celular (< 768 px): despliega el menú y la sesión. --}}
                <button type="button" class="portal-menu-boton" aria-controls="portal-menu portal-lateral-pie"
                    aria-expanded="false" :aria-expanded="abierto ? 'true' : 'false'" @click="abierto = !abierto">
                    <span class="portal-menu-boton__icono" aria-hidden="true"><span></span><span></span><span></span></span>
                    <span x-text="abierto ? 'Cerrar' : 'Menú'">Menú</span>
                </button>
            </div>

            <nav class="portal-menu" id="portal-menu" aria-label="Mi portal">
                <p class="portal-menu__titulo">MI PORTAL</p>
                @foreach ($menu as $clave => [$texto, $icono, $enlace])
                    {{-- Las rutas con "#" aún no existen en el proyecto. --}}
                    <a href="{{ $enlace }}" @class(['portal-menu__enlace', 'is-activo' => $clave === $seccionActiva])
                        @if ($clave === $seccionActiva) aria-current="page" @endif>
                        <img src="{{ asset('images/iconos/'.$icono) }}" width="18" height="18" alt="">
                        {{ $texto }}
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="portal-lateral__pie" id="portal-lateral-pie">
            <div class="portal-ayuda">
                <span class="portal-ayuda__icono"><img src="{{ asset('images/iconos/ayuda-tarjeta.svg') }}" width="14" height="12.9986" alt=""></span>
                <strong>Estamos para ayudarte</strong>
                <p>Resuelve tus dudas sobre el portal o tus próximos pasos.</p>
                <a href="#">Contactar con CNI →</a>
            </div>
            <hr class="portal-lateral__separador">
            <div class="portal-sesion">
                <span class="portal-avatar" aria-hidden="true">{{ $iniciales }}</span>
                <span class="portal-sesion__usuario">
                    <strong>{{ $nombreCorto }}</strong>
                    <small>{{ $perfil }}</small>
                </span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="portal-salir">
                    <span class="portal-salir__icono"><img src="{{ asset('images/iconos/cerrar-sesion.svg') }}" width="16.0521" height="15.7" alt=""></span>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>
    @endif

    <div class="portal-area">
        <div>
            @if ($variante === 'derecho')
                @include('layouts.partes.barra-derecho')
            @else
            <header class="portal-barra">
                <nav class="portal-migas" aria-label="Migas de pan">
                    <a href="{{ $enlaceInicio }}">Portal del alumno</a>
                    <span aria-hidden="true">/</span>
                    <span>Simulador</span>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page">{{ $migas ?? 'Examen en curso' }}</span>
                </nav>
                <div class="portal-cuenta">
                    <a class="portal-cuenta__ayuda" href="#">
                        <img src="{{ asset('images/iconos/ayuda.svg') }}" width="15" height="15" alt="">
                        Ayuda
                    </a>
                    <span class="portal-cuenta__separador" aria-hidden="true"></span>
                    <span class="portal-cuenta__nombre">{{ $nombreCorto }}</span>
                    <span class="portal-avatar" aria-hidden="true">{{ $iniciales }}</span>
                </div>
            </header>
            @endif

            <main class="portal-contenido">
                @if ($variante === 'derecho' && session('status'))
                    <p class="derecho-aviso derecho-aviso--exito" role="status">
                        <x-alumno.icono nombre="check-circulo" :tamano="18" /> {{ session('status') }}
                    </p>
                @endif
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>

        <footer class="portal-pie">
            <span>CNI · Portal del alumno</span>
            <span class="portal-pie__enlaces"><a href="#">Privacidad</a><a href="#">Términos y condiciones</a></span>
        </footer>
    </div>
    @stack('scripts')
    @if(auth('web')->user()?->rol === 'estudiante') <x-alumno.preferencias-accesibilidad /> @endif
    @livewireScripts
</body>
</html>
