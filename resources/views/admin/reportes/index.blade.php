@extends('admin.shell')
@section('admin-page', 'Reportes de alumnos')
@section('admin-content')
@php
    $estados = \App\Http\Controllers\Admin\ReportesPreguntaController::ESTADOS;
    $motivos = \App\Services\Alumno\RepasoService::MOTIVOS;
    $iconos = ['pendiente' => '●', 'en_revision' => '◐', 'resuelto' => '✓', 'descartado' => '✕'];
@endphp
<section class="cni-page-title">
    <div><span class="cni-eyebrow">BANCO DE PREGUNTAS</span><h1>Reportes de alumnos</h1>
        <p>Problemas que los alumnos encontraron en preguntas. Cambiar el estado no modifica la pregunta: corrígela desde «Preguntas» después de revisarla.</p></div>
</section>
@if(session('status'))<div class="cni-alert cni-alert--success" role="status">{{ session('status') }}</div>@endif

<nav class="cni-mini-overview" aria-label="Filtrar por estado" style="gap:8px;flex-wrap:wrap">
    <a class="cni-button {{ $estado === null ? 'cni-button--primary' : '' }}" href="{{ route('admin.reportes.index') }}">Todos ({{ $conteo->sum() }})</a>
    @foreach($estados as $valor => $texto)
        <a class="cni-button {{ $estado === $valor ? 'cni-button--primary' : '' }}" href="{{ route('admin.reportes.index', ['estado' => $valor]) }}">{{ $iconos[$valor] }} {{ $texto }} ({{ $conteo[$valor] ?? 0 }})</a>
    @endforeach
</nav>

<section class="cni-card cni-table-card" aria-label="Listado de reportes">
    <div class="cni-table-card__head"><div><h2>Reportes</h2><p>Los pendientes aparecen primero.</p></div>
        <span class="cni-table-count">{{ $reportes->total() }} en total</span></div>
    <div class="cni-table-scroll">
        <table class="cni-table">
            <thead><tr><th scope="col">#</th><th scope="col">Pregunta</th><th scope="col">Motivo</th><th scope="col">Comentario</th><th scope="col">Alumno</th><th scope="col">Estado</th><th scope="col">Acción</th></tr></thead>
            <tbody>
                @forelse($reportes as $r)
                    <tr>
                        <td>{{ $r->id }}<br><small>{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</small></td>
                        <td><a href="{{ route('admin.preguntas.show', $r->pregunta_id) }}">{{ $r->codigo ?? 'ID '.$r->pregunta_id }}</a>
                            @unless($r->pregunta_activa)<br><small>(inactiva)</small>@endunless
                            <br><small>{{ \Illuminate\Support\Str::limit($r->enunciado, 90) }}</small></td>
                        <td>{{ $motivos[$r->motivo] ?? $r->motivo }}</td>
                        <td>{{ $r->comentario ?? '—' }}</td>
                        <td>{{ $r->alumno }}@if($r->intento_id)<br><small>Intento #{{ $r->intento_id }}</small>@endif @if($r->practica_id)<br><small>Práctica #{{ $r->practica_id }}</small>@endif</td>
                        <td>{{ $iconos[$r->estado] }} {{ $estados[$r->estado] }}@if($r->revisor)<br><small>por {{ $r->revisor }}</small>@endif</td>
                        <td>
                            <form method="POST" action="{{ route('admin.reportes.actualizar', $r->id) }}">@csrf @method('PATCH')
                                <label style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)" for="estado-{{ $r->id }}">Nuevo estado del reporte {{ $r->id }}</label>
                                <select id="estado-{{ $r->id }}" name="estado">
                                    @foreach($estados as $valor => $texto)<option value="{{ $valor }}" @selected($r->estado === $valor)>{{ $texto }}</option>@endforeach
                                </select>
                                <button type="submit" class="cni-button">Guardar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No hay reportes{{ $estado ? ' con este estado' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($reportes->hasPages())<div class="cni-pagination">{{ $reportes->links() }}</div>@endif
</section>
@endsection
