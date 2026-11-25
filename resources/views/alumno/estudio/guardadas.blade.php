@extends('layouts.app')
@section('content')
<x-alumno.pagina :ctx="$ctx" :usuario="$usuario" activo="practica" titulo="Preguntas guardadas" icono="★">
    <main class="cni-alumno-content estudio-grid">
        <section class="estudio-tarjeta" aria-labelledby="guardadas-titulo">
            <h2 id="guardadas-titulo">Tu lista de repaso</h2>
            <p class="estudio-ayuda">Preguntas que guardaste desde tus resultados o tus prácticas. Vuelve a ellas antes de tu próximo examen.</p>
            <form method="GET" class="estudio-campos" aria-label="Filtrar preguntas guardadas">
                <label class="estudio-campo">Asignatura
                    <select name="asignatura" onchange="this.form.tema && (this.form.tema.value = ''); this.form.submit()">
                        <option value="">Todas ({{ $asignaturas->count() }})</option>
                        @foreach($asignaturas as $a)<option value="{{ $a }}" @selected(($filtros['asignatura'] ?? '') === $a)>{{ $a }}</option>@endforeach
                    </select>
                </label>
                <label class="estudio-campo">Tema
                    <select name="tema" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach($temas as $t)<option value="{{ $t }}" @selected(($filtros['tema'] ?? '') === $t)>{{ $t }}</option>@endforeach
                    </select>
                </label>
                <noscript><button type="submit" class="estudio-boton estudio-boton--secundario">Filtrar</button></noscript>
            </form>
            <div class="estudio-acciones" style="margin-top:12px">
                <a class="estudio-boton estudio-boton--secundario" href="{{ route('alumno.practica') }}">← Volver a práctica</a>
                <span class="estado estado--info">{{ $total }} {{ $total === 1 ? 'pregunta' : 'preguntas' }}</span>
            </div>
        </section>

        @forelse($preguntas as $p)
            <article class="estudio-tarjeta" id="pregunta-{{ $p['guardada']->pregunta_id }}">
                @include('alumno.partials.pregunta-revision', ['d' => $p['datos']])
                @include('alumno.partials.acciones-pregunta', [
                    'preguntaId' => $p['guardada']->pregunta_id, 'guardada' => true,
                    'asignatura' => $p['datos']['activa'] ? $p['datos']['asignatura'] : null,
                ])
            </article>
        @empty
            <div class="estudio-vacio">
                <p><strong>Aún no tienes preguntas guardadas{{ ($filtros['asignatura'] ?? null) ? ' con este filtro' : '' }}.</strong></p>
                <p>Usa «☆ Guardar pregunta» en la revisión de tus resultados o en tus prácticas.</p>
            </div>
        @endforelse
        @if($total > 50)<p class="estudio-ayuda">Se muestran las 50 más recientes; usa los filtros para ver el resto.</p>@endif
    </main>
</x-alumno.pagina>
@endsection
