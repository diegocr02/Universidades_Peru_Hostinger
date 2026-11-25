{{-- Enlaces de estudio en el menú de los paneles (después de Historial): Práctica, Mi avance y Tutor IA. --}}
@php
    $undc = ($estilo ?? null) === 'undc';
    $enlaces = array_filter([
        [route('alumno.practica'), '✎', 'Práctica'],
        [route('alumno.progreso'), '📈', 'Mi avance'],
        config('tutor.enabled') ? [route('alumno.tutor'), '✦', 'Tutor IA'] : null,
    ]);
@endphp
@foreach($enlaces as [$url, $icono, $texto])
<a href="{{ $url }}">@if($undc){{ $icono }} <span>{{ $texto }}</span>@else<span aria-hidden="true">{{ $icono }}</span> {{ $texto }}@endif</a>
@endforeach
