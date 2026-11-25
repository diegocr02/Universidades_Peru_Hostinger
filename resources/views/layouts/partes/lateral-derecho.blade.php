{{--
    Menú lateral de la variante "derecho" del layout del alumno (fondo guinda).
    Las opciones vienen de config/alumno.php; se marca la de la ruta actual.
    Usa las mismas clases portal-* del layout para reutilizar el menú ☰ en celular.
--}}
@php
    $carreraLateral = $usuario->carrera?->nombre;
    $enlaceInicio = \Illuminate\Support\Facades\Route::has('alumno.inicio') ? route('alumno.inicio') : route('panel');
@endphp
<aside class="portal-lateral" x-data="{ abierto: false }" :class="{ 'is-abierto': abierto }"
    @keydown.escape.window="abierto = false">
    <div class="portal-lateral__principal">
        <div class="portal-lateral__cabecera">
            <a class="derecho-marca" href="{{ $enlaceInicio }}">
                {{-- En Figma la marca del menú es la balanza (nodo 281:1577), no una imagen. --}}
                <span class="derecho-marca__logo" aria-hidden="true"><x-alumno.icono nombre="balanza" :tamano="30" /></span>
                <span class="derecho-marca__nombre">Simulador <strong>CNI</strong></span>
            </a>
            {{-- Solo visible en celular (< 768 px): despliega el menú. --}}
            <button type="button" class="portal-menu-boton" aria-controls="portal-menu"
                aria-expanded="false" :aria-expanded="abierto ? 'true' : 'false'" @click="abierto = !abierto">
                <span class="portal-menu-boton__icono" aria-hidden="true"><span></span><span></span><span></span></span>
                <span class="sr-only" x-text="abierto ? 'Cerrar menú' : 'Abrir menú'">Abrir menú</span>
            </button>
        </div>

        <nav class="portal-menu" id="portal-menu" aria-label="Menú del alumno">
            @if ($carreraLateral)
                <p class="derecho-carrera"><x-alumno.icono nombre="balanza" :tamano="18" /> {{ $carreraLateral }}</p>
            @endif
            @foreach (config('alumno.menu') as $opcion)
                @php
                    // Las rutas que el equipo aún no creó quedan en "#".
                    $existe = \Illuminate\Support\Facades\Route::has($opcion['ruta']);
                    $activa = $existe && request()->routeIs($opcion['ruta'].'*');
                @endphp
                <a href="{{ $existe ? route($opcion['ruta']) : '#' }}" @class(['portal-menu__enlace', 'is-activo' => $activa])
                    @if ($activa) aria-current="page" @endif>
                    <x-alumno.icono :nombre="$opcion['icono']" />
                    {{ $opcion['texto'] }}
                </a>
            @endforeach
        </nav>
    </div>

    <div class="portal-lateral__pie derecho-ilustracion">
        <img src="{{ asset('images/fondos/edificio-justicia-humanidades.webp') }}" alt="Ilustración de un edificio con la palabra JUSTICIA">
    </div>
</aside>
