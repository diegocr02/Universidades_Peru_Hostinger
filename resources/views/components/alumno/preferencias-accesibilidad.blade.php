{{-- Ajustes de accesibilidad del alumno (tamaño de texto, contraste, animaciones), guardados en este navegador. --}}
<details class="a11y-widget" data-a11y>
    <summary aria-label="Ajustes de accesibilidad"><span aria-hidden="true">Aa</span><span class="a11y-sr">Accesibilidad</span></summary>
    <div class="a11y-panel" role="group" aria-label="Ajustes de accesibilidad">
        <p class="a11y-titulo">Accesibilidad</p>
        <fieldset>
            <legend>Tamaño del texto</legend>
            <label><input type="radio" name="a11y-texto" value="normal"> Normal</label>
            <label><input type="radio" name="a11y-texto" value="grande"> Grande</label>
            <label><input type="radio" name="a11y-texto" value="muy-grande"> Muy grande</label>
        </fieldset>
        <label class="a11y-opcion"><input type="checkbox" name="a11y-contraste"> Alto contraste</label>
        <label class="a11y-opcion"><input type="checkbox" name="a11y-movimiento"> Reducir animaciones</label>
        <p class="a11y-nota">Se guarda solo en este dispositivo.</p>
    </div>
</details>
<script>
    (() => {
        const caja = document.querySelector('[data-a11y]');
        if (!caja || caja.dataset.listo) return;
        caja.dataset.listo = '1';
        const h = document.documentElement;
        let p = {};
        try { p = JSON.parse(localStorage.getItem('cni-accesibilidad') || '{}'); } catch (e) {}
        const aplicar = () => {
            h.classList.remove('a11y-texto-grande', 'a11y-texto-muy-grande');
            if (p.texto && p.texto !== 'normal') h.classList.add('a11y-texto-' + p.texto);
            h.classList.toggle('a11y-contraste', !!p.contraste);
            h.classList.toggle('a11y-sin-animaciones', !!p.movimiento || window.matchMedia('(prefers-reduced-motion: reduce)').matches);
            try { localStorage.setItem('cni-accesibilidad', JSON.stringify(p)); } catch (e) {}
        };
        caja.querySelector(`input[name="a11y-texto"][value="${p.texto || 'normal'}"]`).checked = true;
        caja.querySelector('input[name="a11y-contraste"]').checked = !!p.contraste;
        caja.querySelector('input[name="a11y-movimiento"]').checked = !!p.movimiento;
        caja.addEventListener('change', (e) => {
            if (e.target.name === 'a11y-texto') p.texto = e.target.value;
            if (e.target.name === 'a11y-contraste') p.contraste = e.target.checked;
            if (e.target.name === 'a11y-movimiento') p.movimiento = e.target.checked;
            aplicar();
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && caja.open) { caja.open = false; caja.querySelector('summary').focus(); } });
    })();
</script>
