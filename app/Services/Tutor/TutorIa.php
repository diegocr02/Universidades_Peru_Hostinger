<?php

namespace App\Services\Tutor;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente del proveedor de IA (OpenAI Chat Completions) con bucle de herramientas.
 * Ahorro: modelo pequeño, sin razonamiento extra, respuestas cortas, historial
 * recortado y como máximo N llamadas a herramientas por turno.
 */
class TutorIa
{
    public function configurada(): bool
    {
        return config('tutor.provider') === 'demo'
            || (config('tutor.provider') === 'openai' && filled(config('tutor.openai.api_key')));
    }

    public function esDemo(): bool
    {
        return config('tutor.provider') === 'demo';
    }

    /**
     * @param  array  $mensajes  [['role' => 'system'|'user'|'assistant', 'content' => string], ...]
     * @param  callable|null  $herramienta  fn(string $nombre, array $args): array
     * @return array{texto: string, tokens: int}
     */
    public function responder(array $mensajes, array $herramientas = [], ?callable $herramienta = null): array
    {
        if ($this->esDemo()) {
            return ['texto' => "**Modo demostración · respuesta de ejemplo.** En el modo real te explicaría tu consulta paso a paso.\n\n1. Lee con calma el enunciado.\n2. Identifica los datos.\n3. Aplica la fórmula del tema.", 'tokens' => 0];
        }
        if (! $this->configurada()) {
            throw new TutorIaException(TutorIaException::SIN_CONFIGURAR, 'El tutor de IA todavía no está configurado.');
        }

        $tokens = 0;
        $llamadas = 0;
        $maxLlamadas = max(0, (int) config('tutor.herramientas_por_turno', 3));

        while (true) {
            // Sin herramientas restantes, se pide la respuesta final (tool_choice = none).
            $conHerramientas = $herramienta !== null && $herramientas !== [];
            $ofrecer = $conHerramientas && $llamadas < $maxLlamadas;
            $respuesta = $this->llamar($mensajes, $conHerramientas ? $herramientas : [], $ofrecer);
            $tokens += (int) data_get($respuesta, 'usage.total_tokens', 0);
            $mensaje = data_get($respuesta, 'choices.0.message', []);
            $pedidas = $ofrecer ? (array) ($mensaje['tool_calls'] ?? []) : [];

            if ($pedidas === []) {
                $texto = trim((string) ($mensaje['content'] ?? ''));
                if ($texto === '' && data_get($respuesta, 'choices.0.finish_reason') === 'content_filter') {
                    $texto = 'No puedo ayudarte con eso. Preguntemos algo de tu preparación académica.';
                }
                if ($texto === '') {
                    throw new TutorIaException(TutorIaException::NO_DISPONIBLE, 'El tutor no generó una respuesta. Intenta de nuevo.');
                }

                return ['texto' => $texto, 'tokens' => $tokens];
            }

            $mensajes[] = ['role' => 'assistant', 'content' => $mensaje['content'] ?? null, 'tool_calls' => $pedidas];
            foreach ($pedidas as $pedida) {
                $llamadas++;
                $argumentos = json_decode((string) data_get($pedida, 'function.arguments', '{}'), true);
                $resultado = $llamadas > $maxLlamadas
                    ? ['error' => 'Límite de consultas por mensaje alcanzado.']
                    : $herramienta((string) data_get($pedida, 'function.name'), is_array($argumentos) ? $argumentos : []);
                // Los datos van como JSON: son información, nunca instrucciones.
                $mensajes[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($pedida['id'] ?? ''),
                    'content' => json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                ];
            }
        }
    }

    private function llamar(array $mensajes, array $herramientas, bool $permitirHerramientas = true): array
    {
        $cuerpo = [
            'model' => config('tutor.openai.model'),
            'messages' => $mensajes,
            'max_completion_tokens' => (int) config('tutor.max_tokens_respuesta', 600),
        ];
        if (filled(config('tutor.openai.reasoning_effort'))) {
            $cuerpo['reasoning_effort'] = config('tutor.openai.reasoning_effort');
        }
        if ($herramientas !== []) {
            $cuerpo['tools'] = $herramientas;
            $cuerpo['tool_choice'] = $permitirHerramientas ? 'auto' : 'none';
        }

        $peticion = Http::withToken((string) config('tutor.openai.api_key'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(max(5, (int) config('tutor.timeout', 25)));
        // PHP en Windows suele no traer certificados raíz: se usa el almacén del sistema
        // (la verificación TLS sigue activa; nunca se desactiva).
        if (config('tutor.certificados_sistema') && defined('CURLSSLOPT_NATIVE_CA')) {
            $peticion = $peticion->withOptions(['curl' => [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA]]);
        }

        try {
            $http = $peticion->post(rtrim((string) config('tutor.openai.base_url'), '/').'/chat/completions', $cuerpo);
        } catch (ConnectionException $e) {
            // Solo el código de cURL: sin URL con datos, cabeceras ni contenido.
            preg_match('/cURL error (\d+)/', $e->getMessage(), $codigo);
            Log::warning('Tutor IA: sin conexión con el proveedor', ['curl' => $codigo[1] ?? null]);

            throw ($codigo[1] ?? null) === '28' || ! isset($codigo[1])
                ? new TutorIaException(TutorIaException::TIEMPO, 'El tutor tardó demasiado en responder. Intenta de nuevo.')
                : new TutorIaException(TutorIaException::NO_DISPONIBLE, 'No se pudo conectar con el servicio de IA. Intenta de nuevo en unos minutos.');
        }

        if ($http->successful()) {
            return $http->json() ?? [];
        }

        // Solo se registra el estado y el tipo de error: nunca la clave ni la conversación.
        Log::warning('Tutor IA: el proveedor respondió con error', [
            'estado' => $http->status(),
            'tipo' => data_get($http->json(), 'error.code') ?? data_get($http->json(), 'error.type'),
        ]);

        throw match (true) {
            in_array($http->status(), [401, 403], true) => new TutorIaException(TutorIaException::SIN_CONFIGURAR,
                'La clave del tutor de IA no es válida. Avísale a tu docente.'),
            $http->status() === 429 => new TutorIaException(TutorIaException::NO_DISPONIBLE,
                'El servicio de IA está saturado o sin saldo. Intenta en unos minutos.'),
            default => new TutorIaException(TutorIaException::NO_DISPONIBLE,
                'El servicio de IA no está disponible en este momento. Intenta de nuevo.'),
        };
    }
}
