/**
 * Prueba de carga controlada del Simulador CNI con k6 (escenario ATAM de rendimiento).
 *
 * Escenario: 15 estudiantes concurrentes del área C (Villarreal) inician sesión con
 * sesión web + CSRF de Laravel y consultan repetidamente su panel (/alumno/panel),
 * que lee intentos, progreso e historial desde MariaDB.
 *
 * Uso (desde la raíz del proyecto, con el servidor QA levantado en :8001):
 *   APP_ENV=qa php tests/quality/preparar-qa.php      # crea cuentas k6 y la clave
 *   k6 run tests/performance/cni-load.js
 *
 * Seguridad:
 *  - Por defecto solo permite localhost / 127.0.0.1. Otro host exige K6_ENTORNO_AUTORIZADO=1
 *    (solo para un entorno de pruebas autorizado; nunca producción).
 *  - Las credenciales salen de tests/quality/.credenciales-qa.json (ignorado por git).
 *
 * Metas (criterios de evaluación, no resultados): p95 < 2 s y < 1 % de solicitudes fallidas.
 */
import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

const cred = JSON.parse(open('../quality/.credenciales-qa.json'));
const BASE = __ENV.BASE_URL || cred.base_url || 'http://127.0.0.1:8001';
const host = BASE.replace(/^https?:\/\//, '').split(/[:/]/)[0];
if (!['127.0.0.1', 'localhost'].includes(host) && __ENV.K6_ENTORNO_AUTORIZADO !== '1') {
  throw new Error(`Bloqueado: ${host} no es local. Usa K6_ENTORNO_AUTORIZADO=1 solo en un entorno de pruebas autorizado.`);
}

const panelDuracion = new Trend('cni_panel_duracion', true);
const loginsFallidos = new Counter('cni_logins_fallidos');

export const options = {
  scenarios: {
    estudiantes: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '15s', target: 5 },   // Fase 1: hasta 5 estudiantes
        { duration: '15s', target: 15 },  // Fase 2: hasta 15 estudiantes
        { duration: '30s', target: 15 },  // Fase 3: 15 estudiantes sostenidos
        { duration: '10s', target: 0 },   // Fase 4: reducción progresiva
      ],
      gracefulRampDown: '10s',
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<2000'],
    http_req_failed: ['rate<0.01'],
    'http_req_duration{pagina:panel}': ['p(95)<2000'],
    checks: ['rate>0.99'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)'],
  // Cada estudiante conserva su cookie de sesión entre iteraciones (k6 la borra por defecto).
  noCookiesReset: true,
};

let autenticado = false;

function iniciarSesion() {
  const usuario = cred.k6_usuarios[(__VU - 1) % cred.k6_usuarios.length];
  const formulario = http.get(`${BASE}/login`, { tags: { pagina: 'login_form' } });
  const token = (formulario.body || '').match(/name="_token" value="([^"]+)"/);
  if (!token) {
    loginsFallidos.add(1);
    fail('No se encontró el token CSRF en /login');
  }
  const respuesta = http.post(`${BASE}/login`, {
    _token: token[1], usuario, password: cred.clave,
  }, { tags: { pagina: 'login' }, redirects: 0 });
  const ok = check(respuesta, {
    'login redirige (302)': r => r.status === 302,
    'login lleva al panel': r => (r.headers.Location || '').includes('/alumno/panel'),
  });
  if (!ok) {
    loginsFallidos.add(1);
    fail(`Login fallido para ${usuario}: HTTP ${respuesta.status}`);
  }
  autenticado = true;
}

export default function () {
  if (!autenticado) {
    iniciarSesion();
  }
  const panel = http.get(`${BASE}/alumno/panel`, { tags: { pagina: 'panel' } });
  panelDuracion.add(panel.timings.duration);
  check(panel, {
    'panel responde 200': r => r.status === 200,
    'panel es el del estudiante (Examen UNFV)': r => (r.body || '').includes('Examen UNFV'),
  });
  sleep(1);
}

export function handleSummary(data) {
  const m = data.metrics;
  const v = (nombre, campo) => (m[nombre] && m[nombre].values[campo] !== undefined ? m[nombre].values[campo] : null);
  const seg = x => (x === null ? 'n/d' : `${(x / 1000).toFixed(2)} s`);
  const pct = x => (x === null ? 'n/d' : `${(x * 100).toFixed(1)} %`);
  const marca = ok => (ok ? '[ CUMPLE ]   ' : '[NO CUMPLE]  ');

  const usuarios = v('vus_max', 'max') || 0;
  const p95 = v('http_req_duration', 'p(95)');
  const promedio = v('http_req_duration', 'avg');
  const minimo = v('http_req_duration', 'min');
  const fallidas = v('http_req_failed', 'rate') || 0;
  const checks = v('checks', 'rate') || 0;
  const loginsFallidos = v('cni_logins_fallidos', 'count') || 0;
  const porSegundo = v('http_reqs', 'rate') || 0;

  const metaTiempo = p95 !== null && p95 < 2000;
  const metaErrores = fallidas < 0.01;
  const metaFuncional = checks > 0.99 && loginsFallidos === 0;
  const local = ['127.0.0.1', 'localhost'].includes(host);

  // Conclusión en lenguaje simple según lo que realmente se midió.
  const conclusion = [];
  if (metaTiempo && metaErrores && metaFuncional) {
    conclusion.push('El sistema soportó la carga: respondió rápido, sin errores y mostrando el panel correcto.');
  } else {
    if (!metaFuncional) {
      conclusion.push('ATENCIÓN: algunas respuestas no fueron el panel del estudiante (sesión perdida o error).',
        'Revisa que el servidor QA esté encendido y que existan las cuentas (preparar-qa.php).');
    }
    if (!metaErrores) {
      conclusion.push(`ATENCIÓN: ${pct(fallidas)} de las solicitudes fallaron (meta: menos de 1 %).`);
    }
    if (!metaTiempo && metaErrores && metaFuncional) {
      conclusion.push(
        'El sistema FUNCIONA bien (0 errores, todas las páginas correctas), pero responde LENTO con',
        `${usuarios} usuarios a la vez: el 95 % de las respuestas tardó hasta ${seg(p95)} (meta: menos de 2 s).`,
        `La respuesta más rápida fue ${seg(minimo)}: una sola petición es rápida; la demora es la ESPERA en cola.`,
      );
      if (local) {
        conclusion.push(
          '¿Por qué? Se probó contra "php artisan serve", el servidor de desarrollo, que en Windows',
          `atiende UNA petición a la vez (capacidad medida: ${porSegundo.toFixed(1)} por segundo). Los demás estudiantes esperan.`,
          'Un hosting real (Apache/LiteSpeed/Nginx + PHP-FPM) atiende varias en paralelo; para saberlo',
          'hay que medirlo en un entorno de pruebas (staging) con la misma configuración que producción.',
        );
      }
    }
  }

  const texto = [
    '',
    '==================================================================',
    '  RESULTADO · PRUEBA DE CARGA (k6) · SIMULADOR CNI',
    '==================================================================',
    `  Qué se probó : ${usuarios} estudiantes a la vez inician sesión y abren su panel`,
    `                 una y otra vez durante ~70 s (${BASE}/alumno/panel)`,
    `  Servidor     : ${local ? 'LOCAL (servidor de desarrollo, no es producción)' : BASE}`,
    '',
    '  CIFRAS',
    `  - Solicitudes atendidas ....... ${v('http_reqs', 'count')} (${porSegundo.toFixed(2)} por segundo)`,
    `  - Paneles cargados ............ ${v('iterations', 'count')}`,
    `  - Tiempo promedio ............. ${seg(promedio)}`,
    `  - Más rápida / más lenta ...... ${seg(minimo)} / ${seg(v('http_req_duration', 'max'))}`,
    `  - p95 (el 95 % tardó menos de)  ${seg(p95)}`,
    `  - Solicitudes con error ....... ${pct(fallidas)}`,
    `  - Logins fallidos ............. ${loginsFallidos}`,
    '',
    '  METAS',
    `  ${marca(metaTiempo)}Rapidez     : 95 % de respuestas en menos de 2 s  -> obtenido ${seg(p95)}`,
    `  ${marca(metaErrores)}Errores     : menos de 1 % de fallos             -> obtenido ${pct(fallidas)}`,
    `  ${marca(metaFuncional)}Funcionamiento: login correcto y panel correcto  -> ${pct(checks)} de verificaciones OK`,
    '',
    '  CONCLUSIÓN',
    ...conclusion.map(linea => `  ${linea}`),
    '',
    metaTiempo && metaErrores && metaFuncional
      ? '  Todas las metas se cumplen.'
      : '  Nota: si una meta no se cumple, k6 termina con la línea "ERRO ... thresholds ... crossed".',
    metaTiempo && metaErrores && metaFuncional
      ? ''
      : '  Eso NO es un fallo del programa de prueba: es k6 avisando que la meta no se alcanzó.',
    '==================================================================',
    '',
  ].join('\n');

  return {
    stdout: texto,
    'tests/quality/resultados/k6-resumen.json': JSON.stringify(data, null, 2),
    'tests/quality/resultados/k6-resumen.txt': texto,
  };
}
