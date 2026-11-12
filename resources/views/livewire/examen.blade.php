@php
    $porcentaje = $total > 0 ? (int) round($respondidas * 100 / $total) : 0;
    $elegida = $pregunta ? ($respuestas[$pregunta->id] ?? null) : null;
    $tiempoInicial = $segundosRestantes === null ? '—'
        : sprintf('%02d:%02d:%02d', intdiv($segundosRestantes, 3600), intdiv($segundosRestantes % 3600, 60), $segundosRestantes % 60);
@endphp
@push('scripts')
    <script src="{{ asset('js/examen.js').'?v='.filemtime(public_path('js/examen.js')) }}"></script>
@endpush
@push('styles')
    {{-- El @if va dentro del @push: fuera de él deja texto antes de la raíz y Livewire la pierde. --}}
    @if($unmsm)<link rel="stylesheet" href="{{ asset('css/examen-unmsm.css').'?v='.filemtime(public_path('css/examen-unmsm.css')) }}">@endif
@endpush
<div class="examen-distribucion" :class="{ 'is-mapa-abierto': mapaAbierto }" x-data="examen({{ $intentoId }}, @js($limiteMs))" data-ahora="{{ $ahoraMs }}"
     @if($unmsm) data-modalidad="unmsm" @endif
     @keydown.escape.window="confirmar = false; mapaAbierto = false">
    <div class="examen">
    @if($unmsm)
        <header class="examen-unmsm-cabecera" aria-label="Datos del examen">
            <span class="examen-unmsm-universidad">{{ $unmsm['universidad'] }}</span>
            <strong>{{ $unmsm['area'] }}</strong>
            <span>{{ $unmsm['carrera'] ? 'Carrera: '.$unmsm['carrera'] : 'Carrera no seleccionada' }}</span>
        </header>
    @endif
    <div class="examen-superior">
        <strong class="examen-reloj" wire:ignore x-text="tiempo || @js($tiempoInicial)" role="timer" aria-label="Tiempo restante" aria-live="off">{{ $tiempoInicial }}</strong>
        <button type="button" class="examen-finalizar" @click="confirmar = true" @disabled($sinBanco)>Finalizar examen</button>
    </div>
    <div class="examen-progreso">
        <span>{{ $respondidas }} de {{ $total }} respondidas</span>
        <div class="examen-progreso-barra" role="progressbar" aria-label="Preguntas respondidas"
             aria-valuemin="0" aria-valuemax="{{ max(1, $total) }}" aria-valuenow="{{ $respondidas }}">
            <span style="width:{{ $porcentaje }}%"></span>
        </div>
    </div>
    <div class="examen-metadatos">
        <span>{{ $pregunta ? 'Pregunta '.($actual + 1).' de '.$total : 'Preguntas' }}</span>
        <button type="button" class="examen-ver" @click="mapaAbierto = !mapaAbierto"
                :aria-expanded="String(mapaAbierto)" aria-controls="mapa-preguntas">
            <span aria-hidden="true">▦</span><span x-text="mapaAbierto ? 'Ocultar preguntas' : 'Ver preguntas'">Ver preguntas</span>
        </button>
    </div>

    <section class="examen-pregunta" wire:key="pregunta-{{ $pregunta?->id ?? 'vacia' }}" aria-label="Pregunta del examen">
        @if($pregunta)
            <span class="examen-curso">{{ $pregunta->area }}</span>
            <div @class(['examen-contenido-pregunta', 'examen-contenido-pregunta--con-imagen' => (bool) $pregunta->imagen])>
                <div class="examen-material">
                    @if(trim($pregunta->enunciado) !== '')
                        <h1 class="examen-enunciado" id="enunciado-{{ $pregunta->id }}">{!! nl2br(e($pregunta->enunciado)) !!}</h1>
                    @endif
                    @if($pregunta->imagen)
                        <img class="examen-imagen" src="{{ asset($pregunta->imagen) }}" alt="Pregunta {{ $actual + 1 }}"
                             @if(trim($pregunta->enunciado) === '') id="enunciado-{{ $pregunta->id }}" @endif>
                    @endif
                </div>
                <div class="examen-respuestas">
                    <div class="examen-opciones" role="radiogroup" aria-labelledby="enunciado-{{ $pregunta->id }}">
                        @foreach($pregunta->alternativas as $alternativa)
                            @php $marcada = $elegida === (int) $alternativa->id; @endphp
                            <button type="button" role="radio" aria-checked="{{ $marcada ? 'true' : 'false' }}"
                                    @class(['examen-opcion', 'is-elegida' => $marcada])
                                    wire:key="alternativa-{{ $alternativa->id }}" wire:click="responder({{ $alternativa->id }})"
                                    wire:loading.attr="disabled" wire:target="responder">
                                <span class="examen-radio" aria-hidden="true"></span>
                                <span>{{ $alternativa->texto }}
                                    @if($alternativa->imagen ?? null)
                                        <img class="examen-imagen-alternativa" src="{{ asset($alternativa->imagen) }}" alt="Alternativa {{ $alternativa->letra }}">
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                    @if($unmsm)
                        {{-- La marca vive en el navegador: wire:ignore evita que Livewire la borre al guardar una respuesta;
                             wire:key crea un botón nuevo (y Alpine lo inicializa) al cambiar de pregunta. --}}
                        <div wire:key="revisar-{{ (int) $pregunta->orden }}" wire:ignore>
                            <button type="button" class="examen-revisar" x-data @click="alternar({{ (int) $pregunta->orden }})"
                                    :class="{ 'is-activo': marcada({{ (int) $pregunta->orden }}) }"
                                    :aria-pressed="String(marcada({{ (int) $pregunta->orden }}))">
                                <span aria-hidden="true">⚑</span>
                                <span x-text="marcada({{ (int) $pregunta->orden }}) ? 'Quitar marca de revisión' : 'Marcar para revisar'">Marcar para revisar</span>
                            </button>
                        </div>
                    @endif
                    <span class="examen-guardado" aria-live="polite">
                        <span wire:loading wire:target="responder">Guardando…</span>
                        <span wire:loading.remove wire:target="responder">@if($elegida) Respuesta guardada @endif</span>
                    </span>
                </div>
            </div>
        @else
            <p class="examen-vacio" role="status">Aún no hay preguntas disponibles.</p>
        @endif
    </section>

    <footer class="examen-navegacion">
        <button type="button" class="examen-boton" wire:click="anterior" @disabled($sinBanco || $actual === 0)>‹ &nbsp; Anterior</button>
        @if(! $sinBanco && $actual === $total - 1)
            <button type="button" class="examen-boton" @click="confirmar = true">Revisar y enviar</button>
        @else
            <button type="button" class="examen-boton" wire:click="siguiente" @disabled($sinBanco)>Siguiente &nbsp; ›</button>
        @endif
    </footer>

    </div>

    <aside class="examen-mapa" id="mapa-preguntas" x-show="mapaAbierto" x-cloak aria-label="Mapa de preguntas">
        <div class="examen-mapa-titulo">
            <h2>Preguntas</h2>
            <button type="button" @click="mapaAbierto = false" aria-label="Cerrar mapa de preguntas">×</button>
        </div>
        <p class="examen-mapa-ayuda">Selecciona un número para ir directamente.</p>
        <div class="examen-mapa-numeros">
            @foreach($preguntas as $indice => $item)
                <button type="button" wire:key="mapa-{{ $item->id }}" wire:click="ir({{ $indice }})" @click="if (window.innerWidth < 1200) mapaAbierto = false"
                        @class(['examen-mapa-numero', 'is-respondida' => ($respuestas[$item->id] ?? null) !== null, 'is-actual' => $indice === $actual])
                        @if($indice === $actual) aria-current="step" @endif
                        aria-label="Pregunta {{ $indice + 1 }}{{ ($respuestas[$item->id] ?? null) !== null ? ', respondida' : ', sin responder' }}">{{ $indice + 1 }}@if($unmsm)<span class="examen-mapa-marca" wire:ignore x-show="marcada({{ (int) $item->orden }})" x-cloak aria-hidden="true"></span>@endif</button>
            @endforeach
        </div>
        <ul class="examen-mapa-leyenda" aria-label="Estados de las preguntas">
            <li><span class="examen-estado-actual" aria-hidden="true"></span>Actual</li>
            <li><span class="examen-estado-respondida" aria-hidden="true"></span>Respondida</li>
            <li><span class="examen-estado-pendiente" aria-hidden="true"></span>Pendiente</li>
            @if($unmsm)
                <li><span class="examen-estado-revisar" aria-hidden="true"></span>Marcada para revisar</li>
            @endif
        </ul>
    </aside>

    @unless($sinBanco)
    <div class="examen-modal" x-show="confirmar" x-cloak @click.self="confirmar = false">
        <section class="examen-modal-caja" role="dialog" aria-modal="true" aria-labelledby="titulo-confirmar">
            <h2 id="titulo-confirmar">¿Enviar el examen?</h2>
            <p>Después de enviarlo ya no podrás cambiar tus respuestas.</p>
            <p>{{ $respondidas }} de {{ $total }} respondidas · {{ $total - $respondidas }} sin responder.</p>
            <div class="examen-modal-acciones">
                <button type="button" class="examen-boton examen-boton-secundario" @click="confirmar = false">Volver al examen</button>
                <button type="button" class="examen-finalizar" wire:click="finalizar" wire:loading.attr="disabled" wire:target="finalizar">
                    <span wire:loading.remove wire:target="finalizar">Enviar examen</span>
                    <span wire:loading wire:target="finalizar">Enviando…</span>
                </button>
            </div>
        </section>
    </div>
    @endunless
</div>
