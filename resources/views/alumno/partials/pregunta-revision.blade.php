{{-- Pregunta revisada (clave visible). $d = RepasoService::explicacion(). Estados con texto e ícono. --}}
@php
    $estados = ['correcta' => ['estado--correcta', '✓ Correcta'], 'incorrecta' => ['estado--incorrecta', '✕ Incorrecta']];
    [$claseEstado, $textoEstado] = $estados[$d['estado'] ?? ''] ?? ['estado--blanco', '— En blanco'];
    $img = fn (?string $ruta) => $ruta && ! str_contains($ruta, '://') && ! str_starts_with($ruta, '//') ? asset(ltrim($ruta, '/')) : null;
@endphp
<div class="pregunta-tarjeta">
    <div class="pregunta-meta">
        <span>{{ $d['asignatura'] }}</span>@if($d['tema'] && $d['tema'] !== $d['asignatura'])<span>· {{ $d['tema'] }}</span>@endif
        <span class="estado {{ $claseEstado }}">{{ $textoEstado }}</span>
        @unless($d['activa'])<span class="estado estado--pendiente">⚠ Retirada del banco</span>@endunless
    </div>
    <p class="pregunta-enunciado">{{ $d['enunciado'] }}</p>
    @if($src = $img($d['imagen']))<img class="pregunta-figura" src="{{ $src }}" alt="Figura de la pregunta" loading="lazy">@endif
    <ul class="pregunta-alternativas">
        @foreach($d['alternativas'] as $alt)
            @php $esClave = $d['clave'] === $alt['letra']; $esSuya = $d['respuesta_alumno'] === $alt['letra']; @endphp
            <li @class(['pregunta-alternativa', 'is-clave' => $esClave, 'is-error' => $esSuya && ! $esClave])>
                <span class="pregunta-letra">{{ $alt['letra'] }}</span>
                <span>{{ $alt['texto'] }}
                    @if($s = $img($alt['imagen']))<img class="pregunta-figura" src="{{ $s }}" alt="Figura de la alternativa {{ $alt['letra'] }}" loading="lazy">@endif
                    @if($esClave)<span class="pregunta-marca">✓ Respuesta correcta</span>@endif
                    @if($esSuya)<span class="pregunta-marca">{{ $esClave ? '✓' : '✕' }} Tu respuesta</span>@endif
                </span>
            </li>
        @endforeach
    </ul>
    @if($d['solucion'])
        <div class="pregunta-solucion"><strong>✓ Explicación revisada</strong>{{ $d['solucion'] }}</div>
    @else
        <p class="estudio-aviso">Esta pregunta aún no tiene una explicación revisada por docentes. Puedes pedirle una al tutor.</p>
    @endif
    @unless($d['activa'])
        <p class="estudio-aviso estudio-aviso--alerta">Esta pregunta fue retirada del banco: se conserva aquí para tu repaso, pero ya no aparece en prácticas.</p>
    @endunless
</div>
