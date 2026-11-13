<?php

namespace App\Modules\Asistente\IA;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini (plan gratuito de Google AI Studio), con la API generateContent.
 * La clave viaja en un encabezado, nunca en la URL, para que no quede en los logs.
 */
class ClienteGemini implements ClienteIA
{
    public const MODELO_POR_DEFECTO = 'gemini-flash-lite-latest';

    private const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

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
            $respuesta = Http::withHeaders(['x-goog-api-key' => $this->clave])
                ->timeout($this->tiempoLimite)
                ->connectTimeout(10)
                ->acceptJson()
                ->post(sprintf(self::URL, $this->modelo), [
                    'system_instruction' => ['parts' => [['text' => $instrucciones]]],
                    'contents' => array_map(fn (array $mensaje): array => [
                        'role' => $mensaje['rol'] === ClienteIA::ASISTENTE ? 'model' : 'user',
                        'parts' => [['text' => $mensaje['texto']]],
                    ], $mensajes),
                    'generationConfig' => ['temperature' => 0.2],
                ]);
        } catch (ConnectionException $e) {
            throw new IaNoDisponible('Gemini no respondió a tiempo: '.$e->getMessage(), previous: $e);
        }

        if ($respuesta->failed()) {
            throw new IaNoDisponible("Gemini respondió {$respuesta->status()}: ".$respuesta->json('error.message', 'sin detalle'));
        }

        $texto = collect($respuesta->json('candidates.0.content.parts', []))
            ->reject(fn (mixed $parte): bool => ! is_array($parte) || ($parte['thought'] ?? false) === true)
            ->pluck('text')
            ->implode('');

        if (trim($texto) === '') {
            throw new IaNoDisponible('Gemini no devolvió texto (motivo: '.$respuesta->json('candidates.0.finishReason', 'desconocido').').');
        }

        return trim($texto);
    }
}
