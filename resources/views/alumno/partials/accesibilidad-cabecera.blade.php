{{-- Preferencias de accesibilidad: se aplican antes de pintar la página (sin parpadeo). --}}
<link rel="stylesheet" href="{{ asset('css/alumno/accesibilidad.css') }}?v={{ @filemtime(public_path('css/alumno/accesibilidad.css')) }}">
<script>
    (() => {
        try {
            const p = JSON.parse(localStorage.getItem('cni-accesibilidad') || '{}');
            const h = document.documentElement;
            if (p.texto === 'grande' || p.texto === 'muy-grande') h.classList.add('a11y-texto-' + p.texto);
            if (p.contraste) h.classList.add('a11y-contraste');
            if (p.movimiento || window.matchMedia('(prefers-reduced-motion: reduce)').matches) h.classList.add('a11y-sin-animaciones');
        } catch (e) { /* Sin almacenamiento disponible: se usan los valores por defecto. */ }
    })();
</script>
