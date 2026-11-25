@php
    $img = fn (?string $ruta) => $ruta && ! str_contains($ruta, '://') && ! str_starts_with($ruta, '//') ? asset(ltrim($ruta, '/')) : null;
    $niveles = config('tutor.practica.niveles');
@endphp
<x-alumno.pagina :ctx="$ctx" :usuario="$usuario" activo="practica" titulo="Práctica por temas" icono="✎">
    <main class="cni-alumno-content estudio-grid">
        @if($examenActivo)
            <div class="estudio-aviso estudio-aviso--alerta" role="status">
                <strong>⏱ Tienes un examen en curso.</strong> Termínalo antes de practicar.
                <a href="{{ route('examen', ['intento' => $examenActivo->id]) }}">Volver al examen →</a>
            </div>
        @endif
        @if($error)<div class="estudio-aviso estudio-aviso--error" role="alert">✕ {{ $error }}</div>@endif
        @if($aviso)<div class="estudio-aviso estudio-aviso--ok" role="status">ℹ {{ $aviso }}</div>@endif

        @if(! $practica)
            {{-- Elegir qué practicar --}}
            <section class="estudio-tarjeta" aria-labelledby="practica-titulo">
                <h2 id="practica-titulo">¿Qué quieres practicar?</h2>
                <p class="estudio-ayuda">Preguntas del banco de {{ $ctx['universidad'] }}. Verás si acertaste apenas respondas. Las prácticas no cambian tu historial de exámenes.</p>
                @if($catalogo->isEmpty())
                    <div class="estudio-vacio">Todavía no hay preguntas de práctica para {{ $ctx['universidad'] }}. Consulta con tu docente.</div>
                @else
                    <form wire:submit="comenzar" class="estudio-grid">
                        <div class="estudio-campos">
                            <label class="estudio-campo">Asignatura
                                <select wire:model.live="asignatura" required>
                                    <option value="">Elige una asignatura</option>
                                    @foreach($catalogo as $a)<option value="{{ $a['nombre'] }}">{{ $a['nombre'] }} ({{ $a['cantidad'] }})</option>@endforeach
                                </select>
                            </label>
                            <label class="estudio-campo">Tema
                                <select wire:model="temaId" @disabled(! $seleccion)>
                                    <option value="">Todos los temas</option>
                                    @foreach($seleccion['temas'] ?? [] as $t)<option value="{{ $t['id'] }}">{{ $t['nombre'] }} ({{ $t['cantidad'] }})</option>@endforeach
                                </select>
                            </label>
                            <label class="estudio-campo">Dificultad
                                <select wire:model="nivel" @disabled(! $seleccion || count($seleccion['niveles'] ?? []) === 0)>
                                    <option value="">Cualquiera</option>
                                    @foreach($seleccion['niveles'] ?? [] as $n)<option value="{{ $n }}">{{ $niveles[$n] ?? $n }}</option>@endforeach
                                </select>
                            </label>
                            <label class="estudio-campo">Cantidad
                                <select wire:model="cantidad">
                                    @foreach(config('panel_alumno.practica.cantidades') as $c)<option value="{{ $c }}">{{ $c }} preguntas</option>@endforeach
                                </select>
                            </label>
                        </div>
                        @error('asignatura')<p class="estudio-aviso estudio-aviso--error">✕ Elige una asignatura de la lista.</p>@enderror
                        <div class="estudio-acciones">
                            <button type="submit" class="estudio-boton estudio-boton--primario" wire:loading.attr="disabled" @disabled($examenActivo)>
                                <span wire:loading.remove wire:target="comenzar">Empezar práctica →</span>
                                <span wire:loading wire:target="comenzar">Preparando…</span>
                            </button>
                            <a class="estudio-boton estudio-boton--secundario" href="{{ route('alumno.guardadas') }}">★ Mis preguntas guardadas</a>
                            <span class="estado estado--info">Esta semana: {{ $meta['total'] }} preguntas practicadas</span>
                        </div>
                    </form>
                @endif
            </section>
        @elseif($pregunta)
            {{-- Pregunta en curso --}}
            <section class="estudio-tarjeta" aria-labelledby="pregunta-titulo" wire:key="pregunta-{{ $pregunta['pregunta_id'] }}">
                <div class="practica-progreso" role="progressbar" aria-label="Avance de la práctica"
                     aria-valuemin="0" aria-valuemax="{{ $pregunta['total'] }}" aria-valuenow="{{ $pregunta['orden'] - ($pregunta['respondida'] ? 0 : 1) }}">
                    <span style="width: {{ round(($pregunta['orden'] - ($pregunta['respondida'] ? 0 : 1)) / max(1, $pregunta['total']) * 100) }}%"></span>
                </div>
                <div class="pregunta-tarjeta" style="margin-top:14px">
                    <div class="pregunta-meta">
                        <span id="pregunta-titulo">Pregunta {{ $pregunta['orden'] }} de {{ $pregunta['total'] }}</span>
                        <span>· {{ $pregunta['asignatura'] }}</span>
                        @if($pregunta['tema'] !== $pregunta['asignatura'])<span>· {{ $pregunta['tema'] }}</span>@endif
                        @if($pregunta['nivel'])<span>· {{ $niveles[$pregunta['nivel']] ?? $pregunta['nivel'] }}</span>@endif
                    </div>
                    <p class="pregunta-enunciado">{{ $pregunta['enunciado'] }}</p>
                    @if($src = $img($pregunta['imagen']))<img class="pregunta-figura" src="{{ $src }}" alt="Figura de la pregunta" loading="lazy">@endif

                    <div class="pregunta-alternativas" role="group" aria-label="Alternativas">
                        @foreach($pregunta['alternativas'] as $alt)
                            @php
                                $esClave = $pregunta['respondida'] && $pregunta['clave'] === $alt['letra'];
                                $esSuya = $pregunta['elegida'] === $alt['letra'];
                            @endphp
                            <button type="button" wire:click="responder('{{ $alt['letra'] }}')" wire:loading.attr="disabled"
                                    @class(['pregunta-alternativa', 'is-clave' => $esClave, 'is-error' => $esSuya && ! $esClave])
                                    @disabled($pregunta['respondida'] || $examenActivo) aria-label="Alternativa {{ $alt['letra'] }}">
                                <span class="pregunta-letra">{{ $alt['letra'] }}</span>
                                <span>{{ $alt['texto'] }}
                                    @if($s = $img($alt['imagen']))<img class="pregunta-figura" src="{{ $s }}" alt="Figura de la alternativa {{ $alt['letra'] }}" loading="lazy">@endif
                                    @if($esClave)<span class="pregunta-marca">✓ Respuesta correcta</span>@endif
                                    @if($esSuya)<span class="pregunta-marca">{{ $esClave ? '✓' : '✕' }} Tu respuesta</span>@endif
                                </span>
                            </button>
                        @endforeach
                    </div>

                    <p class="estudio-sr" aria-live="polite">
                        <span wire:loading wire:target="responder">Calificando tu respuesta…</span>
                        @if($pregunta['respondida']) {{ $pregunta['estado'] === 'correcta' ? 'Respuesta correcta.' : 'Respuesta incorrecta. La correcta es '.$pregunta['clave'].'.' }} @endif
                    </p>

                    @if($pregunta['respondida'])
                        <div role="status">
                            @if($pregunta['estado'] === 'correcta')
                                <p class="estado estado--correcta">✓ ¡Correcto! Muy bien.</p>
                            @else
                                <p class="estado estado--incorrecta">✕ No es correcta. La respuesta es {{ $pregunta['clave'] }}.</p>
                            @endif
                        </div>
                        @if($pregunta['solucion'])
                            <div class="pregunta-solucion"><strong>✓ Explicación revisada</strong>{{ $pregunta['solucion'] }}</div>
                        @else
                            <p class="estudio-aviso">Esta pregunta aún no tiene una explicación revisada. Puedes pedirle una al tutor.</p>
                        @endif

                        <div class="repaso-acciones">
                            <button type="button" wire:click="alternarGuardada({{ $pregunta['pregunta_id'] }})" @class(['is-guardada' => $guardada]) aria-pressed="{{ $guardada ? 'true' : 'false' }}">
                                {{ $guardada ? '★ Guardada · quitar' : '☆ Guardar pregunta' }}
                            </button>
                            @if(config('tutor.enabled'))
                                <a href="{{ route('alumno.tutor', ['pregunta' => $pregunta['pregunta_id']]) }}">✦ Explicación del tutor</a>
                            @endif
                            <details class="repaso-reporte" x-data x-on:reporte-enviado.window="$el.open = false">
                                <summary role="button">⚑ Reportar pregunta</summary>
                                <form wire:submit="reportar({{ $pregunta['pregunta_id'] }})" class="repaso-reporte-form">
                                    <fieldset>
                                        <legend>¿Qué problema encontraste?</legend>
                                        @foreach($motivos as $valor => $texto)
                                            <label><input type="radio" wire:model="motivo" value="{{ $valor }}"> {{ $texto }}</label>
                                        @endforeach
                                    </fieldset>
                                    <label class="estudio-campo">Comentario (opcional)
                                        <textarea wire:model="comentario" maxlength="300" placeholder="Cuéntanos brevemente el problema"></textarea>
                                    </label>
                                    <button type="submit" class="estudio-boton estudio-boton--primario" wire:loading.attr="disabled">Enviar reporte</button>
                                </form>
                            </details>
                        </div>
                    @endif

                    <div class="estudio-acciones">
                        @if($pregunta['respondida'])
                            <button type="button" class="estudio-boton estudio-boton--primario" wire:click="siguiente" wire:loading.attr="disabled">
                                {{ $pregunta['orden'] < $pregunta['total'] ? 'Siguiente pregunta →' : 'Ver resumen →' }}
                            </button>
                        @endif
                        <button type="button" class="estudio-boton estudio-boton--secundario" wire:click="terminar" wire:loading.attr="disabled"
                                wire:confirm="¿Terminar la práctica ahora? Las preguntas sin responder no cuentan.">Terminar práctica</button>
                    </div>
                </div>
            </section>
        @else
            {{-- Resumen final --}}
            <section class="estudio-tarjeta" aria-labelledby="resumen-titulo">
                <h2 id="resumen-titulo">🏁 Resumen de tu práctica · {{ $practica->asignatura }}</h2>
                <div class="practica-resumen" style="margin:14px 0">
                    <div><strong>{{ $resumen['correctas'] }}</strong><span>✓ Correctas</span></div>
                    <div><strong>{{ $resumen['incorrectas'] }}</strong><span>✕ Incorrectas</span></div>
                    <div><strong>{{ $resumen['sin_responder'] }}</strong><span>— Sin responder</span></div>
                    <div><strong>{{ $resumen['total'] > 0 ? round($resumen['correctas'] / $resumen['total'] * 100) : 0 }} %</strong><span>Acierto</span></div>
                </div>
                <div class="estudio-acciones">
                    @if($resumen['errores'] !== [])
                        <button type="button" class="estudio-boton estudio-boton--primario" wire:click="repetirErrores" wire:loading.attr="disabled">↻ Repetir mis errores ({{ count($resumen['errores']) }})</button>
                    @endif
                    <button type="button" class="estudio-boton estudio-boton--secundario" wire:click="otraPractica">Practicar otro tema</button>
                    <a class="estudio-boton estudio-boton--secundario" href="{{ route('alumno.guardadas') }}">★ Preguntas guardadas</a>
                </div>
            </section>
        @endif
    </main>
</x-alumno.pagina>
