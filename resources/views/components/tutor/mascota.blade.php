@props(['tamano' => 'grande'])
{{-- Gatito CNI (fondo transparente). Proporción original 639×720: nunca se estira. --}}
@php $ruta = config('tutor.mascota'); @endphp
<img {{ $attributes->class(['tutor-mascota', 'tutor-mascota--'.$tamano]) }}
     src="{{ asset($ruta).'?v='.@filemtime(public_path($ruta)) }}"
     width="639" height="720"
     alt="{{ $tamano === 'grande' ? 'Gatito del CNI con uniforme azul y una laptop' : '' }}"
     @if($tamano !== 'grande') aria-hidden="true" @endif
     loading="{{ $tamano === 'grande' ? 'eager' : 'lazy' }}" decoding="async">
