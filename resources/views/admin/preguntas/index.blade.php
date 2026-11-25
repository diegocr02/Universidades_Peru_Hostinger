@extends('admin.shell')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin/vaciar-banco.css') }}?v={{ filemtime(public_path('css/admin/vaciar-banco.css')) }}">
@endpush
@section('admin-page', 'Preguntas')
@section('admin-content')
<section class="cni-card cni-questions" aria-labelledby="preguntas-titulo">
    <div class="cni-questions-heading">
        <h1 id="preguntas-titulo">Preguntas</h1>
        @if($puedeVaciarBanco)
            <details class="cni-delete-all">
                <summary class="cni-delete-all__trigger" @if($totalPreguntas === 0 && $totalIntentos === 0) aria-disabled="true" @endif>
                    <span aria-hidden="true">🗑</span> Borrar todas las preguntas
                </summary>
                <div class="cni-delete-all__panel">
                    <strong>Reiniciar demo ({{ number_format($totalPreguntas) }} preguntas · {{ number_format($totalIntentos) }} intentos)</strong>
                    <p>Reinicio de demo: se vacía el banco, se reinician los IDs y se eliminan intentos y resultados de prueba. La operación es irreversible.</p>
                    @if($totalPreguntas > 0 || $totalIntentos > 0)
                        <form action="{{ route('admin.preguntas.destroy-all') }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <label for="banco-borrar-confirmacion">Para confirmar, escribe <strong>BORRAR TODO</strong>
                                <input id="banco-borrar-confirmacion" name="confirmacion" type="text" required pattern="BORRAR TODO" maxlength="11" autocomplete="off" placeholder="BORRAR TODO" aria-describedby="banco-borrar-aviso">
                            </label>
                            <p id="banco-borrar-aviso">Esta acción no se puede deshacer. También se eliminan los intentos de examen y sus resultados.</p>
                            @error('confirmacion')<p class="cni-delete-all__error" role="alert">{{ $message }}</p>@enderror
                            <button class="cni-delete-all__confirm" type="submit">Borrar todo y reiniciar IDs</button>
                        </form>
                    @else
                        <p>El banco y el historial de intentos ya están vacíos.</p>
                    @endif
                </div>
            </details>
        @endif
    </div>
    <form action="{{ route('admin.preguntas.index') }}" method="GET" class="cni-question-filters">
        <div class="cni-question-filters__selects">
            <label for="preguntas-categoria">Categoría
                <select id="preguntas-categoria" name="tema_id">
                    <option value="">Todas</option>
                    @foreach($temas as $tema)<option value="{{ $tema->id }}" @selected((string) request('tema_id') === (string) $tema->id)>{{ $tema->nombre }}</option>@endforeach
                </select>
            </label>
            <label for="preguntas-curso">Curso
                <select id="preguntas-curso" name="area_id">
                    <option value="">Todos</option>
                    @foreach($areas as $area)<option value="{{ $area->id }}" @selected((string) request('area_id') === (string) $area->id)>{{ $area->nombre }}</option>@endforeach
                </select>
            </label>
            <label for="preguntas-estado">Estado
                <select id="preguntas-estado" name="estado">
                    <option value="">Todos</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Activas</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivas</option>
                </select>
            </label>
        </div>
        <div class="cni-question-filters__actions">
            <label for="preguntas-buscar">Buscar
                <input id="preguntas-buscar" type="search" name="buscar" value="{{ request('buscar') }}" maxlength="200" placeholder="Enunciado, código o ID">
            </label>
            <button class="cni-button cni-button--primary" type="submit">Filtrar</button>
            <a class="cni-button cni-question-clear" href="{{ route('admin.preguntas.index') }}">Limpiar</a>
        </div>
    </form>
    <div class="cni-table-scroll">
        <table class="cni-table cni-question-table">
            <thead><tr><th scope="col">ID</th><th scope="col">Enunciado</th><th scope="col">Estado</th><th scope="col">Creada</th><th scope="col">Acción</th></tr></thead>
            <tbody>
            @forelse($preguntas as $p)
                <tr>
                    <td>{{ $p->id }}</td>
                    <td class="cni-question-table__text">{{ \Illuminate\Support\Str::limit($p->enunciado ?: ('Pregunta '.($p->codigo ?: $p->id)), 150) }}
                        @if($p->imagen)<small>Imagen adjunta</small>@endif
                    </td>
                    <td><span @class(['cni-question-status', 'is-active' => $p->activo])><span aria-hidden="true">●</span> {{ $p->activo ? 'Activa' : 'Inactiva' }}</span></td>
                    <td class="cni-question-date">
                        @if($p->created_at)<time datetime="{{ \Illuminate\Support\Carbon::parse($p->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($p->created_at)->format('d/m/Y, H:i') }}</time>@else — @endif
                    </td>
                    <td><div class="cni-question-row-actions">
                        <a class="cni-question-view" href="{{ route('admin.preguntas.show', $p->id) }}" aria-label="Ver pregunta {{ $p->id }}">Ver</a>
                        @if($p->activo)
                        <form action="{{ route('admin.preguntas.destroy', $p->id) }}" method="POST" onsubmit="return confirm('¿Desactivar esta pregunta?');">
                            @csrf @method('DELETE')
                            <button class="cni-question-deactivate" type="submit" aria-label="Desactivar pregunta {{ $p->id }}">Desactivar</button>
                        </form>
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="5" class="cni-bank-vacio">No hay preguntas para mostrar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($preguntas->hasPages())<div class="cni-pagination">{{ $preguntas->links() }}</div>@endif
</section>
@endsection
