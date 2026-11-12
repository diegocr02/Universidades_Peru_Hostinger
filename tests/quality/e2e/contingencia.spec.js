// Escenarios E2E del Centro de contingencia (respaldos cifrados) con el Super_Admin de QA.
// Requisitos: preparar-qa.php (crea Super_Admin QA) y en .env.qa CNI_BACKUP_ENABLED/PANEL_ENABLED=true
// con una CNI_BACKUP_KEY solo de pruebas. Los respaldos se crean en la base QA local, nunca en producción.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const cred = JSON.parse(readFileSync(new URL('../.credenciales-qa.json', import.meta.url), 'utf8'));

async function iniciarSesion(page, usuario) {
  await page.goto('/login');
  await page.locator('input[name="usuario"]').fill(usuario);
  await page.locator('input[name="password"]').fill(cred.clave);
  await page.locator('button.login-submit[type="submit"]').click();
  await page.waitForURL(url => !url.pathname.startsWith('/login'));
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  if (!cred.e2e_admin) {
    throw new Error('Falta e2e_admin en .credenciales-qa.json: ejecuta de nuevo APP_ENV=qa php tests/quality/preparar-qa.php');
  }
  // El panel permite 2 respaldos cada 10 min (throttle:2,10). Para poder repetir la prueba se
  // limpia la caché del entorno QA local (ahí vive ese contador). Nunca toca producción.
  execFileSync('php', ['artisan', 'cache:clear'], {
    cwd: fileURLToPath(new URL('../../../', import.meta.url)),
    env: { ...process.env, APP_ENV: 'qa' },
    stdio: 'ignore',
  });
});

test('C1 · CONTINGENCIA · el Super_Admin ve el enlace y el panel listo', async ({ page }, info) => {
  await test.step('Inicia sesión como Super_Admin', async () => {
    await iniciarSesion(page, cred.e2e_admin);
  });
  await test.step('El menú muestra "Centro de contingencia"', async () => {
    await expect(page.getByRole('link', { name: /Centro de contingencia/ }).first()).toBeVisible();
  });
  await test.step('El panel dice "Activado · Listo para pruebas" y el botón está habilitado', async () => {
    await page.getByRole('link', { name: /Centro de contingencia/ }).first().click();
    await page.waitForURL('**/administracion/contingencia');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Centro de contingencia');
    await expect(page.getByText('Listo para pruebas')).toBeVisible();
    await expect(page.getByText('Falta configuración')).toHaveCount(0);
    await expect(page.getByRole('button', { name: /Generar respaldo/ })).toBeEnabled();
    await info.attach('C1-panel-contingencia', { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
  });
});

test('C2 · CONTINGENCIA · genera un respaldo cifrado y lo verifica', async ({ page }, info) => {
  let filasAntes;

  await test.step('Abre el panel como Super_Admin', async () => {
    await iniciarSesion(page, cred.e2e_admin);
    await page.goto('/administracion/contingencia');
    filasAntes = await page.getByRole('button', { name: 'Verificar' }).count();
  });
  await test.step('Presiona "Generar respaldo" y acepta la confirmación', async () => {
    page.once('dialog', dialogo => dialogo.accept());
    await page.getByRole('button', { name: /Generar respaldo/ }).click();
    await expect(page.getByRole('status')).toContainText('Copia local cifrada creada', { timeout: 60_000 });
  });
  await test.step('Aparece una fila nueva en el historial con el archivo .cni', async () => {
    await expect(page.getByRole('button', { name: 'Verificar' })).toHaveCount(filasAntes + 1);
    await expect(page.locator('tbody tr').first()).toContainText('.cni');
    await expect(page.locator('tbody tr').first()).toContainText('Archivo disponible');
    await info.attach('C2-respaldo-creado', { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
  });
  await test.step('Presiona "Verificar": SHA-256 y autenticidad confirmados', async () => {
    await page.getByRole('button', { name: 'Verificar' }).first().click();
    await expect(page.getByRole('status')).toContainText('SHA-256 y autenticidad del respaldo confirmados');
    await info.attach('C2-respaldo-verificado', { body: await page.screenshot(), contentType: 'image/png' });
  });
});

test('C3 · SEGURIDAD · un estudiante no puede entrar al Centro de contingencia', async ({ page }) => {
  await test.step('Inicia sesión como estudiante', async () => {
    await iniciarSesion(page, cred.e2e_alumno);
  });
  await test.step('No ve el enlace en su menú', async () => {
    await expect(page.getByRole('link', { name: /Centro de contingencia/ })).toHaveCount(0);
  });
  await test.step('Si escribe la URL directamente recibe 403', async () => {
    const respuesta = await page.goto('/administracion/contingencia');
    expect(respuesta.status()).toBe(403);
  });
});
