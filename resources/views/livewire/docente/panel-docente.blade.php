<div class="cni-notas" id="cni-notas">
    <link rel="stylesheet" href="{{ asset('css/docente/panel-docente.css') }}?v=36">
    <link rel="stylesheet" href="{{ asset('css/docente/encabezado-docente.css') }}?v=8">
    @php
        $esPreview = app()->environment('local') && (request()->is('docente-preview') || request()->is('preview-docente'));
        $seccionActual = isset($seccion) && $seccion && $seccion->activo ? '5.º '.$seccion->nombre : 'Sin sección';
        $nombreDocente = isset($docente) && $docente ? trim(($docente->nombres ?? '').' '.($docente->apellidos ?? '')) : 'Vista previa docente';

        if ($esPreview) {
            // Vista previa: NO crear alumnos ni puntajes ficticios.
            // Estos nombres son solo encabezados visuales; las notas reales
            // vendran de la base de datos al conectar el panel docente.
            $seccionActual = 'Sin sección asignada (vista previa)';
            $nombreDocente = 'Vista previa docente';
            $areasDisponibles = collect(['Matemática', 'Comunicación', 'C. Sociales', 'C. Naturales']);
            $alumnos = collect();
            $totalEstudiantes = 0;
            $totalEvaluaciones = 0;
            $totalSimulacros = 0;
            $promedioSeccion = null;
        }

        $alumnos = collect($alumnos ?? []);
        $areasDisponibles = collect($areasDisponibles ?? []);
        $pendientes = $alumnos->filter(fn ($a) => $a->puntaje_total === null)->count();
        $simulacrosDisponibles = $alumnos->pluck('simulacro')->filter()->unique()->values();
        $numColumnas = 5 + $areasDisponibles->count();
    @endphp

    <header class="cni-notas-header cni-notas-header--v2">
        <!-- Figuras decorativas blancas y ondas institucionales. -->
        <div class="cni-notas-decoracion" aria-hidden="true">
            <svg class="cni-notas-ondas" viewBox="0 0 1440 200" preserveAspectRatio="none" focusable="false">
                <path d="M0 38C198-10 360 68 550 20S895 10 1080 48S1280 5 1440 28V0H0Z" fill="#74b9ff" opacity=".12"/>
                <path d="M0 164C225 202 295 106 490 157S745 165 941 126S1197 182 1440 125" fill="none" stroke="#92cfff" stroke-width="3" opacity=".24"/>
            </svg>
            <svg class="cni-notas-iconos-academicos" viewBox="0 0 200 155" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                <g transform="translate(8 6) rotate(8 32 28)">
                    <path d="M5 12c14-5 23-4 34 3v34c-11-7-20-8-34-4V12Zm34 3c12-7 22-8 34-3v33c-12-4-22-3-34 4V15ZM39 15v34"/>
                    <path d="M14 21c9-1 14 0 18 3M47 24c6-3 12-4 18-3"/>
                </g>
                <g transform="translate(112 8) rotate(8 26 29)">
                    <rect x="4" y="7" width="48" height="61" rx="4"/>
                    <path d="M20 7V3h17v4M14 25l4 4 7-8M32 25h12M14 44l4 4 7-8M32 44h12"/>
                </g>
                <g transform="translate(42 81) rotate(-10 32 21)">
                    <path d="m2 21 32-15 32 15-32 15L2 21ZM14 27v20c10 8 30 8 40 0V27M66 21v23"/>
                </g>
                <g fill="currentColor" stroke="none">
                    <circle cx="12" cy="139" r="2"/><circle cx="34" cy="139" r="2"/><circle cx="56" cy="139" r="2"/>
                    <circle cx="12" cy="153" r="2"/><circle cx="34" cy="153" r="2"/><circle cx="56" cy="153" r="2"/>
                </g>
            </svg>
            <svg class="cni-notas-laurel" viewBox="0 0 120 150" fill="none" stroke="currentColor" stroke-width="2" focusable="false">
                <path d="M94 4C113 47 108 99 72 140"/>
                <path d="M94 20C78 15 73 7 70 1C88 2 94 11 94 20Zm5 18C80 33 73 24 73 17C93 23 99 30 99 38Zm2 18C84 51 77 45 73 37C92 40 102 49 101 56Zm-2 20C82 71 74 65 69 56C89 57 98 66 99 76Zm-5 20C74 91 68 86 62 76C82 78 92 88 94 96Zm-9 17C67 108 61 104 55 94C73 95 84 104 85 113Zm-12 17C55 127 48 124 43 115C60 116 69 124 73 130Z" fill="currentColor" stroke="none"/>
            </svg>
        </div>
        <img class="cni-notas-foto" src="{{ asset('images/cni/foto-personal-docentes.webp') }}" alt="Personal docente del Colegio Nacional de Imperial">
        <div class="cni-notas-foto-marco" aria-hidden="true"></div>
        <div class="cni-notas-brand">
            <img src="{{ asset('images/cni/escudo-cni-docentes.webp') }}" alt="Escudo del Colegio Nacional de Imperial">
            <div>
               
            <span class="cni-notas-school">COLEGIO NACIONAL DE IMPERIAL</span>
            <h1>Calificaciones del <span class="cni-salon-dorado">salón</span>       <p class="cni-notas-subfrase"><svg class="cni-notas-birrete" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m2 9 10-5 10 5-10 5L2 9Zm5 3v5c3 3 7 3 10 0v-5M22 9v7"/></svg><span>Resultados académicos de tus estudiantes</span></p>
            </div>
        </div>
         <div class="cni-notas-docente">
    <div class="cni-notas-docente-top">
        <span class="cni-notas-docente-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 21v-2a8 8 0 0 1 16 0v2"/>
            </svg>
        </span>

        <div class="cni-notas-docente-texto">
            <small class="cni-notas-docente-label">
                {{ $esPreview ? 'Panel docente' : 'Docente asignado' }}
            </small>

            <strong>{{ $esPreview ? 'Enseñar es inspirar' : $nombreDocente }}</strong>

            <small class="cni-notas-docente-detalle">
                {{ $esPreview ? 'Sin sección asignada · Vista previa' : 'Sección: '.$seccionActual }}
            </small>
        </div>
    </div>

    @unless($esPreview)
        <form action="{{ route('logout') }}" method="POST" class="cni-notas-docente-actions">
            @csrf
            <button type="submit" class="cni-notas-exit" title="Cerrar sesión">Salir</button>
        </form>
    @endunless
</div></header>

    <div class="cni-notas-body">
        @if($esPreview)
            <div class="cni-notas-demo" role="status">Consulta calificaciones, identifica oportunidades de mejora y acompaña el progreso académico de tu sección. Esta vista previa no muestra datos de alumnos.</div>
        @elseif(!isset($seccion) || !$seccion || !$seccion->activo)
            <div class="cni-notas-demo" role="status">Tu cuenta no tiene una sección activa asignada. Solicita la asignación al administrador.</div>
        @endif

        <section class="cni-notas-resumen" aria-label="Resumen de calificaciones">
            <article class="cni-notas-kpi">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M17 4a4 4 0 0 1 0 8M18 15a5 5 0 0 1 3 5v1"/></svg></span>
                <div><strong>{{ $totalEstudiantes ?? 0 }}</strong><h2>Estudiantes</h2><p>de mi salón</p></div>
            </article>
            <article class="cni-notas-kpi">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                <div><strong>{{ $totalSimulacros ?? 0 }}</strong><h2>Simulacros</h2><p>con resultados calificados</p></div>
            </article>
            <article class="cni-notas-kpi">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 20v-6M12 20V9M19 20V4M3 20h18"/></svg></span>
                <div><strong>{{ isset($promedioSeccion) && $promedioSeccion !== null ? number_format((float) $promedioSeccion, 2) : '—' }}</strong><h2>Promedio general</h2><p>puntaje de evaluaciones</p></div>
            </article>
            <article class="cni-notas-kpi">
                <span class="kpi-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg></span>
                <div><strong>{{ $pendientes ?? 0 }}</strong><h2>Sin calificación</h2><p>sin simulacro calificado</p></div>
            </article>
        </section>

        <section class="cni-notas-card" aria-label="Listado de calificaciones">
            <div class="cni-notas-card-head">
                <div class="cni-notas-card-title"><span class="cni-notas-title-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 10h16M10 4v16M4 15h16"/></svg></span><div><h2>Calificaciones de los alumnos</h2><p>Busca estudiantes y consulta sus últimos resultados por área.</p></div></div>
                @if(!$esPreview && isset($seccion) && $seccion && $seccion->activo && $alumnos->isNotEmpty())
                    <a class="cni-notas-export" href="{{ route('docente.exportar') }}" title="Descargar archivo CSV compatible con Excel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M4 17v4h16v-4"/></svg> Exportar Excel (.csv)</a>
                @else
                    <button class="cni-notas-export cni-notas-export-disabled" type="button" title="No hay registros para exportar" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M4 17v4h16v-4"/></svg> Exportar Excel (.csv)</button>
                @endif
            </div>
            <div class="cni-notas-filtros">
                <label><span>Buscar estudiante</span><div class="cni-input-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input id="cni-buscar" type="search" autocomplete="off" placeholder="Ingresa nombre o apellido..."></div></label>
                <label><span>Simulacro</span><select id="cni-simulacro"><option value="">Todos</option>@foreach($simulacrosDisponibles as $sim)<option value="{{ $sim }}">{{ $sim }}</option>@endforeach</select></label>
                <label><span>Estado</span><select id="cni-estado"><option value="">Todos</option><option value="calificado">Calificados</option><option value="pendiente">Sin calificación</option></select></label>
                <button type="button" id="cni-limpiar" class="cni-notas-clear"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg><span>Limpiar filtros</span></button>
            </div>

            <div class="cni-notas-table-wrap">
                <table class="cni-notas-table" id="cni-tabla">
                    <thead><tr><th scope="col">N.º</th><th scope="col">Estudiante</th><th scope="col">Último simulacro</th>@foreach($areasDisponibles as $area)<th scope="col">{{ $area }}</th>@endforeach<th scope="col">Total</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                    <tbody>
                        @forelse($alumnos as $indice => $alumno)
                            @php($calificado = $alumno->puntaje_total !== null)
                            <tr class="cni-fila" data-nombre="{{ mb_strtolower($alumno->apellidos.' '.$alumno->nombres) }}" data-simulacro="{{ $alumno->simulacro ?? '' }}" data-estado="{{ $calificado ? 'calificado' : 'pendiente' }}">
                                <td>{{ $indice + 1 }}</td>
                                <td class="cni-notas-alumno">{{ $alumno->apellidos }}, {{ $alumno->nombres }}</td>
                                <td>{{ $alumno->simulacro ?? 'Sin simulacro' }}</td>
                                @foreach($areasDisponibles as $area)
                                    <td>@if(isset($alumno->notas_area[$area]))<span class="cni-notas-puntaje-area">{{ number_format((float) $alumno->notas_area[$area], 2) }}</span>@else<span class="cni-notas-dash">—</span>@endif</td>
                                @endforeach
                                <td><strong class="cni-notas-total">{{ $calificado ? number_format((float) $alumno->puntaje_total, 2) : '—' }}</strong></td>
                                <td><span class="cni-notas-estado {{ $calificado ? 'es-calificado' : 'es-pendiente' }}">{{ $calificado ? 'Calificado' : 'Pendiente' }}</span></td>
                                <td><button class="cni-notas-ver" type="button" aria-label="Ver detalles de {{ $alumno->nombres }} {{ $alumno->apellidos }}" data-alumno="{{ $alumno->apellidos }}, {{ $alumno->nombres }}" data-simulacro="{{ $alumno->simulacro ?? 'Sin simulacro' }}" data-notas="{{ json_encode($alumno->notas_area ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}" data-puntaje="{{ $calificado ? number_format((float) $alumno->puntaje_total, 2) : 'Sin nota' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></td>
                            </tr>
                        @empty
                            <tr class="cni-empty-db"><td colspan="{{ $numColumnas + 1 }}">Todavía no hay estudiantes registrados en esta sección.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div id="cni-sin-coincidencias" class="cni-notas-noresults" hidden>No hay estudiantes que coincidan con los filtros.</div>
            <footer class="cni-notas-footer"><span id="cni-contador">{{ $alumnos->count() }} estudiantes</span><nav id="cni-paginacion" aria-label="Páginas de estudiantes"></nav></footer>
        </section>
    </div>

    <dialog id="cni-detalle" class="cni-notas-dialog" aria-labelledby="cni-detalle-titulo">
        <div class="cni-notas-dialog-head"><h2 id="cni-detalle-titulo">Detalle de calificaciones</h2><button type="button" id="cni-cerrar-dialogo" aria-label="Cerrar">×</button></div>
        <p id="cni-detalle-simulacro"></p><div id="cni-detalle-areas"></div><strong id="cni-detalle-total"></strong>
    </dialog>

    <script>
        (function () {
            const init = () => {
                const root = document.getElementById('cni-notas');
                if (!root || root.dataset.iniciado === 'si') return;
                root.dataset.iniciado = 'si';
                const filas = [...root.querySelectorAll('tr.cni-fila')];
                const buscar = root.querySelector('#cni-buscar');
                const simulacro = root.querySelector('#cni-simulacro');
                const estado = root.querySelector('#cni-estado');
                const contador = root.querySelector('#cni-contador');
                const paginas = root.querySelector('#cni-paginacion');
                const aviso = root.querySelector('#cni-sin-coincidencias');
                const dialogo = root.querySelector('#cni-detalle');
                const limite = 8;
                let pagina = 1;

                const dibujar = () => {
                    const termino = buscar.value.trim().toLocaleLowerCase('es');
                    const filtradas = filas.filter(f =>
                        f.dataset.nombre.includes(termino) &&
                        (!simulacro.value || f.dataset.simulacro === simulacro.value) &&
                        (!estado.value || f.dataset.estado === estado.value)
                    );
                    const totalPaginas = Math.max(1, Math.ceil(filtradas.length / limite));
                    pagina = Math.min(pagina, totalPaginas);
                    filas.forEach(f => { f.hidden = true; });
                    filtradas.slice((pagina - 1) * limite, pagina * limite).forEach(f => { f.hidden = false; });
                    aviso.hidden = filtradas.length !== 0 || filas.length === 0;
                    const inicio = filtradas.length ? (pagina - 1) * limite + 1 : 0;
                    const fin = Math.min(pagina * limite, filtradas.length);
                    contador.textContent = `Mostrando ${inicio}–${fin} de ${filtradas.length} estudiantes`;
                    paginas.replaceChildren();
                    if (totalPaginas <= 1) return;
                    const agregar = (etiqueta, destino, activo, deshabilitado) => {
                        const b = document.createElement('button');
                        b.type = 'button'; b.textContent = etiqueta;
                        b.className = 'cni-pagina' + (activo ? ' seleccionada' : '');
                        b.disabled = deshabilitado;
                        if (activo) b.setAttribute('aria-current', 'page');
                        b.addEventListener('click', () => { pagina = destino; dibujar(); });
                        paginas.append(b);
                    };
                    agregar('‹', Math.max(1, pagina - 1), false, pagina === 1);
                    for (let p = 1; p <= totalPaginas; p++) agregar(String(p), p, p === pagina, false);
                    agregar('›', Math.min(totalPaginas, pagina + 1), false, pagina === totalPaginas);
                };
                [buscar, simulacro, estado].forEach(c => c.addEventListener('input', () => { pagina = 1; dibujar(); }));
                root.querySelector('#cni-limpiar').addEventListener('click', () => {
                    buscar.value = ''; simulacro.value = ''; estado.value = ''; pagina = 1; dibujar();
                });
                root.querySelectorAll('.cni-notas-ver').forEach(b => b.addEventListener('click', () => {
                    root.querySelector('#cni-detalle-titulo').textContent = b.dataset.alumno;
                    root.querySelector('#cni-detalle-simulacro').textContent = b.dataset.simulacro;
                    root.querySelector('#cni-detalle-total').textContent = 'Puntaje total: ' + b.dataset.puntaje;
                    const areaDiv = root.querySelector('#cni-detalle-areas');
                    areaDiv.replaceChildren();
                    let notas = {};
                    try { notas = JSON.parse(b.dataset.notas || '{}'); } catch (e) { notas = {}; }
                    for (const [area, valor] of Object.entries(notas)) {
                        const fila = document.createElement('div'); fila.className = 'cni-detalle-fila';
                        const n = document.createElement('span'); n.textContent = area;
                        const v = document.createElement('strong'); v.textContent = String(valor);
                        fila.append(n, v); areaDiv.append(fila);
                    }
                    if (!Object.keys(notas).length) areaDiv.textContent = 'No hay puntajes por área registrados.';
                    if (typeof dialogo.showModal === 'function') dialogo.showModal();
                }));
                root.querySelector('#cni-cerrar-dialogo').addEventListener('click', () => dialogo.close());
                dialogo.addEventListener('click', e => { if (e.target === dialogo) dialogo.close(); });
                dibujar();

            };
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
            else init();
            document.addEventListener('livewire:navigated', init);
        })();
    </script>
</div>
