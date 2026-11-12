// Configuración Playwright del Simulador CNI (escenarios ATAM de seguridad y confiabilidad funcional).
// Se ejecuta solo contra el servidor QA local (APP_ENV=qa, puerto 8001, base simulador_cni_qa).
import { defineConfig } from '@playwright/test';
import { readFileSync } from 'node:fs';

const cred = JSON.parse(readFileSync(new URL('./.credenciales-qa.json', import.meta.url), 'utf8'));
const baseURL = process.env.BASE_URL || cred.base_url;
const host = new URL(baseURL).hostname;
if (!['127.0.0.1', 'localhost'].includes(host)) {
  throw new Error(`Bloqueado: ${host} no es local. Las pruebas E2E solo se ejecutan contra el entorno QA local.`);
}

export default defineConfig({
  testDir: './e2e',
  // Los escenarios comparten el estado del alumno E2E (intento creado → resultado): se ejecutan en orden.
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 15_000 },
  outputDir: './test-results',
  reporter: [
    // printSteps: la terminal muestra cada paso del escenario con ✓ / ✘.
    ['list', { printSteps: true }],
    ['html', { outputFolder: './playwright-report', open: 'never' }],
    ['json', { outputFile: './resultados/playwright-resultados.json' }],
  ],
  use: {
    baseURL,
    // Chrome instalado en el equipo: evita descargar navegadores.
    channel: process.env.PW_CHANNEL || 'chrome',
    headless: true,
    locale: 'es-PE',
    timezoneId: 'America/Lima',
    viewport: { width: 1440, height: 900 },
    screenshot: 'on',
    trace: 'retain-on-failure',
  },
});
