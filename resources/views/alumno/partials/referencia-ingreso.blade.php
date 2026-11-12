<section @class(['cni-ingreso', 'is-cumple' => $evaluacionIngreso['supera'] === true, 'is-fuera' => $evaluacionIngreso['supera'] === false, 'is-pendiente' => $evaluacionIngreso['supera'] === null]) aria-label="Proyección de ingreso referencial">
    <div class="cni-ingreso-encabezado">
        <div><p class="cni-ingreso-etiqueta">Proyección de ingreso · {{ $evaluacionIngreso['universidad'] }} {{ $evaluacionIngreso['proceso'] }}</p>
            <h2>{{ $evaluacionIngreso['estado'] }}</h2></div>
        <span class="cni-ingreso-estado">{{ $evaluacionIngreso['supera'] === true ? 'Mínimo alcanzado' : (! $evaluacionIngreso['completo'] ? 'Resultado parcial' : ($evaluacionIngreso['minimo'] === null ? 'Sin referencia' : 'Sigue practicando')) }}</span>
    </div>
    <dl class="cni-ingreso-metricas">
        <div><dt>{{ $evaluacionIngreso['completo'] ? 'Tu puntaje' : 'Tu acumulado parcial' }}</dt><dd>{{ number_format($evaluacionIngreso['puntaje'], 3, '.', ',') }} <small>/ {{ number_format($evaluacionIngreso['maximo'], 0, '.', ',') }}</small></dd></div>
        <div class="cni-ingreso-meta"><dt>Mínimo de referencia · {{ $evaluacionIngreso['proceso'] }}</dt><dd>{{ $evaluacionIngreso['minimo'] === null ? 'Sin dato' : number_format($evaluacionIngreso['minimo'], 3, '.', ',') }} <small>puntos</small></dd><p>{{ $evaluacionIngreso['referencia_contexto'] }}</p></div>
        @if (! $evaluacionIngreso['completo'])
        <div><dt>Días completados</dt><dd>{{ $evaluacionIngreso['dias_rendidos'] }} <small>/ 3</small></dd><p class="cni-ingreso-progreso">El rango se evalúa al terminar los tres días.</p></div>
        @else
        <div><dt>{{ $evaluacionIngreso['supera'] === true ? 'Puntos que te faltan' : 'Puntos para alcanzar la referencia' }}</dt><dd>{{ $evaluacionIngreso['faltan'] === null ? 'Sin dato' : number_format($evaluacionIngreso['faltan'], 3, '.', ',') }}</dd></div>
        @endif
    </dl>
    <p class="cni-ingreso-aviso">Referencia de entrenamiento. El ingreso real depende de vacantes y orden de mérito.</p>
    <details class="cni-ingreso-criterio">
        <summary>Ver criterio y fuentes</summary>
        <p class="cni-ingreso-regla">{{ $evaluacionIngreso['regla'] }}</p>
        <p class="cni-ingreso-detalle">{{ $evaluacionIngreso['detalle'] }}</p>
        <p class="cni-ingreso-detalle">Ingreso oficial: no determinado por este simulacro.</p>
        <div class="cni-ingreso-fuentes">@foreach($evaluacionIngreso['fuentes'] as $fuente)<a href="{{ $fuente['url'] }}" target="_blank" rel="noopener noreferrer">{{ $fuente['titulo'] }} ↗</a>@endforeach</div>
    </details>
</section>
