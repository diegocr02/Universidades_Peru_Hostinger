@php
    $conversando = $mensajes->isNotEmpty();
    $bloqueado = (bool) $examenActivo;
    $ultimoTutorId = $mensajes->where('rol', 'tutor')->last()?->id;
    $md = fn (?string $texto) => \Illuminate\Support\Str::markdown((string) $texto, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    $texto = fn (?string $valor) => nl2br(e((string) $valor));
    $imagen = fn (?string $ruta) => $ruta && ! str_contains($ruta, '://') && ! str_starts_with($ruta, '//') ? asset(ltrim($ruta, '/')) : null;
    $tarjetas = [
        ['resultados', 'Entender mis resultados', '¿Qué temas debo repasar?', 'barras'],
        ['explicar', 'Explicar un ejercicio', 'Ayúdame paso a paso', 'libro'],
        ['practicar', 'Practicar un tema', 'Dame ejercicios para reforzar', 'lapiz'],
        ['progreso', 'Ver mi progreso', '¿Cómo he mejorado?', 'diana'],
    ];
    $iconos = [
        'barras' => '<path d="M5 20V12M12 20V5M19 20v-9"/>',
        'libro' => '<path d="M12 7c-2-1.6-4.6-2-8-2v13c3.4 0 6 .4 8 2m0-13c2-1.6 4.6-2 8-2v13c-3.4 0-6 .4-8 2m0-13v13"/>',
        'lapiz' => '<path d="m15 5 4 4M4 20l1-5L16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 2Z"/>',
        'diana' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="M12 12 20 4m-3 0h3v3"/>',
        'pista' => '<path d="M9 18h6m-5 3h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3Z"/>',
        'pregunta' => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.5a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .8-1 1.5v.7M12 17h.01"/>',
        'chat' => '<path d="M4 18.5V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H8l-4 3.5Z"/><path d="M8.5 10.5h.01M12 10.5h.01M15.5 10.5h.01"/>',
        'birrete' => '<path d="m2 9 10-5 10 5-10 5L2 9Zm4 2v5c3 2.5 9 2.5 12 0v-5M22 9v6"/>',
        'enviar' => '<path d="M21 3 10 14M21 3l-7 18-4-7-7-4 18-7Z"/>',
        'chispa' => '<path d="M12 3v4m0 10v4M3 12h4m10 0h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
    ];
    $icono = fn (string $nombre, string $clase = '') => '<svg class="'.$clase.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$iconos[$nombre].'</svg>';
@endphp
<x-alumno.pagina :ctx="$ctx" :usuario="$usuario" activo="tutor" titulo="Tutor IA" icono="✦" :estilos="['css/alumno/tutor.css']">
        <main class="cni-alumno-content tutor-contenido {{ $conversando ? 'is-conversando' : '' }}" id="tutor">
            @if($bloqueado)
                <div class="tutor-alerta tutor-alerta--examen" role="status">
                    <strong>Tienes un examen en curso.</strong> El tutor se pausa hasta que lo termines para que tu resultado sea solo tuyo.
                    <a href="{{ route('examen', ['intento' => $examenActivo->id]) }}">Volver al examen →</a>
                </div>
            @endif

            <div class="tutor-grid">
                {{-- Bienvenida con la mascota --}}
                <section class="tutor-tarjeta tutor-bienvenida" aria-labelledby="tutor-hola">
                    <div class="tutor-bienvenida-texto">
                        <h2 id="tutor-hola">¡Hola! Soy tu tutor CNI</h2>
                        <p>Aprendamos juntos, paso a paso.</p>
                    </div>
                    <x-tutor.mascota tamano="grande" />
                </section>

                {{-- Consultas y conversación --}}
                <section class="tutor-tarjeta tutor-principal" aria-labelledby="tutor-ayuda-titulo">
                    <div class="tutor-principal-cabecera">
                        <div>
                            <h2 id="tutor-ayuda-titulo">¿En qué te ayudo hoy?</h2>
                            <p>Elige una consulta o escribe tu pregunta.</p>
                        </div>
                        <span class="tutor-contexto">{!! $icono('birrete') !!} {{ $ctx['etiqueta'] }}</span>
                    </div>

                    <nav class="tutor-consultas" aria-label="Consultas sugeridas">
                        @foreach($tarjetas as [$flujo, $titulo, $subtitulo, $ico])
                            <button type="button" class="tutor-consulta" wire:click="iniciar('{{ $flujo }}')"
                                    wire:loading.attr="disabled" @disabled($bloqueado)>
                                <span class="tutor-consulta-icono">{!! $icono($ico) !!}</span>
                                <span class="tutor-consulta-texto"><strong>{{ $titulo }}</strong><small>{{ $subtitulo }}</small></span>
                                <span class="tutor-consulta-flecha" aria-hidden="true">›</span>
                            </button>
                        @endforeach
                    </nav>

                    <div class="tutor-chat" data-tutor-log role="log" aria-live="polite" aria-relevant="additions" aria-label="Conversación con el tutor" tabindex="0">
                        @if(! $conversando)
                            <div class="tutor-msg tutor-msg--tutor">
                                <x-tutor.mascota tamano="avatar" class="tutor-msg-avatar" />
                                <div class="tutor-burbuja"><p>Puedes pedirme una pista, una explicación o una práctica. ¿Por dónde empezamos?</p></div>
                            </div>
                        @endif

                        @foreach($mensajes as $m)
                            @if($m->rol === 'alumno')
                                <div class="tutor-msg tutor-msg--alumno" wire:key="m{{ $m->id }}">
                                    <div class="tutor-burbuja">{!! $texto($m->contenido) !!}</div>
                                </div>
                            @else
                                @php $d = $m->datos ?? []; @endphp
                                <div class="tutor-msg tutor-msg--tutor tutor-msg--{{ $m->tipo }}" wire:key="m{{ $m->id }}">
                                    <x-tutor.mascota tamano="avatar" class="tutor-msg-avatar" />
                                    <div class="tutor-burbuja">
                                        @if($m->origen === 'ia')
                                            <span class="tutor-sello tutor-sello--ia">✦ Generado con IA · revísalo con tu docente</span>
                                        @elseif($m->origen === 'demo')
                                            <span class="tutor-sello tutor-sello--demo">Modo demostración · datos de ejemplo</span>
                                        @endif

                                        @if($m->tipo === 'pregunta')
                                            <p class="tutor-pregunta-meta">Pregunta {{ $d['numero'] ?? 1 }} de {{ $d['total'] ?? 1 }} · {{ $d['asignatura'] ?? '' }}@if(!empty($d['nivel'])) · {{ config('tutor.practica.niveles.'.$d['nivel'], $d['nivel']) }}@endif</p>
                                            <div class="tutor-enunciado">{!! $texto($d['enunciado'] ?? '') !!}</div>
                                            @if($src = $imagen($d['imagen'] ?? null))<img class="tutor-figura" src="{{ $src }}" alt="Figura de la pregunta" loading="lazy">@endif
                                            <div class="tutor-alternativas" role="group" aria-label="Alternativas">
                                                @foreach($d['alternativas'] ?? [] as $alt)
                                                    @php $descartada = in_array($alt['letra'], $d['descartadas'] ?? [], true); $elegida = ($d['elegida'] ?? null) === $alt['letra']; @endphp
                                                    <button type="button" @class(['tutor-alternativa', 'is-descartada' => $descartada, 'is-elegida' => $elegida])
                                                            wire:click="responder('{{ $alt['letra'] }}')" wire:loading.attr="disabled"
                                                            @disabled(! ($d['pendiente'] ?? false) || $descartada || $bloqueado)
                                                            aria-label="Alternativa {{ $alt['letra'] }}{{ $descartada ? ', descartada' : '' }}">
                                                        <span class="tutor-letra">{{ $alt['letra'] }}</span>
                                                        <span>{!! $texto($alt['texto']) !!}@if($s = $imagen($alt['imagen'] ?? null))<img class="tutor-figura tutor-figura--alt" src="{{ $s }}" alt="Figura de la alternativa {{ $alt['letra'] }}" loading="lazy">@endif</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @elseif($m->tipo === 'explicacion')
                                            @if($m->contenido !== '')<div class="tutor-md">{!! $md($m->contenido) !!}</div>@endif
                                            <div class="tutor-explicacion">
                                                <p class="tutor-pregunta-meta">{{ $d['asignatura'] ?? '' }}</p>
                                                <div class="tutor-enunciado">{!! $texto($d['enunciado'] ?? '') !!}</div>
                                                @if($src = $imagen($d['imagen'] ?? null))<img class="tutor-figura" src="{{ $src }}" alt="Figura de la pregunta" loading="lazy">@endif
                                                <ul class="tutor-alternativas tutor-alternativas--revision">
                                                    @foreach($d['alternativas'] ?? [] as $alt)
                                                        @php $esClave = ($d['clave'] ?? null) === $alt['letra']; $esSuya = ($d['respuesta_alumno'] ?? null) === $alt['letra']; @endphp
                                                        <li @class(['tutor-alternativa', 'is-clave' => $esClave, 'is-error' => $esSuya && ! $esClave])>
                                                            <span class="tutor-letra">{{ $alt['letra'] }}</span>
                                                            <span>{!! $texto($alt['texto']) !!}@if($s = $imagen($alt['imagen'] ?? null))<img class="tutor-figura tutor-figura--alt" src="{{ $s }}" alt="Figura de la alternativa {{ $alt['letra'] }}" loading="lazy">@endif
                                                                @if($esClave)<em class="tutor-marca">✓ Correcta</em>@endif
                                                                @if($esSuya)<em class="tutor-marca">Tu respuesta</em>@endif
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                @if(($d['estado'] ?? null) === 'en_blanco' && empty($d['respuesta_alumno']))
                                                    <p class="tutor-nota">Dejaste esta pregunta en blanco.</p>
                                                @endif
                                                @if(!empty($d['solucion']))
                                                    <div class="tutor-solucion">
                                                        <span class="tutor-sello tutor-sello--revisada">✓ Solución revisada</span>
                                                        <div>{!! $texto($d['solucion']) !!}</div>
                                                    </div>
                                                @else
                                                    <p class="tutor-nota">Esta pregunta aún no tiene una explicación revisada por docentes.</p>
                                                @endif
                                            </div>
                                        @else
                                            <div class="tutor-md">{!! $md($m->contenido) !!}</div>
                                        @endif

                                        @if($m->id === $ultimoTutorId && !empty($d['opciones']) && ! $pendiente)
                                            <div class="tutor-opciones" role="group" aria-label="Opciones">
                                                @foreach($d['opciones'] as $op)
                                                    <button type="button" class="tutor-opcion" title="{{ $op['detalle'] ?? '' }}"
                                                            wire:click="elegir(@js($op['accion']), @js($op['valor']))"
                                                            wire:loading.attr="disabled" @disabled($bloqueado)>{{ $op['texto'] }}</button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        @if($pendiente)
                            <div class="tutor-msg tutor-msg--tutor" wire:loading.remove>
                                <x-tutor.mascota tamano="avatar" class="tutor-msg-avatar" />
                                <div class="tutor-burbuja tutor-burbuja--pendiente">
                                    <p>Tu última pregunta quedó sin respuesta.</p>
                                    <button type="button" class="tutor-opcion" wire:click="reintentar" @disabled($bloqueado)>↻ Reintentar</button>
                                </div>
                            </div>
                        @endif

                        <div class="tutor-msg tutor-msg--tutor tutor-escribiendo" wire:loading.flex
                             wire:target="iniciar,elegir,responder,ayuda,enviar,reintentar" role="status">
                            <x-tutor.mascota tamano="avatar" class="tutor-msg-avatar" />
                            <div class="tutor-burbuja"><span class="tutor-puntos" aria-hidden="true"><i></i><i></i><i></i></span><span class="tutor-sr">El tutor está escribiendo…</span></div>
                        </div>
                    </div>

                    @if($error)
                        <div class="tutor-alerta tutor-alerta--error" role="alert">
                            <span>{{ $error }}</span>
                            @if($puedeReintentar && $pendiente)
                                <button type="button" class="tutor-opcion" wire:click="reintentar" wire:loading.attr="disabled">↻ Reintentar</button>
                            @endif
                        </div>
                    @endif

                    <form class="tutor-composer" wire:submit="enviar" data-tutor-form>
                        <label class="tutor-sr" for="tutor-mensaje">Escribe tu pregunta</label>
                        <textarea id="tutor-mensaje" wire:model="mensaje" data-tutor-input rows="1"
                                  maxlength="{{ config('tutor.max_caracteres_mensaje') }}"
                                  placeholder="Escribe tu pregunta aquí…" aria-describedby="tutor-ayuda-envio"
                                  @disabled($bloqueado)></textarea>
                        <button type="submit" class="tutor-enviar" wire:loading.attr="disabled" @disabled($bloqueado)>
                            {!! $icono('enviar') !!}<span>Enviar</span>
                        </button>
                    </form>
                    <div class="tutor-pie" id="tutor-ayuda-envio">
                        <span>Enter para enviar · Shift + Enter para nueva línea</span>
                        @if($iaDemo)
                            <span class="tutor-sello tutor-sello--demo">Modo demostración</span>
                        @elseif(! $iaConfigurada)
                            <span>IA sin configurar: las tarjetas funcionan sin IA.</span>
                        @else
                            <span>Consultas con IA hoy: {{ $restantes }}</span>
                        @endif
                        @if($conversando)
                            <button type="button" class="tutor-nueva" wire:click="nuevaConversacion" wire:loading.attr="disabled">＋ Nueva conversación</button>
                        @endif
                    </div>
                </section>
            </div>

            {{-- Accesos de ayuda --}}
            <section class="tutor-tarjeta tutor-ayudas" aria-labelledby="tutor-prueba">
                <h2 id="tutor-prueba">{!! $icono('chat', 'tutor-ayudas-icono') !!} Prueba preguntarme</h2>
                <div class="tutor-ayudas-botones">
                    <button type="button" wire:click="ayuda('pista')" wire:loading.attr="disabled" @disabled($bloqueado)>{!! $icono('pista') !!} Dame una pista</button>
                    <button type="button" wire:click="ayuda('facil')" wire:loading.attr="disabled" @disabled($bloqueado)>{!! $icono('libro') !!} Explícalo más fácil</button>
                    <button type="button" wire:click="ayuda('pregunta')" wire:loading.attr="disabled" @disabled($bloqueado)>{!! $icono('pregunta') !!} Hazme una pregunta</button>
                </div>
            </section>
        </main>
</x-alumno.pagina>

@script
<script>
    const raiz = $wire.$el;
    const log = raiz.querySelector('[data-tutor-log]');
    const entrada = raiz.querySelector('[data-tutor-input]');
    const reducir = window.matchMedia('(prefers-reduced-motion: reduce)');
    let pegado = true;
    let forzar = false;

    // Solo baja al mensaje nuevo si el alumno ya estaba abajo o acaba de actuar: no interrumpe la lectura.
    const bajar = (suave = true) => log.scrollTo({ top: log.scrollHeight, behavior: suave && !reducir.matches ? 'smooth' : 'auto' });
    log.addEventListener('scroll', () => { pegado = log.scrollHeight - log.scrollTop - log.clientHeight < 90; }, { passive: true });
    raiz.addEventListener('click', (e) => { if (e.target.closest('button')) forzar = true; });
    $wire.on('tutor-actualizado', () => requestAnimationFrame(() => {
        if (pegado || forzar) bajar();
        forzar = false;
        ajustar();
    }));
    bajar(false);

    // Enter envía; Shift + Enter hace salto de línea.
    const ajustar = () => { entrada.style.height = 'auto'; entrada.style.height = Math.min(entrada.scrollHeight, 140) + 'px'; };
    entrada.addEventListener('input', ajustar);
    entrada.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            if (entrada.value.trim() !== '' && !entrada.disabled) { forzar = true; raiz.querySelector('[data-tutor-form]').requestSubmit(); }
        }
    });

</script>
@endscript
