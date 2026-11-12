@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/selector-areas.css') }}?v={{ filemtime(public_path('css/selector-areas.css')) }}">
@endpush

@section('content')
@php
    // Solo cambia la presentación visual: los códigos y los IDs reales de MariaDB se conservan.
    // Cada código se muestra como una universidad. El código D (Económicas) se oculta del selector.
    $codigosOcultos = ['D'];
    $ordenVisual = ['B' => 1, 'A' => 2, 'C' => 3, 'E' => 4];
    $areasOrdenadas = $areas
        ->reject(fn ($area) => in_array($area->codigo, $codigosOcultos, true))
        ->sortBy(fn ($area) => $ordenVisual[$area->codigo] ?? 99);
    // Logos institucionales.
    $mascotas = [
        'B' => 'UNI_logo.png',
        'A' => 'UNDC_LOGO.png',
        'C' => 'villareal_logo.png',
        'E' => 'San_Marcos_logo.png',
    ];

    $presentaciones = [
        'B' => [
            'titulo' => 'UNI',
            'descripcion' => 'Prepárate para la Universidad Nacional de Ingeniería.',
        ],
        'A' => [
            'titulo' => 'UNDC',
            'descripcion' => 'Prepárate para la Universidad Nacional de Cañete.',
        ],
        'C' => [
            'titulo' => 'Villarreal',
            'descripcion' => 'Prepárate para la Universidad Nacional Federico Villarreal.',
        ],
        'E' => [
            'titulo' => 'San Marcos',
            'descripcion' => 'Prepárate para la Universidad Nacional Mayor de San Marcos.',
        ],
    ];
@endphp

<section class="selector-page" aria-labelledby="selector-titulo">
    <form class="selector-page__salir" method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Cerrar sesión</button>
    </form>

    <div class="selector-page__encabezado">
        <p class="selector-page__eyebrow">{{ ($cambiandoUniversidad ?? false) ? 'CAMBIAR UNIVERSIDAD' : 'PRIMERA VEZ' }} <strong>EN EL SIMULADOR</strong></p>
        @if($cambiandoUniversidad ?? false)
            <p><a href="{{ route('alumno.panel') }}">Volver a mi panel</a></p>
            <p>Al cambiar de universidad podrás elegir una nueva carrera. Tus intentos anteriores se conservarán.</p>
        @endif
        <h1 id="selector-titulo">¿A qué universidad deseas postular?</h1>
        <p class="selector-page__subtitulo">Elige tu universidad y personalizaremos tu panel de estudio para su examen de admisión</p>
    </div>

    <div class="selector-page__grid areas-grid" role="group" aria-label="Universidades de postulación">
        @forelse ($areasOrdenadas as $area)
            @php
                $presentacion = $presentaciones[$area->codigo] ?? [
                    'titulo' => $area->nombre,
                    'descripcion' => 'Prepárate para tu examen de admisión.',
                ];
            @endphp
            <form class="area-opcion area-tarjeta" data-area="{{ $area->codigo }}"
                  method="POST" action="{{ route(($cambiandoUniversidad ?? false) ? 'alumno.universidad.guardar' : 'alumno.area.guardar') }}"
                  aria-labelledby="area-titulo-{{ $area->id }}">
                @csrf
                <input type="hidden" name="area_id" value="{{ $area->id }}">

                <div class="area-opcion__ilustracion" aria-hidden="true">
                    @if (isset($mascotas[$area->codigo]))
                        @php
                            $imagenMascota = 'images/mascotas/'.$mascotas[$area->codigo];
                        @endphp
                        <div class="area-opcion__mascota">
                            <img src="{{ asset($imagenMascota).'?v='.filemtime(public_path($imagenMascota)) }}"
                                 alt="" loading="eager" decoding="async"
                                 width="520" height="520">
                        </div>
                    @endif
                </div>

                <div class="area-opcion__info">
                    <span class="area-opcion__icono" aria-hidden="true">
                        {{-- Ícono de universidad (edificio con columnas) --}}
                        <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12 16 4l13 8H3Z"/>
                            <path d="M7 12v11M12.5 12v11M19.5 12v11M25 12v11"/>
                            <path d="M4 27h24M5 23.5h22"/>
                        </svg>
                    </span>

                    <h2 id="area-titulo-{{ $area->id }}" title="{{ $presentacion['descripcion'] }}">{{ $presentacion['titulo'] }}</h2>
                    <p class="area-opcion__descripcion">{{ $presentacion['descripcion'] }}</p>
                    <button class="area-opcion__seleccionar" type="submit"
                            aria-label="Seleccionar {{ $presentacion['titulo'] }}">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 12h16m-7-7 7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </form>
        @empty
            <p class="selector-page__sin-areas">No hay universidades habilitadas. Comunícate con el administrador.</p>
        @endforelse
    </div>

    <p class="selector-page__nota">Tu selección se guardará y en tu próximo ingreso accederás directamente a tu panel.</p>
</section>
@endsection
