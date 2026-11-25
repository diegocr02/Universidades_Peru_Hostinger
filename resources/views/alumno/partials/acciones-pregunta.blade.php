{{--
    Acciones de repaso de una pregunta ya revisable: guardar/quitar, reportar, practicar su tema
    y pedir explicación al tutor. Parámetros: $preguntaId, $guardada (bool), $asignatura (opcional),
    $intentoId / $practicaId (opcional, contexto del reporte).
--}}
@php $motivos = \App\Services\Alumno\RepasoService::MOTIVOS; @endphp
<div class="repaso-acciones" aria-label="Acciones de la pregunta">
    @if($guardada)
        <form method="POST" action="{{ route('alumno.pregunta.quitar', $preguntaId) }}">@csrf @method('DELETE')
            <button type="submit" class="is-guardada" aria-pressed="true">★ Guardada · quitar</button>
        </form>
    @else
        <form method="POST" action="{{ route('alumno.pregunta.guardar', $preguntaId) }}">@csrf
            <button type="submit" aria-pressed="false">☆ Guardar pregunta</button>
        </form>
    @endif
    @if(! empty($asignatura))
        <a href="{{ route('alumno.practica', ['asignatura' => $asignatura]) }}">✎ Practicar este tema</a>
    @endif
    @if(config('tutor.enabled'))
        <a href="{{ route('alumno.tutor', array_filter(['pregunta' => $preguntaId, 'intento' => $intentoId ?? null])) }}">✦ Explicación del tutor</a>
    @endif
    <details class="repaso-reporte">
        <summary role="button">⚑ Reportar pregunta</summary>
        <form method="POST" action="{{ route('alumno.pregunta.reportar', $preguntaId) }}" class="repaso-reporte-form">@csrf
            @if(! empty($intentoId))<input type="hidden" name="intento_id" value="{{ $intentoId }}">@endif
            @if(! empty($practicaId))<input type="hidden" name="practica_id" value="{{ $practicaId }}">@endif
            <fieldset>
                <legend>¿Qué problema encontraste?</legend>
                @foreach($motivos as $valor => $texto)
                    <label><input type="radio" name="motivo" value="{{ $valor }}" required @checked($loop->first)> {{ $texto }}</label>
                @endforeach
            </fieldset>
            <label class="estudio-campo">Comentario (opcional)
                <textarea name="comentario" maxlength="300" placeholder="Cuéntanos brevemente el problema"></textarea>
            </label>
            <button type="submit" class="estudio-boton estudio-boton--primario">Enviar reporte</button>
        </form>
    </details>
</div>
@if(session('repaso_pregunta') === $preguntaId)
    <p @class(['estudio-aviso', 'estudio-aviso--ok' => session('repaso_ok'), 'estudio-aviso--error' => session('repaso_error')]) role="status">
        {{ session('repaso_ok') ?? session('repaso_error') }}
    </p>
@endif
