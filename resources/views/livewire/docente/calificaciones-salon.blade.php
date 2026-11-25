<div class="docente-calificaciones">
    <link rel="stylesheet" href="{{ asset('css/docente/calificaciones-docente.css') }}">

    <header class="calificaciones-encabezado">
        <div class="calificaciones-encabezado-texto">
            <a href="{{ route('docente.panel') }}" class="calificaciones-volver">← Volver al Panel Docente</a>
            <h1>Calificaciones de mi salón</h1>
            <p>Sección {{ $seccion?->nombre ?? 'sin asignar' }} · Resultados de los simulacros de tus estudiantes.</p>
        </div>
        <img src="{{ asset('images/cni/escudo-cni-docentes.webp') }}" alt="Escudo del CNI" class="calificaciones-escudo">
    </header>

    <section class="calificaciones-resumen" aria-label="Resumen académico">
        <article class="calificaciones-indicador">
            <span>👥 Estudiantes</span>
            <h2>{{ $totalEstudiantes }}</h2>
            <p>Alumnos de mi salón</p>
        </article>
        <article class="calificaciones-indicador">
            <span>📝 Evaluaciones</span>
            <h2>{{ $totalEvaluaciones }}</h2>
            <p>Simulacros calificados de la sección</p>
        </article>
        <article class="calificaciones-indicador">
            <span>📊 Promedio general</span>
            <h2>{{ $promedioSeccion === null ? '—' : number_format($promedioSeccion, 2) }}</h2>
            <p>Promedio de evaluaciones calificadas</p>
        </article>
    </section>

    @if (! $seccion || ! $seccion->activo)
        <p class="calificaciones-aviso" role="status">Tu cuenta docente no tiene una sección activa asignada. Solicita la asignación al administrador.</p>
    @else
        <section class="calificaciones-listado">
            <h2>Estudiantes de 5.º {{ $seccion->nombre }}</h2>
            <div class="calificaciones-tabla-scroll">
                <table class="calificaciones-tabla">
                    <thead>
                        <tr>
                            <th scope="col">Estudiante</th>
                            <th scope="col">Último simulacro calificado</th>
                            <th scope="col">Puntaje</th>
                            <th scope="col">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($alumnos as $alumno)
                            <tr wire:key="alumno-{{ $alumno->estudiante_id }}">
                                <td>{{ $alumno->apellidos }}, {{ $alumno->nombres }}</td>
                                <td>{{ $alumno->simulacro ?? 'Sin simulacros rendidos' }}</td>
                                <td>{{ $alumno->puntaje_total === null ? '—' : number_format((float) $alumno->puntaje_total, 3) }}</td>
                                <td>{{ $alumno->fecha_inicio ? \Illuminate\Support\Carbon::parse($alumno->fecha_inicio)->format('d/m/Y') : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Todavía no hay estudiantes registrados en esta sección.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
