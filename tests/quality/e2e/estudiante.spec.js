// Escenarios E2E del estudiante (Villarreal, área C) con selectores y rutas reales del proyecto.
// Credenciales: tests/quality/.credenciales-qa.json, generadas por preparar-qa.php (nunca reales).
// Cada escenario está dividido en pasos (test.step) para que el resultado se lea como una lista de
// comprobaciones: la terminal y el reporte HTML muestran qué paso pasó o falló.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const cred = JSON.parse(readFileSync(new URL('../.credenciales-qa.json', import.meta.url), 'utf8'));

async function iniciarSesion(page, usuario) {
  await page.goto('/login');
  await page.locator('input[name="usuario"]').fill(usuario);
  await page.locator('input[name="password"]').fill(cred.clave);
  await page.locator('button.login-submit[type="submit"]').click();
  await page.waitForURL('**/alumno/panel');
}

// Estado compartido entre escenarios: el intento que crea el escenario 2.
let intentoId = null;
let respondidas = 0;

// Los escenarios dependen entre sí (el 3 revisa el intento del 2): se ejecutan en orden.
test.describe.configure({ mode: 'serial' });

test('1 · AUTENTICACIÓN · el estudiante inicia sesión y llega a su panel', async ({ page }, info) => {
  await test.step('Se abre la página de login', async () => {
    await page.goto('/login');
    await expect(page.locator('input[name="usuario"]')).toBeVisible();
    await info.attach('01-login', { body: await page.screenshot(), contentType: 'image/png' });
  });
  await test.step('Escribe usuario y contraseña de prueba y presiona Ingresar', async () => {
    await iniciarSesion(page, cred.e2e_alumno);
  });
  await test.step('Llega a /alumno/panel con su nombre y el menú "Examen UNFV"', async () => {
    await expect(page).toHaveURL(/\/alumno\/panel$/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Hola, Alumno');
    await expect(page.locator('.cni-alumno-top-profile')).toContainText('Alumno E2E');
    await expect(page.getByRole('link', { name: /Examen UNFV/ })).toBeVisible();
    await info.attach('02-panel-estudiante', { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
  });
});

test('2 · SIMULACRO · inicia el examen, responde, la respuesta se guarda y lo envía', async ({ page }, info) => {
  let progreso;
  let numero;

  await test.step('Inicia sesión y presiona el botón del examen en su panel', async () => {
    await iniciarSesion(page, cred.e2e_alumno);
    await page.locator('.cni-unfv-inicio button[type="submit"]').click();
  });
  await test.step('Se abre el examen (/intentos/{id}/examen) con 50 preguntas', async () => {
    await page.waitForURL(/\/intentos\/\d+\/examen$/);
    intentoId = Number(page.url().match(/\/intentos\/(\d+)\/examen/)[1]);
    expect(intentoId).toBeGreaterThan(0);
    progreso = page.getByRole('progressbar', { name: 'Preguntas respondidas' });
    await expect(progreso).toHaveAttribute('aria-valuemax', '50');
    await info.attach('03-examen', { body: await page.screenshot(), contentType: 'image/png' });
  });
  await test.step('Marca una alternativa y el contador de respondidas sube en 1', async () => {
    // Si el botón reanudó un intento abierto, se parte de sus respuestas previas.
    const previas = Number(await progreso.getAttribute('aria-valuenow'));
    numero = Number((await page.getByText(/^Pregunta \d+ de 50$/).textContent()).match(/\d+/)[0]);
    respondidas = previas + 1;
    const opcion = page.locator('.examen-opcion[role="radio"]').first();
    await opcion.click();
    await expect(opcion).toHaveAttribute('aria-checked', 'true');
    await expect(progreso).toHaveAttribute('aria-valuenow', String(respondidas));
  });
  await test.step('Recarga la página: la respuesta sigue guardada en el servidor', async () => {
    await page.reload();
    await expect(progreso).toHaveAttribute('aria-valuenow', String(respondidas));
    // Al recargar, el examen continúa en la siguiente sin responder; se vuelve a la respondida.
    await expect(page.getByText(`Pregunta ${numero + 1} de 50`)).toBeVisible();
    await page.getByRole('button', { name: /Anterior/ }).click();
    await expect(page.getByText(`Pregunta ${numero} de 50`)).toBeVisible();
    await expect(page.locator('.examen-opcion[aria-checked="true"]')).toHaveCount(1);
    await info.attach('04-respuesta-registrada', { body: await page.screenshot(), contentType: 'image/png' });
  });
  await test.step('Presiona "Finalizar examen", confirma en el aviso y llega a sus resultados', async () => {
    await page.getByRole('button', { name: 'Finalizar examen' }).first().click();
    const dialogo = page.getByRole('dialog', { name: /Enviar el examen/ });
    await expect(dialogo).toBeVisible();
    await expect(dialogo).toContainText(`${respondidas} de 50 respondidas`);
    await dialogo.getByRole('button', { name: 'Enviar examen' }).click();
    await page.waitForURL(new RegExp(`/alumno/resultados/${intentoId}$`));
  });
});

test('3 · RESULTADOS · ve su calificación y corresponde a su propio intento', async ({ page }, info) => {
  test.skip(!intentoId, 'Depende del intento creado en el escenario 2.');
  await test.step('Inicia sesión y abre el resultado de su intento', async () => {
    await iniciarSesion(page, cred.e2e_alumno);
    await page.goto(`/alumno/resultados/${intentoId}`);
  });
  await test.step('Se muestra "Resultados de tu examen" con el Puntaje total (un número)', async () => {
    await expect(page.getByRole('heading', { name: 'Resultados de tu examen' })).toBeVisible();
    const puntaje = page.locator('.is-puntaje');
    await expect(puntaje).toContainText('Puntaje total');
    await expect(puntaje.locator('strong')).toHaveText(/^-?\d/);
    await info.attach('05-resultado', { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
  });
  await test.step('Su historial muestra ese mismo intento con sus respuestas y el enlace correcto', async () => {
    await page.goto('/alumno/panel#historial');
    const fila = page.locator('.cni-historial-tabla tbody tr').filter({ hasText: `#${String(intentoId).padStart(3, '0')}` });
    await expect(fila).toHaveCount(1);
    await expect(fila).toContainText(`${respondidas} / 50`);
    await expect(fila.getByRole('link', { name: 'Ver resultado' })).toHaveAttribute('href', new RegExp(`/alumno/resultados/${intentoId}$`));
    await info.attach('06-historial', { body: await page.screenshot(), contentType: 'image/png' });
  });
});

test('4 · SEGURIDAD · no puede abrir el resultado de OTRO estudiante (responde 404)', async ({ page }, info) => {
  await test.step('Inicia sesión como e2e_alumno', async () => {
    await iniciarSesion(page, cred.e2e_alumno);
  });
  await test.step(`Intenta abrir el resultado ajeno #${cred.intento_ajeno} (de e2e_otro)`, async () => {
    const respuesta = await page.goto(`/alumno/resultados/${cred.intento_ajeno}`);
    expect(respuesta.status(), 'El servidor debe negar el acceso con 404').toBe(404);
  });
  await test.step('La página NO muestra ninguna calificación', async () => {
    await expect(page.getByText('Resultados de tu examen')).toHaveCount(0);
    await expect(page.getByText('Puntaje total')).toHaveCount(0);
    await info.attach('07-resultado-ajeno-rechazado', { body: await page.screenshot(), contentType: 'image/png' });
  });
});

test('4b · SEGURIDAD · sin iniciar sesión, el panel redirige al login', async ({ page }) => {
  await test.step('Abre /alumno/panel sin sesión y termina en /login', async () => {
    await page.goto('/alumno/panel');
    await expect(page).toHaveURL(/\/login$/);
  });
});
