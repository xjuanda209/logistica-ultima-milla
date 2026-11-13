<?php

namespace App\Modules\Asistente\IA;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Groq (plan gratuito), con su API compatible con OpenAI (chat/completions).
 */
class ClienteGroq implements ClienteIA
{
    public const MODELO_POR_DEFECTO = 'llama-3.3-70b-versatile';

    private const URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private readonly ?string $clave,
        private readonly string $modelo,
        private readonly int $tiempoLimite,
    ) {}

    public function responder(string $instrucciones, array $mensajes): string
    {
        if (blank($this->clave)) {
            throw new IaNoDisponible('Falta IA_API_KEY en el .env.');
        }

        try {
            $respuesta = Http::withToken($this->clave)
                ->timeout($this->tiempoLimite)
                ->connectTimeout(10)
                ->acceptJson()
                ->post(self::URL, [
                    'model' => $this->modelo,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $instrucciones],
                        ...array_map(fn (array $mensaje): array => [
                            'role' => $mensaje['rol'] === ClienteIA::ASISTENTE ? 'assistant' : 'user',
                            'content' => $mensaje['texto'],
                        ], $mensajes),
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new IaNoDisponible('Groq no respondió a tiempo: '.$e->getMessage(), previous: $e);
        }

        if ($respuesta->failed()) {
            throw new IaNoDisponible("Groq respondió {$respuesta->status()}: ".$respuesta->json('error.message', 'sin detalle'));
        }

        $texto = $respuesta->json('choices.0.message.content');

        if (! is_string($texto) || trim($texto) === '') {
            throw new IaNoDisponible('Groq no devolvió texto.');
        }

        return trim($texto);
    }
}
