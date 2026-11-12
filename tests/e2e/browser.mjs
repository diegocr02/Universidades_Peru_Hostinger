/**
 * Verificación real con Chromium del frontend Blade/Livewire, Laravel y MariaDB.
 * Solo en runner GitHub Actions con entorno temporal cni_browser_ci.
 */
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { chromium } from 'playwright';

if (process.env.CNI_E2E_CI_ONLY !== '1'
    || process.env.DB_DATABASE !== 'cni_browser_ci'
    || process.env.APP_ENV !== 'testing') {
  throw new Error('Prueba bloqueada: solo para MariaDB efímera de CI');
}
const clave = process.env.CNI_E2E_PASSWORD;
assert.ok(clave && clave.length >= 24, 'Falta la clave temporal del runner');
const base = 'http://127.0.0.1:8000';
const directorio = 'tests/e2e/capturas';
await mkdir(directorio, { recursive: true });
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  locale: 'es-PE',
  timezoneId: 'America/Lima',
});
const page = await context.newPage();
page.setDefaultTimeout(15000);

const errores = [];
page.on('pageerror', error => errores.push('JS: ' + error.message));
page.on('response', response => {
  if (response.status() >= 500 && response.url().startsWith(base)) {
    errores.push('HTTP ' + response.status() + ': ' + response.url());
  }
});

async function captura(nombre) {
  await page.screenshot({ path: directorio + '/' + nombre + '.png', fullPage: true });
}
async function login(usuario, ruta) {
  await page.goto(base + '/login', { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="usuario"]').fill(usuario);
  await page.locator('input[name="password"]').fill(clave);
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await page.waitForURL(url => url.pathname === ruta, { timeout: 20000 });
  console.log('[OK] Sesión ' + usuario + ' → ' + ruta);
}
async function logout() {
  await page.locator('form[action$="/logout"] button').first().click();
  await page.waitForURL(url => url.pathname === '/login');
}
async function verificarCss(fragmento) {
  const hojas = await page.evaluate(() => [...document.styleSheets].map(s => s.href).filter(Boolean));
  assert.ok(hojas.some(url => url.includes(fragmento)), 'No cargó la hoja ' + fragmento);
}
async function anchoMovil(nombre) {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(250);
  const ancho = await page.evaluate(() => ({
    disponible: window.innerWidth,
    contenido: document.documentElement.scrollWidth,
  }));
  await captura(nombre);
  assert.ok(ancho.contenido <= ancho.disponible + 2,
    'Desbordamiento horizontal en ' + nombre + ': ' + JSON.stringify(ancho));
  await page.setViewportSize({ width: 1440, height: 900 });
  console.log('[OK] Responsive sin desplazamiento horizontal: ' + nombre);
}

try {
  await page.goto(base + '/login');
  await page.getByRole('heading', { name: /BIENVENIDO AL SIMULADOR CNI/i }).waitFor();
  await verificarCss('/css/app.css');
  await captura('01-login-desktop');
  await anchoMovil('02-login-movil');

  // Administrador: interfaz renderizada y rutas de gestión (no insertar datos adicionales).
  await login('admin_demo', '/administracion');
  await page.getByRole('heading', { name: 'Panel del administrador' }).waitFor();
  await verificarCss('/css/admin.css');
  await captura('03-panel-admin');
  await page.goto(base + '/administracion/preguntas/nueva');
  await page.locator('form').first().waitFor();
  assert.ok((await page.locator('input,textarea,select').count()) > 3, 'Formulario de preguntas vacío');
  await captura('04-nueva-pregunta');
  await page.goto(base + '/administracion/simulacros/nuevo');
  await page.getByRole('heading', { name: /simulacro/i }).first().waitFor();
  await captura('05-nuevo-simulacro');
  await logout();

  // Alumno: crear estado del perfil por formulario HTML (POST+CSRF) y examinar el backend.
  await login('estudiante_demo', '/alumno/elegir-area');
  const areas = page.locator('.areas-grid form.area-tarjeta');
  assert.equal(await areas.count(), 5, 'Deben verse cinco áreas A-E');
  await captura('06-cinco-areas');
  await anchoMovil('07-areas-movil');
  await areas.filter({ hasText: 'Ingenierías' }).getByRole('button', { name: /Seleccionar área/ }).click();
  await page.waitForURL(url => url.pathname === '/alumno/panel');
  await page.getByRole('heading', { name: /Bienvenido/i }).waitFor();
  await page.getByText('Tu área elegida es').waitFor();
  await captura('08-panel-alumno');
  await anchoMovil('09-panel-alumno-movil');

  const ficha = page.locator('.simulacro-fila').filter({ hasText: 'DEMO CNI - Área C' });
  await ficha.getByRole('button', { name: /Iniciar examen/ }).click();
  await page.waitForURL(url => /^\/intentos\/\d+\/examen$/.test(url.pathname), { timeout: 20000 });
  const intento = page.url().match(/\/intentos\/(\d+)\/examen/);
  assert.ok(intento, 'El navegador debe recibir la ruta real del intento creado');
  await page.getByRole('heading', { name: /Simulador de examen/ }).waitFor();
  await verificarCss('/css/examen-simple.css');
  assert.equal(await page.locator('.examen-mapa__preguntas button').count(), 20);
  await captura('10-examen-livewire');

  // Guardar exactamente una respuesta por petición de Livewire y validar DOM re-renderizado.
  await page.locator('.examen-opcion[role="radio"]').first().click();
  await page.locator('.examen-opcion[aria-checked="true"]').first().waitFor({ timeout: 20000 });
  const actual = await page.getByRole('progressbar').getAttribute('aria-valuenow');
  assert.equal(actual, '1', 'La UI no actualizó el contador tras guardar la respuesta');
  await captura('11-respuesta-livewire-guardada');

  await page.getByRole('button', { name: 'Finalizar examen' }).click();
  const dialogo = page.getByRole('dialog', { name: /Enviar el examen/ });
  await dialogo.waitFor();
  await dialogo.getByRole('button', { name: 'Enviar examen' }).click();
  await page.waitForURL(url => /^\/alumno\/resultados\/\d+$/.test(url.pathname), { timeout: 25000 });
  await page.getByRole('heading', { name: /Resultados de tu examen/ }).waitFor();
  await page.getByText('Resultados por asignatura').waitFor();
  await captura('12-resultados-guardados');
  console.log('[OK] Pantallas y acciones: selección, examen, respuesta Livewire, resultado');

  await logout();

  // Docente de la misma sección: comprobar que los resultados de MariaDB se reflejan en UI.
  await login('docente_demo', '/docente');
  await page.getByRole('heading', { name: 'Calificaciones del salón' }).waitFor();
  await page.locator('.cni-notas-table tbody .cni-fila').first().waitFor();
  const fila = page.locator('.cni-notas-table tbody .cni-fila').filter({ hasText: 'Estudiante' });
  assert.ok((await fila.count()) >= 1, 'Docente no ve al estudiante DEMO de su sección');
  assert.ok((await fila.first().getAttribute('data-estado')) === 'calificado',
    'El resultado guardado no llega al panel docente');
  await captura('13-panel-docente-resultado');
  await page.goto(base + '/docente/calificaciones');
  await page.getByRole('heading', { name: 'Calificaciones de mi salón' }).waitFor();
  await captura('14-calificaciones-docente');

  assert.deepEqual(errores, [], 'Se detectaron errores HTTP 5xx o JavaScript en el recorrido');
  console.log('[OK] E2E REAL: frontend + backend + base MariaDB enlazados; intento=' + intento[1]);
} catch (error) {
  console.error('[ERROR] Falló el recorrido E2E:', error.message);
  console.error('Última URL:', page.url());
  await captura('ERROR-ultima-pantalla').catch(() => {});
  throw error;
} finally {
  await browser.close();
}
