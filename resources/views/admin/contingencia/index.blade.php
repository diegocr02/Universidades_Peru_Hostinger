<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Centro de contingencia | Simulador CNI</title>
    <style>
        :root{color-scheme:light;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}
        *{box-sizing:border-box}body{margin:0;background:#f3f7fc;color:#1b334c}
        .wrap{max-width:1110px;margin:36px auto;padding:0 20px}
        .top{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px}
        h1{margin:10px 0 4px;font-size:clamp(1.55rem,3vw,2.1rem)}p{line-height:1.55}
        .muted{color:#566a80;font-size:.9rem}.card{background:white;border:1px solid #dce6f1;border-radius:14px;padding:22px;box-shadow:0 4px 24px #1e47720b}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(195px,1fr));gap:14px;margin:20px 0}
        .big{font-weight:800;font-size:1.5rem;margin:7px 0}.badge{padding:4px 9px;border-radius:20px;font-size:.75rem;font-weight:700;background:#e8f5e9;color:#235c2b}
        .bad{background:#fff1eb;color:#9a3914}.neutral{background:#edf2f8;color:#52657b}
        .actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center}
        button,.btn{background:#145c96;color:white;border:0;padding:9px 12px;border-radius:8px;cursor:pointer;font-weight:650;text-decoration:none;font-size:.83rem}
        button.secondary,.btn.secondary{background:#e9f1fa;color:#174669}
        button:disabled{opacity:.4;cursor:not-allowed}
        .alert{padding:13px 15px;margin:16px 0;border-radius:8px;background:#e8f6ef;border:1px solid #97d4b4}
        .alert.err{background:#fff1eb;border-color:#ffbf9c}
        .tablewrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:.88rem;min-width:760px}th,td{padding:13px 10px;border-bottom:1px solid #e6edf5;text-align:left;vertical-align:middle}
        th{color:#5f738a;font-size:.76rem;text-transform:uppercase;letter-spacing:.04em}
        footer{margin:22px 0;color:#6f8092;font-size:.82rem}
    </style>
</head>
<body>
<main class="wrap">
    <div class="top">
        <div><div class="muted">Simulador CNI · Seguridad de la información</div><h1>🛡️ Centro de contingencia</h1><p class="muted">Respaldos cifrados de MariaDB y distribución automática a instancias externas</p></div>
        <a class="btn secondary" href="{{ route('panel') }}">← Volver al panel</a>
    </div>
    @if(session('status'))<div class="alert" role="status">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="alert err" role="alert">{{ session('error') }}</div>@endif
    @if($activo && $problema)<div class="alert err" role="alert">⚠️ {{ $problema }}</div>@endif
    <div class="grid">
        <section class="card"><div class="muted">Sistema de respaldo</div><div class="big">{{ $activo ? 'Activado' : 'Desactivado' }}</div><span class="badge {{ $activo && !$problema ? '' : 'bad' }}">{{ !$activo ? 'Configura .env' : ($problema ? 'Falta configuración' : 'Listo para pruebas') }}</span></section>
        <section class="card"><div class="muted">Copias locales registradas</div><div class="big">{{ count($respaldos) }}</div><div class="muted">Hasta 50 recientes</div></section>
        <section class="card"><div class="muted">Destino externo A</div><div class="big">{{ $destinos['destino_a'] ? 'Configurado' : 'Pendiente' }}</div><span class="badge {{ $destinos['destino_a'] ? '' : 'neutral' }}">{{ $destinos['destino_a'] ? 'HTTPS habilitado' : 'Sin conexión externa' }}</span></section>
        <section class="card"><div class="muted">Destino externo B</div><div class="big">{{ $destinos['destino_b'] ? 'Configurado' : 'Pendiente' }}</div><span class="badge {{ $destinos['destino_b'] ? '' : 'neutral' }}">{{ $destinos['destino_b'] ? 'HTTPS habilitado' : 'Sin conexión externa' }}</span></section>
    </div>
    <section class="card" style="margin-bottom:18px">
        <div class="top"><div><h2 style="margin-top:0">Crear un respaldo ahora</h2><p class="muted">Exporta las tablas InnoDB, comprime, cifra y verifica la copia. Si configuraste destinos HTTPS, intenta enviarla.</p></div>
            <form method="POST" action="{{ route('admin.contingencia.crear') }}" onsubmit="return confirm('¿Generar un nuevo respaldo cifrado?')">
                @csrf <button type="submit" @disabled(!$activo || $problema)>🗄️ Generar respaldo</button>
            </form>
        </div>
    </section>
    <section class="card">
        <h2 style="margin-top:0">Historial de seguridad</h2>
        <p class="muted">El estado «Verificado» confirma la integridad del archivo cifrado; no sustituye una prueba de restauración en una base de datos vacía.</p>
        <div class="tablewrap"><table><thead><tr><th>Fecha</th><th>Archivo</th><th>Contenido</th><th>Destino A</th><th>Destino B</th><th>Acciones</th></tr></thead><tbody>
        @forelse($respaldos as $r)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($r['fecha'])->timezone('America/Lima')->format('d/m/Y H:i') }}</td>
                <td><strong>{{ $r['archivo'] }}</strong><br><span class="muted">{{ number_format(($r['bytes'] ?? 0)/1024, 1) }} KB · {{ $r['disponible'] ? 'Archivo disponible' : 'Archivo eliminado' }}</span></td>
                <td>{{ $r['tablas'] }} tablas<br><span class="muted">{{ $r['filas'] }} filas</span></td>
                <td><span class="badge {{ ($r['destinos']['destino_a']['estado'] ?? '') === 'enviado' ? '' : 'neutral' }}">{{ $r['destinos']['destino_a']['estado'] ?? 'pendiente' }}</span></td>
                <td><span class="badge {{ ($r['destinos']['destino_b']['estado'] ?? '') === 'enviado' ? '' : 'neutral' }}">{{ $r['destinos']['destino_b']['estado'] ?? 'pendiente' }}</span></td>
                <td><div class="actions">
                    @if($r['disponible'])
                    <form method="POST" action="{{ route('admin.contingencia.verificar', $r['id']) }}">@csrf <button class="secondary" type="submit">Verificar</button></form>
                    <form method="POST" action="{{ route('admin.contingencia.enviar', $r['id']) }}">@csrf <button class="secondary" type="submit">Reenviar</button></form>
                    <a class="btn" href="{{ route('admin.contingencia.descargar', $r['id']) }}">Descargar cifrado</a>
                    @endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="6" style="padding:30px;text-align:center;color:#72839a">Todavía no se ha generado ningún respaldo.</td></tr>
        @endforelse
        </tbody></table></div>
    </section>
    <footer>⚠️ Nunca publiques archivos .cni, .sql.gz o claves de cifrado en GitHub. Para restaurar, utiliza el comando CLI documentado y una base de datos independiente.</footer>
</main>
</body></html>